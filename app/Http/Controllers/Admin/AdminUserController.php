<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Application\Auth\UseCases\CriarUsuarioUseCase;
use App\Application\Auth\UseCases\AtualizarUsuarioUseCase;
use App\Application\Auth\UseCases\DeletarUsuarioAdminUseCase;
use App\Application\Auth\UseCases\ReativarUsuarioAdminUseCase;
use App\Application\Auth\UseCases\BuscarUsuarioPorEmailAdminUseCase;
use App\Application\Auth\DTOs\CriarUsuarioDTO;
use App\Application\Auth\DTOs\AtualizarUsuarioDTO;
use App\Application\Auth\Presenters\UserPresenter;
use App\Domain\Auth\Repositories\UserReadRepositoryInterface;
use App\Domain\Auth\Services\UserErrorService;
use App\Domain\Shared\ValueObjects\TenantContext;
use App\Domain\Tenant\Repositories\TenantRepositoryInterface;
use App\Domain\Tenant\Services\EmpresaAdminService;
use App\Services\AdminTenancyRunner;
use App\Http\Responses\ApiResponse;
use App\Http\Requests\Admin\StoreUserAdminRequest;
use App\Http\Requests\Admin\UpdateUserAdminRequest;
use App\Http\Requests\Admin\BuscarPorEmailAdminRequest;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Domain\Exceptions\DomainException;

/**
 * 🔥 DDD: Controller Admin para gerenciar usuários das empresas
 * 
 * Controller FINO - apenas recebe request e devolve response
 * Toda lógica está nos UseCases, Domain Services e FormRequests
 * 
 * Responsabilidades:
 * - Receber request HTTP
 * - Validar entrada (via FormRequest)
 * - Chamar UseCase apropriado
 * - Retornar response padronizado (ApiResponse)
 */
class AdminUserController extends Controller
{
    public function __construct(
        private CriarUsuarioUseCase $criarUsuarioUseCase,
        private AtualizarUsuarioUseCase $atualizarUsuarioUseCase,
        private DeletarUsuarioAdminUseCase $deletarUsuarioAdminUseCase,
        private ReativarUsuarioAdminUseCase $reativarUsuarioAdminUseCase,
        private BuscarUsuarioPorEmailAdminUseCase $buscarUsuarioPorEmailAdminUseCase,
        private UserReadRepositoryInterface $userReadRepository,
        private TenantRepositoryInterface $tenantRepository,
        private AdminTenancyRunner $adminTenancyRunner,
        private UserErrorService $userErrorService,
        private EmpresaAdminService $empresaAdminService,
    ) {}

