<?php

namespace App\Http\Controllers;

use App\Models\Processo;
use App\Models\ProcessoItem;
use App\Models\Orcamento;
use App\Models\Fornecedor;
use App\Models\Transportadora;
use Illuminate\Http\Request;

class OrcamentoController extends Controller
{
    public function create(Processo $processo, ProcessoItem $item)
    {
        $empresa = $this->getEmpresaAtivaOrFail();

        if ($processo->empresa_id !== $empresa->id || $item->processo_id !== $processo->id) {
            abort(403);
        }

        if ($processo->isEmExecucao()) {
            return redirect()->route('processos.show', $processo)
                ->with('error', 'Não é possível adicionar orçamentos a processos em execução.');
        }

        $fornecedores = Fornecedor::where('empresa_id', $empresa->id)->orderBy('razao_social')->get();
        $transportadoras = Transportadora::where('empresa_id', $empresa->id)->orderBy('razao_social')->get();

        return view('orcamentos.create', compact('processo', 'item', 'fornecedores', 'transportadoras'));
    }

    public function store(Request $request, Processo $processo, ProcessoItem $item)
    {
        $empresa = $this->getEmpresaAtivaOrFail();

        if ($processo->empresa_id !== $empresa->id || $item->processo_id !== $processo->id) {
            abort(403);
        }

        if ($processo->isEmExecucao()) {
            return redirect()->route('processos.show', $processo)
                ->with('error', 'Não é possível adicionar orçamentos a processos em execução.');
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

        // Se marcar como fornecedor escolhido, desmarcar os outros
        if ($request->has('fornecedor_escolhido')) {
            $item->orcamentos()->update(['fornecedor_escolhido' => false]);
            $validated['fornecedor_escolhido'] = true;
        }

        Orcamento::create($validated);

        return redirect()->route('processos.show', $processo)
            ->with('success', 'Orçamento adicionado com sucesso!');
    }

    public function edit(Processo $processo, ProcessoItem $item, Orcamento $orcamento)
    {
        $empresa = $this->getEmpresaAtivaOrFail();

        if ($processo->empresa_id !== $empresa->id || 
            $item->processo_id !== $processo->id || 
            $orcamento->processo_item_id !== $item->id) {
            abort(403);
        }

        if ($processo->isEmExecucao()) {
            return redirect()->route('processos.show', $processo)
                ->with('error', 'Não é possível editar orçamentos de processos em execução.');
        }

        $fornecedores = Fornecedor::where('empresa_id', $empresa->id)->orderBy('razao_social')->get();
        $transportadoras = Transportadora::where('empresa_id', $empresa->id)->orderBy('razao_social')->get();

        return view('orcamentos.edit', compact('processo', 'item', 'orcamento', 'fornecedores', 'transportadoras'));
    }

    public function update(Request $request, Processo $processo, ProcessoItem $item, Orcamento $orcamento)
    {
        $empresa = $this->getEmpresaAtivaOrFail();

        if ($processo->empresa_id !== $empresa->id || 
            $item->processo_id !== $processo->id || 
            $orcamento->processo_item_id !== $item->id) {
            abort(403);
        }

        if ($processo->isEmExecucao()) {
            return redirect()->route('processos.show', $processo)
                ->with('error', 'Não é possível editar orçamentos de processos em execução.');
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

        // Se marcar como fornecedor escolhido, desmarcar os outros
        if ($request->has('fornecedor_escolhido')) {
            $item->orcamentos()->where('id', '!=', $orcamento->id)->update(['fornecedor_escolhido' => false]);
            $validated['fornecedor_escolhido'] = true;
        } else {
            $validated['fornecedor_escolhido'] = false;
        }

        $orcamento->update($validated);

        return redirect()->route('processos.show', $processo)
            ->with('success', 'Orçamento atualizado com sucesso!');
    }

    public function destroy(Processo $processo, ProcessoItem $item, Orcamento $orcamento)
    {
        $empresa = $this->getEmpresaAtivaOrFail();

        if ($processo->empresa_id !== $empresa->id || 
            $item->processo_id !== $processo->id || 
            $orcamento->processo_item_id !== $item->id) {
            abort(403);
        }

        if ($processo->isEmExecucao()) {
            return redirect()->route('processos.show', $processo)
                ->with('error', 'Não é possível excluir orçamentos de processos em execução.');
        }

        $orcamento->delete();

        return redirect()->route('processos.show', $processo)
            ->with('success', 'Orçamento excluído com sucesso!');
    }
}
