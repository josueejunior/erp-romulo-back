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
            $usuarioDomain = $this->switchEmpresaAtivaUseCase->executar($user->id, $novaEmpresaId, $context);

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
     * IMPORTANTE: Quando a empresa existe em múltiplos tenants, retorna o tenant atual se a empresa
     * existir lá, pois o usuário está trabalhando nesse contexto. Isso garante que o usuário continue
     * no mesmo tenant ao trocar de empresa, a menos que a empresa não exista no tenant atual.
     * 
     * @param int $empresaId ID da empresa
     * @param int $tenantIdAtual Tenant atual (para otimizar busca)
     * @return int|null Tenant ID onde a empresa foi encontrada, ou null se não encontrada
     */
    private function buscarTenantIdDaEmpresa(int $empresaId, int $tenantIdAtual): ?int
    {
        // 🔥 PRIORIDADE 1: Verificar se a empresa existe no tenant atual
        // Se existir, retornar o tenant atual (usuário continua no mesmo contexto)
        try {
            if (tenancy()->initialized) {
                $empresa = \App\Models\Empresa::find($empresaId);
                if ($empresa) {
                    \Log::info('UserController::buscarTenantIdDaEmpresa() - Empresa encontrada no tenant atual', [
                        'empresa_id' => $empresaId,
                        'tenant_id' => $tenantIdAtual,
                        'empresa_razao_social' => $empresa->razao_social ?? 'N/A',
                        'empresa_cnpj' => $empresa->cnpj ?? 'N/A',
                    ]);
                    // Retornar imediatamente o tenant atual se a empresa existir lá
                    return $tenantIdAtual;
                }
            }
        } catch (\Exception $e) {
            \Log::debug('UserController::buscarTenantIdDaEmpresa() - Erro ao buscar no tenant atual', [
                'empresa_id' => $empresaId,
                'tenant_id' => $tenantIdAtual,
                'error' => $e->getMessage(),
            ]);
        }

        // 🔥 PRIORIDADE 2: Se não encontrou no tenant atual, buscar em outros tenants
        // Isso acontece quando o usuário troca para uma empresa que não existe no tenant atual
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
                    \Log::info('UserController::buscarTenantIdDaEmpresa() - Empresa encontrada em outro tenant', [
                        'empresa_id' => $empresaId,
                        'tenant_id_atual' => $tenantIdAtual,
                        'tenant_id_encontrado' => $tenant->id,
                        'empresa_razao_social' => $empresa->razao_social ?? 'N/A',
                        'empresa_cnpj' => $empresa->cnpj ?? 'N/A',
                    ]);
                    
                    tenancy()->end();
                    // Retornar o primeiro encontrado (mudança de tenant necessária)
                    return $tenant->id;
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

        // Se não encontrou em nenhum tenant, retornar null (usar tenant atual como fallback)
        \Log::warning('UserController::buscarTenantIdDaEmpresa() - Empresa não encontrada em nenhum tenant', [
            'empresa_id' => $empresaId,
        ]);
        
        return null;
    }
}

