<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Application\Auth\UseCases\CriarUsuarioUseCase;
use App\Application\Auth\UseCases\AtualizarUsuarioUseCase;
use App\Application\Auth\DTOs\CriarUsuarioDTO;
use App\Application\Auth\DTOs\AtualizarUsuarioDTO;
use App\Application\Auth\Presenters\UserPresenter;
use App\Domain\Auth\Repositories\UserRepositoryInterface;
use App\Domain\Auth\Repositories\UserReadRepositoryInterface;
use App\Domain\Shared\ValueObjects\TenantContext;
use App\Domain\Tenant\Repositories\TenantRepositoryInterface;
use App\Services\AdminTenancyRunner;
use App\Http\Responses\ApiResponse;
use App\Models\Tenant;
use App\Models\Empresa;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use App\Domain\Exceptions\DomainException;

/**
 * Controller Admin para gerenciar usuários das empresas
 * Controller FINO - apenas recebe request e devolve response
 * Toda lógica está nos Use Cases
 * 
 * 🔥 ARQUITETURA LIMPA: Usa AdminTenancyRunner para isolar lógica de tenancy
 */
class AdminUserController extends Controller
{
    public function __construct(
        private CriarUsuarioUseCase $criarUsuarioUseCase,
        private AtualizarUsuarioUseCase $atualizarUsuarioUseCase,
        private UserRepositoryInterface $userRepository,
        private UserReadRepositoryInterface $userReadRepository,
        private TenantRepositoryInterface $tenantRepository,
        private AdminTenancyRunner $adminTenancyRunner,
    ) {}

