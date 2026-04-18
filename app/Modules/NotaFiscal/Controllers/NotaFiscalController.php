<?php

namespace App\Modules\NotaFiscal\Controllers;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Controllers\Traits\HasAuthContext;
// ✅ DDD: Controller não importa modelos Eloquent diretamente
// Apenas usa interfaces de repositório e Use Cases
use App\Modules\NotaFiscal\Services\NotaFiscalService;
use App\Application\NotaFiscal\UseCases\CriarNotaFiscalUseCase;
use App\Application\NotaFiscal\UseCases\ListarNotasFiscaisUseCase;
use App\Application\NotaFiscal\UseCases\BuscarNotaFiscalUseCase;
use App\Application\NotaFiscal\UseCases\AtualizarNotaFiscalUseCase;
use App\Application\NotaFiscal\UseCases\ExcluirNotaFiscalUseCase;
use App\Application\NotaFiscal\DTOs\CriarNotaFiscalDTO;
use App\Application\NotaFiscal\DTOs\AtualizarNotaFiscalDTO;
use App\Application\NotaFiscal\DTOs\FiltroNotaFiscalDTO;
use App\Domain\Processo\Repositories\ProcessoRepositoryInterface;
use App\Domain\NotaFiscal\Repositories\NotaFiscalRepositoryInterface;
use App\Http\Requests\NotaFiscal\NotaFiscalCreateRequest;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

/**
 * Controller para gerenciamento de Notas Fiscais
 * 
 * Refatorado para seguir DDD rigorosamente:
 * - Usa Form Requests para validação
 * - Usa Use Cases para lógica de negócio
 * - Não acessa modelos Eloquent diretamente (exceto para relacionamentos)
 * 
 * Segue o mesmo padrão do AssinaturaController e FornecedorController:
 * - Tenant ID: Obtido automaticamente via tenancy()->tenant (middleware já inicializou)
 * - Empresa ID: Obtido automaticamente via getEmpresaAtivaOrFail() que prioriza header X-Empresa-ID
 */
class NotaFiscalController extends BaseApiController
{
    use HasAuthContext;

    protected NotaFiscalService $notaFiscalService;

    public function __construct(
        NotaFiscalService $notaFiscalService, // Mantido para métodos específicos que ainda usam Service
        private CriarNotaFiscalUseCase $criarNotaFiscalUseCase,
        private ListarNotasFiscaisUseCase $listarNotasFiscaisUseCase,
        private BuscarNotaFiscalUseCase $buscarNotaFiscalUseCase,
        private AtualizarNotaFiscalUseCase $atualizarNotaFiscalUseCase,
        private ExcluirNotaFiscalUseCase $excluirNotaFiscalUseCase,
        private \App\Application\NotaFiscal\UseCases\PagarNotaFiscalUseCase $pagarNotaFiscalUseCase,
        private ProcessoRepositoryInterface $processoRepository,
        private NotaFiscalRepositoryInterface $notaFiscalRepository,
        private \App\Modules\Processo\Services\ProcessoItemVinculoService $processoItemVinculoService,
    ) {
        $this->notaFiscalService = $notaFiscalService; // Para métodos que ainda precisam do Service
    }

    /**
     * API: Listar todas as notas fiscais da empresa (sem filtro de processo)
     * 
     * ✅ DDD: Controller apenas orquestra, toda lógica no Use Case
     */
    public function listAll(Request $request): JsonResponse
    {
        try {
            $empresa = $this->getEmpresaAtivaOrFail();
            
            // Criar DTO de filtros (Application Layer)
            $filtroDTO = FiltroNotaFiscalDTO::fromRequest($request->all(), $empresa->id);
            
            // Executar Use Case (única porta de entrada do domínio)
            $paginado = $this->listarNotasFiscaisUseCase->executar($filtroDTO->toRepositoryFilters());
            
            // Transformar entidades de domínio para resposta
            $items = collect($paginado->items())->map(function ($notaFiscalDomain) {
                // Buscar modelo Eloquent apenas para serialização (Infrastructure)
                $notaFiscalModel = $this->notaFiscalRepository->buscarModeloPorId(
                    $notaFiscalDomain->id,
                    ['processo', 'empenho', 'contrato', 'autorizacaoFornecimento', 'fornecedor']
                );
                
                if (!$notaFiscalModel) {
                    return null;
                }
                
                $array = $notaFiscalModel->toArray();
                // Garantir que processo_id está presente
                if (!isset($array['processo_id']) && $notaFiscalModel->processo) {
                    $array['processo_id'] = $notaFiscalModel->processo->id;
                }
                return $array;
            })->filter();
            
            return response()->json([
                'data' => $items->values()->all(),
                'meta' => [
                    'current_page' => $paginado->currentPage(),
                    'last_page' => $paginado->lastPage(),
                    'per_page' => $paginado->perPage(),
                    'total' => $paginado->total(),
                ],
            ]);
        } catch (\Exception $e) {
            return $this->handleException($e, 'Erro ao listar notas fiscais');
        }
    }

