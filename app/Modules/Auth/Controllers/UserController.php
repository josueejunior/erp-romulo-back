<?php

namespace App\Modules\Auth\Controllers;

use App\Http\Controllers\Api\BaseApiController;
use App\Application\Auth\UseCases\ListarUsuariosUseCase;
use App\Application\Auth\UseCases\BuscarUsuarioUseCase;
use App\Application\Auth\UseCases\CriarUsuarioUseCase;
use App\Application\Auth\UseCases\AtualizarUsuarioUseCase;
use App\Application\Auth\UseCases\DeletarUsuarioUseCase;
use App\Application\Auth\UseCases\SwitchEmpresaAtivaUseCase;
use App\Application\Auth\DTOs\CriarUsuarioDTO;
use App\Application\Auth\DTOs\AtualizarUsuarioDTO;
use App\Domain\Shared\ValueObjects\TenantContext;
use App\Http\Requests\Auth\UserCreateRequest;
use App\Http\Requests\Auth\UserUpdateRequest;
use App\Http\Requests\Auth\SwitchEmpresaRequest;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use DomainException;

/**
 * Controller para gerenciamento de Usuários
 * 
 * Refatorado para seguir DDD rigorosamente:
 * - Usa Form Requests para validação
 * - Usa Use Cases para lógica de negócio
 * - Usa Resources para transformação
 * - Não acessa modelos Eloquent diretamente
 * - Não contém lógica de infraestrutura (cache, etc.)
 */
class UserController extends BaseApiController
{
    public function __construct(
        private ListarUsuariosUseCase $listarUsuariosUseCase,
        private BuscarUsuarioUseCase $buscarUsuarioUseCase,
        private CriarUsuarioUseCase $criarUsuarioUseCase,
        private AtualizarUsuarioUseCase $atualizarUsuarioUseCase,
        private DeletarUsuarioUseCase $deletarUsuarioUseCase,
        private SwitchEmpresaAtivaUseCase $switchEmpresaAtivaUseCase,
    ) {}

    /**
     * Listar usuários
     * Retorna entidades de domínio transformadas via Resource
     */
    public function list(): JsonResponse
    {
        try {
            $paginado = $this->listarUsuariosUseCase->executar(request()->all());
            
            // Transformar entidades de domínio em JSON via Resource
            $items = collect($paginado->items())->map(fn($user) => 
                new UserResource($user)
            );
            
            return response()->json([
                'data' => $items->values()->all(),
                'meta' => [
                    'current_page' => $paginado->currentPage(),
                    'last_page' => $paginado->lastPage(),
                    'per_page' => $paginado->perPage(),
                    'total' => $paginado->total(),
                ],
            ]);
        } catch (\Exception $e) {
            return $this->handleException($e, 'Erro ao listar usuários');
        }
    }

    /**
     * Obter usuário específico
     * Retorna entidade de domínio transformada via Resource
     */
    public function get(int $id): JsonResponse
    {
        try {
            $usuarioDomain = $this->buscarUsuarioUseCase->executar($id);
            
            return (new UserResource($usuarioDomain))
                ->response();
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        } catch (\Exception $e) {
            return $this->handleException($e, 'Erro ao buscar usuário');
        }
    }

    /**
     * Criar usuário
     * Usa Form Request para validação e Resource para transformação
     */
    public function store(UserCreateRequest $request): JsonResponse
    {
        try {
            // O Request já está validado
            $dto = CriarUsuarioDTO::fromRequest($request);
            $context = TenantContext::create(tenancy()->tenant?->id ?? 0);

            // Executar use case (retorna entidade de domínio)
            $usuarioDomain = $this->criarUsuarioUseCase->executar($dto, $context);

            // Transformar via Resource
            return (new UserResource($usuarioDomain))
                ->response()
                ->setStatusCode(201);
        } catch (\Exception $e) {
            return $this->handleException($e, 'Erro ao criar usuário');
        }
    }

    /**
     * Atualizar usuário
     * Usa Form Request para validação e Resource para transformação
     */
    public function update(UserUpdateRequest $request, int $id): JsonResponse
    {
        try {
            // O Request já está validado
            $dto = AtualizarUsuarioDTO::fromRequest($request, $id);
            $context = TenantContext::create(tenancy()->tenant?->id ?? 0);

            // Executar use case (retorna entidade de domínio)
            $usuarioDomain = $this->atualizarUsuarioUseCase->executar($dto, $context);

            // Transformar via Resource
            return (new UserResource($usuarioDomain))
                ->response();
        } catch (\Exception $e) {
            return $this->handleException($e, 'Erro ao atualizar usuário');
        }
    }

