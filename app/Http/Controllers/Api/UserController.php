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
     * Listar usuários
     */
    public function list(Request $request): JsonResponse
    {
        try {
            $filtros = $request->all();
            $usuariosDomain = $this->listarUsuariosUseCase->executar($filtros);
            
            // Converter entidades de domínio para modelos Eloquent para resposta
            $usuarios = $usuariosDomain->getCollection()->map(function ($usuarioDomain) {
                return $this->userRepository->buscarModeloPorId($usuarioDomain->id);
            })->filter();
            
            // Criar paginator manual
            $paginator = new \Illuminate\Pagination\LengthAwarePaginator(
                $usuarios,
                $usuariosDomain->total(),
                $usuariosDomain->perPage(),
                $usuariosDomain->currentPage(),
                ['path' => $request->url(), 'query' => $request->query()]
            );
            
            return response()->json([
                'data' => $paginator->items(),
                'meta' => [
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Erro ao listar usuários', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json(['message' => 'Erro ao listar usuários: ' . $e->getMessage()], 500);
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
            Log::error('Erro ao buscar usuário', [
                'user_id' => $id,
                'error' => $e->getMessage(),
            ]);
            return response()->json(['message' => 'Erro ao buscar usuário: ' . $e->getMessage()], 500);
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
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Erro de validação',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Erro ao criar usuário', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json(['message' => 'Erro ao criar usuário: ' . $e->getMessage()], 500);
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
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Erro de validação',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Erro ao atualizar usuário', [
                'user_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json(['message' => 'Erro ao atualizar usuário: ' . $e->getMessage()], 500);
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
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        } catch (\Exception $e) {
            Log::error('Erro ao deletar usuário', [
                'user_id' => $id,
                'error' => $e->getMessage(),
            ]);
            return response()->json(['message' => 'Erro ao deletar usuário: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Trocar empresa ativa do usuário autenticado
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

            $empresaId = $validated['empresa_ativa_id'];

            // Verificar se o usuário tem acesso a esta empresa via repository
            $empresas = $this->userRepository->buscarEmpresas($user->id);
            $temAcesso = collect($empresas)->contains(function ($empresa) use ($empresaId) {
                return $empresa->id === $empresaId;
            });

            if (!$temAcesso) {
                return response()->json([
                    'message' => 'Você não tem acesso a esta empresa.'
                ], 403);
            }

            // Atualizar empresa ativa usando o repository
            $userDomain = $this->userRepository->atualizarEmpresaAtiva($user->id, $empresaId);

            // Limpar cache relacionado a fornecedores e outras entidades da empresa antiga e nova
            $tenantId = tenancy()->tenant?->id;
            Log::info('switchEmpresaAtiva - Iniciando limpeza de cache', [
                'user_id' => $user->id,
                'empresa_id' => $empresaId,
                'tenant_id' => $tenantId,
            ]);
            
            if ($tenantId) {
                // Limpar cache de fornecedores para todas as empresas do usuário
                $empresas = $this->userRepository->buscarEmpresas($user->id);
                Log::debug('switchEmpresaAtiva - Empresas encontradas para limpar cache', [
                    'total_empresas' => count($empresas),
                    'empresas_ids' => array_map(fn($e) => $e->id, $empresas),
                ]);
                
                foreach ($empresas as $empresa) {
                    Log::debug('switchEmpresaAtiva - Limpando cache', [
                        'empresa_id' => $empresa->id,
                        'tenant_id' => $tenantId,
                    ]);
                    
                    try {
                        // O RedisService usa Cache::store('redis')->put() que adiciona prefixo automaticamente
                        // Precisamos usar Redis::keys() ou Redis::scan() com o prefixo correto
                        $cachePrefix = config('cache.prefix', '');
                        if (!empty($cachePrefix) && !str_ends_with($cachePrefix, ':')) {
                            $cachePrefix .= ':';
                        }
                        
                        // Padrão base (sem prefixo - o Laravel adiciona)
                        $basePattern = "tenant_{$tenantId}:empresa_{$empresa->id}:fornecedores:*";
                        $patternWithPrefix = $cachePrefix . $basePattern;
                        
                        Log::debug('switchEmpresaAtiva - Buscando chaves', [
                            'base_pattern' => $basePattern,
                            'pattern_with_prefix' => $patternWithPrefix,
                            'cache_prefix' => $cachePrefix,
                        ]);
                        
                        $totalKeysDeleted = 0;
                        $allKeys = [];
                        
                        // Método 1: Usar Redis::keys() (funciona, mas pode ser lento em produção)
                        try {
                            $keysFound = Redis::keys($patternWithPrefix);
                            if (!empty($keysFound)) {
                                $allKeys = array_merge($allKeys, $keysFound);
                                Log::debug('switchEmpresaAtiva - Chaves encontradas via Redis::keys()', [
                                    'keys_found' => count($keysFound),
                                    'sample_keys' => array_slice($keysFound, 0, 5),
                                ]);
                            }
                        } catch (\Exception $e) {
                            Log::warning('Erro ao usar Redis::keys()', ['error' => $e->getMessage()]);
                        }
                        
                        // Método 2: Se não encontrou, tentar sem prefixo
                        if (empty($allKeys)) {
                            try {
                                $keysFound2 = Redis::keys($basePattern);
                                if (!empty($keysFound2)) {
                                    $allKeys = array_merge($allKeys, $keysFound2);
                                    Log::debug('switchEmpresaAtiva - Chaves encontradas (sem prefixo)', [
                                        'keys_found' => count($keysFound2),
                                        'sample_keys' => array_slice($keysFound2, 0, 5),
                                    ]);
                                }
                            } catch (\Exception $e) {
                                Log::warning('Erro ao buscar chaves sem prefixo', ['error' => $e->getMessage()]);
                            }
                        }
                        
                        // Método 3: Usar Redis::scan() como fallback
                        if (empty($allKeys)) {
                            $cursor = 0;
                            do {
                                try {
                                    $result = Redis::scan($cursor, ['match' => $patternWithPrefix, 'count' => 1000]);
                                    $cursor = $result[0];
                                    $keys = $result[1];
                                    if (!empty($keys)) {
                                        $allKeys = array_merge($allKeys, $keys);
                                    }
                                } catch (\Exception $e) {
                                    Log::warning('Erro no Redis::scan()', ['error' => $e->getMessage()]);
                                    break;
                                }
                            } while ($cursor != 0);
                        }
                        
                        // Deletar todas as chaves encontradas
                        if (!empty($allKeys)) {
                            $deleted = Redis::del($allKeys);
                            $totalKeysDeleted = $deleted;
                            
                            Log::info('switchEmpresaAtiva - Chaves deletadas', [
                                'empresa_id' => $empresa->id,
                                'total_keys_found' => count($allKeys),
                                'total_keys_deleted' => $deleted,
                                'sample_keys' => array_slice($allKeys, 0, 3),
                            ]);
                        } else {
                            Log::warning('switchEmpresaAtiva - Nenhuma chave encontrada para deletar', [
                                'empresa_id' => $empresa->id,
                                'pattern_with_prefix' => $patternWithPrefix,
                                'base_pattern' => $basePattern,
                            ]);
                        }
                        
                        Log::info('switchEmpresaAtiva - Cache limpo', [
                            'empresa_id' => $empresa->id,
                            'total_keys_deleted' => $totalKeysDeleted,
                        ]);
                    } catch (\Exception $e) {
                        Log::warning("Erro ao limpar cache de fornecedores para empresa {$empresa->id}", [
                            'error' => $e->getMessage(),
                            'trace' => $e->getTraceAsString(),
                        ]);
                    }
                }
            } else {
                Log::warning('switchEmpresaAtiva - Tenant ID não encontrado, não é possível limpar cache');
            }

            Log::info('Empresa ativa alterada', [
                'user_id' => $user->id,
                'empresa_id' => $empresaId,
            ]);

            return response()->json([
                'message' => 'Empresa ativa alterada com sucesso.',
                'data' => [
                    'user_id' => $userDomain->id,
                    'empresa_ativa_id' => $empresaId,
                ],
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Erro de validação',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Erro ao trocar empresa ativa', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Erro ao trocar empresa ativa: ' . $e->getMessage(),
            ], 500);
        }
    }
}

