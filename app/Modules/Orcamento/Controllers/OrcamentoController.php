<?php

namespace App\Modules\Orcamento\Controllers;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Resources\OrcamentoResource;
use App\Modules\Processo\Models\Processo;
use App\Modules\Processo\Models\ProcessoItem;
use App\Models\Orcamento;
use App\Modules\Orcamento\Services\OrcamentoService;
use App\Application\Orcamento\UseCases\CriarOrcamentoUseCase;
use App\Application\Orcamento\DTOs\CriarOrcamentoDTO;
use App\Domain\Processo\Repositories\ProcessoRepositoryInterface;
use App\Domain\ProcessoItem\Repositories\ProcessoItemRepositoryInterface;
use App\Domain\Orcamento\Repositories\OrcamentoRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class OrcamentoController extends BaseApiController
{

    protected OrcamentoService $orcamentoService;

    public function __construct(
        OrcamentoService $orcamentoService,
        private CriarOrcamentoUseCase $criarOrcamentoUseCase,
        private ProcessoRepositoryInterface $processoRepository,
        private ProcessoItemRepositoryInterface $processoItemRepository,
        private OrcamentoRepositoryInterface $orcamentoRepository,
    ) {
        parent::__construct(app(\App\Domain\Empresa\Repositories\EmpresaRepositoryInterface::class), app(\App\Domain\Auth\Repositories\UserRepositoryInterface::class));
        $this->orcamentoService = $orcamentoService;
        $this->service = $orcamentoService; // Para HasDefaultActions
    }

    /**
     * API: Listar orçamentos de um item (Route::module)
     */
    public function list(Request $request)
    {
        $processoId = $request->route()->parameter('processo');
        $itemId = $request->route()->parameter('item');
        
        $processoDomain = $this->processoRepository->buscarModeloPorId($processoId);
        if (!$processoDomain) {
            return response()->json(['message' => 'Processo não encontrado.'], 404);
        }
        
        $itemModel = $this->processoItemRepository->buscarModeloPorId($itemId);
        if (!$itemModel) {
            return response()->json(['message' => 'Item não encontrado.'], 404);
        }
        
        return $this->index($request, $processoDomain, $itemModel);
    }

    /**
     * API: Buscar orçamento específico (Route::module)
     */
    public function get(Request $request)
    {
        $processoId = $request->route()->parameter('processo');
        $itemId = $request->route()->parameter('item');
        $orcamentoId = $request->route()->parameter('orcamento');
        
        $processoModel = $this->processoRepository->buscarModeloPorId($processoId);
        if (!$processoModel) {
            return response()->json(['message' => 'Processo não encontrado.'], 404);
        }
        
        $itemModel = $this->processoItemRepository->buscarModeloPorId($itemId);
        if (!$itemModel) {
            return response()->json(['message' => 'Item não encontrado.'], 404);
        }
        
        $orcamentoModel = $this->orcamentoRepository->buscarModeloPorId($orcamentoId);
        if (!$orcamentoModel) {
            return response()->json(['message' => 'Orçamento não encontrado.'], 404);
        }
        
        return $this->show($processoModel, $itemModel, $orcamentoModel);
    }

    public function index(Request $request, Processo $processo, ProcessoItem $item)
    {
        $empresa = $this->getEmpresaAtivaOrFail();
        
        try {
            $this->orcamentoService->validarProcessoEmpresa($processo, $empresa->id);
            $this->orcamentoService->validarItemPertenceProcesso($item, $processo);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 404);
        }

        $orcamentos = $this->orcamentoService->listByItem($item);

        return OrcamentoResource::collection($orcamentos);
    }

    /**
     * API: Criar orçamento (Route::module)
     */
    public function store(Request $request)
    {
        $processoId = $request->route()->parameter('processo');
        $itemId = $request->route()->parameter('item');
        
        $processoModel = $this->processoRepository->buscarModeloPorId($processoId);
        if (!$processoModel) {
            return response()->json(['message' => 'Processo não encontrado.'], 404);
        }
        
        $itemModel = $this->processoItemRepository->buscarModeloPorId($itemId);
        if (!$itemModel) {
            return response()->json(['message' => 'Item não encontrado.'], 404);
        }
        
        return $this->storeWeb($request, $processoModel, $itemModel);
    }

    /**
     * Web: Criar orçamento
     */
    public function storeWeb(Request $request, Processo $processo, ProcessoItem $item)
    {
        $empresa = $this->getEmpresaAtivaOrFail();
        
        // Verificar permissão usando Policy
        $this->authorize('create', [$processo]);

        try {
            // Preparar dados para DTO
            $data = $request->all();
            $data['processo_id'] = $processo->id;
            $data['processo_item_id'] = $item->id;
            $data['empresa_id'] = $empresa->id;
            
            // Usar Use Case DDD
            $dto = CriarOrcamentoDTO::fromArray($data);
            $orcamentoDomain = $this->criarOrcamentoUseCase->executar($dto);
            
            // Buscar modelo Eloquent para Resource usando repository
            $orcamento = $this->orcamentoRepository->buscarModeloPorId(
                $orcamentoDomain->id,
                ['fornecedor', 'transportadora', 'itens.processoItem', 'itens.formacaoPreco']
            );
            
            if (!$orcamento) {
                return response()->json(['message' => 'Orçamento não encontrado após criação.'], 404);
            }
            
            return new OrcamentoResource($orcamento);
        } catch (ValidationException $e) {
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

    public function show(Processo $processo, ProcessoItem $item, Orcamento $orcamento)
    {
        $empresa = $this->getEmpresaAtivaOrFail();
        
        try {
            $orcamento = $this->orcamentoService->find($processo, $item, $orcamento, $empresa->id);
            return new OrcamentoResource($orcamento);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * API: Atualizar orçamento (Route::module)
     */
    public function update(Request $request, $id)
    {
        $processoId = $request->route()->parameter('processo');
        $itemId = $request->route()->parameter('item');
        
        $processoModel = $this->processoRepository->buscarModeloPorId($processoId);
        if (!$processoModel) {
            return response()->json(['message' => 'Processo não encontrado.'], 404);
        }
        
        $itemModel = $this->processoItemRepository->buscarModeloPorId($itemId);
        if (!$itemModel) {
            return response()->json(['message' => 'Item não encontrado.'], 404);
        }
        
        $orcamentoModel = $this->orcamentoRepository->buscarModeloPorId($id);
        if (!$orcamentoModel) {
            return response()->json(['message' => 'Orçamento não encontrado.'], 404);
        }
        
        return $this->updateWeb($request, $processoModel, $itemModel, $orcamentoModel);
    }

    /**
     * API: Excluir orçamento (Route::module)
     */
    public function destroy(Request $request, $id)
    {
        $processoId = $request->route()->parameter('processo');
        $itemId = $request->route()->parameter('item');
        
        $processoModel = $this->processoRepository->buscarModeloPorId($processoId);
        if (!$processoModel) {
            return response()->json(['message' => 'Processo não encontrado.'], 404);
        }
        
        $itemModel = $this->processoItemRepository->buscarModeloPorId($itemId);
        if (!$itemModel) {
            return response()->json(['message' => 'Item não encontrado.'], 404);
        }
        
        $orcamentoModel = $this->orcamentoRepository->buscarModeloPorId($id);
        if (!$orcamentoModel) {
            return response()->json(['message' => 'Orçamento não encontrado.'], 404);
        }
        
        return $this->destroyWeb($processoModel, $itemModel, $orcamentoModel);
    }

    /**
     * Web: Atualizar orçamento
     */
    public function updateWeb(Request $request, Processo $processo, ProcessoItem $item, Orcamento $orcamento)
    {
        $empresa = $this->getEmpresaAtivaOrFail();
        
        // Verificar permissão usando Policy
        $this->authorize('update', $orcamento);

        try {
            $orcamento = $this->orcamentoService->update($processo, $item, $orcamento, $request->all(), $empresa->id);
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
     * Web: Excluir orçamento
     */
    public function destroyWeb(Processo $processo, ProcessoItem $item, Orcamento $orcamento)
    {
        // Verificar permissão usando Policy
        $this->authorize('delete', $orcamento);

        try {
            $this->orcamentoService->delete($processo, $item, $orcamento);
            return response()->json(null, 204);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 404);
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
    public function indexByProcesso(Processo $processo)
    {
        $empresa = $this->getEmpresaAtivaOrFail();
        
        try {
            $this->orcamentoService->validarProcessoEmpresa($processo, $empresa->id);
            $orcamentos = $this->orcamentoService->listByProcesso($processo);
            return OrcamentoResource::collection($orcamentos);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * Atualiza o fornecedor_escolhido de um orcamento_item específico
     */
    public function updateOrcamentoItem(Request $request, Processo $processo, Orcamento $orcamento, $orcamentoItemId)
    {
        $empresa = $this->getEmpresaAtivaOrFail();
        
        $validated = $request->validate([
            'fornecedor_escolhido' => 'required|boolean',
        ]);

        try {
            $orcamento = $this->orcamentoService->updateOrcamentoItem(
                $processo, 
                $orcamento, 
                $orcamentoItemId, 
                $validated['fornecedor_escolhido'],
                $empresa->id
            );
            return new OrcamentoResource($orcamento);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], $e->getMessage() === 'Não é possível alterar seleção de orçamentos em processos em execução.' ? 403 : 404);
        }
    }
}

