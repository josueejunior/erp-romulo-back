<?php

namespace App\Modules\Fornecedor\Controllers;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Resources\FornecedorResource;
use App\Modules\Fornecedor\Models\Fornecedor;
use App\Application\Fornecedor\UseCases\CriarFornecedorUseCase;
use App\Application\Fornecedor\UseCases\AtualizarFornecedorUseCase;
use App\Application\Fornecedor\DTOs\CriarFornecedorDTO;
use App\Application\Fornecedor\DTOs\AtualizarFornecedorDTO;
use App\Domain\Fornecedor\Repositories\FornecedorRepositoryInterface;
use App\Http\Requests\Fornecedor\FornecedorCreateRequest;
use App\Http\Requests\Fornecedor\FornecedorUpdateRequest;
use App\Helpers\PermissionHelper;
use App\Services\RedisService;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Log;

class FornecedorController extends BaseApiController
{
    public function __construct(
        private CriarFornecedorUseCase $criarFornecedorUseCase,
        private AtualizarFornecedorUseCase $atualizarFornecedorUseCase,
        private FornecedorRepositoryInterface $fornecedorRepository,
    ) {}

    /**
     * Listar fornecedores usando Repository DDD
     */
    public function index(Request $request): \Illuminate\Http\JsonResponse
    {
        $empresa = $this->getEmpresaAtivaOrFail();
        $tenantId = tenancy()->tenant?->id;
        
        Log::debug('FornecedorController::index() - Empresa ativa identificada', [
            'empresa_id' => $empresa->id,
            'empresa_razao_social' => $empresa->razao_social,
            'user_id' => auth()->id(),
            'tenant_id' => $tenantId,
        ]);
        
        $filters = $request->all();
        $filters['empresa_id'] = $empresa->id;
        
        Log::debug('FornecedorController::index() - Filtros aplicados', [
            'filters' => $filters,
        ]);
        
        // Chave de cache composta: tenant_{id}:empresa_{id}:fornecedores:index:{hash}
        // Garante isolamento total entre tenants e empresas
        $filtersHash = md5(json_encode($filters));
        $cacheKey = "fornecedores:index:{$filtersHash}";
        $tags = ["tenant_{$tenantId}", "empresa_{$empresa->id}"];
        
        Log::debug('FornecedorController::index() - Verificando cache', [
            'cache_key' => $cacheKey,
            'tags' => $tags,
            'empresa_id' => $empresa->id,
        ]);
        
        // Tentar obter do cache usando tags (mais eficiente)
        if ($tenantId && RedisService::isAvailable()) {
            $cached = RedisService::getWithTags($cacheKey, $tags);
            if ($cached !== null) {
                Log::debug('FornecedorController::index() - Cache HIT', [
                    'cache_key' => $cacheKey,
                    'tags' => $tags,
                    'total_cached' => $cached['meta']['total'] ?? count($cached['data'] ?? []),
                ]);
                return response()->json($cached);
            } else {
                Log::debug('FornecedorController::index() - Cache MISS', [
                    'cache_key' => $cacheKey,
                    'tags' => $tags,
                ]);
            }
        }

        try {
            // Usar Repository DDD
            $fornecedoresDomain = $this->fornecedorRepository->buscarComFiltros($filters);
            
            // Converter entidades de domínio para modelos Eloquent para Resource
            // Usar repository para buscar modelos, mantendo o Global Scope ativo
            $fornecedores = $fornecedoresDomain->getCollection()->map(function ($fornecedorDomain) use ($empresa) {
                // Usar repository para buscar modelo (mantém Global Scope e segurança)
                $model = $this->fornecedorRepository->buscarModeloPorId($fornecedorDomain->id);
                
                if (!$model) {
                    Log::warning('Fornecedor não encontrado ao converter para modelo', [
                        'fornecedor_id' => $fornecedorDomain->id,
                        'empresa_id_esperado' => $empresa->id,
                    ]);
                    return null;
                }
                
                // VALIDAÇÃO CRÍTICA: Garantir que o fornecedor pertence à empresa correta
                // O Global Scope já garante isso, mas validamos por segurança adicional
                if ($model->empresa_id != $empresa->id) {
                    Log::error('Tentativa de acessar fornecedor de outra empresa - BLOQUEADO', [
                        'fornecedor_id' => $fornecedorDomain->id,
                        'fornecedor_empresa_id' => $model->empresa_id,
                        'empresa_id_esperado' => $empresa->id,
                        'empresa_id_domain' => $fornecedorDomain->empresaId,
                        'user_id' => auth()->id(),
                        'tenant_id' => tenancy()->tenant?->id,
                    ]);
                    return null; // Não retornar fornecedor de outra empresa
                }
                
                return $model;
            })->filter(); // Remove nulls
            
            Log::debug('FornecedorController::index() - Conversão para modelos concluída', [
                'empresa_id' => $empresa->id,
                'total_domain' => $fornecedoresDomain->count(),
                'total_apos_validacao' => $fornecedores->count(),
                'ids_validos' => $fornecedores->pluck('id')->toArray(),
            ]);
            
            // Criar paginator manual para manter estrutura
            $paginator = new \Illuminate\Pagination\LengthAwarePaginator(
                $fornecedores,
                $fornecedoresDomain->total(),
                $fornecedoresDomain->perPage(),
                $fornecedoresDomain->currentPage(),
                ['path' => $request->url(), 'query' => $request->query()]
            );
            
            $response = FornecedorResource::collection($paginator);
            $responseData = $response->response()->getData(true);
            
            // Adicionar paginação
            $responseData = [
                'data' => $responseData['data'] ?? $responseData,
                'links' => [
                    'first' => $paginator->url(1),
                    'last' => $paginator->url($paginator->lastPage()),
                    'prev' => $paginator->previousPageUrl(),
                    'next' => $paginator->nextPageUrl(),
                ],
                'meta' => [
                    'current_page' => $paginator->currentPage(),
                    'from' => $paginator->firstItem(),
                    'last_page' => $paginator->lastPage(),
                    'path' => $paginator->path(),
                    'per_page' => $paginator->perPage(),
                    'to' => $paginator->lastItem(),
                    'total' => $paginator->total(),
                ],
            ];

            // Salvar no cache com tags (5 minutos) - permite invalidação eficiente
            if ($tenantId && RedisService::isAvailable()) {
                RedisService::setWithTags($cacheKey, $responseData, $tags, 300);
                Log::debug('FornecedorController::index() - Cache salvo com tags', [
                    'cache_key' => $cacheKey,
                    'tags' => $tags,
                ]);
            }

            return response()->json($responseData);
        } catch (\Exception $e) {
            Log::error('Erro ao listar fornecedores', ['error' => $e->getMessage()]);
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    /**
     * Buscar fornecedor por ID usando Repository DDD
     */
    public function show(Fornecedor $fornecedor): \Illuminate\Http\JsonResponse
    {
        try {
            $empresa = $this->getEmpresaAtivaOrFail();
            
            $fornecedorDomain = $this->fornecedorRepository->buscarPorId($fornecedor->id);
            
            if (!$fornecedorDomain || $fornecedorDomain->empresaId !== $empresa->id) {
                return response()->json(['message' => 'Fornecedor não encontrado.'], 404);
            }
            
            return response()->json(['data' => new FornecedorResource($fornecedor)]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    /**
     * Criar fornecedor usando Use Case DDD
     * Usa Form Request para validação
     */
    public function store(FornecedorCreateRequest $request): \Illuminate\Http\JsonResponse
    {
        if (!PermissionHelper::canManageMasterData()) {
            return response()->json(['message' => 'Você não tem permissão para cadastrar fornecedores.'], 403);
        }

        try {
            $empresa = $this->getEmpresaAtivaOrFail();
            
            Log::debug('FornecedorController::store() - Empresa ativa identificada', [
                'empresa_id' => $empresa->id,
                'empresa_razao_social' => $empresa->razao_social,
                'user_id' => auth()->id(),
            ]);
            
            // Request já está validado via Form Request
            $validated = $request->validated();
            $validated['empresa_id'] = $empresa->id;
            
            Log::debug('FornecedorController::store() - Criando fornecedor', [
                'empresa_id' => $empresa->id,
                'razao_social' => $validated['razao_social'] ?? null,
            ]);
            
            $dto = CriarFornecedorDTO::fromArray($validated);
            $fornecedorDomain = $this->criarFornecedorUseCase->executar($dto);
            
            // Buscar modelo Eloquent via repository (DDD)
            $fornecedor = $this->fornecedorRepository->buscarModeloPorId($fornecedorDomain->id);
            if (!$fornecedor) {
                return response()->json(['message' => 'Fornecedor não encontrado após criação.'], 404);
            }
            
            Log::info('FornecedorController::store() - Fornecedor criado com sucesso', [
                'fornecedor_id' => $fornecedor->id,
                'empresa_id' => $fornecedor->empresa_id,
                'razao_social' => $fornecedor->razao_social,
            ]);
            
            $this->clearFornecedorCache();

            return response()->json(['data' => new FornecedorResource($fornecedor)], 201);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    /**
     * Atualizar fornecedor usando Use Case DDD
     * Usa Form Request para validação
     */
    public function update(FornecedorUpdateRequest $request, Fornecedor $fornecedor): \Illuminate\Http\JsonResponse
    {
        if (!PermissionHelper::canManageMasterData()) {
            return response()->json(['message' => 'Você não tem permissão para editar fornecedores.'], 403);
        }

        try {
            $empresa = $this->getEmpresaAtivaOrFail();
            
            // Request já está validado via Form Request
            // Criar DTO
            $dto = AtualizarFornecedorDTO::fromRequest($request, $fornecedor->id);

            // Executar Use Case (toda a lógica está aqui)
            $fornecedorDomain = $this->atualizarFornecedorUseCase->executar($dto, $empresa->id);
            
            // Buscar modelo Eloquent via repository (DDD)
            $fornecedor = $this->fornecedorRepository->buscarModeloPorId($fornecedorDomain->id);
            if (!$fornecedor) {
                return response()->json(['message' => 'Fornecedor não encontrado após atualização.'], 404);
            }
            
            $this->clearFornecedorCache();

            return response()->json(['data' => new FornecedorResource($fornecedor)]);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    /**
     * Excluir fornecedor usando Repository DDD
     */
    public function destroy(Fornecedor $fornecedor): \Illuminate\Http\JsonResponse
    {
        if (!PermissionHelper::canManageMasterData()) {
            return response()->json(['message' => 'Você não tem permissão para excluir fornecedores.'], 403);
        }

        try {
            $empresa = $this->getEmpresaAtivaOrFail();
            
            $fornecedorDomain = $this->fornecedorRepository->buscarPorId($fornecedor->id);
            
            if (!$fornecedorDomain || $fornecedorDomain->empresaId !== $empresa->id) {
                return response()->json(['message' => 'Fornecedor não encontrado.'], 404);
            }

            $this->fornecedorRepository->deletar($fornecedor->id);
            $this->clearFornecedorCache();

            return response()->json(null, 204);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    /**
     * Métodos de compatibilidade para Route::module
     */
    public function list(Request $request): \Illuminate\Http\JsonResponse
    {
        return $this->index($request);
    }

    public function get(Request $request): \Illuminate\Http\JsonResponse
    {
        $route = $request->route();
        $id = $route->parameter('fornecedor') ?? $route->parameter('id');
        
        if (!$id) {
            return response()->json(['message' => 'ID não fornecido'], 400);
        }

        // Buscar via repository (DDD)
        $fornecedor = $this->fornecedorRepository->buscarModeloPorId($id);
        if (!$fornecedor) {
            return response()->json(['message' => 'Fornecedor não encontrado.'], 404);
        }

        return $this->show($fornecedor);
    }

    /**
     * Limpar cache de fornecedores
     */
    private function clearFornecedorCache(): void
    {
        $empresa = $this->getEmpresaAtivaOrFail();
        $tenantId = tenancy()->tenant?->id;
        
        if ($tenantId && RedisService::isAvailable()) {
            $tags = ["tenant_{$tenantId}", "empresa_{$empresa->id}"];
            
            Log::debug('FornecedorController::clearFornecedorCache() - Limpando cache com tags', [
                'tags' => $tags,
                'tenant_id' => $tenantId,
                'empresa_id' => $empresa->id,
            ]);
            
            // Tentar usar tags primeiro (mais rápido)
            $tagsSuccess = RedisService::forgetByTags($tags);
            
            if (!$tagsSuccess) {
                // Fallback para pattern matching
                $pattern = "tenant_{$tenantId}:empresa_{$empresa->id}:fornecedores:*";
                $totalKeysDeleted = RedisService::forgetByPattern($pattern);
                
                Log::info('FornecedorController::clearFornecedorCache() - Cache limpo (fallback pattern)', [
                    'keys_deleted' => $totalKeysDeleted,
                    'pattern' => $pattern,
                ]);
            } else {
                Log::info('FornecedorController::clearFornecedorCache() - Cache limpo (tags)', [
                    'tags' => $tags,
                ]);
            }
        }
    }
}

