<?php

namespace App\Modules\Orcamento\Controllers;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Resources\FormacaoPrecoResource;
use App\Modules\Processo\Models\Processo;
use App\Modules\Processo\Models\ProcessoItem;
use App\Models\Orcamento;
use App\Models\FormacaoPreco;
use App\Modules\Orcamento\Services\FormacaoPrecoService;
use App\Domain\Processo\Repositories\ProcessoRepositoryInterface;
use App\Domain\ProcessoItem\Repositories\ProcessoItemRepositoryInterface;
use App\Domain\Orcamento\Repositories\OrcamentoRepositoryInterface;
use App\Domain\FormacaoPreco\Repositories\FormacaoPrecoRepositoryInterface;
use Illuminate\Http\Request;

class FormacaoPrecoController extends BaseApiController
{

    protected FormacaoPrecoService $formacaoPrecoService;

    public function __construct(
        FormacaoPrecoService $formacaoPrecoService,
        private ProcessoRepositoryInterface $processoRepository,
        private ProcessoItemRepositoryInterface $processoItemRepository,
        private OrcamentoRepositoryInterface $orcamentoRepository,
        private FormacaoPrecoRepositoryInterface $formacaoPrecoRepository,
    ) {
        parent::__construct(app(\App\Domain\Empresa\Repositories\EmpresaRepositoryInterface::class), app(\App\Domain\Auth\Repositories\UserRepositoryInterface::class));
        $this->formacaoPrecoService = $formacaoPrecoService;
        $this->service = $formacaoPrecoService; // Para HasDefaultActions
    }

    /**
     * API: Listar formações de preço (Route::module)
     */
    public function list(Request $request)
    {
        // Formação de preço é 1:1 com orçamento, então retorna apenas uma
        return $this->get($request);
    }

    /**
     * API: Buscar formação de preço (Route::module)
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

    public function show(Processo $processo, ProcessoItem $item, Orcamento $orcamento)
    {
        $empresa = $this->getEmpresaAtivaOrFail();
        
        try {
            $formacaoPreco = $this->formacaoPrecoService->find($processo, $item, $orcamento, $empresa->id);
            return new FormacaoPrecoResource($formacaoPreco);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * API: Criar formação de preço (Route::module)
     */
    public function store(Request $request)
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
        
        return $this->storeWeb($request, $processoModel, $itemModel, $orcamentoModel);
    }

    /**
     * API: Atualizar formação de preço (Route::module)
     */
    public function update(Request $request, $id)
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
        
        $formacaoPrecoModel = $this->formacaoPrecoRepository->buscarModeloPorId($id);
        if (!$formacaoPrecoModel) {
            return response()->json(['message' => 'Formação de preço não encontrada.'], 404);
        }
        
        return $this->updateWeb($request, $processoModel, $itemModel, $orcamentoModel, $formacaoPrecoModel);
    }

    /**
     * Web: Criar formação de preço
     */
    public function storeWeb(Request $request, Processo $processo, ProcessoItem $item, Orcamento $orcamento)
    {
        $empresa = $this->getEmpresaAtivaOrFail();
        
        try {
            $formacaoPreco = $this->formacaoPrecoService->store($processo, $item, $orcamento, $request->all(), $empresa->id);
            return new FormacaoPrecoResource($formacaoPreco);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Dados inválidos',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], $e->getMessage() === 'Não é possível criar/editar formação de preço para processos em execução.' ? 403 : 404);
        }
    }

    /**
     * Web: Atualizar formação de preço
     */
    public function updateWeb(Request $request, Processo $processo, ProcessoItem $item, Orcamento $orcamento, FormacaoPreco $formacaoPreco)
    {
        $empresa = $this->getEmpresaAtivaOrFail();
        
        try {
            $formacaoPreco = $this->formacaoPrecoService->update($processo, $item, $orcamento, $formacaoPreco, $request->all(), $empresa->id);
            return new FormacaoPrecoResource($formacaoPreco);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Dados inválidos',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], $e->getMessage() === 'Não é possível criar/editar formação de preço para processos em execução.' ? 403 : 404);
        }
    }
}

