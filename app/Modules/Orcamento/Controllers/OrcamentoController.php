<?php

namespace App\Modules\Orcamento\Controllers;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Controllers\Traits\HasAuthContext;
use App\Http\Resources\OrcamentoResource;
// ✅ DDD: Controller não importa modelos Eloquent diretamente
// Apenas usa interfaces de repositório e Use Cases
use App\Modules\Orcamento\Services\OrcamentoService;
use App\Application\Orcamento\UseCases\CriarOrcamentoUseCase;
use App\Application\Orcamento\UseCases\AtualizarOrcamentoUseCase;
use App\Application\Orcamento\UseCases\ExcluirOrcamentoUseCase;
use App\Application\Orcamento\UseCases\BuscarOrcamentoUseCase;
use App\Application\Orcamento\UseCases\AtualizarOrcamentoItemUseCase;
use App\Application\Orcamento\DTOs\CriarOrcamentoDTO;
use App\Application\Orcamento\DTOs\AtualizarOrcamentoDTO;
use App\Http\Requests\Orcamento\OrcamentoCreateRequest;
use App\Http\Requests\Orcamento\OrcamentoUpdateRequest;
use App\Domain\Processo\Repositories\ProcessoRepositoryInterface;
use App\Domain\ProcessoItem\Repositories\ProcessoItemRepositoryInterface;
use App\Domain\Orcamento\Repositories\OrcamentoRepositoryInterface;
use App\Http\Requests\Orcamento\OrcamentoItemUpdateRequest;
use App\Modules\Processo\Models\Processo;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

