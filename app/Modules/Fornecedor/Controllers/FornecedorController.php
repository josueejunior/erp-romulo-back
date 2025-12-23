<?php

namespace App\Modules\Fornecedor\Controllers;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Resources\FornecedorResource;
use App\Modules\Fornecedor\Models\Fornecedor;
use App\Modules\Fornecedor\Services\FornecedorService;
use Illuminate\Http\Request;
use App\Helpers\PermissionHelper;
use App\Services\RedisService;

class FornecedorController extends BaseApiController
{
    public function __construct(FornecedorService $service)
    {
        $this->service = $service;
    }

    /**
     * Sobrescrever handleList para usar FornecedorResource e cache
     */
    protected function handleList(Request $request, array $mergeParams = []): \Illuminate\Http\JsonResponse
    {
        $empresa = $this->getEmpresaAtivaOrFail();
        $tenantId = tenancy()->tenant?->id;
        
        // Criar chave de cache baseada nos filtros
        $filters = array_merge($request->all(), $mergeParams);
        $cacheKey = "fornecedores:{$tenantId}:{$empresa->id}:" . md5(json_encode($filters));
        
        // Debug: Log empresa e cache
        \Log::debug('FornecedorController->handleList()', [
            'empresa_id' => $empresa->id,
            'tenant_id' => $tenantId,
            'cache_key' => $cacheKey,
            'filters' => $filters,
        ]);
        
        // Tentar obter do cache
        if ($tenantId && RedisService::isAvailable()) {
            $cached = RedisService::get($cacheKey);
            if ($cached !== null) {
                \Log::debug('FornecedorController->handleList() cache hit', [
                    'cache_key' => $cacheKey,
                    'cached_total' => $cached['meta']['total'] ?? 0,
                ]);
                return response()->json($cached);
            }
        }

        try {
            $params = $this->service->createListParamBag($filters);
            $fornecedores = $this->service->list($params);
            
            // Debug: Log resultado antes do resource
            \Log::debug('FornecedorController->handleList() resultado', [
                'total' => $fornecedores->total(),
                'count' => $fornecedores->count(),
                'empresa_id' => $empresa->id,
            ]);
            
            $response = FornecedorResource::collection($fornecedores);

            // Salvar no cache (5 minutos)
            if ($tenantId && RedisService::isAvailable()) {
                RedisService::set($cacheKey, $response->response()->getData(true), 300);
            }

            return response()->json($response);
        } catch (\Exception $e) {
            \Log::error('FornecedorController->handleList() erro', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Sobrescrever handleGet para usar FornecedorResource
     */
    protected function handleGet(Request $request, array $mergeParams = []): \Illuminate\Http\JsonResponse
    {
        $route = $request->route();
        $id = $this->getRouteId($route);
        
        if (!$id) {
            return response()->json(['message' => 'ID não fornecido'], 400);
        }

        try {
            $params = array_merge($request->all(), $mergeParams);
            $paramBag = $this->service->createFindByIdParamBag($params);
            $fornecedor = $this->service->findById($id, $paramBag);
            
            if (!$fornecedor) {
                return response()->json([
                    'message' => 'Fornecedor não encontrado ou não pertence à empresa ativa.'
                ], 404);
            }
            
            return response()->json(['data' => new FornecedorResource($fornecedor)]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Sobrescrever handleStore para validação de permissão e usar FornecedorResource
     */
    protected function handleStore(Request $request, array $mergeParams = []): \Illuminate\Http\JsonResponse
    {
        if (!PermissionHelper::canManageMasterData()) {
            return response()->json([
                'message' => 'Você não tem permissão para cadastrar fornecedores.',
            ], 403);
        }

        try {
            $data = array_merge($request->all(), $mergeParams);
            $validator = $this->service->validateStoreData($data);
            
            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Erro de validação',
                    'errors' => $validator->errors()
                ], 422);
            }

            $fornecedor = $this->service->store($validator->validated());
            
            // Debug: Log após criar
            \Log::debug('FornecedorController->handleStore() criado', [
                'fornecedor_id' => $fornecedor->id,
                'fornecedor_empresa_id' => $fornecedor->empresa_id,
                'empresa_ativa_id' => $this->getEmpresaAtivaOrFail()->id,
            ]);
            
            $this->clearFornecedorCache();

            return response()->json(['data' => new FornecedorResource($fornecedor)], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Sobrescrever handleUpdate para validação de permissão e usar FornecedorResource
     */
    protected function handleUpdate(Request $request, int|string $id, array $mergeParams = []): \Illuminate\Http\JsonResponse
    {
        if (!PermissionHelper::canManageMasterData()) {
            return response()->json([
                'message' => 'Você não tem permissão para editar fornecedores.',
            ], 403);
        }

        try {
            $data = array_merge($request->all(), $mergeParams);
            $validator = $this->service->validateUpdateData($data, $id);
            
            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Erro de validação',
                    'errors' => $validator->errors()
                ], 422);
            }

            $fornecedor = $this->service->update($id, $validator->validated());
            $this->clearFornecedorCache();

            return response()->json(['data' => new FornecedorResource($fornecedor)]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Sobrescrever handleDestroy para validação de permissão
     */
    protected function handleDestroy(Request $request, int|string $id, array $mergeParams = []): \Illuminate\Http\JsonResponse
    {
        if (!PermissionHelper::canManageMasterData()) {
            return response()->json([
                'message' => 'Você não tem permissão para excluir fornecedores.',
            ], 403);
        }

        try {
            $this->service->deleteById($id);
            $this->clearFornecedorCache();

            return response()->json(null, 204);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Métodos de compatibilidade
     */
    public function index(Request $request)
    {
        return $this->handleList($request);
    }

    public function list(Request $request)
    {
        return $this->handleList($request);
    }

    public function get(Request $request)
    {
        return $this->handleGet($request);
    }

    public function store(Request $request)
    {
        return $this->handleStore($request);
    }

    public function show(Fornecedor $fornecedor)
    {
        $request = request();
        $request->route()->setParameter('fornecedor', $fornecedor->id);
        return $this->handleGet($request);
    }

    public function update(Request $request, Fornecedor $fornecedor)
    {
        return $this->handleUpdate($request, $fornecedor->id);
    }

    public function destroy(Fornecedor $fornecedor)
    {
        return $this->handleDestroy(request(), $fornecedor->id);
    }

    /**
     * Limpar cache de fornecedores
     */
    protected function clearFornecedorCache(): void
    {
        $empresa = $this->getEmpresaAtivaOrFail();
        $tenantId = tenancy()->tenant?->id;
        
        \Log::debug('FornecedorController->clearFornecedorCache()', [
            'empresa_id' => $empresa->id,
            'tenant_id' => $tenantId,
        ]);
        
        if ($tenantId && RedisService::isAvailable()) {
            $pattern = "fornecedores:{$tenantId}:{$empresa->id}:*";
            try {
                $cursor = 0;
                $totalDeleted = 0;
                do {
                    $result = \Illuminate\Support\Facades\Redis::scan($cursor, ['match' => $pattern, 'count' => 100]);
                    $cursor = $result[0];
                    $keys = $result[1];
                    if (!empty($keys)) {
                        $deleted = \Illuminate\Support\Facades\Redis::del($keys);
                        $totalDeleted += $deleted;
                    }
                } while ($cursor != 0);
                
                \Log::debug('FornecedorController->clearFornecedorCache() concluído', [
                    'pattern' => $pattern,
                    'total_deleted' => $totalDeleted,
                ]);
            } catch (\Exception $e) {
                \Log::warning('Erro ao limpar cache de fornecedores: ' . $e->getMessage());
            }
        }
    }
}

