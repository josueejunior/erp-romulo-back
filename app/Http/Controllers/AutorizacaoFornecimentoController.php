<?php

namespace App\Http\Controllers;

use App\Models\Processo;
use App\Models\Contrato;
use App\Models\AutorizacaoFornecimento;
use Illuminate\Http\Request;

class AutorizacaoFornecimentoController extends Controller
{
    public function create(Processo $processo)
    {
        $empresa = $this->getEmpresaAtivaOrFail();

        if ($processo->empresa_id !== $empresa->id) {
            abort(403);
        }

        if (!$processo->isEmExecucao()) {
            return redirect()->route('processos.show', $processo)
                ->with('error', 'Autorizações de Fornecimento só podem ser criadas para processos em execução.');
        }

        $contratos = $processo->contratos;

        return view('autorizacoes-fornecimento.create', compact('processo', 'contratos'));
    }

    public function store(Request $request, Processo $processo)
    {
        $empresa = $this->getEmpresaAtivaOrFail();

        if ($processo->empresa_id !== $empresa->id) {
            abort(403);
        }

        if (!$processo->isEmExecucao()) {
            return redirect()->route('processos.show', $processo)
                ->with('error', 'Autorizações de Fornecimento só podem ser criadas para processos em execução.');
        }

        $validated = $request->validate([
            'contrato_id' => 'nullable|exists:contratos,id',
            'numero' => 'required|string|max:255',
            'data' => 'required|date',
            'valor' => 'required|numeric|min:0',
            'situacao' => 'required|in:aguardando_empenho,atendendo,concluida',
            'observacoes' => 'nullable|string',
        ]);

        // Validar se contrato pertence ao processo
        if ($validated['contrato_id']) {
            $contrato = Contrato::find($validated['contrato_id']);
            if (!$contrato || $contrato->processo_id !== $processo->id) {
                return redirect()->back()
                    ->with('error', 'Contrato inválido.')
                    ->withInput();
            }
        }

        $validated['processo_id'] = $processo->id;
        $validated['saldo'] = $validated['valor']; // Saldo inicial = valor

        AutorizacaoFornecimento::create($validated);

        return redirect()->route('processos.show', $processo)
            ->with('success', 'Autorização de Fornecimento cadastrada com sucesso!');
    }

    public function show(Processo $processo, AutorizacaoFornecimento $autorizacaoFornecimento)
    {
        $empresa = $this->getEmpresaAtivaOrFail();

        if ($processo->empresa_id !== $empresa->id || $autorizacaoFornecimento->processo_id !== $processo->id) {
            abort(403);
        }

        $autorizacaoFornecimento->load(['empenhos', 'contrato']);

        return view('autorizacoes-fornecimento.show', compact('processo', 'autorizacaoFornecimento'));
    }

    public function edit(Processo $processo, AutorizacaoFornecimento $autorizacaoFornecimento)
    {
        $empresa = $this->getEmpresaAtivaOrFail();

        if ($processo->empresa_id !== $empresa->id || $autorizacaoFornecimento->processo_id !== $processo->id) {
            abort(403);
        }

        $contratos = $processo->contratos;

        return view('autorizacoes-fornecimento.edit', compact('processo', 'autorizacaoFornecimento', 'contratos'));
    }

    public function update(Request $request, Processo $processo, AutorizacaoFornecimento $autorizacaoFornecimento)
    {
        $empresa = $this->getEmpresaAtivaOrFail();

        if ($processo->empresa_id !== $empresa->id || $autorizacaoFornecimento->processo_id !== $processo->id) {
            abort(403);
        }

        $validated = $request->validate([
            'contrato_id' => 'nullable|exists:contratos,id',
            'numero' => 'required|string|max:255',
            'data' => 'required|date',
            'valor' => 'required|numeric|min:0',
            'situacao' => 'required|in:aguardando_empenho,atendendo,concluida',
            'observacoes' => 'nullable|string',
        ]);

        // Validar se contrato pertence ao processo
        if ($validated['contrato_id']) {
            $contrato = Contrato::find($validated['contrato_id']);
            if (!$contrato || $contrato->processo_id !== $processo->id) {
                return redirect()->back()
                    ->with('error', 'Contrato inválido.')
                    ->withInput();
            }
        }

        $valorAnterior = $autorizacaoFornecimento->valor;
        $autorizacaoFornecimento->update($validated);

        // Recalcular saldo se o valor mudou
        if ($validated['valor'] != $valorAnterior) {
            $autorizacaoFornecimento->atualizarSaldo();
        }

        return redirect()->route('processos.show', $processo)
            ->with('success', 'Autorização de Fornecimento atualizada com sucesso!');
    }

    public function destroy(Processo $processo, AutorizacaoFornecimento $autorizacaoFornecimento)
    {
        $empresa = $this->getEmpresaAtivaOrFail();

        if ($processo->empresa_id !== $empresa->id || $autorizacaoFornecimento->processo_id !== $processo->id) {
            abort(403);
        }

        if ($autorizacaoFornecimento->empenhos()->count() > 0) {
            return redirect()->route('processos.show', $processo)
                ->with('error', 'Não é possível excluir uma AF que possui empenhos vinculados.');
        }

        $autorizacaoFornecimento->delete();

        return redirect()->route('processos.show', $processo)
            ->with('success', 'Autorização de Fornecimento excluída com sucesso!');
    }
}
