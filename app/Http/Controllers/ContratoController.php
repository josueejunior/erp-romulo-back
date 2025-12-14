<?php

namespace App\Http\Controllers;

use App\Models\Processo;
use App\Models\Contrato;
use Illuminate\Http\Request;

class ContratoController extends Controller
{
    public function create(Processo $processo)
    {
        $empresa = $this->getEmpresaAtivaOrFail();

        if ($processo->empresa_id !== $empresa->id) {
            abort(403);
        }

        if (!$processo->isEmExecucao()) {
            return redirect()->route('processos.show', $processo)
                ->with('error', 'Contratos só podem ser criados para processos em execução.');
        }

        return view('contratos.create', compact('processo'));
    }

    public function store(Request $request, Processo $processo)
    {
        $empresa = $this->getEmpresaAtivaOrFail();

        if ($processo->empresa_id !== $empresa->id) {
            abort(403);
        }

        if (!$processo->isEmExecucao()) {
            return redirect()->route('processos.show', $processo)
                ->with('error', 'Contratos só podem ser criados para processos em execução.');
        }

        $validated = $request->validate([
            'numero' => 'required|string|max:255',
            'data_inicio' => 'required|date',
            'data_fim' => 'nullable|date|after:data_inicio',
            'valor_total' => 'required|numeric|min:0',
            'situacao' => 'required|in:vigente,encerrado,cancelado',
            'observacoes' => 'nullable|string',
        ]);

        $validated['processo_id'] = $processo->id;
        $validated['saldo'] = $validated['valor_total']; // Saldo inicial = valor total

        Contrato::create($validated);

        return redirect()->route('processos.show', $processo)
            ->with('success', 'Contrato cadastrado com sucesso!');
    }

    public function show(Processo $processo, Contrato $contrato)
    {
        $empresa = $this->getEmpresaAtivaOrFail();

        if ($processo->empresa_id !== $empresa->id || $contrato->processo_id !== $processo->id) {
            abort(403);
        }

        $contrato->load(['empenhos', 'autorizacoesFornecimento']);

        return view('contratos.show', compact('processo', 'contrato'));
    }

    public function edit(Processo $processo, Contrato $contrato)
    {
        $empresa = $this->getEmpresaAtivaOrFail();

        if ($processo->empresa_id !== $empresa->id || $contrato->processo_id !== $processo->id) {
            abort(403);
        }

        return view('contratos.edit', compact('processo', 'contrato'));
    }

    public function update(Request $request, Processo $processo, Contrato $contrato)
    {
        $empresa = $this->getEmpresaAtivaOrFail();

        if ($processo->empresa_id !== $empresa->id || $contrato->processo_id !== $processo->id) {
            abort(403);
        }

        $validated = $request->validate([
            'numero' => 'required|string|max:255',
            'data_inicio' => 'required|date',
            'data_fim' => 'nullable|date|after:data_inicio',
            'valor_total' => 'required|numeric|min:0',
            'situacao' => 'required|in:vigente,encerrado,cancelado',
            'observacoes' => 'nullable|string',
        ]);

        $valorTotalAnterior = $contrato->valor_total;
        $contrato->update($validated);

        // Recalcular saldo se o valor total mudou
        if ($validated['valor_total'] != $valorTotalAnterior) {
            $contrato->atualizarSaldo();
        }

        return redirect()->route('processos.show', $processo)
            ->with('success', 'Contrato atualizado com sucesso!');
    }

    public function destroy(Processo $processo, Contrato $contrato)
    {
        $empresa = $this->getEmpresaAtivaOrFail();

        if ($processo->empresa_id !== $empresa->id || $contrato->processo_id !== $processo->id) {
            abort(403);
        }

        if ($contrato->empenhos()->count() > 0) {
            return redirect()->route('processos.show', $processo)
                ->with('error', 'Não é possível excluir um contrato que possui empenhos vinculados.');
        }

        $contrato->delete();

        return redirect()->route('processos.show', $processo)
            ->with('success', 'Contrato excluído com sucesso!');
    }
}