/**
 * Controller para gerenciamento de Orçamentos
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
class OrcamentoController extends BaseApiController
{
    use HasAuthContext;

    protected OrcamentoService $orcamentoService;

    public function __construct(
        OrcamentoService $orcamentoService, // Mantido temporariamente para métodos legacy
        private CriarOrcamentoUseCase $criarOrcamentoUseCase,
        private AtualizarOrcamentoUseCase $atualizarOrcamentoUseCase,
        private ExcluirOrcamentoUseCase $excluirOrcamentoUseCase,
        private BuscarOrcamentoUseCase $buscarOrcamentoUseCase,
        private AtualizarOrcamentoItemUseCase $atualizarOrcamentoItemUseCase,
        private ProcessoRepositoryInterface $processoRepository,
        private ProcessoItemRepositoryInterface $processoItemRepository,
        private OrcamentoRepositoryInterface $orcamentoRepository,
    ) {
        $this->orcamentoService = $orcamentoService; // Para métodos que ainda precisam do Service
    }

    /**
     * API: Listar orçamentos de um item (Route::module)
     */
    /**
     * API: Listar orçamentos (Route::module)
     * 
     * ✅ DDD: Apenas delega para index
     */
    public function list(Request $request)
    {
        return $this->index($request);
    }

    /**
     * API: Buscar orçamento específico (Route::module)
     */
    /**
     * API: Buscar orçamento (Route::module)
     * 
     * ✅ DDD: Apenas delega para show
     */
    public function get(Request $request)
    {
        return $this->show($request);
    }

    /**
     * Listar orçamentos de um item
     * 
     * O middleware já inicializou o tenant correto baseado no X-Tenant-ID do header.
     * Apenas retorna os dados dos orçamentos da empresa ativa.
     */
    /**
     * Listar orçamentos de um item
     * 
     * ✅ DDD: Controller apenas orquestra, validações no Use Case
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $processoId = (int) $request->route()->parameter('processo');
            $itemId = (int) $request->route()->parameter('item');
            $empresa = $this->getEmpresaAtivaOrFail();
            
            // Usar Service temporariamente (será migrado para Use Case)
            $itemModel = $this->processoItemRepository->buscarModeloPorId($itemId);
            if (!$itemModel) {
                return response()->json(['message' => 'Item não encontrado'], 404);
            }

            $orcamentos = $this->orcamentoService->listByItem($itemModel);

            return response()->json([
                'data' => OrcamentoResource::collection($orcamentos),
            ]);
        } catch (\App\Domain\Exceptions\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        } catch (\Exception $e) {
            return $this->handleException($e, 'Erro ao listar orçamentos');
        }
    }

    /**
     * API: Criar orçamento (Route::module)
     */
    /**
     * API: Criar orçamento (Route::module)
     * 
     * ✅ DDD: Controller apenas orquestra, validações no Use Case
     */
    public function store(OrcamentoCreateRequest $request)
    {
        try {
            $processoId = (int) $request->route()->parameter('processo');
            $itemId = (int) $request->route()->parameter('item');
            
            // Delegar para método Web (que usa Use Case - validação de regras de negócio)
            return $this->storeWeb($request, $processoId, $itemId);
        } catch (\App\Domain\Exceptions\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        } catch (\Exception $e) {
            return $this->handleException($e, 'Erro ao criar orçamento');
        }
    }

    /**
     * Web: Criar orçamento
     * Usa Form Request para validação e Use Case para lógica de negócio
     */
    /**
     * Web: Criar orçamento
     * 
     * ✅ DDD: Controller apenas orquestra, toda lógica no Use Case
     */
    public function storeWeb(OrcamentoCreateRequest $request, int $processoId, int $itemId): JsonResponse
    {
        try {
            $empresa = $this->getEmpresaAtivaOrFail();
            
            // Verificar permissão usando Policy (precisa do modelo)
            $processoModel = $this->processoRepository->buscarModeloPorId($processoId);
            if ($processoModel) {
                $this->authorize('create', [$processoModel]);
            }

            // Request já está validado via Form Request
            // Preparar dados para DTO
            $data = $request->validated();
            $data['processo_id'] = $processoId;
            $data['processo_item_id'] = $itemId;
            $data['empresa_id'] = $empresa->id;
            
            \Log::info('OrcamentoController::storeWeb - Dados recebidos', [
                'processo_id' => $processoId,
                'item_id' => $itemId,
                'empresa_id' => $empresa->id,
                'data_validated' => $data,
            ]);
            
            // Usar Use Case DDD (validações de negócio dentro do Use Case)
            $dto = CriarOrcamentoDTO::fromArray($data);
            $orcamentoDomain = $this->criarOrcamentoUseCase->executar($dto);
            
            \Log::info('OrcamentoController::storeWeb - Orçamento criado pelo UseCase', [
                'orcamento_id' => $orcamentoDomain->id,
                'empresa_id' => $orcamentoDomain->empresaId,
                'processo_id' => $orcamentoDomain->processoId,
            ]);
            
            // Buscar modelo Eloquent para Resource usando repository
            \Log::info('OrcamentoController::storeWeb - Buscando modelo Eloquent', [
                'orcamento_id' => $orcamentoDomain->id,
                'with' => ['fornecedor', 'transportadora', 'itens.processoItem', 'itens.formacaoPreco'],
            ]);
            
            $orcamento = $this->orcamentoRepository->buscarModeloPorId(
                $orcamentoDomain->id,
                ['fornecedor', 'transportadora', 'itens.processoItem', 'itens.formacaoPreco']
            );
            
            \Log::info('OrcamentoController::storeWeb - Resultado da busca do modelo', [
                'orcamento_id_procurado' => $orcamentoDomain->id,
                'orcamento_encontrado' => $orcamento !== null,
                'orcamento_id_encontrado' => $orcamento?->id,
                'orcamento_empresa_id' => $orcamento?->empresa_id,
            ]);
            
            if (!$orcamento) {
                \Log::error('OrcamentoController::storeWeb - Orçamento não encontrado após criação', [
                    'orcamento_id' => $orcamentoDomain->id,
                    'empresa_id' => $orcamentoDomain->empresaId,
                    'tenant_id' => tenancy()->tenant?->id,
                    'database' => \Illuminate\Support\Facades\DB::connection()->getDatabaseName(),
                ]);
                return response()->json(['message' => 'Orçamento não encontrado após criação.'], 404);
            }
            
            return (new OrcamentoResource($orcamento))
                ->response()
                ->setStatusCode(201);
        } catch (\App\Domain\Exceptions\DomainException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 400);
        } catch (\Exception $e) {
            return $this->handleException($e, 'Erro ao criar orçamento');
        }
    }

    /**
     * Obter orçamento específico
     * 
     * ✅ DDD: Controller apenas orquestra, validações no Use Case
     */
    public function show(Request $request)
    {
        try {
            $processoId = (int) $request->route()->parameter('processo');
            $itemId = (int) $request->route()->parameter('item');
            $orcamentoId = (int) $request->route()->parameter('orcamento');
            $empresa = $this->getEmpresaAtivaOrFail();
            
            // Executar Use Case (validações de negócio dentro do Use Case)
            $orcamentoDomain = $this->buscarOrcamentoUseCase->executar($orcamentoId, $empresa->id, $processoId, $itemId);
            
            // Buscar modelo Eloquent apenas para serialização (Infrastructure)
            $orcamento = $this->orcamentoRepository->buscarModeloPorId(
                $orcamentoDomain->id,
                ['fornecedor', 'transportadora', 'itens.processoItem', 'itens.formacaoPreco']
            );
            
            if (!$orcamento) {
                return response()->json(['message' => 'Orçamento não encontrado'], 404);
            }
            
            return new OrcamentoResource($orcamento);
        } catch (\App\Domain\Exceptions\DomainException $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 404);
        } catch (\Exception $e) {
            return $this->handleException($e, 'Erro ao buscar orçamento');
        }
    }

    /**
     * API: Atualizar orçamento (Route::module)
     */
    /**
     * API: Atualizar orçamento (Route::module)
     * 
     * ✅ DDD: Controller apenas orquestra, validações no Use Case
     */
    public function update(OrcamentoUpdateRequest $request)
    {
        try {
            $processoId = (int) $request->route()->parameter('processo');
            $itemId = (int) $request->route()->parameter('item');
            $orcamentoId = (int) $request->route()->parameter('orcamento');
            
            // Delegar para método Web (que usa Use Case - validação de regras de negócio)
            return $this->updateWeb($request, $processoId, $itemId, $orcamentoId);
        } catch (\App\Domain\Exceptions\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        } catch (\Exception $e) {
            return $this->handleException($e, 'Erro ao atualizar orçamento');
        }
    }

    /**
     * API: Excluir orçamento (Route::module)
     */
    /**
     * API: Excluir orçamento (Route::module)
     * 
     * ✅ DDD: Controller apenas orquestra, validações no Use Case
     */
    public function destroy(Request $request)
    {
        try {
            $processoId = (int) $request->route()->parameter('processo');
            $itemId = (int) $request->route()->parameter('item');
            $orcamentoId = (int) $request->route()->parameter('orcamento');
            
            // Delegar para método Web (que usa Use Case - validação de regras de negócio)
            return $this->destroyWeb($processoId, $itemId, $orcamentoId);
        } catch (\App\Domain\Exceptions\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        } catch (\Exception $e) {
            return $this->handleException($e, 'Erro ao excluir orçamento');
        }
    }

    /**
     * Web: Atualizar orçamento
     */
    /**
     * Web: Atualizar orçamento
     * 
     * ✅ DDD: Usa Use Case, não Service
     */
    public function updateWeb(OrcamentoUpdateRequest $request, int $processoId, int $itemId, int $orcamentoId)
    {
        $empresa = $this->getEmpresaAtivaOrFail();
        
        try {
            // Verificar permissão usando Policy (precisa do modelo)
            $orcamentoModel = $this->orcamentoRepository->buscarModeloPorId($orcamentoId);
            if ($orcamentoModel) {
                $this->authorize('update', $orcamentoModel);
            }

            // Request já está validado via Form Request
            $data = $request->validated();
            
            // Usar Use Case DDD (contém toda a lógica de negócio)
            $dto = AtualizarOrcamentoDTO::fromArray($data, $orcamentoId);
            $orcamentoDomain = $this->atualizarOrcamentoUseCase->executar($dto, $empresa->id, $processoId, $itemId);
            
            // Buscar modelo Eloquent para resposta usando repository
            $orcamento = $this->orcamentoRepository->buscarModeloPorId(
                $orcamentoDomain->id,
                ['fornecedor', 'transportadora', 'itens.processoItem', 'itens.formacaoPreco']
            );
            
            if (!$orcamento) {
                return response()->json(['message' => 'Orçamento não encontrado após atualização.'], 404);
            }
            
            return new OrcamentoResource($orcamento);
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
            return $this->handleException($e, 'Erro ao atualizar orçamento');
        }
    }

    /**
     * Web: Excluir orçamento
     */
    /**
     * Web: Excluir orçamento
     * 
     * ✅ DDD: Usa Use Case, não Service
     */
    public function destroyWeb(int $processoId, int $itemId, int $orcamentoId)
    {
        $empresa = $this->getEmpresaAtivaOrFail();
        
        try {
            // Verificar permissão usando Policy (precisa do modelo)
            $orcamentoModel = $this->orcamentoRepository->buscarModeloPorId($orcamentoId);
            if ($orcamentoModel) {
                $this->authorize('delete', $orcamentoModel);
            }

            // Usar Use Case DDD (contém toda a lógica de negócio)
            $this->excluirOrcamentoUseCase->executar($orcamentoId, $empresa->id);
            return response()->json(null, 204);
        } catch (\App\Domain\Exceptions\DomainException $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 404);
        } catch (\Exception $e) {
            return $this->handleException($e, 'Erro ao excluir orçamento');
        }
    }

    /**
     * Cria um orçamento vinculado ao processo (pode ter múltiplos itens)
     */
    public function storeByProcesso(Request $request, Processo $processo)
    {
        $empresa = $this->getEmpresaAtivaOrFail();
        
        // Verificar permissão usando Policy
        $this->authorize('create', [$processo]);

        try {
            $orcamento = $this->orcamentoService->storeByProcesso($processo, $request->all(), $empresa->id);
            $orcamento->load(['fornecedor', 'transportadora', 'itens.processoItem', 'itens.formacaoPreco']);
            return new OrcamentoResource($orcamento);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Dados inválidos',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * Lista orçamentos vinculados ao processo
     */
    public function indexByProcesso(Processo $processo): JsonResponse
    {
        $empresa = $this->getEmpresaAtivaOrFail();
        
        try {
            $this->orcamentoService->validarProcessoEmpresa($processo, $empresa->id);
            $orcamentos = $this->orcamentoService->listByProcesso($processo);
            return response()->json([
                'data' => OrcamentoResource::collection($orcamentos),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * Atualiza o fornecedor_escolhido de um orcamento_item específico
     * Usa Form Request para validação
     */
    /**
     * Atualiza o fornecedor_escolhido de um orcamento_item específico
     * 
     * ✅ DDD: Usa Use Case, não Service
     */
    public function updateOrcamentoItem(OrcamentoItemUpdateRequest $request, int $processoId, int $orcamentoId, int $orcamentoItemId)
    {
        $empresa = $this->getEmpresaAtivaOrFail();
        
        try {
            // Request já está validado via Form Request
            $validated = $request->validated();

            // Usar Use Case DDD (contém toda a lógica de negócio)
            $orcamento = $this->atualizarOrcamentoItemUseCase->executar(
                $orcamentoId,
                $processoId,
                $orcamentoItemId,
                $validated['fornecedor_escolhido'],
                $empresa->id
            );
            
            return new OrcamentoResource($orcamento);
        } catch (\App\Domain\Exceptions\DomainException $e) {
            $statusCode = $e->getMessage() === 'Não é possível alterar seleção de orçamentos em processos em execução.' ? 403 : 404;
            return response()->json([
                'message' => $e->getMessage()
            ], $statusCode);
        } catch (\Exception $e) {
            return $this->handleException($e, 'Erro ao atualizar item do orçamento');
        }
    }

    /**
     * Lista todos os orçamentos da empresa (não apenas de um processo)
     * 
     * ✅ DDD: Controller apenas orquestra, usa Service para buscar dados
     * 
     * O middleware já inicializou o tenant correto baseado no X-Tenant-ID do header.
     * A empresa é obtida automaticamente via getEmpresaAtivaOrFail() que prioriza header X-Empresa-ID.
     */
    public function listAll(Request $request): JsonResponse
    {
        try {
            $empresa = $this->getEmpresaAtivaOrFail();
            
            // Usar repositório para buscar orçamentos com paginação
            $filtros = [
                'empresa_id' => $empresa->id,
                'per_page' => $request->input('per_page', 15),
            ];

            // Adicionar filtro de processo se fornecido
            if ($request->filled('processo_id')) {
                $filtros['processo_id'] = (int) $request->input('processo_id');
            }

            // Buscar orçamentos usando repositório
            $paginator = $this->orcamentoRepository->buscarComFiltros($filtros);
            
            // Converter entidades de domínio para modelos Eloquent para o Resource
            // Buscar modelos com relacionamentos carregados
            $orcamentos = collect($paginator->items())->map(function ($orcamentoDomain) {
                return $this->orcamentoRepository->buscarModeloPorId(
                    $orcamentoDomain->id,
                    ['fornecedor', 'transportadora', 'itens.processoItem', 'itens.formacaoPreco', 'processo']
                );
            })->filter();

            return response()->json([
                'data' => OrcamentoResource::collection($orcamentos),
                'meta' => [
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                ],
            ]);
        } catch (\App\Domain\Exceptions\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        } catch (\Exception $e) {
            return $this->handleException($e, 'Erro ao listar orçamentos');
        }
    }
}


