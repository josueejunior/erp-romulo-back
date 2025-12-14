<?php

namespace App\Http\Controllers;

use App\Models\Processo;
use App\Models\ProcessoItem;
use App\Models\Orcamento;
use App\Models\FormacaoPreco;
use Illuminate\Http\Request;

class FormacaoPrecoController extends Controller
{
    public function create(Processo $processo, ProcessoItem $item, Orcamento $orcamento)
    {
        $empresa = $this->getEmpresaAtivaOrFail();

        if ($processo->empresa_id !== $empresa->id || 
            $item->processo_id !== $processo->id || 
            $orcamento->processo_item_id !== $item->id) {
            abort(403);
        }

        if ($processo->isEmExecucao()) {
            return redirect()->route('processos.show', $processo)
                ->with('error', 'Não é possível criar formação de preço para processos em execução.');
        }

        return view('formacao-precos.create', compact('processo', 'item', 'orcamento'));
    }

    public function store(Request $request, Processo $processo, ProcessoItem $item, Orcamento $orcamento)
    {
        $empresa = $this->getEmpresaAtivaOrFail();

        if ($processo->empresa_id !== $empresa->id || 
            $item->processo_id !== $processo->id || 
            $orcamento->processo_item_id !== $item->id) {
            abort(403);
        }

        if ($processo->isEmExecucao()) {
            return redirect()->route('processos.show', $processo)
                ->with('error', 'Não é possível criar formação de preço para processos em execução.');
        }

        $validated = $request->validate([
            'custo_produto' => 'required|numeric|min:0',
            'frete' => 'required|numeric|min:0',
            'percentual_impostos' => 'required|numeric|min:0|max:100',
            'percentual_margem' => 'required|numeric|min:0|max:100',
            'preco_minimo' => 'required|numeric|min:0',
            'preco_recomendado' => 'nullable|numeric|min:0',
            'observacoes' => 'nullable|string',
        ]);

        // Calcular valores
        $custoTotal = $validated['custo_produto'] + $validated['frete'];
        $validated['valor_impostos'] = ($custoTotal * $validated['percentual_impostos']) / 100;
        $custoComImpostos = $custoTotal + $validated['valor_impostos'];
        $validated['valor_margem'] = ($custoComImpostos * $validated['percentual_margem']) / 100;

        $validated['processo_item_id'] = $item->id;
        $validated['orcamento_id'] = $orcamento->id;

        // Se já existe formação de preço para este orçamento, atualizar
        $formacaoPreco = FormacaoPreco::where('orcamento_id', $orcamento->id)->first();
        if ($formacaoPreco) {
            $formacaoPreco->update($validated);
        } else {
            FormacaoPreco::create($validated);
        }

        return redirect()->route('processos.show', $processo)
            ->with('success', 'Formação de preço salva com sucesso!');
    }

    public function edit(Processo $processo, ProcessoItem $item, Orcamento $orcamento, FormacaoPreco $formacaoPreco)
    {
        $empresa = $this->getEmpresaAtivaOrFail();

        if ($processo->empresa_id !== $empresa->id || 
            $item->processo_id !== $processo->id || 
            $orcamento->processo_item_id !== $item->id ||
            $formacaoPreco->orcamento_id !== $orcamento->id) {
            abort(403);
        }

        if ($processo->isEmExecucao()) {
            return redirect()->route('processos.show', $processo)
                ->with('error', 'Não é possível editar formação de preço de processos em execução.');
        }

        return view('formacao-precos.edit', compact('processo', 'item', 'orcamento', 'formacaoPreco'));
    }

    public function update(Request $request, Processo $processo, ProcessoItem $item, Orcamento $orcamento, FormacaoPreco $formacaoPreco)
    {
        $empresa = $this->getEmpresaAtivaOrFail();

        if ($processo->empresa_id !== $empresa->id || 
            $item->processo_id !== $processo->id || 
            $orcamento->processo_item_id !== $item->id ||
            $formacaoPreco->orcamento_id !== $orcamento->id) {
            abort(403);
        }

        if ($processo->isEmExecucao()) {
            return redirect()->route('processos.show', $processo)
                ->with('error', 'Não é possível editar formação de preço de processos em execução.');
        }

        $validated = $request->validate([
            'custo_produto' => 'required|numeric|min:0',
            'frete' => 'required|numeric|min:0',
            'percentual_impostos' => 'required|numeric|min:0|max:100',
            'percentual_margem' => 'required|numeric|min:0|max:100',
            'preco_minimo' => 'required|numeric|min:0',
            'preco_recomendado' => 'nullable|numeric|min:0',
            'observacoes' => 'nullable|string',
        ]);

        // Calcular valores
        $custoTotal = $validated['custo_produto'] + $validated['frete'];
        $validated['valor_impostos'] = ($custoTotal * $validated['percentual_impostos']) / 100;
        $custoComImpostos = $custoTotal + $validated['valor_impostos'];
        $validated['valor_margem'] = ($custoComImpostos * $validated['percentual_margem']) / 100;

        $formacaoPreco->update($validated);

        return redirect()->route('processos.show', $processo)
            ->with('success', 'Formação de preço atualizada com sucesso!');
    }
}
