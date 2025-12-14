<?php

namespace App\Http\Controllers;

use App\Models\Processo;
use App\Models\ProcessoItem;
use Illuminate\Http\Request;

class ProcessoItemController extends Controller
{
    public function create(Processo $processo)
    {
        $empresa = $this->getEmpresaAtivaOrFail();

        if ($processo->empresa_id !== $empresa->id) {
            abort(403);
        }

        if ($processo->isEmExecucao()) {
            return redirect()->route('processos.show', $processo)
                ->with('error', 'Não é possível adicionar itens a processos em execução.');
        }

        // Calcular próximo número de item
        $ultimoItem = $processo->itens()->orderBy('numero_item', 'desc')->first();
        $proximoNumero = $ultimoItem ? $ultimoItem->numero_item + 1 : 1;

        return view('processo-itens.create', compact('processo', 'proximoNumero'));
    }

    public function store(Request $request, Processo $processo)
    {
        $empresa = $this->getEmpresaAtivaOrFail();

        if ($processo->empresa_id !== $empresa->id) {
            abort(403);
        }

        if ($processo->isEmExecucao()) {
            return redirect()->route('processos.show', $processo)
                ->with('error', 'Não é possível adicionar itens a processos em execução.');
        }

        $validated = $request->validate([
            'numero_item' => 'required|integer|min:1',
            'quantidade' => 'required|numeric|min:0.01',
            'unidade' => 'required|string|max:50',
            'especificacao_tecnica' => 'required|string',
            'marca_modelo_referencia' => 'nullable|string|max:255',
            'exige_atestado' => 'boolean',
            'quantidade_minima_atestado' => 'nullable|integer|min:1|required_if:exige_atestado,1',
            'valor_estimado' => 'nullable|numeric|min:0',
            'observacoes' => 'nullable|string',
        ]);

        $validated['processo_id'] = $processo->id;
        $validated['exige_atestado'] = $request->has('exige_atestado');
        $validated['status_item'] = 'pendente';

        ProcessoItem::create($validated);

        return redirect()->route('processos.show', $processo)
            ->with('success', 'Item adicionado com sucesso!');
    }

    public function edit(Processo $processo, ProcessoItem $item)
    {
        $empresa = $this->getEmpresaAtivaOrFail();

        if ($processo->empresa_id !== $empresa->id || $item->processo_id !== $processo->id) {
            abort(403);
        }

        if ($processo->isEmExecucao()) {
            return redirect()->route('processos.show', $processo)
                ->with('error', 'Não é possível editar itens de processos em execução.');
        }

        return view('processo-itens.edit', compact('processo', 'item'));
    }

    public function update(Request $request, Processo $processo, ProcessoItem $item)
    {
        $empresa = $this->getEmpresaAtivaOrFail();

        if ($processo->empresa_id !== $empresa->id || $item->processo_id !== $processo->id) {
            abort(403);
        }

        if ($processo->isEmExecucao()) {
            return redirect()->route('processos.show', $processo)
                ->with('error', 'Não é possível editar itens de processos em execução.');
        }

        $validated = $request->validate([
            'numero_item' => 'required|integer|min:1',
            'quantidade' => 'required|numeric|min:0.01',
            'unidade' => 'required|string|max:50',
            'especificacao_tecnica' => 'required|string',
            'marca_modelo_referencia' => 'nullable|string|max:255',
            'exige_atestado' => 'boolean',
            'quantidade_minima_atestado' => 'nullable|integer|min:1|required_if:exige_atestado,1',
            'valor_estimado' => 'nullable|numeric|min:0',
            'observacoes' => 'nullable|string',
        ]);

        $validated['exige_atestado'] = $request->has('exige_atestado');

        $item->update($validated);

        return redirect()->route('processos.show', $processo)
            ->with('success', 'Item atualizado com sucesso!');
    }

    public function destroy(Processo $processo, ProcessoItem $item)
    {
        $empresa = $this->getEmpresaAtivaOrFail();

        if ($processo->empresa_id !== $empresa->id || $item->processo_id !== $processo->id) {
            abort(403);
        }

        if ($processo->isEmExecucao()) {
            return redirect()->route('processos.show', $processo)
                ->with('error', 'Não é possível excluir itens de processos em execução.');
        }

        $item->delete();

        return redirect()->route('processos.show', $processo)
            ->with('success', 'Item excluído com sucesso!');
    }
}
