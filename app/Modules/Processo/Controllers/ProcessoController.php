<?php

namespace App\Modules\Processo\Controllers;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Controllers\Traits\HasAuthContext;
use App\Modules\Processo\Services\ProcessoService;
use App\Modules\Processo\Models\Processo;
use App\Modules\Processo\Services\ProcessoStatusService;
use App\Modules\Processo\Services\ProcessoValidationService;
use App\Modules\Processo\Resources\ProcessoResource;
use App\Modules\Processo\Resources\ProcessoListResource;
use App\Application\Processo\UseCases\ExportarProcessosUseCase;
use App\Application\Processo\UseCases\ObterResumoProcessosUseCase;
use App\Application\Processo\UseCases\CriarProcessoUseCase;
use App\Application\Processo\UseCases\AtualizarProcessoUseCase;
use App\Application\Processo\UseCases\ExcluirProcessoUseCase;
use App\Application\Processo\UseCases\ListarProcessosUseCase;
use App\Application\Processo\UseCases\BuscarProcessoUseCase;
use App\Application\Processo\UseCases\MoverParaJulgamentoUseCase;
use App\Application\Processo\UseCases\MarcarProcessoVencidoUseCase;
use App\Application\Processo\UseCases\MarcarProcessoPerdidoUseCase;
use App\Application\Processo\UseCases\ConfirmarPagamentoProcessoUseCase;
use App\Application\Processo\UseCases\BuscarHistoricoConfirmacoesUseCase;
use App\Application\Processo\DTOs\CriarProcessoDTO;
use App\Application\Processo\DTOs\AtualizarProcessoDTO;
use App\Application\Processo\DTOs\ListarProcessosDTO;
use App\Application\Processo\Presenters\ProcessoApiPresenter;
use App\Domain\Processo\Repositories\ProcessoRepositoryInterface;
use App\Domain\Exceptions\NotFoundException;
use App\Http\Requests\Processo\ProcessoCreateRequest;
use App\Http\Requests\Processo\ProcessoUpdateRequest;
use App\Http\Requests\Processo\ConfirmarPagamentoRequest;
use App\Helpers\PermissionHelper;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

/**
 * Controller para gerenciar processos licitatórios
 * 
 * Refatorado para seguir DDD rigorosamente:
 * - Usa Use Cases para lógica de negócio
 * - Usa Resources para transformação
 * - Não acessa modelos Eloquent diretamente (exceto quando necessário para relacionamentos)
 * 
 * Nota: Ainda usa ProcessoService para alguns métodos (update, delete) que serão migrados gradualmente
 */
