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
use App\Http\Requests\Processo\ConfirmarPagamentoRequest;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

/**
 * Controller para gerenciar processos licitatórios
 * 
 * Segue o padrão de controllers do sistema:
 * - Estende Controller (que estende RoutingController)
 * - Implementa métodos CRUD diretamente
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
     * Exporta processos para CSV
     * 
     * Suporta parâmetros de query:
     * - formato: csv (padrão) ou json
     * - Todos os filtros de listagem normais
     */
    public function exportar(Request $request)
    {
        $empresa = $this->getEmpresaOrFail();
        
        $params = $this->processoService->createListParamBag($request->all());
        
        // Remover paginação para exportar todos
        $params['per_page'] = 10000; // Limite alto para exportar todos
        
        $processos = $this->processoService->list($params);
        
        // Carregar relacionamentos necessários
        $processos->getCollection()->load([
            'orgao',
            'setor',
            'itens',
        ]);

        $formato = $request->get('formato', 'csv');

        if ($formato === 'json') {
            // Retornar JSON
            return response()->json([
                'data' => ProcessoListResource::collection($processos->items()),
                'meta' => [
                    'total' => $processos->total(),
                ],
            ]);
        }

        // Exportar CSV
        return $this->exportarCSV($processos->items());
    }

    /**
     * Exporta processos para CSV
     */
    private function exportarCSV($processos)
    {
        $filename = 'processos_' . date('Y-m-d_His') . '.csv';
        
        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        // Adicionar BOM para UTF-8 (ajuda Excel a reconhecer corretamente)
        $callback = function() use ($processos) {
            $file = fopen('php://output', 'w');
            
            // Adicionar BOM UTF-8
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));
            
            // Cabeçalhos
            fputcsv($file, [
                'ID',
                'Identificador',
                'Número Modalidade',
                'Modalidade',
                'Número Processo Administrativo',
                'Órgão',
                'UASG',
                'Setor',
                'Objeto Resumido',
                'Status',
                'Status Label',
                'Fase Atual',
                'Data Sessão Pública',
                'Próxima Data',
                'Valor Estimado',
                'Valor Mínimo',
                'Valor Vencido',
                'Resultado',
                'Tem Alerta',
                'Data Criação',
                'Data Atualização',
            ], ';');

            // Dados
            foreach ($processos as $processo) {
                $resource = new ProcessoListResource($processo);
                $data = $resource->toArray(request());
                
                $proximaData = $data['proxima_data'] 
                    ? ($data['proxima_data']['data'] ?? '') . ' - ' . ($data['proxima_data']['tipo'] ?? '')
                    : '';
                
                $alertas = $data['alertas'] ?? [];
                $temAlerta = !empty($alertas);
                $alertasTexto = $temAlerta 
                    ? implode('; ', array_map(fn($a) => $a['mensagem'] ?? '', $alertas))
                    : '';

                fputcsv($file, [
                    $data['id'] ?? '',
                    $data['identificador'] ?? '',
                    $data['numero_modalidade'] ?? '',
                    $data['modalidade'] ?? '',
                    $data['numero_processo_administrativo'] ?? '',
                    $data['orgao']['razao_social'] ?? '',
                    $data['orgao']['uasg'] ?? '',
                    $data['setor']['nome'] ?? '',
                    $data['objeto_resumido'] ?? '',
                    $data['status'] ?? '',
                    $data['status_label'] ?? '',
                    $data['fase_atual'] ?? '',
                    $data['data_sessao_publica_formatted'] ?? '',
                    $proximaData,
                    number_format($data['valores']['estimado'] ?? 0, 2, ',', '.'),
                    $data['valores']['minimo'] ? number_format($data['valores']['minimo'], 2, ',', '.') : '',
                    $data['valores']['vencido'] ? number_format($data['valores']['vencido'], 2, ',', '.') : '',
                    $data['resultado'] ?? '',
                    $temAlerta ? 'Sim' : 'Não',
                    $data['created_at'] ?? '',
                    $data['updated_at'] ?? '',
                ], ';');
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
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
     * POST /processos/{processo}/confirmar-pagamento
     * Confirma pagamento do processo e atualiza saldos
     * Usa Form Request para validação
     */
    public function confirmarPagamento(ConfirmarPagamentoRequest $request, Processo $processo): JsonResponse
    {
        try {
            // Request já está validado via Form Request
            $validated = $request->validated();

            $processo = $this->processoService->confirmarPagamento(
                $processo,
                $validated['data_recebimento'] ?? null
            );

            return response()->json([
                'message' => 'Pagamento confirmado e saldos atualizados com sucesso',
                'data' => new ProcessoResource($processo)
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * GET /processos - Listar processos
     * Método chamado pelo Route::module()
     */
    public function list(Request $request): JsonResponse
    {
        try {
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
        } catch (\Exception $e) {
            \Log::error('Erro ao listar processos', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_params' => $request->all(),
            ]);
            
            return response()->json([
                'message' => 'Erro ao listar processos: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Alias para list() - mantém compatibilidade
     */
    public function index(Request $request): JsonResponse
    {
        return $this->list($request);
    }

    /**
     * GET /processos/{id} - Buscar processo por ID
     * Método chamado pelo Route::module()
     */
    public function get(Request $request, int|string $id): JsonResponse
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

    /**
     * Alias para get() - mantém compatibilidade
     */
    public function show(Request $request, int|string $id): JsonResponse
    {
        return $this->get($request, $id);
    }

    /**
     * POST /processos - Criar novo processo
     * Método chamado pelo Route::module()
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validator = $this->processoService->validateStoreData($request->all());
            
            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Dados inválidos',
                    'errors' => $validator->errors()
                ], 422);
            }

            $processo = $this->processoService->store($request->all());

            return response()->json([
                'message' => 'Processo criado com sucesso',
                'data' => new ProcessoResource($processo->load(['orgao', 'setor']))
            ], 201);
        } catch (\Exception $e) {
            \Log::error('Erro ao criar processo', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return response()->json([
                'message' => 'Erro ao criar processo: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * PUT /processos/{id} - Atualizar processo
     * Método chamado pelo Route::module()
     */
    public function update(Request $request, int|string $id): JsonResponse
    {
        try {
            $validator = $this->processoService->validateUpdateData($request->all(), $id);
            
            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Dados inválidos',
                    'errors' => $validator->errors()
                ], 422);
            }

            $processo = $this->processoService->update($id, $request->all());

            return response()->json([
                'message' => 'Processo atualizado com sucesso',
                'data' => new ProcessoResource($processo->load(['orgao', 'setor']))
            ]);
        } catch (\Exception $e) {
            \Log::error('Erro ao atualizar processo', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'id' => $id,
            ]);
            
            return response()->json([
                'message' => 'Erro ao atualizar processo: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * DELETE /processos/{id} - Excluir processo
     * Método chamado pelo Route::module()
     */
    public function destroy(Request $request, int|string $id): JsonResponse
    {
        try {
            $deleted = $this->processoService->deleteById($id);
            
            if (!$deleted) {
                return response()->json([
                    'message' => 'Processo não encontrado'
                ], 404);
            }

            return response()->json([
                'message' => 'Processo excluído com sucesso'
            ]);
        } catch (\Exception $e) {
            \Log::error('Erro ao excluir processo', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'id' => $id,
            ]);
            
            return response()->json([
                'message' => 'Erro ao excluir processo: ' . $e->getMessage()
            ], 500);
        }
    }
}
