<?php

namespace App\Modules\Orcamento\Controllers;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Controllers\Traits\HasDefaultActions;
use App\Http\Resources\FormacaoPrecoResource;
use App\Modules\Processo\Models\Processo;
use App\Modules\Processo\Models\ProcessoItem;
use App\Models\Orcamento;
use App\Models\FormacaoPreco;
use App\Modules\Orcamento\Services\FormacaoPrecoService;
use Illuminate\Http\Request;

class FormacaoPrecoController extends BaseApiController
{
    use HasDefaultActions;

    protected FormacaoPrecoService $formacaoPrecoService;

    public function __construct(FormacaoPrecoService $formacaoPrecoService)
    {
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
        return $this->show(
            Processo::findOrFail($request->route()->parameter('processo')),
            ProcessoItem::findOrFail($request->route()->parameter('item')),
            Orcamento::findOrFail($request->route()->parameter('orcamento'))
        );
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
        $route = $request->route();
        $processo = Processo::findOrFail($route->parameter('processo'));
        $item = ProcessoItem::findOrFail($route->parameter('item'));
        $orcamento = Orcamento::findOrFail($route->parameter('orcamento'));
        
        return $this->storeWeb($request, $processo, $item, $orcamento);
    }

    /**
     * API: Atualizar formação de preço (Route::module)
     */
    public function update(Request $request, $id)
    {
        $route = $request->route();
        $processo = Processo::findOrFail($route->parameter('processo'));
        $item = ProcessoItem::findOrFail($route->parameter('item'));
        $orcamento = Orcamento::findOrFail($route->parameter('orcamento'));
        $formacaoPreco = FormacaoPreco::findOrFail($id);
        
        return $this->updateWeb($request, $processo, $item, $orcamento, $formacaoPreco);
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