    /**
     * API: Listar notas fiscais (Route::module)
     */
    public function list(Request $request)
    {
        $processoId = $request->route()->parameter('processo');
        $processoModel = $this->processoRepository->buscarModeloPorId($processoId);
        if (!$processoModel) {
            return response()->json(['message' => 'Processo não encontrado.'], 404);
        }
        return $this->index($request);
    }

    /**
     * API: Buscar nota fiscal (Route::module)
     * 
     * ✅ DDD: Apenas delega para show
     */
    public function get(Request $request)
    {
        return $this->show($request);
    }

    /**
     * Listar notas fiscais de um processo
     * 
     * ✅ DDD: Controller não conhece Eloquent, apenas orquestra Use Cases
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $processoId = (int) $request->route()->parameter('processo');
            $empresa = $this->getEmpresaAtivaOrFail();
            
            // Preparar filtros via DTO (Application Layer)
            $filtroDTO = FiltroNotaFiscalDTO::fromRequest(
                array_merge($request->all(), ['processo_id' => $processoId]),
                $empresa->id
            );
            
            // Executar Use Case (única porta de entrada do domínio)
            $paginado = $this->listarNotasFiscaisUseCase->executar($filtroDTO->toRepositoryFilters());
            
            // Transformar entidades de domínio para resposta
            $items = collect($paginado->items())->map(function ($notaFiscalDomain) {
                // Buscar modelo Eloquent apenas para serialização (Infrastructure)
                $notaFiscalModel = $this->notaFiscalRepository->buscarModeloPorId(
                    $notaFiscalDomain->id,
                    ['empenho', 'contrato', 'autorizacaoFornecimento', 'fornecedor', 'processo']
                );
                return $notaFiscalModel ? $notaFiscalModel->toArray() : null;
            })->filter();
            
            return response()->json([
                'data' => $items->values()->all(),
                'meta' => [
                    'current_page' => $paginado->currentPage(),
                    'last_page' => $paginado->lastPage(),
                    'per_page' => $paginado->perPage(),
                    'total' => $paginado->total(),
                ],
            ]);
        } catch (\App\Domain\Exceptions\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        } catch (\Exception $e) {
            return $this->handleException($e, 'Erro ao listar notas fiscais');
        }
    }

    /**
     * API: Criar nota fiscal (Route::module)
     */
    public function store(Request $request)
    {
        $processoId = $request->route()->parameter('processo');
        $processoModel = $this->processoRepository->buscarModeloPorId($processoId);
        if (!$processoModel) {
            return response()->json(['message' => 'Processo não encontrado.'], 404);
        }
        
        // Validar dados manualmente (mesmo padrão do EmpenhoController)
        $rules = (new NotaFiscalCreateRequest())->rules();
        $validator = Validator::make($request->all(), $rules);
        
        if ($validator->fails()) {
            return response()->json([
                'message' => 'Dados inválidos',
                'errors' => $validator->errors()
            ], 422);
        }
        
        // Passar dados validados diretamente como array
        return $this->storeWeb($validator->validated(), $processoId);
    }

