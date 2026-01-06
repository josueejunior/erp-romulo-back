<?php

namespace App\Modules\Processo\Controllers;

use App\Http\Controllers\Api\BaseApiController;
use App\Modules\Processo\Models\Processo;
use App\Modules\Processo\Models\ProcessoItem;
use App\Modules\Processo\Models\ProcessoItemVinculo;
use App\Modules\Processo\Services\ProcessoItemVinculoService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ProcessoItemVinculoController extends BaseApiController
{
    protected ProcessoItemVinculoService $vinculoService;

    public function __construct(ProcessoItemVinculoService $vinculoService)
    {
        $this->vinculoService = $vinculoService;
    }

    /**
     * GET /processos/{processo}/itens/{item}/vinculos
     * Lista vínculos de um item
     */
    public function list(Request $request): JsonResponse
    {
        $route = $request->route();
        $processoId = $route->parameter('processo');
        $itemId = $route->parameter('item');

        if (!$processoId || !$itemId) {
            return response()->json(['message' => 'Processo e item são obrigatórios'], 400);
        }

        $empresa = $this->getEmpresaAtivaOrFail();
        
        $processo = Processo::where('id', $processoId)
            ->where('empresa_id', $empresa->id)
            ->firstOrFail();

        $item = ProcessoItem::where('id', $itemId)
            ->where('processo_id', $processo->id)
            ->where('empresa_id', $empresa->id)
            ->firstOrFail();

        try {
            $vinculos = $this->vinculoService->listByItem($item);
            return response()->json(['data' => $vinculos]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * GET /processos/{processo}/itens/{item}/vinculos/{vinculo}
     * Busca um vínculo específico
     */
    public function get(Request $request): JsonResponse
    {
        $route = $request->route();
        $processoId = $route->parameter('processo');
        $itemId = $route->parameter('item');
        $vinculoId = $route->parameter('vinculo');

        if (!$processoId || !$itemId || !$vinculoId) {
            return response()->json(['message' => 'Processo, item e vínculo são obrigatórios'], 400);
        }

        $empresa = $this->getEmpresaAtivaOrFail();
        
        $processo = Processo::where('id', $processoId)
            ->where('empresa_id', $empresa->id)
            ->firstOrFail();

        $item = ProcessoItem::where('id', $itemId)
            ->where('processo_id', $processo->id)
            ->where('empresa_id', $empresa->id)
            ->firstOrFail();

        $vinculo = ProcessoItemVinculo::where('id', $vinculoId)
            ->where('processo_item_id', $item->id)
            ->where('empresa_id', $empresa->id)
            ->with(['contrato', 'autorizacaoFornecimento', 'empenho'])
            ->firstOrFail();

        return response()->json(['data' => $vinculo]);
    }

    /**
     * POST /processos/{processo}/itens/{item}/vinculos
     * Cria um novo vínculo
     */
    public function store(Request $request): JsonResponse
    {
        $route = $request->route();
        $processoId = $route->parameter('processo');
        $itemId = $route->parameter('item');

        if (!$processoId || !$itemId) {
            return response()->json(['message' => 'Processo e item são obrigatórios'], 400);
        }

        $empresa = $this->getEmpresaAtivaOrFail();
        
        $processo = Processo::where('id', $processoId)
            ->where('empresa_id', $empresa->id)
            ->firstOrFail();

        $item = ProcessoItem::where('id', $itemId)
            ->where('processo_id', $processo->id)
            ->where('empresa_id', $empresa->id)
            ->firstOrFail();

        try {
            $data = $request->all();
            $data['processo_item_id'] = $item->id;
            
            $vinculo = $this->vinculoService->store($processo, $item, $data, $empresa->id);
            
            return response()->json([
                'message' => 'Vínculo criado com sucesso',
                'data' => $vinculo
            ], 201);
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
     * PUT /processos/{processo}/itens/{item}/vinculos/{vinculo}
     * Atualiza um vínculo existente
     */
    public function update(Request $request): JsonResponse
    {
        $route = $request->route();
        $processoId = $route->parameter('processo');
        $itemId = $route->parameter('item');
        $vinculoId = $route->parameter('vinculo');

        if (!$processoId || !$itemId || !$vinculoId) {
            return response()->json(['message' => 'Processo, item e vínculo são obrigatórios'], 400);
        }

        $empresa = $this->getEmpresaAtivaOrFail();
        
        $processo = Processo::where('id', $processoId)
            ->where('empresa_id', $empresa->id)
            ->firstOrFail();

        $item = ProcessoItem::where('id', $itemId)
            ->where('processo_id', $processo->id)
            ->where('empresa_id', $empresa->id)
            ->firstOrFail();

        $vinculo = ProcessoItemVinculo::where('id', $vinculoId)
            ->where('processo_item_id', $item->id)
            ->where('empresa_id', $empresa->id)
            ->firstOrFail();

        try {
            $data = $request->all();
            $data['processo_item_id'] = $item->id;
            
            $vinculo = $this->vinculoService->update($processo, $item, $vinculo, $data, $empresa->id);
            
            return response()->json([
                'message' => 'Vínculo atualizado com sucesso',
                'data' => $vinculo
            ]);
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
     * DELETE /processos/{processo}/itens/{item}/vinculos/{vinculo}
     * Remove um vínculo
     */
    public function destroy(Request $request): JsonResponse
    {
        $route = $request->route();
        $processoId = $route->parameter('processo');
        $itemId = $route->parameter('item');
        $vinculoId = $route->parameter('vinculo');

        if (!$processoId || !$itemId || !$vinculoId) {
            return response()->json(['message' => 'Processo, item e vínculo são obrigatórios'], 400);
        }

        $empresa = $this->getEmpresaAtivaOrFail();
        
        $processo = Processo::where('id', $processoId)
            ->where('empresa_id', $empresa->id)
            ->firstOrFail();

        $item = ProcessoItem::where('id', $itemId)
            ->where('processo_id', $processo->id)
            ->where('empresa_id', $empresa->id)
            ->firstOrFail();

        $vinculo = ProcessoItemVinculo::where('id', $vinculoId)
            ->where('processo_item_id', $item->id)
            ->where('empresa_id', $empresa->id)
            ->firstOrFail();

        try {
            $this->vinculoService->delete($processo, $item, $vinculo, $empresa->id);
            
            return response()->json([
                'message' => 'Vínculo removido com sucesso'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 400);
        }
    }
}



