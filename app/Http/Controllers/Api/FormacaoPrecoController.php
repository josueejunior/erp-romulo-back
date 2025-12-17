<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Resources\FormacaoPrecoResource;
use App\Models\Processo;
use App\Models\ProcessoItem;
use App\Models\Orcamento;
use App\Models\FormacaoPreco;
use Illuminate\Http\Request;

class FormacaoPrecoController extends BaseApiController
{
    public function show(Processo $processo, ProcessoItem $item, Orcamento $orcamento)
    {
        $empresa = $this->getEmpresaAtivaOrFail();
        
        if ($processo->empresa_id !== $empresa->id) {
            return response()->json([
                'message' => 'Processo não encontrado ou não pertence à empresa ativa.'
            ], 404);
        }
        
        if ($item->processo_id !== $processo->id || $orcamento->processo_item_id !== $item->id) {
            return response()->json(['message' => 'Orçamento não pertence a este item.'], 404);
        }
        
        if ($orcamento->empresa_id !== $empresa->id) {
            return response()->json([
                'message' => 'Orçamento não encontrado ou não pertence à empresa ativa.'
            ], 404);
        }

        $formacaoPreco = $orcamento->formacaoPreco;

        if (!$formacaoPreco) {
            return response()->json(['message' => 'Formação de preço não encontrada.'], 404);
        }

        return new FormacaoPrecoResource($formacaoPreco);
    }

    public function store(Request $request, Processo $processo, ProcessoItem $item, Orcamento $orcamento)
    {
        $empresa = $this->getEmpresaAtivaOrFail();
        
        if ($processo->empresa_id !== $empresa->id) {
            return response()->json([
                'message' => 'Processo não encontrado ou não pertence à empresa ativa.'
            ], 404);
        }
        
        if ($item->processo_id !== $processo->id || $orcamento->processo_item_id !== $item->id) {
            return response()->json(['message' => 'Orçamento não pertence a este item.'], 404);
        }
        
        if ($orcamento->empresa_id !== $empresa->id) {
            return response()->json([
                'message' => 'Orçamento não encontrado ou não pertence à empresa ativa.'
            ], 404);
        }

        if ($processo->isEmExecucao()) {
            return response()->json([
                'message' => 'Não é possível criar formação de preço para processos em execução.'
            ], 403);
        }

        $validated = $request->validate([
            'custo_produto' => 'required|numeric|min:0',
            'frete' => 'required|numeric|min:0',
            'percentual_impostos' => 'required|numeric|min:0|max:100',
            'percentual_margem' => 'required|numeric|min:0|max:100',
            'preco_minimo' => 'nullable|numeric|min:0', // Calculado automaticamente se não fornecido
            'preco_recomendado' => 'nullable|numeric|min:0',
            'observacoes' => 'nullable|string',
        ]);

        $custoTotal = $validated['custo_produto'] + $validated['frete'];
        $validated['valor_impostos'] = ($custoTotal * $validated['percentual_impostos']) / 100;
        $custoComImpostos = $custoTotal + $validated['valor_impostos'];
        $validated['valor_margem'] = ($custoComImpostos * $validated['percentual_margem']) / 100;
        
        // Calcular preço mínimo se não fornecido
        if (!isset($validated['preco_minimo']) || $validated['preco_minimo'] == 0) {
            $validated['preco_minimo'] = $custoComImpostos + $validated['valor_margem'];
        }

        $validated['processo_item_id'] = $item->id;
        $validated['orcamento_id'] = $orcamento->id;

        $formacaoPreco = FormacaoPreco::updateOrCreate(
            ['orcamento_id' => $orcamento->id],
            $validated
        );

        // Atualizar valor mínimo de venda no item se o orçamento for o escolhido
        if ($orcamento->fornecedor_escolhido) {
            $item->valor_minimo_venda = $validated['preco_minimo'];
            $item->save();
        }

        return new FormacaoPrecoResource($formacaoPreco);
    }

    public function update(Request $request, Processo $processo, ProcessoItem $item, Orcamento $orcamento, FormacaoPreco $formacaoPreco)
    {
        $empresa = $this->getEmpresaAtivaOrFail();
        
        if ($processo->empresa_id !== $empresa->id) {
            return response()->json([
                'message' => 'Processo não encontrado ou não pertence à empresa ativa.'
            ], 404);
        }
        
        if ($item->processo_id !== $processo->id || 
            $orcamento->processo_item_id !== $item->id ||
            $formacaoPreco->orcamento_id !== $orcamento->id) {
            return response()->json(['message' => 'Formação de preço não pertence a este orçamento.'], 404);
        }
        
        if ($orcamento->empresa_id !== $empresa->id) {
            return response()->json([
                'message' => 'Orçamento não encontrado ou não pertence à empresa ativa.'
            ], 404);
        }

        if ($processo->isEmExecucao()) {
            return response()->json([
                'message' => 'Não é possível editar formação de preço de processos em execução.'
            ], 403);
        }

        $validated = $request->validate([
            'custo_produto' => 'required|numeric|min:0',
            'frete' => 'required|numeric|min:0',
            'percentual_impostos' => 'required|numeric|min:0|max:100',
            'percentual_margem' => 'required|numeric|min:0|max:100',
            'preco_minimo' => 'nullable|numeric|min:0', // Calculado automaticamente se não fornecido
            'preco_recomendado' => 'nullable|numeric|min:0',
            'observacoes' => 'nullable|string',
        ]);

        $custoTotal = $validated['custo_produto'] + $validated['frete'];
        $validated['valor_impostos'] = ($custoTotal * $validated['percentual_impostos']) / 100;
        $custoComImpostos = $custoTotal + $validated['valor_impostos'];
        $validated['valor_margem'] = ($custoComImpostos * $validated['percentual_margem']) / 100;
        
        // Calcular preço mínimo se não fornecido
        if (!isset($validated['preco_minimo']) || $validated['preco_minimo'] == 0) {
            $validated['preco_minimo'] = $custoComImpostos + $validated['valor_margem'];
        }

        $formacaoPreco->update($validated);

        // Atualizar valor mínimo de venda no item se o orçamento for o escolhido
        if ($orcamento->fornecedor_escolhido) {
            $item->valor_minimo_venda = $validated['preco_minimo'];
            $item->save();
        }

        return new FormacaoPrecoResource($formacaoPreco);
    }
}




