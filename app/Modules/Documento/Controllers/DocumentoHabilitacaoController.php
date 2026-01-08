<?php

namespace App\Modules\Documento\Controllers;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Controllers\Traits\HasAuthContext;
use App\Modules\Documento\Models\DocumentoHabilitacao;
use App\Modules\Documento\Services\DocumentoHabilitacaoService;
use App\Application\DocumentoHabilitacao\UseCases\CriarDocumentoHabilitacaoUseCase;
use App\Application\DocumentoHabilitacao\UseCases\AtualizarDocumentoHabilitacaoUseCase;
use App\Application\DocumentoHabilitacao\DTOs\CriarDocumentoHabilitacaoDTO;
use App\Domain\Shared\ValueObjects\TenantContext;
use App\Http\Middleware\EnsureEmpresaAtivaContext;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Helpers\PermissionHelper;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

/**
 * Controller de Documentos de Habilitação
 * 
 * ✅ DDD: Usa Use Cases para criar/atualizar
 * ✅ Retorna dados completos após operações
 */
class DocumentoHabilitacaoController extends BaseApiController
{
    use HasAuthContext;

    public function __construct(
        DocumentoHabilitacaoService $service,
        private CriarDocumentoHabilitacaoUseCase $criarDocumentoUseCase,
        private AtualizarDocumentoHabilitacaoUseCase $atualizarDocumentoUseCase,
    ) {
        $this->service = $service;
        $this->middleware(EnsureEmpresaAtivaContext::class);
    }

    /**
     * Garante que TenantContext tenha empresa_id usando a empresa ativa
     */
    protected function ensureEmpresaContext(): void
    {
        $tenantId = $this->getTenantId();
        if (!TenantContext::has() || TenantContext::get()->empresaId === null) {
            $empresa = $this->getEmpresaAtivaOrFail();
            if ($tenantId) {
                TenantContext::set($tenantId, $empresa->id);
            }
        }
    }

