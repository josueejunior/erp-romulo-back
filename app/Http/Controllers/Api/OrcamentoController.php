<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrcamentoResource;
use App\Models\Processo;
use App\Models\ProcessoItem;
use App\Models\Orcamento;
use App\Models\OrcamentoItem;
use Illuminate\Http\Request;

class OrcamentoController extends Controller
{
    public function index(Request $request, Processo $processo, ProcessoItem $item)
    {
        // Verificar se o item pertence ao processo
        if ($item->processo_id !== $processo->id) {
            return response()->json([
                'message' => 'Item não pertence a este processo.',
                'item_processo_id' => $item->processo_id,
                'processo_id' => $processo->id,
            ], 404);
        }

        // Carregar orçamentos com relacionamentos
        $orcamentos = $item->orcamentos()
            ->with(['fornecedor', 'transportadora', 'formacaoPreco'])
            ->orderBy('created_at', 'desc')
            ->get();

        // Log para debug (remover em produção se necessário)
        \Log::info('Orçamentos carregados', [
            'processo_id' => $processo->id,
            'item_id' => $item->id,
            'total_orcamentos' => $orcamentos->count(),
        ]);

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

    /**
     * Cria um orçamento vinculado ao processo (pode ter múltiplos itens)
     */
    public function storeByProcesso(Request $request, Processo $processo)
    {
        if ($processo->isEmExecucao()) {
            return response()->json([
                'message' => 'Não é possível adicionar orçamentos a processos em execução.'
            ], 403);
        }

        $validated = $request->validate([
            'fornecedor_id' => 'required|exists:fornecedores,id',
            'transportadora_id' => 'nullable|exists:transportadoras,id',
            'itens' => 'required|array|min:1',
            'itens.*.processo_item_id' => 'required|exists:processo_itens,id',
            'itens.*.custo_produto' => 'required|numeric|min:0',
            'itens.*.marca_modelo' => 'nullable|string|max:255',
            'itens.*.ajustes_especificacao' => 'nullable|string',
            'itens.*.frete' => 'nullable|numeric|min:0',
            'itens.*.frete_incluido' => 'boolean',
            'itens.*.fornecedor_escolhido' => 'boolean',
            'itens.*.observacoes' => 'nullable|string',
            'observacoes' => 'nullable|string',
        ]);

        // Verificar se todos os itens pertencem ao processo
        $itensIds = collect($validated['itens'])->pluck('processo_item_id')->unique();
        $itensDoProcesso = ProcessoItem::where('processo_id', $processo->id)
            ->whereIn('id', $itensIds)
            ->pluck('id')
            ->toArray();

        if (count($itensIds) !== count($itensDoProcesso)) {
            return response()->json([
                'message' => 'Um ou mais itens não pertencem a este processo.'
            ], 422);
        }

        // Criar orçamento vinculado ao processo
        $orcamento = Orcamento::create([
            'processo_id' => $processo->id,
            'fornecedor_id' => $validated['fornecedor_id'],
            'transportadora_id' => $validated['transportadora_id'] ?? null,
            'observacoes' => $validated['observacoes'] ?? null,
        ]);

        // Criar itens do orçamento
        foreach ($validated['itens'] as $itemData) {
            $orcamentoItem = OrcamentoItem::create([
                'orcamento_id' => $orcamento->id,
                'processo_item_id' => $itemData['processo_item_id'],
                'custo_produto' => $itemData['custo_produto'],
                'marca_modelo' => $itemData['marca_modelo'] ?? null,
                'ajustes_especificacao' => $itemData['ajustes_especificacao'] ?? null,
                'frete' => $itemData['frete'] ?? 0,
                'frete_incluido' => $itemData['frete_incluido'] ?? false,
                'fornecedor_escolhido' => $itemData['fornecedor_escolhido'] ?? false,
                'observacoes' => $itemData['observacoes'] ?? null,
            ]);

            // Se marcado como escolhido, desmarcar outros orçamentos do mesmo item
            if ($orcamentoItem->fornecedor_escolhido) {
                ProcessoItem::find($itemData['processo_item_id'])
                    ->orcamentos()
                    ->where('id', '!=', $orcamento->id)
                    ->update(['fornecedor_escolhido' => false]);
                
                // Também desmarcar em orcamento_itens
                OrcamentoItem::where('processo_item_id', $itemData['processo_item_id'])
                    ->where('id', '!=', $orcamentoItem->id)
                    ->update(['fornecedor_escolhido' => false]);
            }
        }

        $orcamento->load(['fornecedor', 'transportadora', 'itens.processoItem', 'itens.formacaoPreco']);

        return new OrcamentoResource($orcamento);
    }

    /**
     * Lista orçamentos vinculados ao processo
     */
    public function indexByProcesso(Processo $processo)
    {
        $orcamentos = $processo->orcamentos()
            ->with(['fornecedor', 'transportadora', 'itens.processoItem', 'itens.formacaoPreco'])
            ->orderBy('created_at', 'desc')
            ->get();

        return OrcamentoResource::collection($orcamentos);
    }
}




