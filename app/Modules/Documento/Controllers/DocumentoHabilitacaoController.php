<?php

namespace App\Modules\Documento\Controllers;

use App\Http\Controllers\Api\BaseApiController;
use App\Modules\Documento\Models\DocumentoHabilitacao;
use App\Modules\Documento\Services\DocumentoHabilitacaoService;
use App\Application\DocumentoHabilitacao\UseCases\CriarDocumentoHabilitacaoUseCase;
use App\Application\DocumentoHabilitacao\UseCases\AtualizarDocumentoHabilitacaoUseCase;
use App\Application\DocumentoHabilitacao\DTOs\CriarDocumentoHabilitacaoDTO;
use App\Domain\Shared\ValueObjects\TenantContext;
use Illuminate\Http\Request;
use App\Helpers\PermissionHelper;
use Illuminate\Support\Facades\Storage;

class DocumentoHabilitacaoController extends BaseApiController
{

    public function __construct(
        DocumentoHabilitacaoService $service,
        private CriarDocumentoHabilitacaoUseCase $criarDocumentoUseCase,
        private AtualizarDocumentoHabilitacaoUseCase $atualizarDocumentoUseCase,
    ) {
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
     * GET /documentos-habilitacao/vencendo - Listar documentos vencendo
     */
    public function vencendo(Request $request): \Illuminate\Http\JsonResponse
    {
        try {
            $context = \App\Domain\Shared\ValueObjects\TenantContext::get();
            $dias = (int) ($request->get('dias', 30));
            
            $repository = app(\App\Domain\DocumentoHabilitacao\Repositories\DocumentoHabilitacaoRepositoryInterface::class);
            $documentos = $repository->buscarVencendo($context->empresaId, $dias);
            
            return response()->json([
                'data' => $documentos,
                'total' => count($documentos),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * GET /documentos-habilitacao/vencidos - Listar documentos vencidos
     */
    public function vencidos(Request $request): \Illuminate\Http\JsonResponse
    {
        try {
            $context = \App\Domain\Shared\ValueObjects\TenantContext::get();
            
            $repository = app(\App\Domain\DocumentoHabilitacao\Repositories\DocumentoHabilitacaoRepositoryInterface::class);
            $documentos = $repository->buscarVencidos($context->empresaId);
            
            return response()->json([
                'data' => $documentos,
                'total' => count($documentos),
            ]);
        } catch (\Exception $e) {
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
     * Sobrescrever handleStore para validação de permissão e usar Use Case
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
            
            // Validar dados
            $validator = $this->service->validateStoreData($data);
            
            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Erro de validação',
                    'errors' => $validator->errors()
                ], 422);
            }

            $validated = $validator->validated();
            
            // Processar arquivo se presente
            $nomeArquivo = null;
            if ($request->hasFile('arquivo')) {
                $arquivo = $request->file('arquivo');
                $nomeArquivo = time() . '_' . $arquivo->getClientOriginalName();
                $arquivo->storeAs('documentos-habilitacao', $nomeArquivo, 'public');
                $validated['arquivo'] = $nomeArquivo;
            }

            // Criar DTO e executar Use Case
            $dto = CriarDocumentoHabilitacaoDTO::fromArray($validated);
            $documento = $this->criarDocumentoUseCase->executar($dto);

            // Buscar modelo para retornar com relacionamentos
            $model = $this->service->findById($documento->id);
            
            return response()->json($model, 201);
        } catch (\DomainException $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 400);
        } catch (\Exception $e) {
            \Log::error('Erro ao criar documento de habilitação', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'message' => 'Erro ao criar documento de habilitação.'
            ], 500);
        }
    }

    /**
     * Sobrescrever handleUpdate para validação de permissão e usar Use Case
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
            
            // Validar dados
            $validator = $this->service->validateUpdateData($data, $id);
            
            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Erro de validação',
                    'errors' => $validator->errors()
                ], 422);
            }

            $validated = $validator->validated();
            
            // Processar arquivo se presente
            if ($request->hasFile('arquivo')) {
                // Deletar arquivo antigo se existir
                $documentoExistente = $this->service->findById($id);
                if ($documentoExistente && $documentoExistente->arquivo) {
                    Storage::disk('public')->delete('documentos-habilitacao/' . $documentoExistente->arquivo);
                }
                
                $arquivo = $request->file('arquivo');
                $nomeArquivo = time() . '_' . $arquivo->getClientOriginalName();
                $arquivo->storeAs('documentos-habilitacao', $nomeArquivo, 'public');
                $validated['arquivo'] = $nomeArquivo;
            }

            // Criar DTO e executar Use Case
            $dto = CriarDocumentoHabilitacaoDTO::fromArray($validated);
            $documento = $this->atualizarDocumentoUseCase->executar($id, $dto);

            // Buscar modelo para retornar com relacionamentos
            $model = $this->service->findById($documento->id);
            
            return response()->json($model);
        } catch (\DomainException $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 400);
        } catch (\Exception $e) {
            \Log::error('Erro ao atualizar documento de habilitação', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'message' => 'Erro ao atualizar documento de habilitação.'
            ], 500);
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


