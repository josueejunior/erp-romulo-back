<?php

namespace App\Modules\Documento\Controllers;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Controllers\Traits\HasDefaultActions;
use App\Models\DocumentoHabilitacao;
use App\Modules\Documento\Services\DocumentoHabilitacaoService;
use Illuminate\Http\Request;
use App\Helpers\PermissionHelper;

class DocumentoHabilitacaoController extends BaseApiController
{
    use HasDefaultActions;

    public function __construct(protected DocumentoHabilitacaoService $service)
    {
    }

    /**
     * Sobrescrever handleList para usar service
     */
    protected function handleList(Request $request, array $mergeParams = []): \Illuminate\Http\JsonResponse
    {
        try {
            $params = $this->service->createListParamBag(array_merge($request->all(), $mergeParams));
            $documentos = $this->service->list($params);
            
            // Se for collection (todos), retornar diretamente
            if ($documentos instanceof \Illuminate\Support\Collection) {
                return response()->json($documentos);
            }
            
            return response()->json($documentos);
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

    public function show(DocumentoHabilitacao $documentoHabilitacao)
    {
        $request = request();
        $request->route()->setParameter('documentoHabilitacao', $documentoHabilitacao->id);
        return $this->handleGet($request);
    }

    public function update(Request $request, DocumentoHabilitacao $documentoHabilitacao)
    {
        return parent::update($request, $documentoHabilitacao->id);
    }

    public function destroy(DocumentoHabilitacao $documentoHabilitacao)
    {
        return parent::destroy(request(), $documentoHabilitacao->id);
    }
}

