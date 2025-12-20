<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Processo;
use App\Models\Contrato;
use Illuminate\Http\Request;

class ContratoController extends Controller
{
    public function index(Processo $processo)
    {
        $contratos = $processo->contratos()->with(['empenhos', 'autorizacoesFornecimento'])->get();
        return response()->json($contratos);
    }

    public function store(Request $request, Processo $processo)
    {
        if (!$processo->isEmExecucao()) {
            return response()->json([
                'message' => 'Contratos só podem ser criados para processos em execução.'
            ], 403);
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
        $validated['saldo'] = $validated['valor_total'];

        $contrato = Contrato::create($validated);

        return response()->json($contrato, 201);
    }

    public function show(Processo $processo, Contrato $contrato)
    {
        if ($contrato->processo_id !== $processo->id) {
            return response()->json(['message' => 'Contrato não pertence a este processo.'], 404);
        }

        $contrato->load(['empenhos', 'autorizacoesFornecimento']);
        return response()->json($contrato);
    }

    public function update(Request $request, Processo $processo, Contrato $contrato)
    {
        if ($contrato->processo_id !== $processo->id) {
            return response()->json(['message' => 'Contrato não pertence a este processo.'], 404);
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

        if ($validated['valor_total'] != $valorTotalAnterior) {
            $contrato->atualizarSaldo();
        }

        return response()->json($contrato);
    }

    public function destroy(Processo $processo, Contrato $contrato)
    {
        if ($contrato->processo_id !== $processo->id) {
            return response()->json(['message' => 'Contrato não pertence a este processo.'], 404);
        }

        if ($contrato->empenhos()->count() > 0) {
            return response()->json([
                'message' => 'Não é possível excluir um contrato que possui empenhos vinculados.'
            ], 403);
        }

        $contrato->delete();

        return response()->json(null, 204);
    }
}






