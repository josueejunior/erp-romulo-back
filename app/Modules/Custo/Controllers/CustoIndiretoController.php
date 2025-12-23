<?php

namespace App\Modules\Custo\Controllers;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Controllers\Traits\HasDefaultActions;
use App\Models\CustoIndireto;
use App\Modules\Custo\Services\CustoIndiretoService;
use Illuminate\Http\Request;

class CustoIndiretoController extends BaseApiController
{
    use HasDefaultActions;

    public function __construct(protected CustoIndiretoService $service)
    {
    }

    /**
     * Sobrescrever handleList para usar service
     */
    protected function handleList(Request $request, array $mergeParams = []): \Illuminate\Http\JsonResponse
    {
        try {
            $params = $this->service->createListParamBag(array_merge($request->all(), $mergeParams));
            $custos = $this->service->list($params);
            return response()->json($custos);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Sobrescrever handleGet para usar service
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
            $custo = $this->service->findById($id, $paramBag);
            
            if (!$custo) {
                return response()->json([
                    'message' => 'Custo indireto não encontrado.'
                ], 404);
            }
            
            return response()->json(['data' => $custo]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Sobrescrever handleStore para usar service
     */
    protected function handleStore(Request $request, array $mergeParams = []): \Illuminate\Http\JsonResponse
    {
        try {
            $data = array_merge($request->all(), $mergeParams);
            $validator = $this->service->validateStoreData($data);
            
            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Erro de validação',
                    'errors' => $validator->errors()
                ], 422);
            }

            $custo = $this->service->store($validator->validated());

            return response()->json([
                'message' => 'Custo indireto criado com sucesso',
                'data' => $custo
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Sobrescrever handleUpdate para usar service
     */
    protected function handleUpdate(Request $request, int|string $id, array $mergeParams = []): \Illuminate\Http\JsonResponse
    {
        try {
            $data = array_merge($request->all(), $mergeParams);
            $validator = $this->service->validateUpdateData($data, $id);
            
            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Erro de validação',
                    'errors' => $validator->errors()
                ], 422);
            }

            $custo = $this->service->update($id, $validator->validated());

            return response()->json([
                'message' => 'Custo indireto atualizado com sucesso',
                'data' => $custo
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Sobrescrever handleDestroy para usar service
     */
    protected function handleDestroy(Request $request, int|string $id, array $mergeParams = []): \Illuminate\Http\JsonResponse
    {
        try {
            $this->service->deleteById($id);

            return response()->json([
                'message' => 'Custo indireto removido com sucesso'
            ]);
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

    public function show($id)
    {
        $request = request();
        $request->route()->setParameter('id', $id);
        return $this->handleGet($request);
    }

    public function update(Request $request, $id)
    {
        return parent::update($request, $id);
    }

    public function destroy($id)
    {
        return parent::destroy(request(), $id);
    }

    /**
     * Retorna resumo de custos indiretos
     */
    public function resumo(Request $request)
    {
        try {
            $params = $this->service->createListParamBag($request->all());
            $resumo = $this->service->resumo($params);
            return response()->json($resumo);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 400);
        }
    }
}