    /**
     * Web: Criar nota fiscal
     * 
     * ✅ O QUE O CONTROLLER FAZ:
     * - Recebe request
     * - Valida dados (via Form Request ou array validado)
     * - Chama um Application Service
     * 
     * ❌ O QUE O CONTROLLER NÃO FAZ:
     * - Não lê tenant_id
     * - Não acessa Tenant
     * - Não sabe se existe multi-tenant
     * - Não filtra nada por tenant_id
     */
    public function storeWeb(NotaFiscalCreateRequest|array $request, int $processoId): JsonResponse
    {
        try {
            return \Illuminate\Support\Facades\DB::transaction(function () use ($request, $processoId) {
                $empresa = $this->getEmpresaAtivaOrFail();
                
                // Se for array, já está validado. Se for FormRequest, chamar validated()
                $data = is_array($request) ? $request : $request->validated();
                $data['processo_id'] = $processoId;
                
                // Extrair itens antes de criar a nota fiscal
                $itens = $data['itens'] ?? [];
                unset($data['itens']); // Remover itens do data principal
                
                // Usar Use Case DDD (contém toda a lógica de negócio, incluindo tenant)
                $dto = CriarNotaFiscalDTO::fromArray($data);
                $notaFiscalDomain = $this->criarNotaFiscalUseCase->executar($dto);
                
                // Buscar modelo Eloquent para resposta usando repository
                $notaFiscal = $this->notaFiscalRepository->buscarModeloPorId(
                    $notaFiscalDomain->id,
                    ['empenho', 'contrato', 'autorizacaoFornecimento', 'fornecedor']
                );
                
                if (!$notaFiscal) {
                    throw new \Exception('Nota fiscal não encontrada após criação.');
                }
                
                // 🔥 Criar vínculos com itens do processo
                if (!empty($itens)) {
                    $processo = $this->processoRepository->buscarModeloPorId($processoId);
                    
                    foreach ($itens as $itemData) {
                        try {
                            $processoItem = \App\Modules\Processo\Models\ProcessoItem::find($itemData['processo_item_id']);
                            if (!$processoItem) {
                                throw new \Exception("Item {$itemData['processo_item_id']} não encontrado.");
                            }
                            
                            $vinculoData = [
                                'processo_item_id' => $itemData['processo_item_id'],
                                'nota_fiscal_id' => $notaFiscal->id,
                                'quantidade' => $itemData['quantidade'] ?? 1,
                                'valor_unitario' => $itemData['valor_unitario'] ?? 0,
                                'valor_total' => $itemData['valor_total'] ?? ($itemData['quantidade'] * $itemData['valor_unitario']),
                                'ignore_quantity_check' => ($notaFiscal->tipo === 'entrada'), // 🔥 Ignorar validação de quantidade para entradas
                            ];
                            
                            // Se a NF tem empenho, vincular também ao empenho
                            if ($notaFiscal->empenho_id) {
                                $vinculoData['empenho_id'] = $notaFiscal->empenho_id;
                            }
                            
                            $this->processoItemVinculoService->store($processo, $processoItem, $vinculoData, $empresa->id);
                        } catch (\Exception $e) {
                            // Se falhar o vínculo, lançamos exceção para dar rollback em TUDO
                            throw $e;
                        }
                    }
                }
                
                // Recalcular financeiros do processo para o Dashboard
                try {
                    $this->saldoService->recalcularValoresFinanceirosItens($processoId);
                } catch (\Exception $e) {
                    \Log::warning('Erro ao recalcular financeiros após criar nota fiscal: ' . $e->getMessage());
                }

                return response()->json([
                    'message' => 'Nota fiscal criada com sucesso',
                    'data' => $notaFiscal->toArray(),
                ], 201);
            });
        } catch (\App\Domain\Exceptions\DomainException $e) {
            $statusCode = $e->getMessage() === 'Notas fiscais só podem ser criadas para processos em execução.' ? 403 : 
                         ($e->getMessage() === 'Nota fiscal deve estar vinculada a um Empenho, Contrato ou Autorização de Fornecimento.' ? 400 : 400);
            return response()->json([
                'message' => $e->getMessage(),
            ], $statusCode);
        } catch (\Exception $e) {
            return $this->handleException($e, 'Erro ao criar nota fiscal');
        }
    }

    /**
     * Obter nota fiscal específica
     * 
     * ✅ DDD: Controller não conhece Eloquent, apenas orquestra Use Cases
     */
    public function show(Request $request): JsonResponse
    {
        try {
            $processoId = (int) $request->route()->parameter('processo');
            $notaFiscalId = (int) $request->route()->parameter('notaFiscal');
            $empresa = $this->getEmpresaAtivaOrFail();
            
            // Executar Use Case (validações de negócio dentro do Use Case)
            $notaFiscalDomain = $this->buscarNotaFiscalUseCase->executar($notaFiscalId);
            
            // Validar que a nota fiscal pertence à empresa (regra de domínio - deveria estar no Use Case)
            // Por enquanto mantemos aqui, mas idealmente o Use Case deveria receber empresaId
            if ($notaFiscalDomain->empresaId !== $empresa->id) {
                return response()->json(['message' => 'Nota fiscal não encontrada'], 404);
            }
            
            // Buscar modelo Eloquent apenas para serialização (Infrastructure)
            $notaFiscalModel = $this->notaFiscalRepository->buscarModeloPorId(
                $notaFiscalDomain->id,
                ['empenho', 'contrato', 'autorizacaoFornecimento', 'fornecedor', 'processo']
            );
            
            if (!$notaFiscalModel) {
                return response()->json(['message' => 'Nota fiscal não encontrada'], 404);
            }
            
            return response()->json(['data' => $notaFiscalModel->toArray()]);
        } catch (\App\Domain\Exceptions\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        } catch (\Exception $e) {
            return $this->handleException($e, 'Erro ao buscar nota fiscal');
        }
    }