class ProcessoController extends BaseApiController
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
        private ExportarProcessosUseCase $exportarProcessosUseCase,
        private ObterResumoProcessosUseCase $obterResumoProcessosUseCase,
        private CriarProcessoUseCase $criarProcessoUseCase,
        private AtualizarProcessoUseCase $atualizarProcessoUseCase,
        private ExcluirProcessoUseCase $excluirProcessoUseCase,
        private ListarProcessosUseCase $listarProcessosUseCase,
        private BuscarProcessoUseCase $buscarProcessoUseCase,
        private MoverParaJulgamentoUseCase $moverParaJulgamentoUseCase,
        private MarcarProcessoVencidoUseCase $marcarProcessoVencidoUseCase,
        private MarcarProcessoPerdidoUseCase $marcarProcessoPerdidoUseCase,
        private ConfirmarPagamentoProcessoUseCase $confirmarPagamentoProcessoUseCase,
        private BuscarHistoricoConfirmacoesUseCase $buscarHistoricoConfirmacoesUseCase,
        private ProcessoApiPresenter $presenter,
        private ProcessoRepositoryInterface $processoRepository,
        \App\Modules\Processo\Services\ProcessoDocumentoService $processoDocumentoService = null
    ) {
        $this->service = $processoService; // Para RoutingController (compatibilidade)
        $this->processoService = $processoService; // Mantido para métodos específicos que ainda usam Service
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
     * Resolver processo a partir da rota (compatibilidade API e Web)
     * 
     * @param Request $request
     * @return Processo|null Modelo Eloquent ou null
     */
    protected function resolveProcessoFromRoute(Request $request): ?Processo
    {
        // Tentar route binding primeiro (web routes)
        $processo = $request->route()->parameter('processo');
        
        if ($processo instanceof Processo) {
            return $processo;
        }
        
        // Se for ID, buscar via repository
        if ($processo) {
            return $this->processoRepository->buscarModeloPorId((int) $processo);
        }
        
        return null;
    }

    /**
     * GET /processos/resumo
     * Retorna resumo dos processos
     * 
     * ✅ Refatorado para usar Use Case
     */
    public function resumo(Request $request): JsonResponse
    {
        try {
            $empresa = $this->getEmpresaAtivaOrFail();
            
            // Preparar filtros (remover vazios e mapear)
            $filtros = array_filter($request->all(), function($value) {
                return $value !== '' && $value !== null;
            });
            
            $filtros['empresa_id'] = $empresa->id;

            $resumo = $this->obterResumoProcessosUseCase->executar($filtros);

            return response()->json(['data' => $resumo]);
        } catch (\InvalidArgumentException $e) {
            \Log::error('Erro de validação ao obter resumo de processos', [
                'error' => $e->getMessage(),
                'filtros' => $request->all(),
            ]);
            return response()->json([
                'message' => $e->getMessage()
            ], 400);
        } catch (\Exception $e) {
            \Log::error('Erro ao obter resumo de processos', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'filtros' => $request->all(),
            ]);
            return $this->handleException($e, 'Erro ao obter resumo de processos');
        }
    }

    /**
     * GET /processos/exportar
     * Exporta processos para CSV ou JSON
     * 
     * ✅ Refatorado para usar Use Case
     * - Lógica de formatação movida para Use Case
     * - Controller apenas recebe request e retorna response
     * 
     * Suporta parâmetros de query:
     * - formato: csv (padrão) ou json
     * - Todos os filtros de listagem normais
     */
    public function exportar(Request $request)
    {
        try {
            $empresa = $this->getEmpresaAtivaOrFail();
            
            // Preparar filtros
            $filtros = array_merge($request->all(), [
                'empresa_id' => $empresa->id,
            ]);

            $formato = $request->get('formato', 'csv');

            // Executar Use Case
            return $this->exportarProcessosUseCase->executar($filtros, $formato);
        } catch (\Exception $e) {
            return $this->handleException($e, 'Erro ao exportar processos');
        }
    }

    /**
     * POST /processos/{processo}/mover-julgamento
     * Move processo para status de julgamento
     * 
     * ✅ DDD: Usa Use Case e valida empresa
     */
    public function moverParaJulgamento(Request $request): JsonResponse
    {
        try {
            $empresa = $this->getEmpresaAtivaOrFail();
            $processoId = (int) $request->route()->parameter('processo');
            
            // Executar Use Case (retorna entidade de domínio)
            $processoDomain = $this->moverParaJulgamentoUseCase->executar($processoId, $empresa->id);
            
            // Buscar modelo Eloquent para serialização
            $processoModel = $this->processoRepository->buscarModeloPorId($processoDomain->id, ['orgao', 'setor']);

            return response()->json([
                'message' => 'Processo movido para julgamento com sucesso',
                'data' => new ProcessoResource($processoModel)
            ]);
        } catch (NotFoundException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        } catch (\Exception $e) {
            \Log::error('Erro ao mover processo para julgamento', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    /**
     * POST /processos/{processo}/marcar-vencido
     * Marca processo como vencido
     * 
     * ✅ DDD: Usa Use Case e valida empresa
     */
    public function marcarVencido(Request $request): JsonResponse
    {
        try {
            $empresa = $this->getEmpresaAtivaOrFail();
            $processoId = (int) $request->route()->parameter('processo');
            
            // Executar Use Case (retorna modelo Eloquent - ainda necessário para ProcessoStatusService)
            $processoModel = $this->marcarProcessoVencidoUseCase->executar($processoId, $empresa->id);

            return response()->json([
                'message' => 'Processo marcado como vencido e movido para execução',
                'data' => new ProcessoResource($processoModel)
            ]);
        } catch (NotFoundException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        } catch (\Exception $e) {
            \Log::error('Erro ao marcar processo como vencido', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    /**
     * POST /processos/{processo}/marcar-perdido
     * Marca processo como perdido
     * 
     * ✅ DDD: Usa Use Case e valida empresa
     * 
     * @param Request $request (aceita motivo_perda no body)
     */
    public function marcarPerdido(Request $request): JsonResponse
    {
        try {
            $empresa = $this->getEmpresaAtivaOrFail();
            $processoId = (int) $request->route()->parameter('processo');
            $motivoPerda = $request->input('motivo_perda');
            
            // Executar Use Case (retorna modelo Eloquent - ainda necessário para ProcessoStatusService)
            $processoModel = $this->marcarProcessoPerdidoUseCase->executar($processoId, $empresa->id, $motivoPerda);

            return response()->json([
                'message' => 'Processo marcado como perdido',
                'data' => new ProcessoResource($processoModel)
            ]);
        } catch (NotFoundException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        } catch (\Exception $e) {
            \Log::error('Erro ao marcar processo como perdido', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json(['message' => $e->getMessage()], 400);
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
     * 
     * ✅ DDD: Usa Use Case e valida empresa
     */
    public function confirmarPagamento(ConfirmarPagamentoRequest $request): JsonResponse
    {
        try {
            $empresa = $this->getEmpresaAtivaOrFail();
            $processoId = (int) $request->route()->parameter('processo');
            
            // Request já está validado via Form Request
            $validated = $request->validated();
            $dataRecebimento = isset($validated['data_recebimento']) 
                ? Carbon::parse($validated['data_recebimento']) 
                : null;

            // Executar Use Case (retorna modelo Eloquent - necessário para itens)
            $processoModel = $this->confirmarPagamentoProcessoUseCase->executar(
                $processoId,
                $empresa->id,
                $dataRecebimento
            );

            return response()->json([
                'message' => 'Pagamento confirmado e saldos atualizados com sucesso',
                'data' => new ProcessoResource($processoModel)
            ]);
        } catch (NotFoundException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        } catch (\Exception $e) {
            \Log::error('Erro ao confirmar pagamento', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    /**
     * GET /processos/{processo}/confirmacoes-pagamento
     * Retorna histórico de confirmações de pagamento
     * 
     * ✅ DDD: Usa Use Case
     */
    public function historicoConfirmacoes(Request $request): JsonResponse
    {
        try {
            $empresa = $this->getEmpresaAtivaOrFail();
            $processoId = (int) $request->route()->parameter('processo');
            
            // Executar Use Case
            $historico = $this->buscarHistoricoConfirmacoesUseCase->executar($processoId, $empresa->id);
            
            return response()->json([
                'data' => $historico,
                'total' => count($historico),
            ]);
        } catch (NotFoundException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        } catch (\Exception $e) {
            \Log::error('Erro ao buscar histórico de confirmações', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'message' => 'Erro ao buscar histórico: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET /processos - Listar processos
     * Método chamado pelo Route::module()
     * 
     * ✅ DDD: Usa Use Case e DTO
     */
    public function list(Request $request): JsonResponse
    {
        try {
            $empresa = $this->getEmpresaAtivaOrFail();
            
            \Log::debug('ProcessoController::list() - INÍCIO', [
                'tenant_id' => tenancy()->tenant?->id,
                'empresa_id' => $empresa->id,
                'request_all' => $request->all(),
                'somente_com_orcamento' => $request->input('somente_com_orcamento'),
                'somente_com_orcamento_type' => gettype($request->input('somente_com_orcamento')),
            ]);
            
            // Criar DTO a partir do Request
            $dto = ListarProcessosDTO::fromRequest($request->all(), $empresa->id);
            
            // Executar Use Case (retorna entidades de domínio paginadas)
            $paginado = $this->listarProcessosUseCase->executar($dto);
            
            // Determinar relacionamentos a carregar
            $with = ['orgao', 'setor', 'itens', 'itens.formacoesPreco', 'documentos', 'documentos.documentoHabilitacao', 'empenhos'];
            
            // Se solicitou filtro de orçamento, carregar também os orçamentos dos itens
            $somenteComOrcamento = $request->input('somente_com_orcamento', false);
            
            \Log::debug('ProcessoController::list() - Verificando somente_com_orcamento', [
                'tenant_id' => tenancy()->tenant?->id,
                'empresa_id' => $empresa->id,
                'somente_com_orcamento_raw' => $somenteComOrcamento,
                'somente_com_orcamento_type' => gettype($somenteComOrcamento),
                'check_true' => $somenteComOrcamento === true,
                'check_string_true' => $somenteComOrcamento === 'true',
                'check_string_1' => $somenteComOrcamento === '1',
                'will_load_orcamentos' => ($somenteComOrcamento === true || $somenteComOrcamento === 'true' || $somenteComOrcamento === '1'),
            ]);
            
            if ($somenteComOrcamento === true || $somenteComOrcamento === 'true' || $somenteComOrcamento === '1') {
                $with[] = 'itens.orcamentos.fornecedor';
                \Log::debug('ProcessoController::list() - Carregando orçamentos', [
                    'tenant_id' => tenancy()->tenant?->id,
                    'empresa_id' => $empresa->id,
                    'with' => $with,
                ]);
            }
            
            // Buscar modelos Eloquent para serialização (apenas para relacionamentos)
            $models = collect($paginado->items())->map(function ($processoDomain) use ($with) {
                return $this->processoRepository->buscarModeloPorId(
                    $processoDomain->id,
                    $with
                );
            })->filter();
            
            // Se solicitou filtro de orçamento, garantir que os orçamentos foram carregados
            if ($somenteComOrcamento === true || $somenteComOrcamento === 'true' || $somenteComOrcamento === '1') {
                $empresaId = $empresa->id;
                
                // Coletar todos os item_ids
                $itemIds = [];
                foreach ($models as $processo) {
                    if ($processo && $processo->itens) {
                        foreach ($processo->itens as $item) {
                            $itemIds[] = $item->id;
                        }
                    }
                }
                
                if (!empty($itemIds)) {
                    // Buscar orçamentos diretamente via orcamento_itens
                    $orcamentosPorItem = \DB::table('orcamento_itens')
                        ->join('orcamentos', 'orcamento_itens.orcamento_id', '=', 'orcamentos.id')
                        ->whereIn('orcamento_itens.processo_item_id', $itemIds)
                        ->where('orcamentos.empresa_id', $empresaId)
                        ->whereNotNull('orcamentos.empresa_id')
                        ->select(
                            'orcamento_itens.processo_item_id',
                            'orcamentos.id as orcamento_id'
                        )
                        ->get()
                        ->groupBy('processo_item_id');
                    
                    \Log::debug('ProcessoController::list() - Orçamentos encontrados via query direta', [
                        'tenant_id' => tenancy()->tenant?->id,
                        'empresa_id' => $empresaId,
                        'total_item_ids' => count($itemIds),
                        'orcamentos_por_item' => $orcamentosPorItem->map(fn($group) => $group->count())->toArray(),
                    ]);
                    
                    // Carregar os orçamentos nos itens
                    foreach ($models as $processo) {
                        if ($processo && $processo->itens) {
                            foreach ($processo->itens as $item) {
                                $orcamentosIds = $orcamentosPorItem->get($item->id)?->pluck('orcamento_id')->unique()->toArray() ?? [];
                                
                                if (!empty($orcamentosIds)) {
                                    // Carregar os modelos de orçamento
                                    $orcamentosModels = \App\Modules\Orcamento\Models\Orcamento::withoutGlobalScope('empresa')
                                        ->whereIn('id', $orcamentosIds)
                                        ->where('empresa_id', $empresaId)
                                        ->with('fornecedor')
                                        ->orderBy('criado_em', 'desc')
                                        ->get();
                                    
                                    // Definir o relacionamento manualmente
                                    $item->setRelation('orcamentos', $orcamentosModels);
                                    
                                    \Log::debug('ProcessoController::list() - Orçamentos carregados para item', [
                                        'tenant_id' => tenancy()->tenant?->id,
                                        'empresa_id' => $empresaId,
                                        'item_id' => $item->id,
                                        'total_orcamentos' => $orcamentosModels->count(),
                                    ]);
                                } else {
                                    // Marcar como carregado mesmo que vazio
                                    $item->setRelation('orcamentos', collect([]));
                                }
                            }
                        }
                    }
                }
            }
            
            // Usar Presenter para serialização (ou Resource para manter compatibilidade)
            return response()->json([
                'data' => ProcessoListResource::collection($models),
                'meta' => [
                    'current_page' => $paginado->currentPage(),
                    'last_page' => $paginado->lastPage(),
                    'per_page' => $paginado->perPage(),
                    'total' => $paginado->total(),
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
     * 
     * ✅ DDD: Usa Use Case e DTO
     */
    public function get(Request $request, int|string $id): JsonResponse
    {
        try {
            $empresa = $this->getEmpresaAtivaOrFail();
            
            // Executar Use Case (retorna entidade de domínio)
            $processoDomain = $this->buscarProcessoUseCase->executar((int) $id, $empresa->id);
            
            // Buscar modelo Eloquent para serialização (com relacionamentos)
            $with = $request->get('with', ['orgao', 'setor', 'itens', 'itens.formacoesPreco', 'documentos', 'documentos.documentoHabilitacao', 'empenhos']);
            if (is_string($with)) {
                $with = explode(',', $with);
            }
            $processoModel = $this->processoRepository->buscarModeloPorId($processoDomain->id, $with);

            if (!$processoModel) {
                return response()->json(['message' => 'Processo não encontrado'], 404);
            }

            return response()->json([
                'data' => new ProcessoResource($processoModel)
            ]);
        } catch (NotFoundException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        } catch (\Exception $e) {
            \Log::error('Erro ao buscar processo', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'id' => $id,
            ]);
            
            return response()->json([
                'message' => 'Erro ao buscar processo: ' . $e->getMessage()
            ], 500);
        }
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
     * 
     * ✅ DDD: Usa FormRequest, Use Case e DTO
     */
    public function store(ProcessoCreateRequest $request): JsonResponse
    {
        $empresaId = null;
        $tenantId = tenancy()->tenant?->id;
        
        try {
            $empresa = $this->getEmpresaAtivaOrFail();
            $empresaId = $empresa->id;
            
            \Log::info('ProcessoController::store() - INÍCIO', [
                'empresa_id' => $empresaId,
                'tenant_id' => $tenantId,
                'user_id' => auth()->id(),
                'request_keys' => array_keys($request->all()),
                'has_itens' => !empty($request->input('itens')),
                'itens_count' => is_array($request->input('itens')) ? count($request->input('itens')) : 0,
            ]);
            
            // O Request já está validado via FormRequest
            // Criar DTO a partir dos dados validados
            $validatedData = $request->validated();
            
            \Log::debug('ProcessoController::store() - Dados validados', [
                'empresa_id' => $empresaId,
                'tenant_id' => $tenantId,
                'orgao_id' => $validatedData['orgao_id'] ?? null,
                'modalidade' => $validatedData['modalidade'] ?? null,
                'numero_modalidade' => $validatedData['numero_modalidade'] ?? null,
                'itens_count' => is_array($validatedData['itens'] ?? null) ? count($validatedData['itens']) : 0,
            ]);
            
            $dto = CriarProcessoDTO::fromArray(array_merge($validatedData, ['empresa_id' => $empresa->id]));
            
            \Log::debug('ProcessoController::store() - DTO criado, executando Use Case', [
                'empresa_id' => $empresaId,
                'tenant_id' => $tenantId,
            ]);
            
            // Executar Use Case (retorna entidade de domínio)
            $processoDomain = $this->criarProcessoUseCase->executar($dto);
            
            \Log::info('ProcessoController::store() - Processo criado com sucesso', [
                'empresa_id' => $empresaId,
                'tenant_id' => $tenantId,
                'processo_id' => $processoDomain->id,
            ]);
            
            // Buscar modelo Eloquent para serialização
            $processoModel = $this->processoRepository->buscarModeloPorId($processoDomain->id, ['orgao', 'setor']);

            if (!$processoModel) {
                \Log::error('ProcessoController::store() - Processo criado mas não encontrado no repositório', [
                    'empresa_id' => $empresaId,
                    'tenant_id' => $tenantId,
                    'processo_domain_id' => $processoDomain->id,
                ]);
                return response()->json(['message' => 'Erro ao buscar processo criado'], 500);
            }

            return response()->json([
                'message' => 'Processo criado com sucesso',
                'data' => new ProcessoResource($processoModel)
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            // Erro de validação (422)
            \Log::warning('ProcessoController::store() - Erro de validação', [
                'empresa_id' => $empresaId,
                'tenant_id' => $tenantId,
                'errors' => $e->errors(),
                'message' => $e->getMessage(),
            ]);
            
            return response()->json([
                'message' => 'Erro de validação',
                'errors' => $e->errors()
            ], 422);
        } catch (\DomainException $e) {
            // Erro de negócio (limites de plano, etc) - retornar 400
            \Log::warning('ProcessoController::store() - Erro de domínio', [
                'empresa_id' => $empresaId,
                'tenant_id' => $tenantId,
                'message' => $e->getMessage(),
                'code' => $e->getCode(),
            ]);
            
            return response()->json([
                'message' => $e->getMessage()
            ], 400);
        } catch (\Exception $e) {
            \Log::error('ProcessoController::store() - Erro ao criar processo', [
                'empresa_id' => $empresaId,
                'tenant_id' => $tenantId,
                'user_id' => auth()->id(),
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'request_data_keys' => array_keys($request->all()),
            ]);
            
            return response()->json([
                'message' => 'Erro ao criar processo: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * PUT /processos/{id} - Atualizar processo
     * Método chamado pelo Route::module()
     * 
     * ✅ DDD: Usa FormRequest, Use Case e DTO
     */
    public function update(ProcessoUpdateRequest $request, int|string $id): JsonResponse
    {
        try {
            $empresa = $this->getEmpresaAtivaOrFail();
            
            // O Request já está validado via FormRequest
            // Criar DTO a partir dos dados validados
            $dto = AtualizarProcessoDTO::fromArray($request->validated(), (int) $id, $empresa->id);
            
            // Executar Use Case (retorna entidade de domínio)
            $processoDomain = $this->atualizarProcessoUseCase->executar($dto);
            
            // Buscar modelo Eloquent para serialização
            $processoModel = $this->processoRepository->buscarModeloPorId($processoDomain->id, ['orgao', 'setor']);

            if (!$processoModel) {
                return response()->json(['message' => 'Erro ao buscar processo atualizado'], 500);
            }

            return response()->json([
                'message' => 'Processo atualizado com sucesso',
                'data' => new ProcessoResource($processoModel)
            ]);
        } catch (NotFoundException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        } catch (\DomainException $e) {
            // Erro de negócio (regras de domínio)
            return response()->json([
                'message' => $e->getMessage()
            ], 400);
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
     * 
     * ✅ DDD: Usa Use Case
     */
    public function destroy(Request $request, int|string $id): JsonResponse
    {
        try {
            $empresa = $this->getEmpresaAtivaOrFail();
            
            // Executar Use Case (valida propriedade e deleta)
            $this->excluirProcessoUseCase->executar((int) $id, $empresa->id);

            return response()->json([
                'message' => 'Processo excluído com sucesso'
            ]);
        } catch (NotFoundException $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 404);
        } catch (\DomainException $e) {
            // Erro de negócio (regras de domínio)
            return response()->json([
                'message' => $e->getMessage()
            ], 400);
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
            $empresa = $this->getEmpresaAtivaOrFail();

            $useCase = app(\App\Application\ProcessoDocumento\UseCases\ImportarDocumentosProcessoUseCase::class);
            $importados = $useCase->executar($processo->id, $empresa->id);

            return response()->json([
                'message' => "{$importados} documento(s) importado(s) com sucesso.",
                'importados' => $importados,
            ]);
        } catch (\App\Domain\Exceptions\NotFoundException $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 404);
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
            $empresa = $this->getEmpresaAtivaOrFail();

            $dto = \App\Application\ProcessoDocumento\DTOs\SincronizarDocumentosDTO::fromArray($request->all());
            $useCase = app(\App\Application\ProcessoDocumento\UseCases\SincronizarDocumentosProcessoUseCase::class);
            $useCase->executar($processo->id, $empresa->id, $dto);

            return response()->json([
                'message' => 'Documentos sincronizados com sucesso.',
            ]);
        } catch (\App\Domain\Exceptions\DomainException $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 400);
        } catch (\App\Domain\Exceptions\NotFoundException $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 404);
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
            $empresa = $this->getEmpresaAtivaOrFail();

            $useCase = app(\App\Application\ProcessoDocumento\UseCases\ListarDocumentosProcessoUseCase::class);
            $documentos = $useCase->executar($processo->id, $empresa->id);

            return response()->json([
                'data' => $documentos,
            ]);
        } catch (\App\Domain\Exceptions\NotFoundException $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 404);
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
            $empresa = $this->getEmpresaAtivaOrFail();

            $validated = $request->validate([
                'exigido' => 'sometimes|boolean',
                'disponivel_envio' => 'sometimes|boolean',
                'status' => 'sometimes|string|in:pendente,possui,anexado',
                'observacoes' => 'nullable|string',
                'versao_documento_habilitacao_id' => 'nullable|integer',
            ]);

            $dto = \App\Application\ProcessoDocumento\DTOs\AtualizarDocumentoProcessoDTO::fromArray($validated);
            $useCase = app(\App\Application\ProcessoDocumento\UseCases\AtualizarDocumentoProcessoUseCase::class);
            $procDoc = $useCase->executar(
                $processo->id,
                $empresa->id,
                $processoDocumentoId,
                $dto,
                $request->file('arquivo')
            );

            return response()->json(['data' => $procDoc]);
        } catch (\App\Domain\Exceptions\DomainException $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 400);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Erro de validação',
                'errors' => $e->errors(),
            ], 422);
        } catch (\App\Domain\Exceptions\NotFoundException $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 404);
        } catch (\Exception $e) {
            \Log::error('Erro ao atualizar documento do processo', [
                'processo_id' => $processo->id,
                'documento_id' => $processoDocumentoId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
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
            $empresa = $this->getEmpresaAtivaOrFail();

            $validated = $request->validate([
                'titulo_custom' => 'required|string|max:255',
                'exigido' => 'sometimes|boolean',
                'disponivel_envio' => 'sometimes|boolean',
                'status' => 'sometimes|string|in:pendente,possui,anexado',
                'observacoes' => 'nullable|string',
            ]);

            $dto = \App\Application\ProcessoDocumento\DTOs\CriarDocumentoCustomDTO::fromArray($validated);
            $useCase = app(\App\Application\ProcessoDocumento\UseCases\CriarDocumentoCustomUseCase::class);
            $procDoc = $useCase->executar(
                $processo->id,
                $empresa->id,
                $dto,
                $request->file('arquivo')
            );

            return response()->json(['data' => $procDoc], 201);
        } catch (\App\Domain\Exceptions\DomainException $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 400);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Erro de validação',
                'errors' => $e->errors(),
            ], 422);
        } catch (\App\Domain\Exceptions\NotFoundException $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 404);
        } catch (\Exception $e) {
            \Log::error('Erro ao criar documento custom do processo', [
                'processo_id' => $processo->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
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
            $empresa = $this->getEmpresaAtivaOrFail();

            $useCase = app(\App\Application\ProcessoDocumento\UseCases\BaixarArquivoDocumentoUseCase::class);
            $info = $useCase->executar($processo->id, $empresa->id, $processoDocumentoId);
            
            if (!$info) {
                return response()->json(['message' => 'Arquivo não encontrado para este documento.'], 404);
            }

            return Storage::disk('public')->download($info['path'], $info['nome'], [
                'Content-Type' => $info['mime'] ?? 'application/octet-stream'
            ]);
        } catch (\App\Domain\Exceptions\NotFoundException $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 404);
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

    /**
     * GET /processos/{processo}/download-edital
     * Baixa o arquivo do edital a partir do link_edital
     */
    public function downloadEdital(Processo $processo)
    {
        try {
            $this->assertProcessoEmpresa($processo);

            if (!$processo->link_edital) {
                return response()->json([
                    'message' => 'Nenhum link de edital cadastrado para este processo.'
                ], 404);
            }

            $linkEdital = $processo->link_edital;

            // Verificar se é uma URL válida
            if (!filter_var($linkEdital, FILTER_VALIDATE_URL)) {
                return response()->json([
                    'message' => 'Link do edital inválido.'
                ], 400);
            }

            // Fazer requisição HTTP para baixar o arquivo
            try {
                // Usar file_get_contents com stream_context para baixar o arquivo
                $context = stream_context_create([
                    'http' => [
                        'timeout' => 30,
                        'method' => 'GET',
                        'header' => [
                            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                        ],
                        'ignore_errors' => true,
                    ],
                    'ssl' => [
                        'verify_peer' => false,
                        'verify_peer_name' => false,
                    ],
                ]);

                $content = @file_get_contents($linkEdital, false, $context);
                
                if ($content === false) {
                    throw new \Exception('Não foi possível baixar o arquivo do edital.');
                }

                // Tentar obter headers da resposta
                $contentType = 'application/octet-stream';
                $fileName = 'edital.pdf'; // Nome padrão
                
                if (isset($http_response_header)) {
                    foreach ($http_response_header as $header) {
                        if (stripos($header, 'Content-Type:') === 0) {
                            $contentType = trim(substr($header, 13));
                        } elseif (stripos($header, 'Content-Disposition:') === 0) {
                            $contentDisposition = trim(substr($header, 20));
                            // Extrair nome do arquivo do header Content-Disposition
                            if (preg_match('/filename[^;=\n]*=(([\'"]).*?\2|[^;\n]*)/', $contentDisposition, $matches)) {
                                $fileName = trim($matches[1], '"\'');
                            }
                        }
                    }
                }

                // Se não encontrou nome do arquivo no header, tentar extrair da URL
                if ($fileName === 'edital.pdf') {
                    $path = parse_url($linkEdital, PHP_URL_PATH);
                    if ($path) {
                        $fileName = basename($path) ?: 'edital.pdf';
                    }
                }

                // Se não tiver extensão, tentar detectar pelo Content-Type
                if (!pathinfo($fileName, PATHINFO_EXTENSION)) {
                    if (str_contains($contentType, 'pdf')) {
                        $fileName .= '.pdf';
                    } elseif (str_contains($contentType, 'word') || str_contains($contentType, 'document')) {
                        $fileName .= '.docx';
                    } elseif (str_contains($contentType, 'html')) {
                        $fileName .= '.html';
                    }
                }

                // Retornar o arquivo para download
                return response($content, 200, [
                    'Content-Type' => $contentType,
                    'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
                    'Content-Length' => strlen($content),
                ]);

            } catch (\Exception $e) {
                \Log::error('Erro ao baixar arquivo do edital', [
                    'processo_id' => $processo->id,
                    'link_edital' => $linkEdital,
                    'error' => $e->getMessage(),
                ]);

                return response()->json([
                    'message' => 'Erro ao baixar arquivo do edital. O link pode estar indisponível ou inacessível.'
                ], 500);
            }

        } catch (\Exception $e) {
            \Log::error('Erro ao processar download do edital', [
                'processo_id' => $processo->id ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Erro ao baixar arquivo do edital: ' . $e->getMessage()
            ], 500);
        }
    }
}
