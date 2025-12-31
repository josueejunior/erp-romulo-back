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

            // O tenant não muda ao trocar de empresa se ambas estão no mesmo tenant
            // Mas vamos garantir que estamos usando o tenant correto do contexto atual
            // IMPORTANTE: Empresas estão no banco do tenant, então todas as empresas
            // de um tenant estão no mesmo banco. O tenant_id só mudaria se o usuário
            // tivesse acesso a empresas de tenants diferentes (cenário raro).
            $tenant = tenancy()->tenant;
            $tenantId = $tenant?->id ?? $context->tenantId;
            
            // 🔍 DEBUG: Verificar em qual tenant a empresa está armazenada
            // Isso ajuda a entender se as empresas estão no mesmo tenant ou não
            $databaseAtual = tenancy()->initialized ? \Illuminate\Support\Facades\DB::connection()->getDatabaseName() : null;
            $empresaExisteNoTenant = false;
            if (tenancy()->initialized) {
                try {
                    $empresaModel = \App\Models\Empresa::find($novaEmpresaId);
                    $empresaExisteNoTenant = $empresaModel !== null;
                } catch (\Exception $e) {
                    // Ignorar erro
                }
            }
            
            \Log::info('UserController::switchEmpresaAtiva() - Retornando tenant_id', [
                'tenant_id' => $tenantId,
                'empresa_ativa_id' => $usuarioDomain->empresaAtivaId,
                'tenancy_initialized' => tenancy()->initialized,
                'database_atual' => $databaseAtual,
                'empresa_existe_no_tenant' => $empresaExisteNoTenant,
                'tenant_database' => $tenant?->database,
            ]);

            return response()->json([
                'message' => 'Empresa ativa alterada com sucesso.',
                'data' => [
                    'empresa_ativa_id' => $usuarioDomain->empresaAtivaId,
                    'tenant_id' => $tenantId, // Retornar tenant_id do contexto atual (empresas no mesmo tenant)
                ],
            ]);
        } catch (\Exception $e) {
            return $this->handleException($e, 'Erro ao trocar empresa ativa');
        }
    }
}

