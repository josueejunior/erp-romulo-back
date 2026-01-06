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
use App\Helpers\PermissionHelper;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

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
    protected ?\App\Modules\Processo\Services\ProcessoDocumentoService $processoDocumentoService;

    public function __construct(
        ProcessoService $processoService,
        ProcessoStatusService $statusService,
        ProcessoValidationService $validationService,
        \App\Modules\Processo\Services\ProcessoDocumentoService $processoDocumentoService = null
    ) {
        $this->service = $processoService; // Para RoutingController
        $this->processoService = $processoService;
        $this->statusService = $statusService;
        $this->validationService = $validationService;
        $this->processoDocumentoService = $processoDocumentoService;
    }

    protected function assertProcessoEmpresa(Processo $processo): void
    {
        $empresa = $this->getEmpresaAtivaOrFail();
        if ($processo->empresa_id !== $empresa->id) {
            abort(response()->json(['message' => 'Processo não pertence à empresa ativa'], 403));
        }
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
     * 
     * O middleware já inicializou o tenant correto baseado no X-Tenant-ID do header.
     * Apenas retorna os dados dos processos da empresa ativa.
     */
    public function exportar(Request $request)
    {
        // Obter empresa automaticamente (middleware já inicializou baseado no X-Empresa-ID)
        $empresa = $this->getEmpresaAtivaOrFail();
        
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
     * GET /processos/{processo}/confirmacoes-pagamento
     * Retorna histórico de confirmações de pagamento
     */
    public function historicoConfirmacoes(Request $request, Processo $processo): JsonResponse
    {
        try {
            $historico = [];
            
            // Se o processo já tem data de recebimento, incluir no histórico
            if ($processo->data_recebimento_pagamento) {
                // Calcular valores no momento da confirmação
                $receitaTotal = 0;
                $custosDiretos = 0;
                
                foreach ($processo->itens as $item) {
                    if (in_array($item->status_item, ['aceito', 'aceito_habilitado'])) {
                        $receitaTotal += $item->valor_pago ?? $item->valor_faturado ?? 0;
                    }
                }
                
                // Buscar custos diretos (notas fiscais de entrada)
                $notasEntrada = \App\Modules\NotaFiscal\Models\NotaFiscal::where('processo_id', $processo->id)
                    ->where('tipo', 'entrada')
                    ->get();
                
                $custosDiretos = $notasEntrada->sum(fn($nf) => $nf->custo_total ?? 0);
                
                $historico[] = [
                    'id' => 1,
                    'data_recebimento' => $processo->data_recebimento_pagamento->format('Y-m-d'),
                    'data_confirmacao' => $processo->updated_at->format('Y-m-d H:i:s'),
                    'confirmado_por' => $processo->updated_by ?? null,
                    'receita_total' => round($receitaTotal, 2),
                    'custos_diretos' => round($custosDiretos, 2),
                    'lucro_bruto' => round($receitaTotal - $custosDiretos, 2),
                    'status' => 'confirmado',
                ];
            }
            
            return response()->json([
                'data' => $historico,
                'total' => count($historico),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erro ao buscar histórico: ' . $e->getMessage()
            ], 500);
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
                $errors = $validator->errors();
                
                // Log detalhado dos erros de validação
                \Log::warning('Erro de validação ao criar processo', [
                    'errors' => $errors->toArray(),
                    'fields' => array_keys($errors->toArray()),
                    'data' => $request->all(),
                ]);
                
                return response()->json([
                    'message' => 'Dados inválidos',
                    'errors' => $errors->toArray()
                ], 422);
            }

            $processo = $this->processoService->store($request->all());

            return response()->json([
                'message' => 'Processo criado com sucesso',
                'data' => new ProcessoResource($processo->load(['orgao', 'setor']))
            ], 201);
        } catch (\DomainException $e) {
            // Erro de negócio (limites de plano, etc) - retornar 400
            return response()->json([
                'message' => $e->getMessage()
            ], 400);
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
                $errors = $validator->errors();
                
                // Log detalhado dos erros de validação
                \Log::warning('Erro de validação ao atualizar processo', [
                    'processo_id' => $id,
                    'errors' => $errors->toArray(),
                    'fields' => array_keys($errors->toArray()),
                    'data' => $request->all(),
                ]);
                
                return response()->json([
                    'message' => 'Dados inválidos',
                    'errors' => $errors->toArray()
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

    /**
     * POST /processos/{id}/documentos/importar - Importar todos os documentos ativos
     */
    public function importarDocumentos(Request $request, Processo $processo): JsonResponse
    {
        if (!PermissionHelper::canManageDocuments()) {
            return response()->json(['message' => 'Você não tem permissão para gerenciar documentos.'], 403);
        }

        try {
            $this->assertProcessoEmpresa($processo);
            if (!$this->processoDocumentoService) {
                $this->processoDocumentoService = app(\App\Modules\Processo\Services\ProcessoDocumentoService::class);
            }

            $importados = $this->processoDocumentoService->importarTodosDocumentosAtivos($processo);

            return response()->json([
                'message' => "{$importados} documento(s) importado(s) com sucesso.",
                'importados' => $importados,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erro ao importar documentos: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * POST /processos/{id}/documentos/sincronizar - Sincronizar documentos selecionados
     */
    public function sincronizarDocumentos(Request $request, Processo $processo): JsonResponse
    {
        if (!PermissionHelper::canManageDocuments()) {
            return response()->json(['message' => 'Você não tem permissão para gerenciar documentos.'], 403);
        }

        try {
            $request->validate([
                'documentos' => 'required|array',
                'documentos.*.exigido' => 'boolean',
                'documentos.*.disponivel_envio' => 'boolean',
                'documentos.*.status' => 'nullable|string|in:pendente,possui,anexado',
                'documentos.*.observacoes' => 'nullable|string',
            ]);

            $this->assertProcessoEmpresa($processo);

            if (!$this->processoDocumentoService) {
                $this->processoDocumentoService = app(\App\Modules\Processo\Services\ProcessoDocumentoService::class);
            }

            $this->processoDocumentoService->sincronizarDocumentos($processo, $request->documentos);

            return response()->json([
                'message' => 'Documentos sincronizados com sucesso.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erro ao sincronizar documentos: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET /processos/{id}/documentos - Listar documentos do processo
     */
    public function listarDocumentos(Request $request, Processo $processo): JsonResponse
    {
        try {
            $this->assertProcessoEmpresa($processo);
            if (!$this->processoDocumentoService) {
                $this->processoDocumentoService = app(\App\Modules\Processo\Services\ProcessoDocumentoService::class);
            }

            $documentos = $this->processoDocumentoService->obterDocumentosComStatus($processo);

            return response()->json([
                'data' => $documentos,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erro ao listar documentos: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET /processos/{id}/ficha-export
     * Exporta ficha inicial (cabecalho + itens + documentos) em CSV
     */
    public function exportarFicha(Processo $processo)
    {
        $this->assertProcessoEmpresa($processo);

        if (!$this->processoDocumentoService) {
            $this->processoDocumentoService = app(\App\Modules\Processo\Services\ProcessoDocumentoService::class);
        }

        $itens = $processo->itens()->orderBy('numero')->get();
        $documentos = $this->processoDocumentoService->obterDocumentosComStatus($processo);

        $filename = 'ficha_processo_' . $processo->id . '_' . date('Y-m-d_His') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function () use ($processo, $itens, $documentos) {
            $file = fopen('php://output', 'w');
            // BOM UTF-8
            fprintf($file, chr(0xEF) . chr(0xBB) . chr(0xBF));

            // Cabeçalho do processo
            fputcsv($file, ['Ficha inicial do processo']);
            fputcsv($file, ['ID', $processo->id]);
            fputcsv($file, ['Empresa', $processo->empresa_id]);
            fputcsv($file, ['Modalidade', $processo->modalidade]);
            fputcsv($file, ['Número modalidade', $processo->numero_modalidade]);
            fputcsv($file, ['Número processo adm', $processo->numero_processo_administrativo]);
            fputcsv($file, ['SRP', $processo->srp ? 'Sim' : 'Não']);
            fputcsv($file, ['Órgão', optional($processo->orgao)->razao_social]);
            fputcsv($file, ['Setor', optional($processo->setor)->nome]);
            fputcsv($file, ['Objeto resumido', $processo->objeto_resumido]);
            fputcsv($file, ['Tipo seleção fornecedor', $processo->tipo_selecao_fornecedor]);
            fputcsv($file, ['Tipo disputa', $processo->tipo_disputa]);
            fputcsv($file, ['Data sessão pública', $processo->data_hora_sessao_publica]);
            fputcsv($file, ['Endereço entrega', $processo->endereco_entrega]);
            fputcsv($file, ['Forma entrega', $processo->forma_entrega]);
            fputcsv($file, ['Prazo entrega', $processo->prazo_entrega]);
            fputcsv($file, ['Prazo pagamento', $processo->prazo_pagamento]);
            fputcsv($file, ['Validade proposta', $processo->validade_proposta]);
            fputcsv($file, []);

            // Itens
            fputcsv($file, ['Itens']);
            fputcsv($file, ['#', 'Quantidade', 'Unidade', 'Especificação', 'Marca/Modelo ref', 'Atestado cap. técnica', 'Qtd atestado', 'Valor estimado']);
            foreach ($itens as $item) {
                fputcsv($file, [
                    $item->numero ?? '',
                    $item->quantidade ?? '',
                    $item->unidade_medida ?? '',
                    $item->especificacao_tecnica ?? '',
                    $item->marca_modelo_referencia ?? '',
                    $item->pede_atestado_capacidade ? 'Sim' : 'Não',
                    $item->quantidade_atestado ?? '',
                    $item->valor_estimado ?? '',
                ]);
            }

            fputcsv($file, []);

            // Documentos
            fputcsv($file, ['Documentos de habilitação']);
            fputcsv($file, ['Tipo/Título', 'Número', 'Status vinculação', 'Status vencimento', 'Versão selecionada', 'Exigido', 'Disponível para envio', 'Observações']);
            foreach ($documentos as $doc) {
                $versaoLabel = $doc['versao_selecionada']['versao'] ?? $doc['versao_documento_habilitacao_id'] ?? '';
                fputcsv($file, [
                    $doc['documento_custom'] ? ($doc['titulo_custom'] ?? 'Custom') : ($doc['tipo'] ?? ''),
                    $doc['numero'] ?? '',
                    $doc['status'] ?? '',
                    $doc['status_vencimento'] ?? '',
                    $versaoLabel,
                    !empty($doc['exigido']) ? 'Sim' : 'Não',
                    !empty($doc['disponivel_envio']) ? 'Sim' : 'Não',
                    $doc['observacoes'] ?? '',
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * PATCH /processos/{id}/documentos/{processoDocumento}
     */
    public function atualizarDocumento(Request $request, Processo $processo, int $processoDocumentoId): JsonResponse
    {
        if (!PermissionHelper::canManageDocuments()) {
            return response()->json(['message' => 'Você não tem permissão para gerenciar documentos.'], 403);
        }

        try {
            $this->assertProcessoEmpresa($processo);

            $validated = $request->validate([
                'exigido' => 'sometimes|boolean',
                'disponivel_envio' => 'sometimes|boolean',
                'status' => 'sometimes|string|in:pendente,possui,anexado',
                'observacoes' => 'nullable|string',
                'versao_documento_habilitacao_id' => 'nullable|integer',
            ]);

            if (!$this->processoDocumentoService) {
                $this->processoDocumentoService = app(\App\Modules\Processo\Services\ProcessoDocumentoService::class);
            }

            $procDoc = $this->processoDocumentoService->atualizarDocumentoProcesso(
                $processo,
                $processoDocumentoId,
                $validated,
                $request->file('arquivo')
            );

            return response()->json(['data' => $procDoc]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Erro de validação',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erro ao atualizar documento: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * POST /processos/{id}/documentos/custom
     */
    public function criarDocumentoCustom(Request $request, Processo $processo): JsonResponse
    {
        if (!PermissionHelper::canManageDocuments()) {
            return response()->json(['message' => 'Você não tem permissão para gerenciar documentos.'], 403);
        }

        try {
            $this->assertProcessoEmpresa($processo);

            $validated = $request->validate([
                'titulo_custom' => 'required|string|max:255',
                'exigido' => 'sometimes|boolean',
                'disponivel_envio' => 'sometimes|boolean',
                'status' => 'sometimes|string|in:pendente,possui,anexado',
                'observacoes' => 'nullable|string',
            ]);

            if (!$this->processoDocumentoService) {
                $this->processoDocumentoService = app(\App\Modules\Processo\Services\ProcessoDocumentoService::class);
            }

            $procDoc = $this->processoDocumentoService->criarDocumentoCustom(
                $processo,
                $validated,
                $request->file('arquivo')
            );

            return response()->json(['data' => $procDoc], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Erro de validação',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erro ao criar documento: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET /processos/{id}/documentos/{processoDocumento}/download
     */
    public function downloadDocumento(Processo $processo, int $processoDocumentoId)
    {
        try {
            $this->assertProcessoEmpresa($processo);

            if (!$this->processoDocumentoService) {
                $this->processoDocumentoService = app(\App\Modules\Processo\Services\ProcessoDocumentoService::class);
            }

            $info = $this->processoDocumentoService->baixarArquivo($processo, $processoDocumentoId);
            if (!$info) {
                return response()->json(['message' => 'Arquivo não encontrado para este documento.'], 404);
            }

            return Storage::disk('public')->download($info['path'], $info['nome'], [
                'Content-Type' => $info['mime'] ?? 'application/octet-stream'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erro ao baixar documento: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET /processos/{processo}/ficha-export
     * Exporta ficha técnica/proposta comercial do processo
     */
    public function fichaTecnicaExport(Processo $processo, Request $request): JsonResponse
    {
        try {
            $this->assertProcessoEmpresa($processo);
            
            $tipo = $request->get('tipo', 'ficha-tecnica'); // ficha-tecnica ou proposta
            $formato = $request->get('formato', 'pdf'); // pdf ou docx
            
            $processo->load(['orgao', 'setor', 'itens']);
            
            // Preparar dados da ficha/proposta (simplificado)
            $data = [
                'id' => $processo->id,
                'numero_modalidade' => $processo->numero_modalidade,
                'numero_processo_administrativo' => $processo->numero_processo_administrativo,
                'modalidade' => $processo->modalidade,
                'objeto_resumido' => $processo->objeto_resumido,
                'orgao' => $processo->orgao?->toArray(),
                'setor' => $processo->setor?->toArray(),
                'data_sessao_publica' => $processo->data_sessao_publica,
                'validade_proposta' => $processo->validade_proposta,
                'itens' => $processo->itens?->toArray() ?? [],
                'data_emissao' => now()->format('d/m/Y'),
            ];
            
            // Retornar dados ou arquivo (implementar conforme necessidade)
            return response()->json([
                'success' => true,
                'message' => "Exportação de {$tipo} ({$formato}) preparada",
                'data' => $data,
            ]);
        } catch (\Exception $e) {
            \Log::error('Erro ao exportar ficha: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'message' => 'Erro ao exportar ficha: ' . $e->getMessage()
            ], 500);
        }
    }
}
