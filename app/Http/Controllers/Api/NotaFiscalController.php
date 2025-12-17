<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Processo;
use App\Models\NotaFiscal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class NotaFiscalController extends Controller
{
    public function index(Processo $processo)
    {
        $notasFiscais = $processo->notasFiscais()
            ->with(['empenho', 'contrato', 'autorizacaoFornecimento', 'fornecedor'])
            ->get();
        return response()->json($notasFiscais);
    }

    public function store(Request $request, Processo $processo)
    {
        if (!$processo->isEmExecucao()) {
            return response()->json([
                'message' => 'Notas fiscais só podem ser criadas para processos em execução.'
            ], 403);
        }

        $validated = $request->validate([
            'empenho_id' => 'nullable|exists:empenhos,id',
            'contrato_id' => 'nullable|exists:contratos,id',
            'autorizacao_fornecimento_id' => 'nullable|exists:autorizacoes_fornecimento,id',
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

        if ($validated['empenho_id']) {
            $empenho = \App\Models\Empenho::find($validated['empenho_id']);
            if (!$empenho || $empenho->processo_id !== $processo->id) {
                return response()->json(['message' => 'Empenho inválido.'], 400);
            }
        }

        if (isset($validated['contrato_id']) && $validated['contrato_id']) {
            $contrato = \App\Models\Contrato::find($validated['contrato_id']);
            if (!$contrato || $contrato->processo_id !== $processo->id) {
                return response()->json(['message' => 'Contrato inválido.'], 400);
            }
        }

        if (isset($validated['autorizacao_fornecimento_id']) && $validated['autorizacao_fornecimento_id']) {
            $af = \App\Models\AutorizacaoFornecimento::find($validated['autorizacao_fornecimento_id']);
            if (!$af || $af->processo_id !== $processo->id) {
                return response()->json(['message' => 'Autorização de Fornecimento inválida.'], 400);
            }
        }

        $validated['processo_id'] = $processo->id;

        if ($request->hasFile('arquivo')) {
            $arquivo = $request->file('arquivo');
            $nomeArquivo = time() . '_' . $arquivo->getClientOriginalName();
            $arquivo->storeAs('notas-fiscais', $nomeArquivo, 'public');
            $validated['arquivo'] = $nomeArquivo;
        }

        $notaFiscal = NotaFiscal::create($validated);
        $notaFiscal->load(['empenho', 'contrato', 'autorizacaoFornecimento', 'fornecedor']);

        return response()->json($notaFiscal, 201);
    }

    public function show(Processo $processo, NotaFiscal $notaFiscal)
    {
        if ($notaFiscal->processo_id !== $processo->id) {
            return response()->json(['message' => 'Nota fiscal não pertence a este processo.'], 404);
        }

        $notaFiscal->load(['empenho', 'contrato', 'autorizacaoFornecimento', 'fornecedor']);
        return response()->json($notaFiscal);
    }

    public function update(Request $request, Processo $processo, NotaFiscal $notaFiscal)
    {
        if ($notaFiscal->processo_id !== $processo->id) {
            return response()->json(['message' => 'Nota fiscal não pertence a este processo.'], 404);
        }

        $validated = $request->validate([
            'empenho_id' => 'nullable|exists:empenhos,id',
            'contrato_id' => 'nullable|exists:contratos,id',
            'autorizacao_fornecimento_id' => 'nullable|exists:autorizacoes_fornecimento,id',
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

        if ($validated['empenho_id']) {
            $empenho = \App\Models\Empenho::find($validated['empenho_id']);
            if (!$empenho || $empenho->processo_id !== $processo->id) {
                return response()->json(['message' => 'Empenho inválido.'], 400);
            }
        }

        if (isset($validated['contrato_id']) && $validated['contrato_id']) {
            $contrato = \App\Models\Contrato::find($validated['contrato_id']);
            if (!$contrato || $contrato->processo_id !== $processo->id) {
                return response()->json(['message' => 'Contrato inválido.'], 400);
            }
        }

        if (isset($validated['autorizacao_fornecimento_id']) && $validated['autorizacao_fornecimento_id']) {
            $af = \App\Models\AutorizacaoFornecimento::find($validated['autorizacao_fornecimento_id']);
            if (!$af || $af->processo_id !== $processo->id) {
                return response()->json(['message' => 'Autorização de Fornecimento inválida.'], 400);
            }
        }

        if ($request->hasFile('arquivo')) {
            if ($notaFiscal->arquivo) {
                Storage::disk('public')->delete('notas-fiscais/' . $notaFiscal->arquivo);
            }
            $arquivo = $request->file('arquivo');
            $nomeArquivo = time() . '_' . $arquivo->getClientOriginalName();
            $arquivo->storeAs('notas-fiscais', $nomeArquivo, 'public');
            $validated['arquivo'] = $nomeArquivo;
        }

        $notaFiscal->update($validated);
        $notaFiscal->load(['empenho', 'contrato', 'autorizacaoFornecimento', 'fornecedor']);

        return response()->json($notaFiscal);
    }

    public function destroy(Processo $processo, NotaFiscal $notaFiscal)
    {
        if ($notaFiscal->processo_id !== $processo->id) {
            return response()->json(['message' => 'Nota fiscal não pertence a este processo.'], 404);
        }

        if ($notaFiscal->arquivo) {
            Storage::disk('public')->delete('notas-fiscais/' . $notaFiscal->arquivo);
        }

        $notaFiscal->delete();

        return response()->json(null, 204);
    }
}




