<?php

namespace App\Modules\Processo\Controllers;

use App\Http\Controllers\Api\BaseApiController;
use App\Modules\Processo\Models\Processo;
use App\Modules\Processo\Models\ProcessoItem;
use App\Modules\Processo\Services\ProcessoItemService;
use Illuminate\Http\Request;

class ProcessoItemController extends BaseApiController
{

    protected ProcessoItemService $itemService;

    public function __construct(ProcessoItemService $itemService)
    {
        $this->itemService = $itemService;
        $this->service = $itemService; // Para HasDefaultActions
    }

    /**
     * API: Listar itens de um processo
     */
    public function list(Request $request)
    {
        $route = $request->route();
        $processoId = $route->parameter('processo');
        
        if (!$processoId) {
            return response()->json(['message' => 'Processo não fornecido'], 400);
        }

        $empresa = $this->getEmpresaAtivaOrFail();
        $processo = Processo::where('id', $processoId)
            ->where('empresa_id', $empresa->id)
            ->firstOrFail();

        try {
            $this->itemService->validarProcessoEmpresa($processo, $empresa->id);
            $itens = $this->itemService->listByProcesso($processo);
            return response()->json($itens);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * API: Buscar item específico
     */
    public function get(Request $request)
    {
        $route = $request->route();
        $itemId = $route->parameter('item');
        
        if (!$itemId) {
            return response()->json(['message' => 'Item não fornecido'], 400);
        }

        $empresa = $this->getEmpresaAtivaOrFail();
        $item = ProcessoItem::where('id', $itemId)
            ->where('empresa_id', $empresa->id)
            ->firstOrFail();

        return response()->json(['data' => $item]);
    }

    /**
     * API: Criar item
     */
    public function store(Request $request)
    {
        $route = $request->route();
        $processoId = $route->parameter('processo');
        
        if (!$processoId) {
            return response()->json(['message' => 'Processo não fornecido'], 400);
        }

        $empresa = $this->getEmpresaAtivaOrFail();
        $processo = Processo::where('id', $processoId)
            ->where('empresa_id', $empresa->id)
            ->firstOrFail();

        try {
            $this->itemService->validarProcessoEmpresa($processo, $empresa->id);
            $item = $this->itemService->storeItem($processo, $request->all());
            return response()->json(['data' => $item], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Erro de validação',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * API: Atualizar item
     */
    public function update(Request $request, $id)
    {
        $route = $request->route();
        $processoId = $route->parameter('processo');
        
        if (!$processoId) {
            return response()->json(['message' => 'Processo não fornecido'], 400);
        }

        $empresa = $this->getEmpresaAtivaOrFail();
        $processo = Processo::where('id', $processoId)
            ->where('empresa_id', $empresa->id)
            ->firstOrFail();
        
        $item = ProcessoItem::where('id', $id)
            ->where('empresa_id', $empresa->id)
            ->firstOrFail();

        try {
            $this->itemService->validarProcessoEmpresa($processo, $empresa->id);
            $this->itemService->validarItemEmpresa($item, $empresa->id);
            $item = $this->itemService->updateItem($processo, $item, $request->all());
            return response()->json(['data' => $item]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Erro de validação',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * API: Excluir item
     */
    public function destroy(Request $request, $id)
    {
        $route = $request->route();
        $processoId = $route->parameter('processo');
        
        if (!$processoId) {
            return response()->json(['message' => 'Processo não fornecido'], 400);
        }

        $empresa = $this->getEmpresaAtivaOrFail();
        $processo = Processo::where('id', $processoId)
            ->where('empresa_id', $empresa->id)
            ->firstOrFail();
        
        $item = ProcessoItem::where('id', $id)
            ->where('empresa_id', $empresa->id)
            ->firstOrFail();

        try {
            $this->itemService->validarProcessoEmpresa($processo, $empresa->id);
            $this->itemService->validarItemEmpresa($item, $empresa->id);
            $this->itemService->delete($processo, $item);
            return response()->json(null, 204);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * API: Importar itens
     */
    public function importar(Request $request, Processo $processo)
    {
        $empresa = $this->getEmpresaAtivaOrFail();

        try {
            $this->itemService->validarProcessoEmpresa($processo, $empresa->id);
            $resultado = $this->itemService->importar($processo, $request->all());
            return response()->json($resultado, 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 400);
        }
    }

    public function create(Processo $processo)
    {
        $empresa = $this->getEmpresaAtivaOrFail();

        try {
            $this->itemService->validarProcessoEmpresa($processo, $empresa->id);
            $this->itemService->validarProcessoPodeEditar($processo);
        } catch (\Exception $e) {
            return redirect()->route('processos.show', $processo)
                ->with('error', $e->getMessage());
        }

        $proximoNumero = $this->itemService->calcularProximoNumeroItem($processo);

        return view('processo-itens.create', compact('processo', 'proximoNumero'));
    }

    /**
     * Web: Criar item (para views)
     */
    public function storeWeb(Request $request, Processo $processo)
    {
        $empresa = $this->getEmpresaAtivaOrFail();

        try {
            $this->itemService->validarProcessoEmpresa($processo, $empresa->id);
            $this->itemService->storeItem($processo, $request->all());

            return redirect()->route('processos.show', $processo)
                ->with('success', 'Item adicionado com sucesso!');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()->back()
                ->withErrors($e->errors())
                ->withInput();
        } catch (\Exception $e) {
            return redirect()->route('processos.show', $processo)
                ->with('error', $e->getMessage());
        }
    }

    public function edit(Processo $processo, ProcessoItem $item)
    {
        $empresa = $this->getEmpresaAtivaOrFail();

        try {
            $this->itemService->validarProcessoEmpresa($processo, $empresa->id);
            $this->itemService->validarItemEmpresa($item, $empresa->id);
            $this->itemService->validarProcessoPodeEditar($processo);
            $this->itemService->validarItemPertenceProcesso($item, $processo);
        } catch (\Exception $e) {
            return redirect()->route('processos.show', $processo)
                ->with('error', $e->getMessage());
        }

        return view('processo-itens.edit', compact('processo', 'item'));
    }

    /**
     * Web: Atualizar item (para views)
     */
    public function updateWeb(Request $request, Processo $processo, ProcessoItem $item)
    {
        $empresa = $this->getEmpresaAtivaOrFail();

        try {
            $this->itemService->validarProcessoEmpresa($processo, $empresa->id);
            $this->itemService->validarItemEmpresa($item, $empresa->id);
            $this->itemService->updateItem($processo, $item, $request->all());

            return redirect()->route('processos.show', $processo)
                ->with('success', 'Item atualizado com sucesso!');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()->back()
                ->withErrors($e->errors())
                ->withInput();
        } catch (\Exception $e) {
            return redirect()->route('processos.show', $processo)
                ->with('error', $e->getMessage());
        }
    }

    /**
     * Web: Excluir item (para views)
     */
    public function destroyWeb(Processo $processo, ProcessoItem $item)
    {
        $empresa = $this->getEmpresaAtivaOrFail();

        try {
            $this->itemService->validarProcessoEmpresa($processo, $empresa->id);
            $this->itemService->validarItemEmpresa($item, $empresa->id);
            $this->itemService->delete($processo, $item);

            return redirect()->route('processos.show', $processo)
                ->with('success', 'Item excluído com sucesso!');
        } catch (\Exception $e) {
            return redirect()->route('processos.show', $processo)
                ->with('error', $e->getMessage());
        }
    }
}
