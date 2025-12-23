<?php

namespace App\Modules\Orgao\Controllers;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Controllers\Traits\HasDefaultActions;
use App\Http\Resources\SetorResource;
use App\Models\Setor;
use App\Modules\Orgao\Services\SetorService;
use Illuminate\Http\Request;
use App\Helpers\PermissionHelper;
use App\Services\RedisService;

class SetorController extends BaseApiController
{
    use HasDefaultActions;

    public function __construct(protected SetorService $service)
    {
    }

    /**
     * Sobrescrever handleList para usar SetorResource e cache
     */
    protected function handleList(Request $request, array $mergeParams = []): \Illuminate\Http\JsonResponse
    {
        if (!PermissionHelper::canView()) {
            return response()->json([
                'message' => 'Não autenticado.',
            ], 401);
        }

        $empresa = $this->getEmpresaAtivaOrFail();
        $tenantId = tenancy()->tenant?->id;
        
        // Criar chave de cache baseada nos filtros
        $filters = array_merge($request->all(), $mergeParams);
        $cacheKey = "setores:{$tenantId}:{$empresa->id}:" . md5(json_encode($filters));
        
        // Tentar obter do cache
        if ($tenantId && RedisService::isAvailable()) {
            $cached = RedisService::get($cacheKey);
            if ($cached !== null) {
                return response()->json($cached);
            }
        }

        try {
            $params = $this->service->createListParamBag($filters);
            $setors = $this->service->list($params);
            $response = SetorResource::collection($setors);

            // Salvar no cache (5 minutos)
            if ($tenantId && RedisService::isAvailable()) {
                RedisService::set($cacheKey, $response->response()->getData(true), 300);
            }

            return response()->json($response);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Sobrescrever handleGet para usar SetorResource
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
            $setor = $this->service->findById($id, $paramBag);
            
            if (!$setor) {
                return response()->json([
                    'message' => 'Setor não encontrado ou não pertence à empresa ativa.'
                ], 404);
            }
            
            return response()->json(['data' => new SetorResource($setor)]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Sobrescrever handleStore para validação de permissão e usar SetorResource
     */
    protected function handleStore(Request $request, array $mergeParams = []): \Illuminate\Http\JsonResponse
    {
        if (!PermissionHelper::canManageMasterData()) {
            return response()->json([
                'message' => 'Você não tem permissão para cadastrar setores.',
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

            $setor = $this->service->store($validator->validated());
            $setor->load('orgao');

            // Limpar cache
            $this->clearSetorCache();

            return response()->json(['data' => new SetorResource($setor)], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Sobrescrever handleUpdate para validação de permissão e usar SetorResource
     */
    protected function handleUpdate(Request $request, int|string $id, array $mergeParams = []): \Illuminate\Http\JsonResponse
    {
        if (!PermissionHelper::canManageMasterData()) {
            return response()->json([
                'message' => 'Você não tem permissão para editar setores.',
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

            $setor = $this->service->update($id, $validator->validated());
            $setor->load('orgao');

            // Limpar cache
            $this->clearSetorCache();

            return response()->json(['data' => new SetorResource($setor)]);
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
                'message' => 'Você não tem permissão para excluir setores.',
            ], 403);
        }

        try {
            $this->service->deleteById($id);
            
            // Limpar cache
            $this->clearSetorCache();

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
        return $this->list($request);
    }

    public function store(Request $request)
    {
        return $this->handleStore($request);
    }

    public function show(Setor $setor)
    {
        $request = request();
        $request->route()->setParameter('setor', $setor->id);
        return $this->handleGet($request);
    }

    public function update(Request $request, Setor $setor)
    {
        return parent::update($request, $setor->id);
    }

    public function destroy(Setor $setor)
    {
        return parent::destroy(request(), $setor->id);
    }

    /**
     * Limpar cache de setores
     */
    protected function clearSetorCache(): void
    {
        $empresa = $this->getEmpresaAtivaOrFail();
        $tenantId = tenancy()->tenant?->id;
        
        if ($tenantId && RedisService::isAvailable()) {
            $pattern = "setores:{$tenantId}:{$empresa->id}:*";
            try {
                $cursor = 0;
                do {
                    $result = \Illuminate\Support\Facades\Redis::scan($cursor, ['match' => $pattern, 'count' => 100]);
                    $cursor = $result[0];
                    $keys = $result[1];
                    if (!empty($keys)) {
                        \Illuminate\Support\Facades\Redis::del($keys);
                    }
                } while ($cursor != 0);
            } catch (\Exception $e) {
                \Log::warning('Erro ao limpar cache de setores: ' . $e->getMessage());
            }
        }
    }
}