    /**
     * Deletar usuário
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $this->deletarUsuarioUseCase->executar($id);
            return response()->json(['message' => 'Usuário deletado com sucesso'], 204);
        } catch (\Exception $e) {
            return $this->handleException($e, 'Erro ao deletar usuário');
        }
    }

    /**
     * Trocar empresa ativa do usuário autenticado
     * Usa Form Request para validação e Use Case que dispara Domain Event
     * A limpeza de cache é feita pelo Listener (infraestrutura)
     */
    public function switchEmpresaAtiva(SwitchEmpresaRequest $request): JsonResponse
    {
        try {
            $user = auth()->user();
            
            if (!$user) {
                return response()->json([
                    'message' => 'Usuário não autenticado.'
                ], 401);
            }

            // O Request já está validado
            $novaEmpresaId = $request->validated()['empresa_ativa_id'];
            $context = TenantContext::create(tenancy()->tenant?->id ?? 0);

            // Executar use case (dispara Domain Event que limpa cache via Listener)
            // O use case verifica se a empresa tem assinatura ativa
            try {
                $usuarioDomain = $this->switchEmpresaAtivaUseCase->executar($user->id, $novaEmpresaId, $context);
            } catch (\App\Domain\Exceptions\DomainException $e) {
                // Se a empresa não tem assinatura, retornar erro específico para o frontend redirecionar
                if ($e->getErrorCode() === 'NO_SUBSCRIPTION' || str_contains($e->getMessage(), 'assinatura')) {
                    return response()->json([
                        'message' => $e->getMessage(),
                        'code' => 'NO_SUBSCRIPTION',
                        'action' => 'subscribe',
                        'empresa_id' => $novaEmpresaId,
                    ], 403);
                }
                throw $e; // Re-lançar outras exceções
            }

            // 🔍 IMPORTANTE: Buscar o tenant correto da empresa ativa
            // Como empresas podem estar em tenants diferentes, precisamos encontrar
            // em qual tenant a empresa ativa está armazenada
            $tenantIdCorreto = $this->buscarTenantIdDaEmpresa($novaEmpresaId, $context->tenantId);
            
            // Se encontrou um tenant diferente, usar esse; senão, usar o atual
            $tenantId = $tenantIdCorreto ?? $context->tenantId;
            
            // 🔥 DEBUG: Buscar informações do tenant para retornar
            $tenantInfo = null;
            if ($tenantId) {
                $tenantModel = \App\Models\Tenant::find($tenantId);
                if ($tenantModel) {
                    $tenantInfo = [
                        'id' => $tenantModel->id,
                        'razao_social' => $tenantModel->razao_social,
                        'cnpj' => $tenantModel->cnpj,
                        'database' => $tenantModel->database()->getName(),
                    ];
                }
            }
            
            // 🔥 DEBUG: Buscar informações da empresa para retornar
            $empresaInfo = null;
            if ($tenantId && $novaEmpresaId) {
                try {
                    $tenantModel = \App\Models\Tenant::find($tenantId);
                    if ($tenantModel) {
                        $jaInicializado = tenancy()->initialized;
                        $tenantAtual = tenancy()->tenant;
                        $precisaReinicializar = !$jaInicializado || ($tenantAtual && $tenantAtual->id !== $tenantId);
                        
                        if ($precisaReinicializar) {
                            if ($jaInicializado) {
                                tenancy()->end();
                            }
                            tenancy()->initialize($tenantModel);
                        }
                        
                        $empresaModel = \App\Models\Empresa::find($novaEmpresaId);
                        if ($empresaModel) {
                            $empresaInfo = [
                                'id' => $empresaModel->id,
                                'razao_social' => $empresaModel->razao_social,
                                'cnpj' => $empresaModel->cnpj,
                                'status' => $empresaModel->status,
                            ];
                        }
                        
                        if ($precisaReinicializar && tenancy()->initialized) {
                            tenancy()->end();
                        }
                    }
                } catch (\Exception $e) {
                    \Log::error('UserController::switchEmpresaAtiva() - Erro ao buscar empresa', [
                        'error' => $e->getMessage(),
                        'tenant_id' => $tenantId,
                        'empresa_id' => $novaEmpresaId,
                    ]);
                }
            }
            
            \Log::info('UserController::switchEmpresaAtiva() - Retornando tenant_id', [
                'tenant_id_retornado' => $tenantId,
                'tenant_id_contexto' => $context->tenantId,
                'tenant_id_empresa' => $tenantIdCorreto,
                'empresa_ativa_id' => $usuarioDomain->empresaAtivaId,
                'tenancy_initialized' => tenancy()->initialized,
                'tenant_mudou' => $tenantIdCorreto !== null && $tenantIdCorreto !== $context->tenantId,
                'tenant_info' => $tenantInfo,
                'empresa_info' => $empresaInfo,
            ]);

            return response()->json([
                'message' => 'Empresa ativa alterada com sucesso.',
                'data' => [
                    'empresa_ativa_id' => $usuarioDomain->empresaAtivaId,
                    'tenant_id' => $tenantId, // Retornar tenant_id correto da empresa ativa
                    // 🔥 DEBUG: Informações adicionais para debug
                    'tenant' => $tenantInfo,
                    'empresa' => $empresaInfo,
                    'debug' => [
                        'tenant_id_contexto' => $context->tenantId,
                        'tenant_id_empresa_encontrado' => $tenantIdCorreto,
                        'tenant_id_final' => $tenantId,
                        'tenant_mudou' => $tenantIdCorreto !== null && $tenantIdCorreto !== $context->tenantId,
                    ],
                ],
            ]);
        } catch (\Exception $e) {
            return $this->handleException($e, 'Erro ao trocar empresa ativa');
        }
    }

