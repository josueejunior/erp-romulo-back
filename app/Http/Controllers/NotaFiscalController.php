<?php

namespace App\Http\Controllers;

use App\Models\Processo;
use App\Models\Empenho;
use App\Models\Fornecedor;
use App\Models\NotaFiscal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class NotaFiscalController extends Controller
{
    public function create(Processo $processo)
    {
        $empresa = $this->getEmpresaAtivaOrFail();

        if ($processo->empresa_id !== $empresa->id) {
            abort(403);
        }

        if (!$processo->isEmExecucao()) {
            return redirect()->route('processos.show', $processo)
                ->with('error', 'Notas fiscais só podem ser criadas para processos em execução.');
        }

        $empenhos = $processo->empenhos;
        $fornecedores = Fornecedor::where('empresa_id', $empresa->id)->orderBy('razao_social')->get();

        return view('notas-fiscais.create', compact('processo', 'empenhos', 'fornecedores'));
    }

    public function store(Request $request, Processo $processo)
    {
        $empresa = $this->getEmpresaAtivaOrFail();

        if ($processo->empresa_id !== $empresa->id) {
            abort(403);
        }

        if (!$processo->isEmExecucao()) {
            return redirect()->route('processos.show', $processo)
                ->with('error', 'Notas fiscais só podem ser criadas para processos em execução.');
        }

        $validated = $request->validate([
            'empenho_id' => 'nullable|exists:empenhos,id',
            'tipo' => 'required|in:entrada,saida',
            'numero' => 'required|string|max:255',
            'serie' => 'nullable|string|max:10',
            'data_emissao' => 'required|date',
            'fornecedor_id' => 'nullable|exists:fornecedores,id',
            'transportadora' => 'nullable|string|max:255',
            'numero_cte' => 'nullable|string|max:255',
            'data_entrega_prevista' => 'nullable|date',
            'data_entrega_realizada' => 'nullable|date',
            'situacao_logistica' => 'nullable|in:aguardando_envio,em_transito,entregue,atrasada',
            'valor' => 'required|numeric|min:0',
            'custo_produto' => 'nullable|numeric|min:0',
            'custo_frete' => 'nullable|numeric|min:0',
            'custo_total' => 'nullable|numeric|min:0',
            'comprovante_pagamento' => 'nullable|string|max:255',
            'arquivo' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
            'situacao' => 'required|in:pendente,paga,cancelada',
            'data_pagamento' => 'nullable|date',
            'observacoes' => 'nullable|string',
        ]);

        // Validar se empenho pertence ao processo
        if ($validated['empenho_id']) {
            $empenho = Empenho::find($validated['empenho_id']);
            if (!$empenho || $empenho->processo_id !== $processo->id) {
                return redirect()->back()
                    ->with('error', 'Empenho inválido.')
                    ->withInput();
            }
        }

        $validated['processo_id'] = $processo->id;

        // Upload do arquivo
        if ($request->hasFile('arquivo')) {
            $arquivo = $request->file('arquivo');
            $nomeArquivo = time() . '_' . $arquivo->getClientOriginalName();
            $arquivo->storeAs('notas-fiscais', $nomeArquivo, 'public');
            $validated['arquivo'] = $nomeArquivo;
        }

        NotaFiscal::create($validated);

        return redirect()->route('processos.show', $processo)
            ->with('success', 'Nota fiscal cadastrada com sucesso!');
    }

    public function show(Processo $processo, NotaFiscal $notaFiscal)
    {
        $empresa = $this->getEmpresaAtivaOrFail();

        if ($processo->empresa_id !== $empresa->id || $notaFiscal->processo_id !== $processo->id) {
            abort(403);
        }

        $notaFiscal->load(['empenho', 'fornecedor']);

        return view('notas-fiscais.show', compact('processo', 'notaFiscal'));
    }

    public function edit(Processo $processo, NotaFiscal $notaFiscal)
    {
        $empresa = $this->getEmpresaAtivaOrFail();

        if ($processo->empresa_id !== $empresa->id || $notaFiscal->processo_id !== $processo->id) {
            abort(403);
        }

        $empenhos = $processo->empenhos;
        $fornecedores = Fornecedor::where('empresa_id', $empresa->id)->orderBy('razao_social')->get();

        return view('notas-fiscais.edit', compact('processo', 'notaFiscal', 'empenhos', 'fornecedores'));
    }

    public function update(Request $request, Processo $processo, NotaFiscal $notaFiscal)
    {
        $empresa = $this->getEmpresaAtivaOrFail();

        if ($processo->empresa_id !== $empresa->id || $notaFiscal->processo_id !== $processo->id) {
            abort(403);
        }

        $validated = $request->validate([
            'empenho_id' => 'nullable|exists:empenhos,id',
            'tipo' => 'required|in:entrada,saida',
            'numero' => 'required|string|max:255',
            'serie' => 'nullable|string|max:10',
            'data_emissao' => 'required|date',
            'fornecedor_id' => 'nullable|exists:fornecedores,id',
            'transportadora' => 'nullable|string|max:255',
            'numero_cte' => 'nullable|string|max:255',
            'data_entrega_prevista' => 'nullable|date',
            'data_entrega_realizada' => 'nullable|date',
            'situacao_logistica' => 'nullable|in:aguardando_envio,em_transito,entregue,atrasada',
            'valor' => 'required|numeric|min:0',
            'custo_produto' => 'nullable|numeric|min:0',
            'custo_frete' => 'nullable|numeric|min:0',
            'custo_total' => 'nullable|numeric|min:0',
            'comprovante_pagamento' => 'nullable|string|max:255',
            'arquivo' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
            'situacao' => 'required|in:pendente,paga,cancelada',
            'data_pagamento' => 'nullable|date',
            'observacoes' => 'nullable|string',
        ]);

        // Validar se empenho pertence ao processo
        if ($validated['empenho_id']) {
            $empenho = Empenho::find($validated['empenho_id']);
            if (!$empenho || $empenho->processo_id !== $processo->id) {
                return redirect()->back()
                    ->with('error', 'Empenho inválido.')
                    ->withInput();
            }
        }

        // Upload do arquivo
        if ($request->hasFile('arquivo')) {
            // Deletar arquivo antigo se existir
            if ($notaFiscal->arquivo) {
                Storage::disk('public')->delete('notas-fiscais/' . $notaFiscal->arquivo);
            }
            $arquivo = $request->file('arquivo');
            $nomeArquivo = time() . '_' . $arquivo->getClientOriginalName();
            $arquivo->storeAs('notas-fiscais', $nomeArquivo, 'public');
            $validated['arquivo'] = $nomeArquivo;
        }

        $notaFiscal->update($validated);

        return redirect()->route('processos.show', $processo)
            ->with('success', 'Nota fiscal atualizada com sucesso!');
    }

    public function destroy(Processo $processo, NotaFiscal $notaFiscal)
    {
        $empresa = $this->getEmpresaAtivaOrFail();

        if ($processo->empresa_id !== $empresa->id || $notaFiscal->processo_id !== $processo->id) {
            abort(403);
        }

        // Deletar arquivo se existir
        if ($notaFiscal->arquivo) {
            Storage::disk('public')->delete('notas-fiscais/' . $notaFiscal->arquivo);
        }

        $notaFiscal->delete();

        return redirect()->route('processos.show', $processo)
            ->with('success', 'Nota fiscal excluída com sucesso!');
    }
}
