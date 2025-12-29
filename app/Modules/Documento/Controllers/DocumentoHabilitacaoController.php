<?php

namespace App\Modules\Documento\Controllers;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\DocumentoHabilitacao;
use App\Modules\Documento\Services\DocumentoHabilitacaoService;
use Illuminate\Http\Request;
use App\Helpers\PermissionHelper;

class DocumentoHabilitacaoController extends BaseApiController
{

    public function __construct(DocumentoHabilitacaoService $service)
    {
        $this->service = $service;
    }

    /**
     * Sobrescrever handleList para usar service
     */
    protected function handleList(Request $request, array $mergeParams = []): \Illuminate\Http\JsonResponse
    {
        try {
            $params = $this->service->createListParamBag(array_merge($request->all(), $mergeParams));
            $documentos = $this->service->list($params);
            
            // Sempre retorna LengthAwarePaginator
            return response()->json($documentos);
        } catch (\Exception $e) {
            \Log::error('Erro ao listar documentos de habilitação', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'params' => $request->all()
            ]);
            return response()->json([
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Extrai o ID da rota
     */
    protected function getRouteId($route): ?int
    {
        $parameters = $route->parameters();
        // Tentar 'documentoHabilitacao' primeiro, depois 'id'
        $id = $parameters['documentoHabilitacao'] ?? $parameters['id'] ?? null;
        return $id ? (int) $id : null;
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
            $documento = $this->service->findById($id, $paramBag);
            
            if (!$documento) {
                return response()->json([
                    'message' => 'Documento não encontrado ou não pertence à empresa ativa.'
                ], 404);
            }
            
            return response()->json(['data' => $documento]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Sobrescrever handleStore para validação de permissão e usar service
     */
    protected function handleStore(Request $request, array $mergeParams = []): \Illuminate\Http\JsonResponse
    {
        if (!PermissionHelper::canManageDocuments()) {
            return response()->json([
                'message' => 'Você não tem permissão para cadastrar documentos de habilitação.',
            ], 403);
        }

        try {
            $data = array_merge($request->all(), $mergeParams);
            
            // Processar arquivo se presente
            if ($request->hasFile('arquivo')) {
                $data['arquivo'] = $request->file('arquivo');
            }
            
            $validator = $this->service->validateStoreData($data);
            
            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Erro de validação',
                    'errors' => $validator->errors()
                ], 422);
            }

            $documento = $this->service->store($validator->validated());

            return response()->json($documento, 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Sobrescrever handleUpdate para validação de permissão e usar service
     */
    protected function handleUpdate(Request $request, int|string $id, array $mergeParams = []): \Illuminate\Http\JsonResponse
    {
        if (!PermissionHelper::canManageDocuments()) {
            return response()->json([
                'message' => 'Você não tem permissão para editar documentos de habilitação.',
            ], 403);
        }

        try {
            $data = array_merge($request->all(), $mergeParams);
            
            // Processar arquivo se presente
            if ($request->hasFile('arquivo')) {
                $data['arquivo'] = $request->file('arquivo');
            }
            
            $validator = $this->service->validateUpdateData($data, $id);
            
            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Erro de validação',
                    'errors' => $validator->errors()
                ], 422);
            }

            $documento = $this->service->update($id, $validator->validated());

            return response()->json($documento);
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
        if (!PermissionHelper::canManageDocuments()) {
            return response()->json([
                'message' => 'Você não tem permissão para excluir documentos de habilitação.',
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

    /**
     * GET /documentos-habilitacao - Listar documentos
     * Método chamado pelo Route::module()
     */
    public function list(Request $request): \Illuminate\Http\JsonResponse
    {
        return $this->handleList($request);
    }

    /**
     * GET /documentos-habilitacao/{id} - Buscar documento por ID
     * Método chamado pelo Route::module()
     */
    public function get(Request $request, int|string $id = null): \Illuminate\Http\JsonResponse
    {
        // Se o ID foi passado como parâmetro, definir na rota
        if ($id !== null) {
            $route = $request->route();
            $route->setParameter('documentoHabilitacao', $id);
        }
        return $this->handleGet($request);
    }

    /**
     * POST /documentos-habilitacao - Criar documento
     * Método chamado pelo Route::module()
     */
    public function store(Request $request): \Illuminate\Http\JsonResponse
    {
        return $this->handleStore($request);
    }

    /**
     * PUT /documentos-habilitacao/{id} - Atualizar documento
     * Método chamado pelo Route::module()
     */
    public function update(Request $request, int|string $id): \Illuminate\Http\JsonResponse
    {
        return $this->handleUpdate($request, $id);
    }

    /**
     * DELETE /documentos-habilitacao/{id} - Excluir documento
     * Método chamado pelo Route::module()
     */
    public function destroy(Request $request, int|string $id): \Illuminate\Http\JsonResponse
    {
        return $this->handleDestroy($request, $id);
    }

    /**
     * Métodos de compatibilidade (mantidos para compatibilidade com rotas antigas)
     */
    public function index(Request $request)
    {
        return $this->list($request);
    }

    public function show(DocumentoHabilitacao $documentoHabilitacao)
    {
        $request = request();
        $request->route()->setParameter('documentoHabilitacao', $documentoHabilitacao->id);
        return $this->handleGet($request);
    }
}


