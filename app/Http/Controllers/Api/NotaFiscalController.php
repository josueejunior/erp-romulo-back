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
        $notasFiscais = $processo->notasFiscais()->with(['empenho', 'fornecedor'])->get();
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
            'tipo' => 'required|in:entrada,saida',
            'numero' => 'required|string|max:255',
            'serie' => 'nullable|string|max:10',
            'data_emissao' => 'required|date',
            'fornecedor_id' => 'nullable|exists:fornecedores,id',
            'valor' => 'required|numeric|min:0',
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

        $validated['processo_id'] = $processo->id;

        if ($request->hasFile('arquivo')) {
            $arquivo = $request->file('arquivo');
            $nomeArquivo = time() . '_' . $arquivo->getClientOriginalName();
            $arquivo->storeAs('notas-fiscais', $nomeArquivo, 'public');
            $validated['arquivo'] = $nomeArquivo;
        }

        $notaFiscal = NotaFiscal::create($validated);
        $notaFiscal->load(['empenho', 'fornecedor']);

        return response()->json($notaFiscal, 201);
    }

    public function show(Processo $processo, NotaFiscal $notaFiscal)
    {
        if ($notaFiscal->processo_id !== $processo->id) {
            return response()->json(['message' => 'Nota fiscal não pertence a este processo.'], 404);
        }

        $notaFiscal->load(['empenho', 'fornecedor']);
        return response()->json($notaFiscal);
    }

    public function update(Request $request, Processo $processo, NotaFiscal $notaFiscal)
    {
        if ($notaFiscal->processo_id !== $processo->id) {
            return response()->json(['message' => 'Nota fiscal não pertence a este processo.'], 404);
        }

        $validated = $request->validate([
            'empenho_id' => 'nullable|exists:empenhos,id',
            'tipo' => 'required|in:entrada,saida',
            'numero' => 'required|string|max:255',
            'serie' => 'nullable|string|max:10',
            'data_emissao' => 'required|date',
            'fornecedor_id' => 'nullable|exists:fornecedores,id',
            'valor' => 'required|numeric|min:0',
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
        $notaFiscal->load(['empenho', 'fornecedor']);

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






