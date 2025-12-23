<?php

namespace App\Modules\Orgao\Controllers;

use App\Http\Controllers\Api\RoutingController;
use App\Http\Controllers\Traits\HasDefaultActions;
use App\Http\Resources\OrgaoResource;
use App\Models\Orgao;
use App\Modules\Orgao\Services\OrgaoService;
use Illuminate\Http\Request;
use App\Helpers\PermissionHelper;

class OrgaoController extends RoutingController
{
    use HasDefaultActions;

    public function __construct(OrgaoService $service)
    {
        $this->service = $service;
    }
    /**
     * Sobrescrever handleList para usar OrgaoResource
     */
    protected function handleList(Request $request, array $mergeParams = []): \Illuminate\Http\JsonResponse
    {
        try {
            $params = $this->service->createListParamBag(array_merge($request->all(), $mergeParams));
            $orgaos = $this->service->list($params);
            
            // Verificar se é um paginator
            if ($orgaos instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator) {
                return response()->json([
                    'data' => OrgaoResource::collection($orgaos->items()),
                    'meta' => [
                        'current_page' => $orgaos->currentPage(),
                        'last_page' => $orgaos->lastPage(),
                        'per_page' => $orgaos->perPage(),
                        'total' => $orgaos->total(),
                    ]
                ]);
            }
            
            // Se for uma coleção simples
            if (is_iterable($orgaos)) {
                return response()->json([
                    'data' => OrgaoResource::collection($orgaos)
                ]);
            }
            
            // Fallback: retornar vazio
            return response()->json([
                'data' => [],
                'meta' => [
                    'current_page' => 1,
                    'last_page' => 1,
                    'per_page' => 15,
                    'total' => 0,
                ]
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            \Log::error('Erro de validação ao listar órgãos', [
                'errors' => $e->errors()
            ]);
            return response()->json([
                'message' => 'Erro de validação',
                'errors' => $e->errors()
            ], 422);
        } catch (\Illuminate\Database\QueryException $e) {
            \Log::error('Erro de banco de dados ao listar órgãos', [
                'error' => $e->getMessage(),
                'sql' => $e->getSql() ?? 'N/A',
                'bindings' => $e->getBindings() ?? [],
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'message' => 'Erro ao consultar banco de dados. Verifique os logs para mais detalhes.'
            ], 500);
        } catch (\Exception $e) {
            \Log::error('Erro ao listar órgãos', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'class' => get_class($e)
            ]);
            return response()->json([
                'message' => $e->getMessage() ?: 'Erro ao listar órgãos'
            ], 500);
        }
    }

    /**
     * Sobrescrever handleGet para usar OrgaoResource
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
            $orgao = $this->service->findById($id, $paramBag);
            
            if (!$orgao) {
                return response()->json([
                    'message' => 'Órgão não encontrado ou não pertence à empresa ativa.'
                ], 404);
            }
            
            return response()->json(['data' => new OrgaoResource($orgao)]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 400);
        }
    }

    public function index(Request $request)
    {
        return $this->handleList($request);
    }

    /**
     * Sobrescrever handleStore para validação de permissão e usar OrgaoResource
     */
    protected function handleStore(Request $request, array $mergeParams = []): \Illuminate\Http\JsonResponse
    {
        if (!PermissionHelper::canManageMasterData()) {
            return response()->json([
                'message' => 'Você não tem permissão para cadastrar órgãos.',
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

            $orgao = $this->service->store($validator->validated());
            $orgao->load('setors');

            return response()->json(['data' => new OrgaoResource($orgao)], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 400);
        }
    }

    public function store(Request $request)
    {
        return $this->handleStore($request);
    }

    public function show(Orgao $orgao)
    {
        // Chamar handleGet diretamente com o ID
        $request = request();
        $request->route()->setParameter('orgao', $orgao->id);
        return $this->handleGet($request);
    }

    /**
     * Sobrescrever handleUpdate para validação de permissão e usar OrgaoResource
     */
    protected function handleUpdate(Request $request, int|string $id, array $mergeParams = []): \Illuminate\Http\JsonResponse
    {
        if (!PermissionHelper::canManageMasterData()) {
            return response()->json([
                'message' => 'Você não tem permissão para editar órgãos.',
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

            $orgao = $this->service->update($id, $validator->validated());
            $orgao->load('setors');

            return response()->json(['data' => new OrgaoResource($orgao)]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 400);
        }
    }

    public function update(Request $request, Orgao $orgao)
    {
        // Converter para chamar o método do trait HasDefaultActions
        return parent::update($request, $orgao->id);
    }

    /**
     * Sobrescrever handleDestroy para validação de permissão
     */
    protected function handleDestroy(Request $request, int|string $id, array $mergeParams = []): \Illuminate\Http\JsonResponse
    {
        if (!PermissionHelper::canManageMasterData()) {
            return response()->json([
                'message' => 'Você não tem permissão para excluir órgãos.',
            ], 403);
        }

        try {
            $this->service->deleteById($id);
            return response()->json(null, 204);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 400);
        }
    }

    public function destroy(Orgao $orgao)
    {
        // Converter para chamar o método do trait HasDefaultActions
        return parent::destroy(request(), $orgao->id);
    }
}