    /**
     * API: Atualizar nota fiscal (Route::module)
     */
    /**
     * API: Atualizar nota fiscal (Route::module)
     * 
     * ✅ DDD: Controller apenas orquestra, validações no Use Case
     */
    public function update(Request $request)
    {
        try {
            $processoId = (int) $request->route()->parameter('processo');
            $notaFiscalId = (int) $request->route()->parameter('notaFiscal');
            $empresa = $this->getEmpresaAtivaOrFail();
            
            // Validar dados (Form Request - validação de formato)
            $rules = (new NotaFiscalCreateRequest())->rules();
            $validator = Validator::make($request->all(), $rules);
            
            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Dados inválidos',
                    'errors' => $validator->errors()
                ], 422);
            }
            
            // Delegar para método Web (que usa Use Case - validação de regras de negócio)
            return $this->updateWeb($request, $processoId, $notaFiscalId);
        } catch (\App\Domain\Exceptions\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        } catch (\Exception $e) {
            Log::error('Erro ao atualizar nota fiscal', [
                'processo_id' => $request->route()->parameter('processo'),
                'nota_fiscal_id' => $request->route()->parameter('notaFiscal'),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return $this->handleException($e, 'Erro ao atualizar nota fiscal');
        }
    }

    /**
     * API: Excluir nota fiscal (Route::module)
     */
    /**
     * API: Excluir nota fiscal (Route::module)
     * 
     * ✅ DDD: Controller apenas orquestra, validações no Use Case
     */
    public function destroy(Request $request)
    {
        try {
            $processoId = (int) $request->route()->parameter('processo');
            $notaFiscalId = (int) $request->route()->parameter('notaFiscal');
            
            // Delegar para método Web (que usa Use Case - validação de regras de negócio)
            return $this->destroyWeb($processoId, $notaFiscalId);
        } catch (\App\Domain\Exceptions\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        } catch (\Exception $e) {
            Log::error('Erro ao excluir nota fiscal', [
                'processo_id' => $request->route()->parameter('processo'),
                'nota_fiscal_id' => $request->route()->parameter('notaFiscal'),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return $this->handleException($e, 'Erro ao excluir nota fiscal');
        }
    }

    /**
     * Web: Atualizar nota fiscal
     * 
     * ✅ DDD: Não recebe modelos Eloquent, apenas IDs
     */
    public function updateWeb(Request $request, int $processoId, int $notaFiscalId)
    {
        try {
            return \Illuminate\Support\Facades\DB::transaction(function () use ($request, $processoId, $notaFiscalId) {
                $empresa = $this->getEmpresaAtivaOrFail();
                
                // Validar dados usando as mesmas regras do create
                $rules = (new NotaFiscalCreateRequest())->rules();
                $validator = Validator::make($request->all(), $rules);
                
                if ($validator->fails()) {
                    throw new \Illuminate\Validation\ValidationException($validator);
                }
                
                $data = $validator->validated();
                
                // 🔥 GARANTIA: Se o tipo veio no request mas via validator se perdeu (improvável, mas possível), restaurar.
                if ($request->has('tipo') && !isset($data['tipo'])) {
                   $data['tipo'] = $request->input('tipo');
                   \Log::info('Restaurando tipo manualmente para data:', ['tipo' => $data['tipo']]);
                }

                \Log::info('NotaFiscalController::updateWeb - Dados Validados', [
                    'request_all' => $request->all(),
                    'validated_data' => $data,
                    'tipo_in_request' => $request->input('tipo'),
                    'tipo_in_data' => $data['tipo'] ?? 'NULL',
                ]);
                
                // Extrair itens antes de atualizar a nota fiscal
                $itens = $data['itens'] ?? [];
                unset($data['itens']); // Remover itens do data principal
                
                // Usar Use Case DDD (contém toda a lógica de negócio)
                $dto = AtualizarNotaFiscalDTO::fromArray($data, $notaFiscalId);
                $notaFiscalDomain = $this->atualizarNotaFiscalUseCase->executar($dto, $empresa->id);
                
                // 🔥 Atualizar vínculos com itens do processo
                // Primeiro, remover vínculos antigos para esta NF para evitar duplicidade ou órfãos
                \App\Modules\Processo\Models\ProcessoItemVinculo::where('nota_fiscal_id', $notaFiscalId)->delete();
                
                if (!empty($itens)) {
                    $processo = $this->processoRepository->buscarModeloPorId($processoId);
                    
                    foreach ($itens as $itemData) {
                        try {
                            $processoItem = \App\Modules\Processo\Models\ProcessoItem::find($itemData['processo_item_id']);
                            if (!$processoItem) {
                                throw new \Exception("Item {$itemData['processo_item_id']} não encontrado.");
                            }
                            
                            // Determinar se é entrada considerando Request E Domain
                            $tipoReal = $request->input('tipo') ?? $notaFiscalDomain->tipo;
                            
                            $vinculoData = [
                                'processo_item_id' => $itemData['processo_item_id'],
                                'nota_fiscal_id' => $notaFiscalId,
                                'quantidade' => $itemData['quantidade'] ?? 1,
                                'valor_unitario' => $itemData['valor_unitario'] ?? 0,
                                'valor_total' => $itemData['valor_total'] ?? ($itemData['quantidade'] * $itemData['valor_unitario']),
                                'ignore_quantity_check' => ($tipoReal === 'entrada'), // 🔥 Ignorar validação de quantidade para entradas
                            ];
                            
                            // Se a NF tem empenho, vincular também ao empenho
                            if ($notaFiscalDomain->empenhoId) {
                                $vinculoData['empenho_id'] = $notaFiscalDomain->empenhoId;
                            }
                            
                            $this->processoItemVinculoService->store($processo, $processoItem, $vinculoData, $empresa->id);
                        } catch (\Exception $e) {
                            throw $e;
                        }
                    }
                }
                
                // Recalcular financeiros do processo para o Dashboard
                try {
                    $this->saldoService->recalcularValoresFinanceirosItens($processoId);
                } catch (\Exception $e) {
                    \Log::warning('Erro ao recalcular financeiros após atualizar nota fiscal: ' . $e->getMessage());
                }

                // Buscar modelo Eloquent para resposta usando repository
                $notaFiscalModel = $this->notaFiscalRepository->buscarModeloPorId(
                    $notaFiscalDomain->id,
                    ['empenho', 'contrato', 'autorizacaoFornecimento', 'fornecedor', 'processo']
                );
                
                if (!$notaFiscalModel) {
                    throw new \Exception('Nota fiscal não encontrada após atualização.');
                }
                
                return response()->json([
                    'message' => 'Nota fiscal atualizada com sucesso',
                    'data' => $notaFiscalModel->toArray(),
                ]);
            });
        } catch (\App\Domain\Exceptions\DomainException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 400);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Dados inválidos',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return $this->handleException($e, 'Erro ao atualizar nota fiscal');
        }
    }

    /**
     * Web: Excluir nota fiscal
     * 
     * ✅ DDD: Usa Use Case, não Service
     */
    public function destroyWeb(int $processoId, int $notaFiscalId)
    {
        $empresa = $this->getEmpresaAtivaOrFail();
        
        try {
            // Usar Use Case DDD (contém toda a lógica de negócio)
            $this->excluirNotaFiscalUseCase->executar($notaFiscalId, $empresa->id);
            return response()->json(null, 204);
        } catch (\App\Domain\Exceptions\DomainException $e) {
            $message = $e->getMessage();
            $statusCode = str_contains($message, 'não pode ser excluída') ? 422 : 404;
            return response()->json(['message' => $message], $statusCode);
        } catch (\Exception $e) {
            return $this->handleException($e, 'Erro ao excluir nota fiscal');
        }
    }
    /**
     * Marcar nota fiscal como paga
     */
    public function pagar(Request $request): JsonResponse
    {
        try {
            $notaFiscalId = (int) $request->route()->parameter('notaFiscal');
            $empresa = $this->getEmpresaAtivaOrFail();
            
            $this->pagarNotaFiscalUseCase->executar($notaFiscalId, $empresa->id);
            
            return response()->json([
                'message' => 'Nota fiscal marcada como paga com sucesso',
            ]);
        } catch (\App\Domain\Exceptions\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            return $this->handleException($e, 'Erro ao marcar nota fiscal como paga');
        }
    }
}

