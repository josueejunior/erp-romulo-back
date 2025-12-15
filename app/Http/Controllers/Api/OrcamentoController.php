<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrcamentoResource;
use App\Models\Processo;
use App\Models\ProcessoItem;
use App\Models\Orcamento;
use Illuminate\Http\Request;

class OrcamentoController extends Controller
{
    public function index(Processo $processo, ProcessoItem $item)
    {
        if ($item->processo_id !== $processo->id) {
            return response()->json(['message' => 'Item não pertence a este processo.'], 404);
        }

        $orcamentos = $item->orcamentos()->with(['fornecedor', 'transportadora'])->get();
        return OrcamentoResource::collection($orcamentos);
    }

    public function store(Request $request, Processo $processo, ProcessoItem $item)
    {
        if ($item->processo_id !== $processo->id) {
            return response()->json(['message' => 'Item não pertence a este processo.'], 404);
        }

        if ($processo->isEmExecucao()) {
            return response()->json([
                'message' => 'Não é possível adicionar orçamentos a processos em execução.'
            ], 403);
        }

        $validated = $request->validate([
            'fornecedor_id' => 'required|exists:fornecedores,id',
            'transportadora_id' => 'nullable|exists:transportadoras,id',
            'custo_produto' => 'required|numeric|min:0',
            'marca_modelo' => 'nullable|string|max:255',
            'ajustes_especificacao' => 'nullable|string',
            'frete' => 'nullable|numeric|min:0',
            'frete_incluido' => 'boolean',
            'observacoes' => 'nullable|string',
        ]);

        $validated['processo_item_id'] = $item->id;
        $validated['frete'] = $validated['frete'] ?? 0;
        $validated['frete_incluido'] = $request->has('frete_incluido');
        $validated['fornecedor_escolhido'] = false;

        if ($request->has('fornecedor_escolhido')) {
            $item->orcamentos()->update(['fornecedor_escolhido' => false]);
            $validated['fornecedor_escolhido'] = true;
        }

        $orcamento = Orcamento::create($validated);
        $orcamento->load(['fornecedor', 'transportadora']);

        return new OrcamentoResource($orcamento);
    }

    public function show(Processo $processo, ProcessoItem $item, Orcamento $orcamento)
    {
        if ($item->processo_id !== $processo->id || $orcamento->processo_item_id !== $item->id) {
            return response()->json(['message' => 'Orçamento não pertence a este item.'], 404);
        }

        $orcamento->load(['fornecedor', 'transportadora', 'formacaoPreco']);
        return new OrcamentoResource($orcamento);
    }

    public function update(Request $request, Processo $processo, ProcessoItem $item, Orcamento $orcamento)
    {
        if ($item->processo_id !== $processo->id || $orcamento->processo_item_id !== $item->id) {
            return response()->json(['message' => 'Orçamento não pertence a este item.'], 404);
        }

        if ($processo->isEmExecucao()) {
            return response()->json([
                'message' => 'Não é possível editar orçamentos de processos em execução.'
            ], 403);
        }

        $validated = $request->validate([
            'fornecedor_id' => 'required|exists:fornecedores,id',
            'transportadora_id' => 'nullable|exists:transportadoras,id',
            'custo_produto' => 'required|numeric|min:0',
            'marca_modelo' => 'nullable|string|max:255',
            'ajustes_especificacao' => 'nullable|string',
            'frete' => 'nullable|numeric|min:0',
            'frete_incluido' => 'boolean',
            'observacoes' => 'nullable|string',
        ]);

        $validated['frete'] = $validated['frete'] ?? 0;
        $validated['frete_incluido'] = $request->has('frete_incluido');

        if ($request->has('fornecedor_escolhido') && $request->boolean('fornecedor_escolhido')) {
            $item->orcamentos()->where('id', '!=', $orcamento->id)->update(['fornecedor_escolhido' => false]);
            $validated['fornecedor_escolhido'] = true;
        } else {
            $validated['fornecedor_escolhido'] = false;
        }

        $orcamento->update($validated);
        $orcamento->refresh();
        $orcamento->load(['fornecedor', 'transportadora', 'formacaoPreco']);
        
        // Se o orçamento foi marcado como escolhido e tem formação de preço, atualizar valor mínimo no item
        if ($validated['fornecedor_escolhido'] && $orcamento->formacaoPreco) {
            $item->valor_minimo_venda = $orcamento->formacaoPreco->preco_minimo;
            $item->calcularValorMinimoVenda(); // Usar método do modelo se existir
            $item->save();
        } elseif (!$validated['fornecedor_escolhido']) {
            // Se foi desmarcado, limpar valor mínimo
            $item->valor_minimo_venda = null;
            $item->save();
        }

        return new OrcamentoResource($orcamento);
    }

    public function destroy(Processo $processo, ProcessoItem $item, Orcamento $orcamento)
    {
        if ($item->processo_id !== $processo->id || $orcamento->processo_item_id !== $item->id) {
            return response()->json(['message' => 'Orçamento não pertence a este item.'], 404);
        }

        if ($processo->isEmExecucao()) {
            return response()->json([
                'message' => 'Não é possível excluir orçamentos de processos em execução.'
            ], 403);
        }

        $orcamento->delete();

        return response()->json(null, 204);
    }
}




