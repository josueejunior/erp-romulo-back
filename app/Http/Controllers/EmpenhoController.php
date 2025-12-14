<?php

namespace App\Http\Controllers;

use App\Models\Processo;
use App\Models\Contrato;
use App\Models\AutorizacaoFornecimento;
use App\Models\Empenho;
use Illuminate\Http\Request;

class EmpenhoController extends Controller
{
    public function create(Processo $processo)
    {
        $empresa = $this->getEmpresaAtivaOrFail();

        if ($processo->empresa_id !== $empresa->id) {
            abort(403);
        }

        if (!$processo->isEmExecucao()) {
            return redirect()->route('processos.show', $processo)
                ->with('error', 'Empenhos só podem ser criados para processos em execução.');
        }

        $contratos = $processo->contratos;
        $autorizacoesFornecimento = $processo->autorizacoesFornecimento;

        return view('empenhos.create', compact('processo', 'contratos', 'autorizacoesFornecimento'));
    }

    public function store(Request $request, Processo $processo)
    {
        $empresa = $this->getEmpresaAtivaOrFail();

        if ($processo->empresa_id !== $empresa->id) {
            abort(403);
        }

        if (!$processo->isEmExecucao()) {
            return redirect()->route('processos.show', $processo)
                ->with('error', 'Empenhos só podem ser criados para processos em execução.');
        }

        $validated = $request->validate([
            'contrato_id' => 'nullable|exists:contratos,id',
            'autorizacao_fornecimento_id' => 'nullable|exists:autorizacoes_fornecimento,id',
            'numero' => 'required|string|max:255',
            'data' => 'required|date',
            'valor' => 'required|numeric|min:0',
            'data_entrega' => 'nullable|date',
            'observacoes' => 'nullable|string',
        ]);

        // Validar se contrato/AF pertence ao processo
        if ($validated['contrato_id']) {
            $contrato = Contrato::find($validated['contrato_id']);
            if (!$contrato || $contrato->processo_id !== $processo->id) {
                return redirect()->back()
                    ->with('error', 'Contrato inválido.')
                    ->withInput();
            }
        }

        if ($validated['autorizacao_fornecimento_id']) {
            $af = AutorizacaoFornecimento::find($validated['autorizacao_fornecimento_id']);
            if (!$af || $af->processo_id !== $processo->id) {
                return redirect()->back()
                    ->with('error', 'Autorização de Fornecimento inválida.')
                    ->withInput();
            }
        }

        $validated['processo_id'] = $processo->id;
        $validated['concluido'] = false;

        $empenho = Empenho::create($validated);

        // Atualizar saldos
        if ($empenho->contrato_id) {
            $empenho->contrato->atualizarSaldo();
        }
        if ($empenho->autorizacao_fornecimento_id) {
            $empenho->autorizacaoFornecimento->atualizarSaldo();
        }

        return redirect()->route('processos.show', $processo)
            ->with('success', 'Empenho cadastrado com sucesso!');
    }

    public function show(Processo $processo, Empenho $empenho)
    {
        $empresa = $this->getEmpresaAtivaOrFail();

        if ($processo->empresa_id !== $empresa->id || $empenho->processo_id !== $processo->id) {
            abort(403);
        }

        $empenho->load(['contrato', 'autorizacaoFornecimento', 'notasFiscais']);

        return view('empenhos.show', compact('processo', 'empenho'));
    }

    public function edit(Processo $processo, Empenho $empenho)
    {
        $empresa = $this->getEmpresaAtivaOrFail();

        if ($processo->empresa_id !== $empresa->id || $empenho->processo_id !== $processo->id) {
            abort(403);
        }

        $contratos = $processo->contratos;
        $autorizacoesFornecimento = $processo->autorizacoesFornecimento;

        return view('empenhos.edit', compact('processo', 'empenho', 'contratos', 'autorizacoesFornecimento'));
    }

    public function update(Request $request, Processo $processo, Empenho $empenho)
    {
        $empresa = $this->getEmpresaAtivaOrFail();

        if ($processo->empresa_id !== $empresa->id || $empenho->processo_id !== $processo->id) {
            abort(403);
        }

        $validated = $request->validate([
            'contrato_id' => 'nullable|exists:contratos,id',
            'autorizacao_fornecimento_id' => 'nullable|exists:autorizacoes_fornecimento,id',
            'numero' => 'required|string|max:255',
            'data' => 'required|date',
            'valor' => 'required|numeric|min:0',
            'concluido' => 'boolean',
            'data_entrega' => 'nullable|date',
            'observacoes' => 'nullable|string',
        ]);

        // Validar se contrato/AF pertence ao processo
        if ($validated['contrato_id']) {
            $contrato = Contrato::find($validated['contrato_id']);
            if (!$contrato || $contrato->processo_id !== $processo->id) {
                return redirect()->back()
                    ->with('error', 'Contrato inválido.')
                    ->withInput();
            }
        }

        if ($validated['autorizacao_fornecimento_id']) {
            $af = AutorizacaoFornecimento::find($validated['autorizacao_fornecimento_id']);
            if (!$af || $af->processo_id !== $processo->id) {
                return redirect()->back()
                    ->with('error', 'Autorização de Fornecimento inválida.')
                    ->withInput();
            }
        }

        $valorAnterior = $empenho->valor;
        $contratoAnterior = $empenho->contrato_id;
        $afAnterior = $empenho->autorizacao_fornecimento_id;

        $validated['concluido'] = $request->has('concluido');
        if ($validated['concluido'] && !$empenho->concluido) {
            $validated['data_entrega'] = $validated['data_entrega'] ?? now();
        }

        $empenho->update($validated);

        // Atualizar saldos dos contratos/AFs afetados
        if ($contratoAnterior) {
            Contrato::find($contratoAnterior)?->atualizarSaldo();
        }
        if ($afAnterior) {
            AutorizacaoFornecimento::find($afAnterior)?->atualizarSaldo();
        }
        if ($empenho->contrato_id) {
            $empenho->contrato->atualizarSaldo();
        }
        if ($empenho->autorizacao_fornecimento_id) {
            $empenho->autorizacaoFornecimento->atualizarSaldo();
        }

        return redirect()->route('processos.show', $processo)
            ->with('success', 'Empenho atualizado com sucesso!');
    }

    public function destroy(Processo $processo, Empenho $empenho)
    {
        $empresa = $this->getEmpresaAtivaOrFail();

        if ($processo->empresa_id !== $empresa->id || $empenho->processo_id !== $processo->id) {
            abort(403);
        }

        $contratoId = $empenho->contrato_id;
        $afId = $empenho->autorizacao_fornecimento_id;

        $empenho->delete();

        // Atualizar saldos
        if ($contratoId) {
            Contrato::find($contratoId)?->atualizarSaldo();
        }
        if ($afId) {
            AutorizacaoFornecimento::find($afId)?->atualizarSaldo();
        }

        return redirect()->route('processos.show', $processo)
            ->with('success', 'Empenho excluído com sucesso!');
    }
}