    /**
     * Listar TODOS os usuários de TODOS os tenants (visão global)
     * 
     * 🔥 REFATORADO: Agora usa UserReadRepository para garantir isolamento e formato consistente.
     * Esta rota permite ao admin ver todos os usuários do sistema sem precisar fazer
     * múltiplas requisições por tenant, mantendo a mesma estrutura de resposta do index().
     */
    public function indexGlobal(Request $request)
    {
        try {
            \Log::info('AdminUserController::indexGlobal - Listando usuários de todos os tenants');
            
            // Buscar todos os tenants ativos usando repository (Domain, não Eloquent)
            $tenantsPaginator = $this->tenantRepository->buscarComFiltros([
                'status' => 'ativa',
                'per_page' => 1000, // Buscar todos para admin
            ]);
            
            $usuariosConsolidados = [];
            
            // 🔥 ARQUITETURA LIMPA: AdminTenancyRunner isola toda lógica de tenancy
            foreach ($tenantsPaginator->items() as $tenantDomain) {
                try {
                    $resultado = $this->adminTenancyRunner->runForTenant($tenantDomain, function () use ($request, $tenantDomain) {
                        // Usar o repositório para garantir isolamento e formato consistente
                        // O filtro de busca é aplicado no repositório (mais eficiente que filtrar após consolidação)
                        $filtros = [];
                        if ($request->has('search') && $request->filled('search')) {
                            $filtros['search'] = $request->input('search');
                        }
                        
                        $users = $this->userReadRepository->listarSemPaginacao($filtros);
                        
                        return [
                            'users' => $users,
                            'tenant' => $tenantDomain,
                        ];
                    });
                    
                    $users = $resultado['users'];
                    $tenantDomain = $resultado['tenant'];
                    
                    // Enriquecer dados dos usuários com informações do tenant
                    foreach ($users as $userData) {
                        $email = $userData['email'];
                        
                        // Enriquecer empresas com informações do tenant
                        $empresasComTenant = array_map(function ($empresa) use ($tenantDomain) {
                            return array_merge($empresa, [
                                'tenant_id' => $tenantDomain->id,
                                'tenant_razao_social' => $tenantDomain->razaoSocial,
                            ]);
                        }, $userData['empresas'] ?? []);
                        
                        // Preparar informações do tenant para o usuário
                        $tenantInfo = [
                            'id' => $tenantDomain->id,
                            'razao_social' => $tenantDomain->razaoSocial,
                        ];
                        
                        if (isset($usuariosConsolidados[$email])) {
                            // Usuário já existe - adicionar empresas deste tenant
                            $usuariosConsolidados[$email]['empresas'] = array_merge(
                                $usuariosConsolidados[$email]['empresas'] ?? [],
                                $empresasComTenant
                            );
                            $usuariosConsolidados[$email]['tenants'][] = $tenantInfo;
                            
                            // Atualizar deleted_at se necessário (usar o mais recente)
                            if (!empty($userData['deleted_at'])) {
                                $currentDeletedAt = $usuariosConsolidados[$email]['deleted_at'] ?? null;
                                $newDeletedAt = $userData['deleted_at'];
                                
                                if (!$currentDeletedAt || 
                                    ($newDeletedAt && strtotime($newDeletedAt) > strtotime($currentDeletedAt ?? ''))) {
                                    $usuariosConsolidados[$email]['deleted_at'] = $newDeletedAt;
                                }
                            }
                            
                            // Atualizar total_empresas e is_multi_empresa
                            $totalEmpresas = count($usuariosConsolidados[$email]['empresas']);
                            $usuariosConsolidados[$email]['total_empresas'] = $totalEmpresas;
                            $usuariosConsolidados[$email]['is_multi_empresa'] = $totalEmpresas > 1;
                        } else {
                            // Novo usuário - usar dados do repositório e enriquecer com tenant info
                            $usuariosConsolidados[$email] = array_merge($userData, [
                                'empresas' => $empresasComTenant,
                                'tenants' => [$tenantInfo],
                                'primary_tenant_id' => $tenantDomain->id, // Primeiro tenant onde foi encontrado
                            ]);
                            
                            // Garantir que roles_list existe (alguns campos podem vir como 'roles')
                            if (!isset($usuariosConsolidados[$email]['roles_list']) && isset($usuariosConsolidados[$email]['roles'])) {
                                $usuariosConsolidados[$email]['roles_list'] = $usuariosConsolidados[$email]['roles'];
                            }
                        }
                    }
                } catch (\Exception $e) {
                    Log::warning('Erro ao buscar usuários do tenant', [
                        'tenant_id' => $tenantDomain->id,
                        'error' => $e->getMessage(),
                    ]);
                    // AdminTenancyRunner já garantiu finalização do tenancy no finally
                }
            }
            
            // Converter para array indexado (remover chaves de email)
            $allUsers = array_values($usuariosConsolidados);
            
            // Ordenar por nome (já vem ordenado do repositório, mas garantimos após consolidação)
            usort($allUsers, function ($a, $b) {
                return strcmp($a['name'] ?? '', $b['name'] ?? '');
            });
            
            \Log::info('AdminUserController::indexGlobal - Usuários consolidados', [
                'total_users' => count($allUsers),
            ]);
            
            return ApiResponse::collection($allUsers);
        } catch (\Exception $e) {
            Log::error('Erro ao listar usuários globalmente', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return ApiResponse::error('Erro ao listar usuários.', 500);
        }
    }

    /**
     * Buscar usuário específico de forma GLOBAL (busca em todos os tenants)
     * 
     * Retorna o usuário com TODAS as empresas de TODOS os tenants onde ele existe.
     */
    public function showGlobal(Request $request, int $userId)
    {
        try {
            \Log::info('AdminUserController::showGlobal - Buscando usuário', ['userId' => $userId]);
            
            // 🔥 PERFORMANCE: Limitar busca a 100 tenants (razoável para admin)
            // Se houver mais de 100 tenants, será necessário paginação ou filtros adicionais
            $tenantsPaginator = $this->tenantRepository->buscarComFiltros([
                'status' => 'ativa',
                'per_page' => 100, // Reduzido de 1000 para 100 (razoável)
            ]);
            
            $userData = null;
            $todasEmpresas = [];
            $todosTenantsDoUsuario = [];
            $tenantsProcessados = 0;
            $maxTenants = 100; // Limite máximo de segurança
            
            // 🔥 PERFORMANCE: Processar apenas tenants retornados (máximo 100)
            // AdminTenancyRunner isola toda lógica de tenancy
            foreach ($tenantsPaginator->items() as $tenantDomain) {
                // Limite de segurança adicional
                if ($tenantsProcessados >= $maxTenants) {
                    \Log::warning('AdminUserController::showGlobal - Limite de tenants atingido', [
                        'userId' => $userId,
                        'tenants_processados' => $tenantsProcessados,
                    ]);
                    break;
                }
                
                try {
                    $resultado = $this->adminTenancyRunner->runForTenant($tenantDomain, function () use ($userId) {
                        // 🔥 PERFORMANCE: Usar eager loading para roles apenas
                        // Carregar empresas manualmente para evitar ambiguidade de colunas no PostgreSQL
                        // O problema ocorre porque belongsToMany gera JOIN e PostgreSQL exige qualificação explícita
                        $user = \App\Modules\Auth\Models\User::with(['roles' => function($query) {
                            // Carregar apenas nome das roles
                            $query->select('id', 'name');
                        }])
                        ->withTrashed()
                        ->select('id', 'name', 'email', 'empresa_ativa_id', 'excluido_em')
                        ->find($userId);
                        
                        // Carregar empresas manualmente para evitar ambiguidade no JOIN
                        if ($user) {
                            // Buscar IDs das empresas primeiro
                            $empresasIds = \DB::table('empresa_user')
                                ->where('user_id', $userId)
                                ->pluck('empresa_id')
                                ->toArray();
                            
                            if (!empty($empresasIds)) {
                                // Carregar empresas sem JOIN (evita ambiguidade)
                                $empresas = \App\Models\Empresa::whereIn('id', $empresasIds)
                                    ->whereNull('excluido_em')
                                    ->get();
                                
                                // Para cada empresa, adicionar dados do pivot manualmente
                                // Isso simula o comportamento do relacionamento belongsToMany
                                $empresasComPivot = $empresas->map(function ($empresa) use ($userId) {
                                    $pivotData = \DB::table('empresa_user')
                                        ->where('user_id', $userId)
                                        ->where('empresa_id', $empresa->id)
                                        ->first();
                                    
                                    if ($pivotData) {
                                        // Criar objeto pivot manualmente
                                        $pivot = new \stdClass();
                                        $pivot->user_id = $pivotData->user_id;
                                        $pivot->empresa_id = $pivotData->empresa_id;
                                        $pivot->perfil = $pivotData->perfil ?? 'consulta';
                                        $pivot->criado_em = $pivotData->criado_em;
                                        $pivot->atualizado_em = $pivotData->atualizado_em;
                                        
                                        $empresa->setAttribute('pivot', $pivot);
                                    }
                                    
                                    return $empresa;
                                });
                                
                                // Associar empresas ao usuário
                                $user->setRelation('empresas', $empresasComPivot);
                            } else {
                                // Nenhuma empresa encontrada
                                $user->setRelation('empresas', collect([]));
                            }
                        }
                        
                        return $user;
                    });
                    
                    if ($resultado) {
                        $user = $resultado;
                        
                        // Coletar dados do usuário (usar o primeiro encontrado como base)
                        if (!$userData) {
                            $userData = [
                                'id' => $user->id,
                                'name' => $user->name,
                                'email' => $user->email,
                                'roles_list' => $user->roles->pluck('name')->toArray(),
                                'empresa_ativa_id' => $user->empresa_ativa_id,
                                'deleted_at' => $user->trashed() ? ($user->excluido_em?->toISOString() ?? $user->excluido_em?->toDateTimeString() ?? now()->toDateTimeString()) : null,
                            ];
                        }
                        
                        // Coletar empresas deste tenant (já carregadas via eager loading)
                        foreach ($user->empresas as $empresa) {
                            $todasEmpresas[] = [
                                'id' => $empresa->id,
                                'razao_social' => $empresa->razao_social,
                                'cnpj' => $empresa->cnpj,
                                'tenant_id' => $tenantDomain->id,
                                'tenant_razao_social' => $tenantDomain->razaoSocial,
                            ];
                        }
                        
                        $todosTenantsDoUsuario[] = [
                            'id' => $tenantDomain->id,
                            'razao_social' => $tenantDomain->razaoSocial,
                        ];
                    }
                    
                    $tenantsProcessados++;
                } catch (\Exception $e) {
                    \Log::warning('Erro ao buscar usuário no tenant', [
                        'tenant_id' => $tenantDomain->id,
                        'userId' => $userId,
                        'error' => $e->getMessage(),
                    ]);
                    // AdminTenancyRunner já garantiu finalização do tenancy no finally
                    $tenantsProcessados++; // Contar mesmo em caso de erro para não travar
                }
            }
            
            if (!$userData) {
                return ApiResponse::error('Usuário não encontrado.', 404);
            }
            
            // Consolidar dados
            $userData['empresas'] = $todasEmpresas;
            $userData['empresas_list'] = $todasEmpresas;
            $userData['tenants'] = $todosTenantsDoUsuario;
            
            \Log::info('AdminUserController::showGlobal - Usuário encontrado', [
                'userId' => $userId,
                'totalEmpresas' => count($todasEmpresas),
                'totalTenants' => count($todosTenantsDoUsuario),
                'tenants_processados' => $tenantsProcessados,
            ]);
            
            return ApiResponse::item($userData);
        } catch (\Exception $e) {
            Log::error('Erro ao buscar usuário globalmente', [
                'userId' => $userId,
                'error' => $e->getMessage(),
            ]);
            
            return ApiResponse::error('Erro ao buscar usuário.', 500);
        }
    }

    /**
     * Listar usuários de uma empresa (tenant)
     * Middleware InitializeTenant cuida do contexto
     * Usa ReadRepository (CQRS) - controller nunca conhece Eloquent
     */
    public function index(Request $request, Tenant $tenant)
    {
        try {
            // 🔥 CRÍTICO: Garantir que o tenant está inicializado antes de fazer qualquer query
            // Se não estiver inicializado, inicializar agora (pode acontecer se o model binding executar antes do middleware)
            if (!tenancy()->initialized || tenancy()->tenant?->id !== $tenant->id) {
                \Log::warning('AdminUserController::index - Tenancy não inicializado ou tenant incorreto, inicializando', [
                    'tenant_id_param' => $tenant->id,
                    'tenancy_initialized' => tenancy()->initialized,
                    'current_tenant_id' => tenancy()->tenant?->id,
                ]);
                
                // Finalizar tenancy atual se existir
                if (tenancy()->initialized) {
                    tenancy()->end();
                }
                
                // Inicializar tenant correto
                tenancy()->initialize($tenant);
                
                \Log::info('AdminUserController::index - Tenant inicializado pelo controller', [
                    'tenant_id' => $tenant->id,
                    'database_connection' => \DB::connection()->getName(),
                    'database_name' => \DB::connection()->getDatabaseName(),
                ]);
            }

            \Log::info('AdminUserController::index - Iniciando', [
                'tenant_id' => $tenant->id,
                'tenant_razao_social' => $tenant->razao_social,
                'request_params' => $request->all(),
                'tenancy_initialized' => tenancy()->initialized,
                'current_tenant_id' => tenancy()->tenant?->id,
                'database_connection' => \DB::connection()->getName(),
                'database_name' => \DB::connection()->getDatabaseName(),
            ]);

            // 🔥 UX: Filtrar por empresa específica quando solicitado
            // Comportamento:
            // - Se empresa_id for passado via query param: mostrar APENAS usuários vinculados àquela empresa
            // - Se não for passado: mostrar TODOS os usuários do tenant (todas as empresas)
            // Frontend deve passar empresa_id quando estiver na tela de uma empresa específica
            $filtros = [
                'search' => $request->search,
                'per_page' => $request->per_page ?? 15,
                'empresa_id' => $request->empresa_id ? (int) $request->empresa_id : null, // Filtro opcional por empresa específica
            ];

            \Log::info('AdminUserController::index - Filtros preparados', [
                'filtros' => $filtros,
                'contexto_empresa' => $filtros['empresa_id'] ? 'filtrado_por_empresa' : 'todos_usuarios',
            ]);

            // Usar ReadRepository (não conhece Eloquent)
            $users = $this->userReadRepository->listarComRelacionamentos($filtros);

            \Log::info('AdminUserController::index - Usuários obtidos do repository', [
                'total' => $users->total(),
                'count' => $users->count(),
                'current_page' => $users->currentPage(),
                'per_page' => $users->perPage(),
                'tenant_id_esperado' => $tenant->id,
                'tenancy_initialized' => tenancy()->initialized,
                'database_name' => \DB::connection()->getDatabaseName(),
            ]);

            // Usar ResponseBuilder padronizado
            $response = ApiResponse::paginated($users);
            
            \Log::info('AdminUserController::index - Resposta preparada', [
                'response_data' => json_decode($response->getContent(), true),
            ]);

            return $response;
        } catch (\Exception $e) {
            \Log::error('Erro ao listar usuários', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'tenant_id' => $tenant->id ?? null,
                'tenancy_initialized' => tenancy()->initialized,
                'database_name' => \DB::connection()->getDatabaseName(),
            ]);
            return ApiResponse::error('Erro ao listar usuários.', 500);
        }
    }

