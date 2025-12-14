<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Processo;
use App\Models\ProcessoItem;
use Illuminate\Http\Request;

class DisputaController extends Controller
{
    public function show(Processo $processo)
    {
        if ($processo->isEmExecucao()) {
            return response()->json([
                'message' => 'Não é possível visualizar disputa de processos em execução.'
            ], 403);
        }

        $processo->load('itens');

        return response()->json([
            'processo' => [
                'id' => $processo->id,
                'numero_modalidade' => $processo->numero_modalidade,
                'data_hora_sessao_publica' => $processo->data_hora_sessao_publica?->format('Y-m-d H:i:s'),
            ],
            'itens' => $processo->itens->map(function($item) {
                return [
                    'id' => $item->id,
                    'numero_item' => $item->numero_item,
                    'especificacao_tecnica' => $item->especificacao_tecnica,
                    'quantidade' => $item->quantidade,
                    'unidade' => $item->unidade,
                    'valor_estimado' => $item->valor_estimado,
                    'valor_final_sessao' => $item->valor_final_sessao,
                    'classificacao' => $item->classificacao,
                ];
            }),
        ]);
    }

    public function update(Request $request, Processo $processo)
    {
        if ($processo->isEmExecucao()) {
            return response()->json([
                'message' => 'Não é possível editar disputa de processos em execução.'
            ], 403);
        }

        $validated = $request->validate([
            'itens' => 'required|array',
            'itens.*.id' => 'required|exists:processo_itens,id',
            'itens.*.valor_final_sessao' => 'nullable|numeric|min:0',
            'itens.*.classificacao' => 'nullable|integer|min:1',
        ]);

        foreach ($validated['itens'] as $itemData) {
            $item = ProcessoItem::find($itemData['id']);
            if ($item && $item->processo_id === $processo->id) {
                $item->update([
                    'valor_final_sessao' => $itemData['valor_final_sessao'] ?? null,
                    'classificacao' => $itemData['classificacao'] ?? null,
                ]);
            }
        }

        $processo->load('itens');

        return response()->json([
            'message' => 'Disputa registrada com sucesso!',
            'processo' => $processo,
        ]);
    }
}

