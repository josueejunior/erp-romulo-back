<?php

namespace App\Modules\Processo\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Traits\HasAuthContext;
use App\Modules\Processo\Services\ProcessoService;
use App\Modules\Processo\Models\Processo;
use App\Modules\Processo\Services\ProcessoStatusService;
use App\Modules\Processo\Services\ProcessoValidationService;
use App\Modules\Processo\Resources\ProcessoResource;
use App\Modules\Processo\Resources\ProcessoListResource;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

/**
 * Controller para gerenciar processos licitatórios
 * 
 * Segue o padrão de controllers do sistema:
 * - Estende Controller (que estende RoutingController)
 * - Usa HasDefaultActions para ações padrão (get, list, store, update, destroy)
 * - Usa HasAuthContext para acessar contexto de autenticação
 * - Injeta ProcessoService no construtor
 * - Define $storeDataCast para casting de dados
 */
class ProcessoController extends Controller
{
    use HasAuthContext;

    /**
     * Classe do modelo para casting de dados no store
     */
    protected ?string $storeDataCast = Processo::class;

    /**
     * Service principal
     */
    protected ProcessoService $processoService;

    /**
     * Services auxiliares
     */
    protected ProcessoStatusService $statusService;
    protected ProcessoValidationService $validationService;

    public function __construct(
        ProcessoService $processoService,
        ProcessoStatusService $statusService,
        ProcessoValidationService $validationService
    ) {
        $this->service = $processoService; // Para RoutingController
        $this->processoService = $processoService;
        $this->statusService = $statusService;
        $this->validationService = $validationService;
    }

    /**
     * GET /processos/resumo
     * Retorna resumo dos processos
     */
    public function resumo(Request $request): JsonResponse
    {
        $resumo = $this->processoService->obterResumo($request->all());

        // O frontend espera response.data, então retornar com wrapper 'data'
        return response()->json(['data' => $resumo]);
    }

    /**
     * GET /processos/exportar
     * Exporta processos para CSV/Excel
     */
    public function exportar(Request $request): JsonResponse
    {
        $empresa = $this->getEmpresaOrFail();
        
        $params = $this->processoService->createListParamBag($request->all());
        $processos = $this->processoService->list($params);

        // TODO: Implementar exportação real
        return response()->json([
            'message' => 'Exportação em desenvolvimento',
            'data' => ProcessoListResource::collection($processos->items()),
        ]);
    }

    /**
     * POST /processos/{processo}/mover-julgamento
     * Move processo para status de julgamento
     */
    public function moverParaJulgamento(Request $request, Processo $processo): JsonResponse
    {
        try {
            $processo = $this->processoService->moverParaJulgamento($processo, $this->statusService);

            return response()->json([
                'message' => 'Processo movido para julgamento com sucesso',
                'data' => new ProcessoResource($processo->load(['orgao', 'setor']))
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * POST /processos/{processo}/marcar-vencido
     * Marca processo como vencido
     */
    public function marcarVencido(Request $request, Processo $processo): JsonResponse
    {
        try {
            $processo = $this->processoService->marcarVencido($processo, $this->statusService);

            return response()->json([
                'message' => 'Processo marcado como vencido e movido para execução',
                'data' => new ProcessoResource($processo->load(['orgao', 'setor']))
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * POST /processos/{processo}/marcar-perdido
     * Marca processo como perdido
     */
    public function marcarPerdido(Request $request, Processo $processo): JsonResponse
    {
        try {
            $processo = $this->processoService->marcarPerdido($processo, $this->statusService);

            return response()->json([
                'message' => 'Processo marcado como perdido',
                'data' => new ProcessoResource($processo->load(['orgao', 'setor']))
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * GET /processos/{processo}/sugerir-status
     * Sugere status baseado nas regras de negócio
     */
    public function sugerirStatus(Request $request, Processo $processo): JsonResponse
    {
        try {
            $sugestoes = $this->processoService->sugerirStatus($processo, $this->statusService);

            return response()->json(['data' => $sugestoes]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Sobrescrever index para usar ProcessoListResource
     */
    public function index(Request $request): JsonResponse
    {
        $params = $this->processoService->createListParamBag($request->all());
        $processos = $this->processoService->list($params);

        return response()->json([
            'data' => ProcessoListResource::collection($processos->items()),
            'meta' => [
                'current_page' => $processos->currentPage(),
                'last_page' => $processos->lastPage(),
                'per_page' => $processos->perPage(),
                'total' => $processos->total(),
            ]
        ]);
    }

    /**
     * Sobrescrever show para usar ProcessoResource
     */
    public function show(Request $request, int|string $id): JsonResponse
    {
        $params = $this->processoService->createFindByIdParamBag($request->all());
        $processo = $this->processoService->findById($id, $params);

        if (!$processo) {
            return response()->json(['message' => 'Processo não encontrado'], 404);
        }

        return response()->json([
            'data' => new ProcessoResource($processo)
        ]);
    }
}
