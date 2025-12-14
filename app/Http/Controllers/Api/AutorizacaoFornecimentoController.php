<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Processo;
use App\Models\AutorizacaoFornecimento;
use Illuminate\Http\Request;

class AutorizacaoFornecimentoController extends Controller
{
    public function index(Processo $processo)
    {
        $afs = $processo->autorizacoesFornecimento()->with(['empenhos', 'contrato'])->get();
        return response()->json($afs);
    }

    public function store(Request $request, Processo $processo)
    {
        if (!$processo->isEmExecucao()) {
            return response()->json([
                'message' => 'Autorizações de Fornecimento só podem ser criadas para processos em execução.'
            ], 403);
        }

        $validated = $request->validate([
            'contrato_id' => 'nullable|exists:contratos,id',
            'numero' => 'required|string|max:255',
            'data' => 'required|date',
            'valor' => 'required|numeric|min:0',
            'situacao' => 'required|in:aguardando_empenho,atendendo,concluida',
            'observacoes' => 'nullable|string',
        ]);

        if ($validated['contrato_id']) {
            $contrato = \App\Models\Contrato::find($validated['contrato_id']);
            if (!$contrato || $contrato->processo_id !== $processo->id) {
                return response()->json(['message' => 'Contrato inválido.'], 400);
            }
        }

        $validated['processo_id'] = $processo->id;
        $validated['saldo'] = $validated['valor'];

        $af = AutorizacaoFornecimento::create($validated);

        return response()->json($af, 201);
    }

    public function show(Processo $processo, AutorizacaoFornecimento $autorizacaoFornecimento)
    {
        if ($autorizacaoFornecimento->processo_id !== $processo->id) {
            return response()->json(['message' => 'AF não pertence a este processo.'], 404);
        }

        $autorizacaoFornecimento->load(['empenhos', 'contrato']);
        return response()->json($autorizacaoFornecimento);
    }

    public function update(Request $request, Processo $processo, AutorizacaoFornecimento $autorizacaoFornecimento)
    {
        if ($autorizacaoFornecimento->processo_id !== $processo->id) {
            return response()->json(['message' => 'AF não pertence a este processo.'], 404);
        }

        $validated = $request->validate([
            'contrato_id' => 'nullable|exists:contratos,id',
            'numero' => 'required|string|max:255',
            'data' => 'required|date',
            'valor' => 'required|numeric|min:0',
            'situacao' => 'required|in:aguardando_empenho,atendendo,concluida',
            'observacoes' => 'nullable|string',
        ]);

        if ($validated['contrato_id']) {
            $contrato = \App\Models\Contrato::find($validated['contrato_id']);
            if (!$contrato || $contrato->processo_id !== $processo->id) {
                return response()->json(['message' => 'Contrato inválido.'], 400);
            }
        }

        $valorAnterior = $autorizacaoFornecimento->valor;
        $autorizacaoFornecimento->update($validated);

        if ($validated['valor'] != $valorAnterior) {
            $autorizacaoFornecimento->atualizarSaldo();
        }

        return response()->json($autorizacaoFornecimento);
    }

    public function destroy(Processo $processo, AutorizacaoFornecimento $autorizacaoFornecimento)
    {
        if ($autorizacaoFornecimento->processo_id !== $processo->id) {
            return response()->json(['message' => 'AF não pertence a este processo.'], 404);
        }

        if ($autorizacaoFornecimento->empenhos()->count() > 0) {
            return response()->json([
                'message' => 'Não é possível excluir uma AF que possui empenhos vinculados.'
            ], 403);
        }

        $autorizacaoFornecimento->delete();

        return response()->json(null, 204);
    }
}