    /**
     * Listar TODOS os usuários de TODOS os tenants (visão global)
     * 
     * Esta rota permite ao admin ver todos os usuários do sistema
     * sem precisar fazer múltiplas requisições por tenant.
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
            
            $allUsers = [];
            
            // 🔥 ARQUITETURA LIMPA: AdminTenancyRunner isola toda lógica de tenancy
            foreach ($tenantsPaginator->items() as $tenantDomain) {
                try {
                    $resultado = $this->adminTenancyRunner->runForTenant($tenantDomain, function () use ($tenantDomain) {
                        // Buscar usuários com suas empresas (dentro do contexto do tenant)
                        $users = \App\Modules\Auth\Models\User::with(['empresas', 'roles'])
                            ->withTrashed() // Incluir inativos
                            ->get();
                        
                        return [
                            'users' => $users,
                            'tenant' => $tenantDomain,
                        ];
                    });
                    
                    $users = $resultado['users'];
                    $tenantDomain = $resultado['tenant'];
                    
                    foreach ($users as $user) {
                        // Verificar se usuário já existe na lista (por email)
                        $existingIndex = null;
                        foreach ($allUsers as $index => $existingUser) {
                            if ($existingUser['email'] === $user->email) {
                                $existingIndex = $index;
                                break;
                            }
                        }
                        
                        // Preparar dados das empresas com tenant_id
                        $empresasComTenant = $user->empresas->map(function ($empresa) use ($tenantDomain) {
                            return [
                                'id' => $empresa->id,
                                'razao_social' => $empresa->razao_social,
                                'cnpj' => $empresa->cnpj,
                                'tenant_id' => $tenantDomain->id,
                                'tenant_razao_social' => $tenantDomain->razaoSocial,
                            ];
                        })->toArray();
                        
                        if ($existingIndex !== null) {
                            // Usuário já existe - adicionar empresas deste tenant
                            $allUsers[$existingIndex]['empresas'] = array_merge(
                                $allUsers[$existingIndex]['empresas'],
                                $empresasComTenant
                            );
                            $allUsers[$existingIndex]['tenants'][] = [
                                'id' => $tenantDomain->id,
                                'razao_social' => $tenantDomain->razaoSocial,
                            ];
                        } else {
                            // Novo usuário
                            $allUsers[] = [
                                'id' => $user->id,
                                'name' => $user->name,
                                'email' => $user->email,
                                'roles_list' => $user->roles->pluck('name')->toArray(),
                                'empresas' => $empresasComTenant,
                                'empresa_ativa_id' => $user->empresa_ativa_id,
                                'deleted_at' => $user->deleted_at,
                                'tenants' => [[
                                    'id' => $tenantDomain->id,
                                    'razao_social' => $tenantDomain->razaoSocial,
                                ]],
                                'primary_tenant_id' => $tenantDomain->id, // Primeiro tenant onde foi encontrado
                            ];
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
            
            // Aplicar filtros se necessário
            $search = $request->input('search');
            if ($search) {
                $allUsers = array_filter($allUsers, function ($user) use ($search) {
                    return stripos($user['name'], $search) !== false 
                        || stripos($user['email'], $search) !== false;
                });
                $allUsers = array_values($allUsers); // Reindexar
            }
            
            // Ordenar por nome
            usort($allUsers, function ($a, $b) {
                return strcmp($a['name'], $b['name']);
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
            
            return response()->json(['message' => 'Erro ao listar usuários.'], 500);
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
                        // 🔥 PERFORMANCE: Usar eager loading para evitar N+1 dentro do tenant
                        // Já carrega empresas e roles em uma única query por tenant
                        return \App\Modules\Auth\Models\User::with([
                            'empresas' => function($query) {
                                // Carregar apenas campos necessários
                                $query->select('id', 'razao_social', 'cnpj');
                            },
                            'roles' => function($query) {
                                // Carregar apenas nome das roles
                                $query->select('id', 'name');
                            }
                        ])
                        ->withTrashed()
                        ->select('id', 'name', 'email', 'empresa_ativa_id', 'deleted_at')
                        ->find($userId);
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
                                'deleted_at' => $user->deleted_at,
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
                return response()->json(['message' => 'Usuário não encontrado.'], 404);
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
            
            return response()->json(['message' => 'Erro ao buscar usuário.'], 500);
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
            \Log::info('AdminUserController::index - Iniciando', [
                'tenant_id' => $tenant->id,
                'tenant_razao_social' => $tenant->razao_social,
                'request_params' => $request->all(),
                'tenancy_initialized' => tenancy()->initialized,
                'current_tenant_id' => tenancy()->tenant?->id,
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
                'items' => $users->items(),
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
            ]);
            return response()->json(['message' => 'Erro ao listar usuários.'], 500);
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
                return response()->json(['message' => 'Usuário não encontrado.'], 404);
            }

            // Garantir que empresas e roles sejam sempre arrays (frontend espera isso)
            // Isso é crítico para evitar erros de .filter() no frontend
            $userData['empresas'] = is_array($userData['empresas'] ?? null) ? $userData['empresas'] : [];
            $userData['roles'] = is_array($userData['roles'] ?? null) ? $userData['roles'] : [];
            $userData['roles_list'] = is_array($userData['roles_list'] ?? null) ? $userData['roles_list'] : $userData['roles'];
            $userData['empresas_list'] = is_array($userData['empresas_list'] ?? null) ? $userData['empresas_list'] : $userData['empresas'];

            // Retornar como array padronizado (frontend pode usar .filter() sem problemas)
            return ApiResponse::item($userData);
        } catch (\Exception $e) {
            Log::error('Erro ao buscar usuário', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Erro ao buscar usuário.'], 500);
        }
    }

    /**
     * Criar novo usuário na empresa
     * Use Case cuida de toda a lógica
     */
    public function store(Request $request, Tenant $tenant)
    {
        try {
            \Log::info('AdminUserController::store - Iniciando', [
                'tenant_id' => $tenant->id,
                'request_data' => $request->except(['password']), // Não logar senha
                'has_password' => $request->has('password'),
                'password_empty' => $request->has('password') && (empty($request->input('password')) || trim($request->input('password')) === ''),
                'has_empresas' => $request->has('empresas'),
                'has_empresa_id' => $request->has('empresa_id'),
                'has_empresa_ativa_id' => $request->has('empresa_ativa_id'),
            ]);
            
            // Validação de FORMATO apenas (Controller não valida regra de negócio)
            // Regras de força de senha ficam no Value Object Senha (Domain)
            $rules = [
                'name' => 'required|string|max:255',
                'email' => 'required|email|max:255',
                'password' => ['required', 'string', 'min:1'], // Apenas formato básico - força validada no Domain
                'role' => 'nullable|string|in:Administrador,Operacional,Financeiro,Consulta',
            ];
            
            // Se empresas for enviado, validar como array
            // Se não, validar empresa_id OU empresa_ativa_id como obrigatório
            // IMPORTANTE: Validação de exists será feita no UseCase (já no contexto do tenant)
            $empresasInput = $request->input('empresas');
            // Verificar se empresas existe e é um array não vazio
            $hasEmpresasArray = ($request->has('empresas') || array_key_exists('empresas', $request->all())) 
                && is_array($empresasInput) 
                && count($empresasInput) > 0;
            
            \Log::debug('AdminUserController::store - Verificando empresas', [
                'has_empresas_key' => $request->has('empresas'),
                'empresas_in_all' => array_key_exists('empresas', $request->all()),
                'empresas_input' => $empresasInput,
                'is_array' => is_array($empresasInput),
                'count' => is_array($empresasInput) ? count($empresasInput) : 0,
                'has_empresas_array' => $hasEmpresasArray,
            ]);
            
            if ($hasEmpresasArray) {
                $rules['empresas'] = 'required|array|min:1';
                $rules['empresas.*'] = 'integer';
                // empresa_ativa_id é opcional quando empresas é fornecido
                if ($request->has('empresa_ativa_id') || array_key_exists('empresa_ativa_id', $request->all())) {
                    $rules['empresa_ativa_id'] = 'nullable|integer';
                }
            } else {
                // Se não tem empresas array, precisa de empresa_id OU empresa_ativa_id
                $hasEmpresaId = $request->has('empresa_id') || array_key_exists('empresa_id', $request->all());
                $hasEmpresaAtivaId = $request->has('empresa_ativa_id') || array_key_exists('empresa_ativa_id', $request->all());
                
                \Log::debug('AdminUserController::store - Verificando empresa_id/empresa_ativa_id', [
                    'has_empresa_id' => $hasEmpresaId,
                    'has_empresa_ativa_id' => $hasEmpresaAtivaId,
                ]);
                
                if (!$hasEmpresaId && !$hasEmpresaAtivaId) {
                    // Nenhum dos dois foi fornecido, exigir pelo menos um
                    $rules['empresa_id'] = 'required_without:empresa_ativa_id|integer';
                    $rules['empresa_ativa_id'] = 'required_without:empresa_id|integer';
                } else {
                    // Pelo menos um foi fornecido, validar o que foi enviado
                    if ($hasEmpresaId) {
                        $rules['empresa_id'] = 'required|integer';
                    }
                    if ($hasEmpresaAtivaId) {
                        $rules['empresa_ativa_id'] = 'required|integer';
                    }
                }
            }
            
            $validated = $request->validate($rules, [
                'name.required' => 'O nome é obrigatório.',
                'email.required' => 'O e-mail é obrigatório.',
                'password.required' => 'A senha é obrigatória.',
                'empresa_id.required' => 'A empresa é obrigatória.',
                'empresas.required' => 'Selecione pelo menos uma empresa.',
                'empresas.min' => 'Selecione pelo menos uma empresa.',
                'role.in' => 'O perfil deve ser: Administrador, Operacional, Financeiro ou Consulta.',
            ]);
            
            \Log::info('AdminUserController::store - Validação passou', [
                'validated_keys' => array_keys($validated),
            ]);

            // Criar TenantContext explícito (não depende de request())
            $context = TenantContext::create($tenant->id);

            // Criar DTO (sem tenantId - vem do context)
            $dto = CriarUsuarioDTO::fromRequest($request);

            // Executar Use Case (toda a lógica está aqui)
            $user = $this->criarUsuarioUseCase->executar($dto, $context);

            // Usar ResponseBuilder padronizado
            return ApiResponse::success(
                'Usuário criado com sucesso!',
                UserPresenter::fromDomain($user),
                201
            );
        } catch (ValidationException $e) {
            \Log::error('AdminUserController::store - Erro de validação', [
                'errors' => $e->errors(),
                'request_data' => $request->except(['password']),
                'has_password' => $request->has('password'),
                'password_empty' => $request->has('password') && (empty($request->input('password')) || trim($request->input('password')) === ''),
            ]);
            return response()->json([
                'message' => 'Dados inválidos.',
                'errors' => $e->errors(),
                'success' => false,
            ], 422);
        } catch (DomainException $e) {
            \Log::warning('AdminUserController::store - Erro de domínio', [
                'error' => $e->getMessage(),
                'email' => $request->input('email'),
                'tenant_id' => $tenant->id,
            ]);
            
            // Determinar o campo de erro baseado na mensagem
            $field = 'general';
            $message = $e->getMessage();
            
            if (str_contains($message, 'email') || str_contains($message, 'E-mail') || str_contains($message, 'e-mail')) {
                $field = 'email';
                
                // 🔥 UX: Se o erro for de email duplicado, buscar informações do usuário existente
                if (str_contains($message, 'já está cadastrado') || str_contains($message, 'já existe')) {
                    try {
                        // Inicializar contexto do tenant para buscar usuário
                        tenancy()->initialize($tenant);
                        
                        $userExistente = $this->userReadRepository->buscarPorEmail($request->input('email'));
                        
                        if ($userExistente) {
                            \Log::info('AdminUserController::store - Usuário existente encontrado', [
                                'user_id' => $userExistente['id'],
                                'user_name' => $userExistente['name'],
                                'empresas_atuais' => $userExistente['empresas'] ?? [],
                            ]);
                            
                            // Retornar erro com informações do usuário existente
                            return response()->json([
                                'message' => $message,
                                'errors' => [
                                    $field => [
                                        $message . ' Este usuário já existe no sistema. Use a opção "Vincular usuário existente" ou atualize o usuário existente para adicioná-lo a esta empresa.'
                                    ]
                                ],
                                'success' => false,
                                'existing_user' => [
                                    'id' => $userExistente['id'],
                                    'name' => $userExistente['name'],
                                    'email' => $userExistente['email'],
                                    'empresas' => $userExistente['empresas'] ?? [],
                                    'can_link' => true, // Indica que pode vincular à empresa
                                ],
                                'suggestion' => 'use_existing_user_link', // Sugestão para o frontend
                            ], 422);
                        }
                    } catch (\Exception $searchError) {
                        \Log::error('AdminUserController::store - Erro ao buscar usuário existente', [
                            'error' => $searchError->getMessage(),
                            'email' => $request->input('email'),
                        ]);
                        // Continuar com erro padrão se não conseguir buscar
                    } finally {
                        if (tenancy()->initialized) {
                            tenancy()->end();
                        }
                    }
                }
            } elseif (str_contains($message, 'senha') || str_contains($message, 'Senha')) {
                $field = 'password';
            } elseif (str_contains($message, 'empresa') || str_contains($message, 'Empresa')) {
                $field = 'empresa_id';
            }
            
            return response()->json([
                'message' => $message,
                'errors' => [$field => [$message]],
                'success' => false,
            ], 422);
        } catch (\Exception $e) {
            Log::error('AdminUserController::store - Erro inesperado', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'email' => $request->input('email'),
                'tenant_id' => $tenant->id,
            ]);
            return response()->json(['message' => 'Erro ao criar usuário.'], 500);
        }
    }

    /**
     * Atualizar usuário
     * Use Case cuida de toda a lógica
     */
    public function update(Request $request, Tenant $tenant, int $userId)
    {
        try {
            // 🔥 NORMALIZAÇÃO ANTES DE VALIDAR (regra de ouro)
            // Se password existir mas estiver vazio → remove completamente
            $data = $request->all();
            
            if (array_key_exists('password', $data)) {
                // Se password existir mas estiver vazio → remove
                if (trim((string) $data['password']) === '') {
                    unset($data['password']);
                }
            }
            
            // Recriar request completamente (replace remove campos não presentes)
            $request->replace($data);
            
            \Log::info('AdminUserController::update - Request após normalização', [
                'request_keys' => array_keys($request->all()),
                'has_password_in_request' => $request->has('password'),
            ]);

            // 🔥 VALIDAÇÃO CORRETA (regra de ouro)
            // Senha em update NUNCA deve ser required
            // Ela deve ser: opcional, validada apenas se existir, ignorada se vazia
            $rules = [
                'name' => 'sometimes|required|string|max:255',
                'email' => 'sometimes|required|email|max:255',
                'role' => 'nullable|string|in:Administrador,Operacional,Financeiro,Consulta',
            ];
            
            // Aceitar empresas (array) OU empresa_id OU empresa_ativa_id
            $empresasInput = $request->input('empresas');
            $hasEmpresasArray = $request->has('empresas') && is_array($empresasInput) && !empty($empresasInput);
            
            if ($hasEmpresasArray) {
                $rules['empresas'] = 'sometimes|required|array|min:1';
                $rules['empresas.*'] = 'integer';
                if ($request->has('empresa_ativa_id')) {
                    $rules['empresa_ativa_id'] = 'sometimes|nullable|integer';
                }
            } else {
                // Se não tem empresas array, aceitar empresa_id OU empresa_ativa_id (opcional em update)
                if ($request->has('empresa_id')) {
                    $rules['empresa_id'] = 'sometimes|required|integer';
                }
                if ($request->has('empresa_ativa_id')) {
                    $rules['empresa_ativa_id'] = 'sometimes|required|integer';
                }
            }
            
            // ⚠️ NUNCA use 'required' aqui para password em update
            // Só valida senha se ela EXISTIR (já foi normalizada acima)
            if ($request->has('password')) {
                // Apenas formato básico - força validada no Value Object Senha (Domain)
                $rules['password'] = ['string', 'min:1'];
            }
            
            \Log::info('AdminUserController::update - Regras de validação', [
                'rules' => array_keys($rules),
                'has_password_rule' => isset($rules['password']),
                'request_has_password' => $request->has('password'),
            ]);
            
            $validated = $request->validate($rules, [
                'role.in' => 'O perfil deve ser: Administrador, Operacional, Financeiro ou Consulta.',
            ]);

            // Criar TenantContext explícito (não depende de request())
            $context = TenantContext::create($tenant->id);

            // Criar DTO (sem tenantId - vem do context)
            $dto = AtualizarUsuarioDTO::fromRequest($request, $userId);

            // Executar Use Case (toda a lógica está aqui)
            $user = $this->atualizarUsuarioUseCase->executar($dto, $context);

            // Usar ResponseBuilder padronizado
            return ApiResponse::success(
                'Usuário atualizado com sucesso!',
                UserPresenter::fromDomain($user)
            );
        } catch (ValidationException $e) {
            \Log::error('AdminUserController::update - Erro de validação', [
                'errors' => $e->errors(),
                'request_data' => $request->except(['password']),
                'has_password' => $request->has('password'),
                'rules_applied' => array_keys($rules ?? []),
            ]);
            return response()->json([
                'message' => 'Dados inválidos.',
                'errors' => $e->errors(),
                'success' => false,
            ], 422);
        } catch (DomainException $e) {
            // Determinar o campo de erro baseado na mensagem
            $field = 'general';
            $message = $e->getMessage();
            
            if (str_contains($message, 'senha') || str_contains($message, 'Senha')) {
                $field = 'password';
            } elseif (str_contains($message, 'email') || str_contains($message, 'E-mail')) {
                $field = 'email';
            }
            
            return response()->json([
                'message' => $message,
                'errors' => [$field => [$message]],
                'success' => false,
            ], 422);
        } catch (\Exception $e) {
            Log::error('Erro ao atualizar usuário', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Erro ao atualizar usuário.'], 500);
        }
    }

    /**
     * Excluir usuário (soft delete)
     */
    public function destroy(Request $request, Tenant $tenant, int $userId)
    {
        try {
            $user = $this->userRepository->buscarPorId($userId);

            if (!$user) {
                return response()->json(['message' => 'Usuário não encontrado.'], 404);
            }

            $this->userRepository->deletar($userId);

            return response()->json([
                'message' => 'Usuário excluído com sucesso!',
                'success' => true,
            ]);
        } catch (\Exception $e) {
            Log::error('Erro ao excluir usuário', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Erro ao excluir usuário.'], 500);
        }
    }

    /**
     * Reativar usuário
     */
    public function reactivate(Request $request, Tenant $tenant, int $userId)
    {
        try {
            $this->userRepository->reativar($userId);

            return response()->json([
                'message' => 'Usuário reativado com sucesso!',
                'success' => true,
            ]);
        } catch (\Exception $e) {
            Log::error('Erro ao reativar usuário', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Erro ao reativar usuário.'], 500);
        }
    }

    /**
     * Buscar usuário por email (para vincular a empresa existente)
     * 
     * 🔥 UX: Permite buscar usuário já existente no sistema para apenas vinculá-lo
     * a uma nova empresa, em vez de criar um duplicado.
     * 
     * Usado quando: "Novo Usuário" dentro de uma empresa específica
     */
    public function buscarPorEmail(Request $request, Tenant $tenant)
    {
        try {
            $request->validate([
                'email' => 'required|email',
            ]);

            $email = $request->input('email');
            
            // Buscar usuário por email no tenant atual
            $user = $this->userReadRepository->buscarPorEmail($email);
            
            if (!$user) {
                return response()->json([
                    'message' => 'Usuário não encontrado com este e-mail.',
                    'data' => null,
                ], 404);
            }

            // Retornar dados do usuário (sem senha)
            return ApiResponse::item($user);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Dados inválidos.',
                'errors' => $e->errors(),
                'success' => false,
            ], 422);
        } catch (\Exception $e) {
            Log::error('Erro ao buscar usuário por email', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Erro ao buscar usuário.'], 500);
        }
    }

    /**
     * Listar empresas disponíveis do tenant atual
     * 
     * Retorna apenas as empresas do tenant especificado na rota.
     * Remove duplicatas baseado no ID da empresa.
     */
    public function empresas(Request $request, Tenant $tenant)
    {
        try {
            // Garantir que o tenancy está inicializado
            if (!tenancy()->initialized) {
                tenancy()->initialize($tenant);
            }
            
            Log::debug('Buscando empresas do tenant', [
                'tenant_id' => $tenant->id,
                'tenancy_initialized' => tenancy()->initialized,
                'current_database' => tenancy()->initialized ? \Illuminate\Support\Facades\DB::connection()->getDatabaseName() : null,
            ]);
            
            // Buscar empresas do tenant atual usando Eloquent (respeita tenancy)
            $empresas = Empresa::select('id', 'razao_social', 'cnpj', 'status')
                ->orderBy('razao_social')
                ->get();
            
            Log::debug('Empresas encontradas no tenant', [
                'tenant_id' => $tenant->id,
                'count' => $empresas->count(),
            ]);
            
            // Remover duplicatas baseado no ID
            $empresasUnicas = [];
            $idsProcessados = [];
            
            foreach ($empresas as $empresa) {
                $empresaId = (int) $empresa->id;
                if (!in_array($empresaId, $idsProcessados)) {
                    $empresasUnicas[] = [
                        'id' => $empresaId,
                        'razao_social' => $empresa->razao_social,
                        'cnpj' => $empresa->cnpj,
                        'status' => $empresa->status,
                    ];
                    $idsProcessados[] = $empresaId;
                }
            }
            
            Log::debug('Empresas únicas após remoção de duplicatas', [
                'tenant_id' => $tenant->id,
                'count' => count($empresasUnicas),
            ]);
            
            // Usar ResponseBuilder padronizado (sempre retorna array)
            return ApiResponse::collection($empresasUnicas);
        } catch (\Exception $e) {
            Log::error('Erro ao listar empresas do tenant', [
                'tenant_id' => $tenant->id,
                'tenancy_initialized' => tenancy()->initialized,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            // Em caso de erro, retornar array vazio para não quebrar o frontend
            return response()->json([
                'data' => [],
                'message' => 'Erro ao listar empresas: ' . $e->getMessage(),
            ], 500);
        }
    }
}
