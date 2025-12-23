<?php

namespace App\Http\Controllers\Api;

use App\Contracts\IService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Controller base que fornece handlers padrão para operações CRUD
 * Conecta métodos HTTP aos services através da interface IService
 */
abstract class RoutingController extends Controller
{

    /**
     * Service injetado via construtor
     * Deve ser definido no controller filho
     */
    protected ?IService $service = null;

    /**
     * Classe do modelo para casting de dados
     * Pode ser sobrescrito no controller filho
     */
    protected ?string $storeDataCast = null;

    /**
     * Binding de parâmetro de rota pai (para recursos aninhados)
     * Ex: ['parameter' => 'tenant_id', 'inject' => 'params']
     */
    protected ?array $routeParentIdBinding = null;

    /**
     * Obter o service
     * @throws \Exception Se service não estiver definido
     */
    protected function getService(): IService
    {
        return $this->service ?? throw new \Exception(
            "Missing service mapping at [" . static::class . "]. " .
            "Define protected \$service in constructor or override getService()."
        );
    }

    /**
     * Obter ID da rota atual
     */
    protected function getRouteId($route): ?string
    {
        $parameters = $route->parameters();
        
        // Tentar encontrar ID padrão (último parâmetro ou 'id')
        if (isset($parameters['id'])) {
            return $parameters['id'];
        }
        
        // Buscar último parâmetro
        $keys = array_keys($parameters);
        if (!empty($keys)) {
            return $parameters[end($keys)];
        }
        
        return null;
    }

    /**
     * Parsear binding de rota pai
     */
    protected function parseRouteParentIdBinding($route): ?array
    {
        if (!$this->routeParentIdBinding) {
            return null;
        }

        $parameter = $this->routeParentIdBinding['parameter'];
        $inject = $this->routeParentIdBinding['inject'] ?? 'params';
        
        $value = $route->parameter($parameter);
        
        if (!$value) {
            return null;
        }

        return [
            'parameter' => $parameter,
            'inject' => $inject,
            'value' => $value,
        ];
    }

    /**
     * Handler para GET /resource/{id}
     */
    protected function handleGet(Request $request, array $mergeParams = []): JsonResponse
    {
        $route = $request->route();
        $id = $this->getRouteId($route);
        $parentId = null;
        
        // Processar binding de rota pai
        if ($parentBinding = $this->parseRouteParentIdBinding($route)) {
            if ($parentBinding['inject'] === 'argument') {
                $parentId = $parentBinding['value'];
            } elseif ($parentBinding['inject'] === 'params') {
                $mergeParams[$parentBinding['parameter']] = $parentBinding['value'];
            }
        }

        return $this->handleServiceGet(
            $this->getService(),
            $request,
            $id,
            $parentId,
            $mergeParams
        );
    }

    /**
     * Handler para GET /resource (listagem)
     */
    protected function handleList(Request $request, array $mergeParams = []): JsonResponse
    {
        $route = $request->route();
        $parentId = null;
        
        // Processar binding de rota pai
        if ($parentBinding = $this->parseRouteParentIdBinding($route)) {
            if ($parentBinding['inject'] === 'argument') {
                $parentId = $parentBinding['value'];
            } elseif ($parentBinding['inject'] === 'params') {
                $mergeParams[$parentBinding['parameter']] = $parentBinding['value'];
            }
        }

        return $this->handleServiceList(
            $this->getService(),
            $request,
            $parentId,
            $mergeParams
        );
    }

    /**
     * Handler para POST /resource
     */
    protected function handleStore(Request $request, array $mergeParams = []): JsonResponse
    {
        $route = $request->route();
        $parentId = null;
        
        // Processar binding de rota pai
        if ($parentBinding = $this->parseRouteParentIdBinding($route)) {
            if ($parentBinding['inject'] === 'argument') {
                $parentId = $parentBinding['value'];
            } elseif ($parentBinding['inject'] === 'params') {
                $mergeParams[$parentBinding['parameter']] = $parentBinding['value'];
            }
        }

        return $this->handleServiceStore(
            $this->getService(),
            $request,
            $parentId,
            $mergeParams
        );
    }

    /**
     * Handler para PUT/PATCH /resource/{id}
     */
    protected function handleUpdate(Request $request, int|string $id, array $mergeParams = []): JsonResponse
    {
        $route = $request->route();
        $parentId = null;
        
        // Processar binding de rota pai
        if ($parentBinding = $this->parseRouteParentIdBinding($route)) {
            if ($parentBinding['inject'] === 'argument') {
                $parentId = $parentBinding['value'];
            } elseif ($parentBinding['inject'] === 'params') {
                $mergeParams[$parentBinding['parameter']] = $parentBinding['value'];
            }
        }

        return $this->handleServiceUpdate(
            $this->getService(),
            $request,
            $id,
            $parentId,
            $mergeParams
        );
    }