    /**
     * Busca o tenant_id correto da empresa
     * Como empresas podem estar em tenants diferentes, precisamos procurar em todos os tenants
     * 
     * IMPORTANTE: Quando a empresa existe em múltiplos tenants, busca TODOS os tenants onde a empresa existe
     * e retorna o primeiro que NÃO seja o tenant atual (se houver), para permitir mudança de tenant.
     * Se não houver outro tenant, retorna o tenant atual.
     * 
     * @param int $empresaId ID da empresa
     * @param int $tenantIdAtual Tenant atual (para otimizar busca)
     * @return int|null Tenant ID onde a empresa foi encontrada, ou null se não encontrada
     */
    private function buscarTenantIdDaEmpresa(int $empresaId, int $tenantIdAtual): ?int
    {
        $tenantIdEncontradoNoAtual = null;
        $tenantIdEncontradoEmOutro = null;
        
        // 🔥 PRIORIDADE 1: Verificar se a empresa existe no tenant atual
        try {
            if (tenancy()->initialized) {
                $empresa = \App\Models\Empresa::find($empresaId);
                if ($empresa) {
                    $tenantIdEncontradoNoAtual = $tenantIdAtual;
                    \Log::info('UserController::buscarTenantIdDaEmpresa() - Empresa encontrada no tenant atual', [
                        'empresa_id' => $empresaId,
                        'tenant_id' => $tenantIdAtual,
                        'empresa_razao_social' => $empresa->razao_social ?? 'N/A',
                        'empresa_cnpj' => $empresa->cnpj ?? 'N/A',
                    ]);
                }
            }
        } catch (\Exception $e) {
            \Log::debug('UserController::buscarTenantIdDaEmpresa() - Erro ao buscar no tenant atual', [
                'empresa_id' => $empresaId,
                'tenant_id' => $tenantIdAtual,
                'error' => $e->getMessage(),
            ]);
        }

        // 🔥 PRIORIDADE 2: Buscar em outros tenants para ver se a empresa existe em múltiplos tenants
        // Se existir em outro tenant, retornar o outro tenant (permite mudança de tenant)
        $tenants = \App\Models\Tenant::all();
        
        foreach ($tenants as $tenant) {
            // Pular o tenant atual (já verificamos)
            if ($tenant->id === $tenantIdAtual) {
                continue;
            }
            
            try {
                tenancy()->initialize($tenant);
                
                $empresa = \App\Models\Empresa::find($empresaId);
                
                if ($empresa) {
                    $tenantIdEncontradoEmOutro = $tenant->id;
                    \Log::info('UserController::buscarTenantIdDaEmpresa() - Empresa encontrada em outro tenant', [
                        'empresa_id' => $empresaId,
                        'tenant_id_atual' => $tenantIdAtual,
                        'tenant_id_encontrado' => $tenant->id,
                        'empresa_razao_social' => $empresa->razao_social ?? 'N/A',
                        'empresa_cnpj' => $empresa->cnpj ?? 'N/A',
                    ]);
                    
                    tenancy()->end();
                    // Retornar o primeiro encontrado em outro tenant (mudança de tenant)
                    break;
                }
                
                tenancy()->end();
            } catch (\Exception $e) {
                tenancy()->end();
                \Log::debug('UserController::buscarTenantIdDaEmpresa() - Erro ao buscar no tenant', [
                    'empresa_id' => $empresaId,
                    'tenant_id' => $tenant->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // 🔥 DECISÃO: Se encontrou em outro tenant, retornar o outro (mudança de tenant)
        // Se só encontrou no atual, retornar o atual
        // Se não encontrou em nenhum, retornar null
        if ($tenantIdEncontradoEmOutro !== null) {
            \Log::info('UserController::buscarTenantIdDaEmpresa() - Retornando tenant diferente do atual', [
                'empresa_id' => $empresaId,
                'tenant_id_atual' => $tenantIdAtual,
                'tenant_id_retornado' => $tenantIdEncontradoEmOutro,
            ]);
            return $tenantIdEncontradoEmOutro;
        }
        
        if ($tenantIdEncontradoNoAtual !== null) {
            \Log::info('UserController::buscarTenantIdDaEmpresa() - Retornando tenant atual (empresa só existe nele)', [
                'empresa_id' => $empresaId,
                'tenant_id_retornado' => $tenantIdEncontradoNoAtual,
            ]);
            return $tenantIdEncontradoNoAtual;
        }

        // Se não encontrou em nenhum tenant, retornar null (usar tenant atual como fallback)
        \Log::warning('UserController::buscarTenantIdDaEmpresa() - Empresa não encontrada em nenhum tenant', [
            'empresa_id' => $empresaId,
        ]);
        
        return null;
    }

    /**
     * Listar todos os tenants onde o usuário atual está cadastrado
     * Usa a tabela central users_lookup para o mapeamento
     */
    public function meusTenants(): JsonResponse
    {
        try {
            $user = auth()->user();
            if (!$user) {
                return response()->json(['message' => 'Usuário não autenticado.'], 401);
            }

            $centralConnection = config('tenancy.database.central_connection', config('database.default'));

            $lookups = \App\Models\UserLookup::on($centralConnection)
                ->where('email', $user->email)
                ->whereNotNull('tenant_id')
                ->get();

            if ($lookups->isEmpty()) {
                return response()->json(['data' => []]);
            }

            $tenantIds = $lookups->pluck('tenant_id')->unique()->values();
            $tenants = \App\Models\Tenant::on($centralConnection)
                ->whereIn('id', $tenantIds)->get();

            $data = $tenants->map(function ($tenant) use ($lookups) {
                $lookup = $lookups->firstWhere('tenant_id', $tenant->id);
                return [
                    'id'            => $tenant->id,
                    'razao_social'  => $tenant->razao_social,
                    'cnpj'          => $tenant->cnpj,
                    'empresa_id'    => $lookup?->empresa_id,
                    'status'        => $lookup?->status ?? 'ativo',
                ];
            });

            return response()->json(['data' => $data->values()->all()]);
        } catch (\Exception $e) {
            return $this->handleException($e, 'Erro ao listar tenants');
        }
    }

    /**
     * Trocar o tenant ativo do usuário (redireciona para switchEmpresaAtiva com empresa do tenant)
     */
    public function trocarTenant(): JsonResponse
    {
        try {
            $tenantId = request()->input('tenant_id');
            if (!$tenantId) {
                return response()->json(['message' => 'tenant_id é obrigatório.'], 422);
            }

            $user = auth()->user();
            if (!$user) {
                return response()->json(['message' => 'Usuário não autenticado.'], 401);
            }

            $centralConnection = config('tenancy.database.central_connection', config('database.default'));

            $lookup = \App\Models\UserLookup::on($centralConnection)
                ->where('email', $user->email)
                ->where('tenant_id', $tenantId)
                ->first();

            if (!$lookup) {
                return response()->json(['message' => 'Você não tem acesso a este tenant.'], 403);
            }

            return response()->json([
                'message' => 'Use /users/empresa-ativa para trocar para a empresa deste tenant.',
                'data' => [
                    'tenant_id'  => $tenantId,
                    'empresa_id' => $lookup->empresa_id,
                ],
            ]);
        } catch (\Exception $e) {
            return $this->handleException($e, 'Erro ao trocar tenant');
        }
    }
}