    /**
     * Buscar usuário específico
     * Usa ReadRepository - controller nunca conhece Eloquent
     */
    public function show(Request $request, Tenant $tenant, int $userId)
    {
        try {
            // Usar ReadRepository (não conhece Eloquent)
            $userData = $this->userReadRepository->buscarComRelacionamentos($userId);

            if (!$userData) {
                return ApiResponse::error('Usuário não encontrado.', 404);
            }

            // Garantir que empresas e roles sejam sempre arrays (frontend espera isso)
            $userData['empresas'] = is_array($userData['empresas'] ?? null) ? $userData['empresas'] : [];
            $userData['roles'] = is_array($userData['roles'] ?? null) ? $userData['roles'] : [];
            $userData['roles_list'] = is_array($userData['roles_list'] ?? null) ? $userData['roles_list'] : $userData['roles'];
            $userData['empresas_list'] = is_array($userData['empresas_list'] ?? null) ? $userData['empresas_list'] : $userData['empresas'];

            return ApiResponse::item($userData);
        } catch (\Exception $e) {
            Log::error('Erro ao buscar usuário', ['error' => $e->getMessage()]);
            return ApiResponse::error('Erro ao buscar usuário.', 500);
        }
    }

    /**
     * Criar novo usuário
     * 🔥 DDD: Controller fino - validação via FormRequest, delega para UseCase
     */
    public function store(StoreUserAdminRequest $request, Tenant $tenant)
    {
        try {
            // Criar TenantContext explícito
            $context = TenantContext::create($tenant->id);

            // Criar DTO (sem tenantId - vem do context)
            $dto = CriarUsuarioDTO::fromRequest($request);

            // Executar Use Case
            $user = $this->criarUsuarioUseCase->executar($dto, $context);

            return ApiResponse::success(
                'Usuário criado com sucesso!',
                UserPresenter::fromDomain($user),
                201
            );
        } catch (DomainException $e) {
            // 🔥 DDD: Usar Domain Service para buscar usuário existente e montar erro customizado
            $tenantDomain = $this->tenantRepository->buscarPorId($tenant->id);
            if ($tenantDomain) {
                $errorResponse = $this->userErrorService->buscarUsuarioExistenteParaErro(
                    $request->input('email'),
                    $tenantDomain,
                    $e->getMessage()
                );

                if ($errorResponse) {
                    // Retornar resposta customizada usando ApiResponse::error com dados extras
                    $response = ApiResponse::error(
                        $errorResponse['message'],
                        422,
                        null,
                        $errorResponse['errors'] ?? []
                    );
                    
                    // Adicionar dados extras ao response
                    $responseData = json_decode($response->getContent(), true);
                    if (isset($errorResponse['existing_user'])) {
                        $responseData['existing_user'] = $errorResponse['existing_user'];
                    }
                    if (isset($errorResponse['suggestion'])) {
                        $responseData['suggestion'] = $errorResponse['suggestion'];
                    }
                    
                    return response()->json($responseData, 422);
                }
            }

            // Erro padrão
            $field = $this->userErrorService->determinarCampoErro($e->getMessage());
            return ApiResponse::error(
                $e->getMessage(),
                422,
                null,
                [$field => [$e->getMessage()]]
            );
        } catch (\Exception $e) {
            Log::error('AdminUserController::store - Erro inesperado', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'email' => $request->input('email'),
                'tenant_id' => $tenant->id,
            ]);
            return ApiResponse::error('Erro ao criar usuário.', 500);
        }
    }

    /**
     * Atualizar usuário
     * 🔥 DDD: Controller fino - validação via FormRequest, delega para UseCase
     */
    public function update(UpdateUserAdminRequest $request, Tenant $tenant, int $userId)
    {
        try {
            // Criar TenantContext explícito
            $context = TenantContext::create($tenant->id);

            // Criar DTO (sem tenantId - vem do context)
            $dto = AtualizarUsuarioDTO::fromRequest($request, $userId);

            // Executar Use Case
            $user = $this->atualizarUsuarioUseCase->executar($dto, $context);

            return ApiResponse::success(
                'Usuário atualizado com sucesso!',
                UserPresenter::fromDomain($user)
            );
        } catch (DomainException $e) {
            $field = $this->userErrorService->determinarCampoErro($e->getMessage());
            return ApiResponse::error(
                $e->getMessage(),
                422,
                null,
                [$field => [$e->getMessage()]]
            );
        } catch (\Exception $e) {
            Log::error('Erro ao atualizar usuário', ['error' => $e->getMessage()]);
            return ApiResponse::error('Erro ao atualizar usuário.', 500);
        }
    }

    /**
     * Excluir usuário globalmente (soft delete em todos os tenants)
     * 🔥 DDD: Busca usuário em todos os tenants e deleta em cada um
     */
    public function destroyGlobal(Request $request, int $userId)
    {
        try {
            Log::info('AdminUserController::destroyGlobal - Iniciando exclusão global', ['userId' => $userId]);
            
            // Buscar todos os tenants ativos (similar ao indexGlobal)
            $tenantsPaginator = $this->tenantRepository->buscarComFiltros([
                'status' => 'ativa',
                'per_page' => 1000, // Buscar todos para admin
            ]);
            
            $tenantsComUsuario = [];
            $tenantsDeletados = 0;
            $tenantsComErro = 0;
            
            // Buscar usuário em cada tenant para identificar onde ele existe
            foreach ($tenantsPaginator->items() as $tenantDomain) {
                try {
                    $resultado = $this->adminTenancyRunner->runForTenant($tenantDomain, function () use ($userId) {
                        return \App\Modules\Auth\Models\User::withTrashed()
                            ->where('id', $userId)
                            ->first();
                    });
                    
                    if ($resultado) {
                        $tenantsComUsuario[] = $tenantDomain;
                    }
                } catch (\Exception $e) {
                    Log::warning('AdminUserController::destroyGlobal - Erro ao buscar usuário no tenant', [
                        'tenant_id' => $tenantDomain->id,
                        'userId' => $userId,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
            
            // Deletar usuário em cada tenant onde ele existe
            foreach ($tenantsComUsuario as $tenantDomain) {
                try {
                    $this->adminTenancyRunner->runForTenant($tenantDomain, function () use ($userId) {
                        $this->deletarUsuarioAdminUseCase->executar($userId);
                    });
                    
                    $tenantsDeletados++;
                    Log::info('AdminUserController::destroyGlobal - Usuário deletado no tenant', [
                        'tenant_id' => $tenantDomain->id,
                        'userId' => $userId,
                    ]);
                } catch (\Exception $e) {
                    $tenantsComErro++;
                    Log::error('AdminUserController::destroyGlobal - Erro ao deletar usuário no tenant', [
                        'tenant_id' => $tenantDomain->id,
                        'userId' => $userId,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
            
            if ($tenantsDeletados === 0 && count($tenantsComUsuario) > 0) {
                // Usuário existe mas houve erro em todos os tenants
                return ApiResponse::error('Erro ao deletar usuário em todos os tenants.', 500);
            }
            
            if (count($tenantsComUsuario) === 0) {
                // Usuário não encontrado em nenhum tenant
                return ApiResponse::error('Usuário não encontrado.', 404);
            }
            
            Log::info('AdminUserController::destroyGlobal - Exclusão concluída', [
                'userId' => $userId,
                'tenants_deletados' => $tenantsDeletados,
                'tenants_com_erro' => $tenantsComErro,
                'total_tenants' => count($tenantsComUsuario),
            ]);
            
            $mensagem = $tenantsComErro > 0
                ? "Usuário deletado em {$tenantsDeletados} tenant(s), mas houve erro em {$tenantsComErro} tenant(s)."
                : "Usuário deletado com sucesso em {$tenantsDeletados} tenant(s)!";
            
            return ApiResponse::success($mensagem);
        } catch (DomainException $e) {
            return ApiResponse::error($e->getMessage(), 404);
        } catch (\Exception $e) {
            Log::error('AdminUserController::destroyGlobal - Erro geral', [
                'userId' => $userId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return ApiResponse::error('Erro ao deletar usuário globalmente.', 500);
        }
    }

    /**
     * Excluir usuário (soft delete)
     * 🔥 DDD: Controller fino - delega para UseCase
     */
    public function destroy(Request $request, Tenant $tenant, int $userId)
    {
        try {
            $this->deletarUsuarioAdminUseCase->executar($userId);

            return ApiResponse::success('Usuário excluído com sucesso!');
        } catch (DomainException $e) {
            return ApiResponse::error($e->getMessage(), 404);
        } catch (\Exception $e) {
            Log::error('Erro ao excluir usuário', ['error' => $e->getMessage()]);
            return ApiResponse::error('Erro ao excluir usuário.', 500);
        }
    }

    /**
     * Reativar usuário
     * 🔥 DDD: Controller fino - delega para UseCase
     */
    public function reactivate(Request $request, Tenant $tenant, int $userId)
    {
        try {
            $this->reativarUsuarioAdminUseCase->executar($userId);

            return ApiResponse::success('Usuário reativado com sucesso!');
        } catch (DomainException $e) {
            return ApiResponse::error($e->getMessage(), 404);
        } catch (\Exception $e) {
            Log::error('Erro ao reativar usuário', ['error' => $e->getMessage()]);
            return ApiResponse::error('Erro ao reativar usuário.', 500);
        }
    }

    /**
     * Buscar usuário por email (para vincular a empresa existente)
     * 🔥 DDD: Controller fino - validação via FormRequest, delega para UseCase
     */
    public function buscarPorEmail(BuscarPorEmailAdminRequest $request, Tenant $tenant)
    {
        try {
            $tenantDomain = $this->tenantRepository->buscarPorId($tenant->id);
            
            if (!$tenantDomain) {
                return ApiResponse::error('Tenant não encontrado.', 404);
            }

            $user = $this->buscarUsuarioPorEmailAdminUseCase->executar(
                $request->input('email'),
                $tenantDomain
            );

            return ApiResponse::item($user);
        } catch (DomainException $e) {
            return ApiResponse::error($e->getMessage(), 404);
        } catch (\Exception $e) {
            Log::error('Erro ao buscar usuário por email', ['error' => $e->getMessage()]);
            return ApiResponse::error('Erro ao buscar usuário.', 500);
        }
    }

    /**
     * Listar empresas disponíveis do tenant atual
     * 🔥 DDD: Controller fino - delega para Domain Service
     * 
     * Retorna apenas as empresas do tenant especificado na rota.
     * Remove duplicatas baseado no ID da empresa.
     */
    public function empresas(Request $request, Tenant $tenant)
    {
        try {
            $tenantDomain = $this->tenantRepository->buscarPorId($tenant->id);
            
            if (!$tenantDomain) {
                return ApiResponse::error('Tenant não encontrado.', 404);
            }

            // 🔥 DDD: Usar Domain Service para buscar empresas (isola tenancy)
            $empresasUnicas = $this->empresaAdminService->buscarEmpresasDoTenant($tenantDomain);
            
            return ApiResponse::collection($empresasUnicas);
        } catch (\Exception $e) {
            Log::error('Erro ao listar empresas do tenant', [
                'tenant_id' => $tenant->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return ApiResponse::error('Erro ao listar empresas.', 500);
        }
    }
}