    /**
     * Handler para DELETE /resource/{id}
     */
    protected function handleDestroy(Request $request, int|string $id, array $mergeParams = []): JsonResponse
    {
        return $this->handleServiceDestroy(
            $this->getService(),
            $request,
            $id,
            $mergeParams
        );
    }

    /**
     * Handler para DELETE /resource/bulk
     */
    protected function handleDestroyMany(Request $request, array $mergeParams = []): JsonResponse
    {
        return $this->handleServiceDestroyMany(
            $this->getService(),
            $request,
            $mergeParams
        );
    }

    /**
     * Chamar service para buscar por ID
     */
    protected function handleServiceGet(
        IService $service,
        Request $request,
        int|string|null $id,
        int|string|null $parentId = null,
        array $mergeParams = []
    ): JsonResponse {
        if (!$id) {
            return response()->json(['message' => 'ID não fornecido'], 400);
        }

        if (!method_exists($service, 'createFindByIdParamBag') || !method_exists($service, 'findById')) {
            throw new \Exception(
                "Service [" . get_class($service) . "] missing createFindByIdParamBag() or findById() method."
            );
        }

        $params = array_merge($request->all(), $mergeParams);
        $paramBag = $service->createFindByIdParamBag($params);
        
        $result = $service->findById($id, $paramBag);

        if (!$result) {
            return response()->json(['message' => 'Registro não encontrado'], 404);
        }

        return response()->json(['data' => $result]);
    }

    /**
     * Chamar service para listar
     */
    protected function handleServiceList(
        IService $service,
        Request $request,
        int|string|null $parentId = null,
        array $mergeParams = []
    ): JsonResponse {
        if (!method_exists($service, 'createListParamBag') || !method_exists($service, 'list')) {
            throw new \Exception(
                "Service [" . get_class($service) . "] missing createListParamBag() or list() method."
            );
        }

        $params = array_merge($request->all(), $mergeParams);
        $paramBag = $service->createListParamBag($params);
        
        $result = $service->list($paramBag);

        return response()->json($result);
    }

    /**
     * Chamar service para criar
     */
    protected function handleServiceStore(
        IService $service,
        Request $request,
        int|string|null $parentId = null,
        array $mergeParams = []
    ): JsonResponse {
        if (!method_exists($service, 'validateStoreData') || !method_exists($service, 'store')) {
            throw new \Exception(
                "Service [" . get_class($service) . "] missing validateStoreData() or store() method."
            );
        }

        $data = array_merge($request->all(), $mergeParams);
        
        $validator = $service->validateStoreData($data);
        
        if ($validator->fails()) {
            throw ValidationException::withMessages($validator->errors()->toArray());
        }

        $result = $service->store($validator->validated());

        return response()->json(['data' => $result], 201);
    }

    /**
     * Chamar service para atualizar
     */
    protected function handleServiceUpdate(
        IService $service,
        Request $request,
        int|string $id,
        int|string|null $parentId = null,
        array $mergeParams = []
    ): JsonResponse {
        if (!method_exists($service, 'validateUpdateData') || !method_exists($service, 'update')) {
            throw new \Exception(
                "Service [" . get_class($service) . "] missing validateUpdateData() or update() method."
            );
        }

        $data = array_merge($request->all(), $mergeParams);
        
        $validator = $service->validateUpdateData($data, $id);
        
        if ($validator->fails()) {
            throw ValidationException::withMessages($validator->errors()->toArray());
        }

        $result = $service->update($id, $validator->validated());

        return response()->json(['data' => $result]);
    }

    /**
     * Chamar service para excluir
     */
    protected function handleServiceDestroy(
        IService $service,
        Request $request,
        int|string $id,
        array $mergeParams = []
    ): JsonResponse {
        if (!method_exists($service, 'deleteById')) {
            throw new \Exception(
                "Service [" . get_class($service) . "] missing deleteById() method."
            );
        }

        $deleted = $service->deleteById($id);

        if (!$deleted) {
            return response()->json(['message' => 'Registro não encontrado'], 404);
        }

        return response()->json(['message' => 'Registro excluído com sucesso']);
    }

    /**
     * Chamar service para excluir múltiplos
     */
    protected function handleServiceDestroyMany(
        IService $service,
        Request $request,
        array $mergeParams = []
    ): JsonResponse {
        if (!method_exists($service, 'deleteByIds')) {
            throw new \Exception(
                "Service [" . get_class($service) . "] missing deleteByIds() method."
            );
        }

        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'required|integer|string',
        ]);

        $count = $service->deleteByIds($request->ids);

        return response()->json([
            'message' => "{$count} registro(s) excluído(s) com sucesso",
            'deleted_count' => $count,
        ]);
    }
}

