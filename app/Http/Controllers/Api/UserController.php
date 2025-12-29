<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\BaseApiController;
use App\Domain\Auth\Repositories\UserRepositoryInterface;
use App\Application\Auth\UseCases\ListarUsuariosUseCase;
use App\Application\Auth\UseCases\CriarUsuarioUseCase;
use App\Application\Auth\UseCases\AtualizarUsuarioUseCase;
use App\Application\Auth\UseCases\DeletarUsuarioUseCase;
use App\Application\Auth\DTOs\CriarUsuarioDTO;
use App\Application\Auth\DTOs\AtualizarUsuarioDTO;
use App\Domain\Shared\ValueObjects\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Cache;
use App\Services\RedisService;
use Illuminate\Validation\ValidationException;
use DomainException;

class UserController extends BaseApiController
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private ListarUsuariosUseCase $listarUsuariosUseCase,
        private CriarUsuarioUseCase $criarUsuarioUseCase,
        private AtualizarUsuarioUseCase $atualizarUsuarioUseCase,
        private DeletarUsuarioUseCase $deletarUsuarioUseCase,
    ) {}

    /**
     * Listar usuários - Refatorado para maior fluidez
     */
    public function list(Request $request): JsonResponse
    {
        try {
            // Deixe o UseCase lidar com a paginação e filtros
            $paginado = $this->listarUsuariosUseCase->executar($request->all());
            
            // Transformação simples usando o Repository para garantir o Eloquent (para o Resource)
            $items = collect($paginado->items())->map(fn($u) => 
                $this->userRepository->buscarModeloPorId($u->id)
            )->filter();
            
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
     */
    public function get(Request $request, int $id): JsonResponse
    {
        try {
            $usuarioDomain = $this->userRepository->buscarPorId($id);
            
            if (!$usuarioDomain) {
                return response()->json(['message' => 'Usuário não encontrado'], 404);
            }
            
            $userModel = $this->userRepository->buscarModeloPorId($id);
            
            return response()->json(['data' => $userModel]);
        } catch (\Exception $e) {
            return $this->handleException($e, 'Erro ao buscar usuário');
        }
    }

    /**
     * Criar usuário
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'required|email|unique:users,email',
                'password' => 'required|string|min:8',
                'empresa_id' => 'required|integer|exists:empresas,id',
                'role' => 'required|string',
                'empresas' => 'nullable|array',
                'empresas.*' => 'integer|exists:empresas,id',
            ]);

            // Criar TenantContext
            $tenantId = tenancy()->tenant?->id ?? 0;
            $context = TenantContext::create($tenantId);

            // Criar DTO
            $dto = new CriarUsuarioDTO(
                nome: $validated['name'],
                email: $validated['email'],
                senha: $validated['password'],
                empresaId: $validated['empresa_id'],
                role: $validated['role'],
                empresas: $validated['empresas'] ?? null,
            );

            // Executar use case
            $usuarioDomain = $this->criarUsuarioUseCase->executar($dto, $context);
            
            // Buscar modelo Eloquent para resposta
            $userModel = $this->userRepository->buscarModeloPorId($usuarioDomain->id);

            return response()->json(['data' => $userModel], 201);
        } catch (\Exception $e) {
            return $this->handleException($e, 'Erro ao criar usuário');
        }
    }

    /**
     * Atualizar usuário
     */
    public function update(Request $request, int $id): JsonResponse
    {
        try {
            $validated = $request->validate([
                'name' => 'sometimes|string|max:255',
                'email' => 'sometimes|email|unique:users,email,' . $id,
                'password' => 'sometimes|string|min:8',
                'empresa_id' => 'sometimes|integer|exists:empresas,id',
                'role' => 'sometimes|string',
                'empresas' => 'nullable|array',
                'empresas.*' => 'integer|exists:empresas,id',
            ]);

            // Criar TenantContext
            $tenantId = tenancy()->tenant?->id ?? 0;
            $context = TenantContext::create($tenantId);

            // Criar DTO
            $dto = new AtualizarUsuarioDTO(
                userId: $id,
                nome: $validated['name'] ?? null,
                email: $validated['email'] ?? null,
                senha: $validated['password'] ?? null,
                empresaId: $validated['empresa_id'] ?? null,
                role: $validated['role'] ?? null,
                empresas: $validated['empresas'] ?? null,
            );

            // Executar use case
            $usuarioDomain = $this->atualizarUsuarioUseCase->executar($dto, $context);
            
            // Buscar modelo Eloquent para resposta
            $userModel = $this->userRepository->buscarModeloPorId($usuarioDomain->id);

            return response()->json(['data' => $userModel]);
        } catch (\Exception $e) {
            return $this->handleException($e, 'Erro ao atualizar usuário');
        }
    }

    /**
     * Deletar usuário
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        try {
            $this->deletarUsuarioUseCase->executar($id);
            return response()->json(['message' => 'Usuário deletado com sucesso'], 204);
        } catch (\Exception $e) {
            return $this->handleException($e, 'Erro ao deletar usuário');
        }
    }

    /**
     * Trocar empresa ativa do usuário autenticado - Refatorado para performance
     */
    public function switchEmpresaAtiva(Request $request): JsonResponse
    {
        try {
            $user = auth()->user();
            
            if (!$user) {
                return response()->json([
                    'message' => 'Usuário não autenticado.'
                ], 401);
            }

            $validated = $request->validate([
                'empresa_ativa_id' => 'required|integer|exists:empresas,id',
            ]);

            $novaEmpresaId = $validated['empresa_ativa_id'];
            $antigaEmpresaId = $user->empresa_ativa_id;

            // 1. Delega a validação de acesso e atualização para o domínio/repo
            // O Repository já valida se o usuário tem acesso à empresa
            $this->userRepository->atualizarEmpresaAtiva($user->id, $novaEmpresaId);

            // 2. Limpeza de cache cirúrgica (Apenas da nova empresa para forçar refresh de dados)
            $tenantId = tenancy()->tenant?->id;
            if ($tenantId) {
                // Limpa apenas o contexto da nova empresa ativa
                $pattern = "tenant_{$tenantId}:empresa_{$novaEmpresaId}:*";
                $totalKeysDeleted = RedisService::forgetByPattern($pattern);
                
                Log::info('Cache invalidado para troca de empresa', [
                    'pattern' => $pattern,
                    'total_keys_deleted' => $totalKeysDeleted,
                    'user_id' => $user->id,
                    'empresa_id_antiga' => $antigaEmpresaId,
                    'empresa_id_nova' => $novaEmpresaId,
                ]);
            }

            return response()->json([
                'message' => 'Empresa ativa alterada com sucesso.',
                'data' => [
                    'empresa_ativa_id' => $novaEmpresaId
                ],
            ]);
        } catch (\Exception $e) {
            return $this->handleException($e, 'Erro ao trocar empresa ativa');
        }
    }
}