    /**
     * GET /documentos-habilitacao - Listar documentos
     */
    public function list(Request $request): JsonResponse
    {
        try {
            $this->ensureEmpresaContext();
            $params = $this->service->createListParamBag($request->all());
            $documentos = $this->service->list($params);
            
            return response()->json($documentos);
        } catch (\Exception $e) {
            Log::error('DocumentoHabilitacaoController::list - Erro', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * GET /documentos-habilitacao/{id} - Buscar documento por ID
     */
    public function get(Request $request, int|string $id = null): JsonResponse
    {
        // Extrair ID da rota se não foi passado
        if ($id === null) {
            $route = $request->route();
            $parameters = $route->parameters();
            $id = $parameters['documentoHabilitacao'] ?? $parameters['id'] ?? null;
        }
        
        if (!$id) {
            return response()->json(['message' => 'ID não fornecido'], 400);
        }

        try {
            $this->ensureEmpresaContext();
            $documento = $this->service->findById($id);
            
            if (!$documento) {
                return response()->json([
                    'message' => 'Documento não encontrado ou não pertence à empresa ativa.'
                ], 404);
            }
            
            // Carregar versões
            $documento->loadMissing('versoes');
            $this->service->logAction($documento, 'view');
            
            return response()->json(['data' => $documento]);
        } catch (\Exception $e) {
            Log::error('DocumentoHabilitacaoController::get - Erro', [
                'error' => $e->getMessage(),
                'id' => $id,
            ]);
            return response()->json([
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * POST /documentos-habilitacao - Criar documento
     * 
     * ✅ DDD: Usa Use Case
     */
    public function store(Request $request): JsonResponse
    {
        if (!PermissionHelper::canManageDocuments()) {
            return response()->json([
                'message' => 'Você não tem permissão para cadastrar documentos de habilitação.',
            ], 403);
        }

        Log::info('DocumentoHabilitacaoController::store - Iniciando', [
            'has_file' => $request->hasFile('arquivo'),
            'tipo' => $request->input('tipo'),
            'numero' => $request->input('numero'),
        ]);

        try {
            $this->ensureEmpresaContext();
            $data = $request->all();
            
            // Validar dados
            $validator = $this->service->validateStoreData($data);
            
            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Erro de validação',
                    'errors' => $validator->errors()
                ], 422);
            }

            $validated = $validator->validated();
            
            // Anexar arquivo para o DTO processar
            if ($request->hasFile('arquivo')) {
                $validated['arquivo'] = $request->file('arquivo');
            }

            // Criar DTO e executar Use Case
            $dto = CriarDocumentoHabilitacaoDTO::fromArray($validated);
            $documentoEntity = $this->criarDocumentoUseCase->executar($dto);

            // Buscar modelo completo para retornar com todos os campos
            $model = DocumentoHabilitacao::with('versoes')->find($documentoEntity->id);
            
            Log::info('DocumentoHabilitacaoController::store - Documento criado', [
                'documento_id' => $model->id,
                'tipo' => $model->tipo,
                'numero' => $model->numero,
            ]);
            
            return response()->json([
                'message' => 'Documento cadastrado com sucesso!',
                'data' => $model,
            ], 201);
            
        } catch (\DomainException $e) {
            Log::warning('DocumentoHabilitacaoController::store - Erro de domínio', [
                'error' => $e->getMessage(),
            ]);
            return response()->json([
                'message' => $e->getMessage()
            ], 400);
        } catch (\Exception $e) {
            Log::error('DocumentoHabilitacaoController::store - Erro', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'message' => 'Erro ao criar documento de habilitação: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * PUT /documentos-habilitacao/{id} - Atualizar documento
     * 
     * ✅ DDD: Usa Use Case
     */
    public function update(Request $request, int|string $id): JsonResponse
    {
        if (!PermissionHelper::canManageDocuments()) {
            return response()->json([
                'message' => 'Você não tem permissão para editar documentos de habilitação.',
            ], 403);
        }

        Log::info('DocumentoHabilitacaoController::update - Iniciando', [
            'id' => $id,
            'has_file' => $request->hasFile('arquivo'),
            'tipo' => $request->input('tipo'),
            'numero' => $request->input('numero'),
        ]);

        try {
            $this->ensureEmpresaContext();
            $data = $request->all();
            
            // Validar dados
            $validator = $this->service->validateUpdateData($data, $id);
            
            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Erro de validação',
                    'errors' => $validator->errors()
                ], 422);
            }

            $validated = $validator->validated();
            
            // Anexar arquivo para o DTO processar
            if ($request->hasFile('arquivo')) {
                $validated['arquivo'] = $request->file('arquivo');
            }

            // Criar DTO e executar Use Case
            $dto = CriarDocumentoHabilitacaoDTO::fromArray($validated);
            $documentoEntity = $this->atualizarDocumentoUseCase->executar((int) $id, $dto);

            // Buscar modelo completo para retornar com todos os campos
            $model = DocumentoHabilitacao::with('versoes')->find($documentoEntity->id);
            
            Log::info('DocumentoHabilitacaoController::update - Documento atualizado', [
                'documento_id' => $model->id,
                'tipo' => $model->tipo,
                'numero' => $model->numero,
            ]);
            
            return response()->json([
                'message' => 'Documento atualizado com sucesso!',
                'data' => $model,
            ]);
            
        } catch (\DomainException $e) {
            Log::warning('DocumentoHabilitacaoController::update - Erro de domínio', [
                'error' => $e->getMessage(),
            ]);
            return response()->json([
                'message' => $e->getMessage()
            ], 400);
        } catch (\Exception $e) {
            Log::error('DocumentoHabilitacaoController::update - Erro', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'message' => 'Erro ao atualizar documento de habilitação: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * DELETE /documentos-habilitacao/{id} - Excluir documento
     */
    public function destroy(Request $request, int|string $id): JsonResponse
    {
        if (!PermissionHelper::canManageDocuments()) {
            return response()->json([
                'message' => 'Você não tem permissão para excluir documentos de habilitação.',
            ], 403);
        }

        try {
            $this->service->deleteById($id);
            return response()->json(['message' => 'Documento excluído com sucesso!'], 200);
        } catch (\Exception $e) {
            Log::error('DocumentoHabilitacaoController::destroy - Erro', [
                'error' => $e->getMessage(),
                'id' => $id,
            ]);
            return response()->json([
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * GET /documentos-habilitacao/vencendo - Listar documentos vencendo
     */
    public function vencendo(Request $request): JsonResponse
    {
        try {
            $this->ensureEmpresaContext();
            $context = TenantContext::get();
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
    public function vencidos(Request $request): JsonResponse
    {
        try {
            $this->ensureEmpresaContext();
            $context = TenantContext::get();
            
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
     * Métodos de compatibilidade
     */
    public function index(Request $request)
    {
        return $this->list($request);
    }

    public function show(DocumentoHabilitacao $documentoHabilitacao)
    {
        return $this->get(request(), $documentoHabilitacao->id);
    }
}
