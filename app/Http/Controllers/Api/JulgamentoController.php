<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Processo;
use App\Models\ProcessoItem;
use Illuminate\Http\Request;

class JulgamentoController extends Controller
{
    public function show(Processo $processo)
    {
        if ($processo->isEmExecucao()) {
            return response()->json([
                'message' => 'Não é possível visualizar julgamento de processos em execução.'
            ], 403);
        }

        $processo->load('itens');

        return response()->json([
            'processo' => [
                'id' => $processo->id,
                'numero_modalidade' => $processo->numero_modalidade,
            ],
            'itens' => $processo->itens->map(function($item) {
                return [
                    'id' => $item->id,
                    'numero_item' => $item->numero_item,
                    'especificacao_tecnica' => $item->especificacao_tecnica,
                    'status_item' => $item->status_item,
                    'valor_negociado' => $item->valor_negociado,
                    'chance_arremate' => $item->chance_arremate,
                    'chance_percentual' => $item->chance_percentual,
                    'lembretes' => $item->lembretes,
                ];
            }),
        ]);
    }

    public function update(Request $request, Processo $processo)
    {
        if ($processo->isEmExecucao()) {
            return response()->json([
                'message' => 'Não é possível editar julgamento de processos em execução.'
            ], 403);
        }

        $validated = $request->validate([
            'itens' => 'required|array',
            'itens.*.id' => 'required|exists:processo_itens,id',
            'itens.*.status_item' => 'required|in:pendente,aceito,aceito_habilitado,desclassificado,inabilitado',
            'itens.*.valor_negociado' => 'nullable|numeric|min:0',
            'itens.*.chance_arremate' => 'nullable|in:baixa,media,alta',
            'itens.*.chance_percentual' => 'nullable|integer|min:0|max:100',
            'itens.*.lembretes' => 'nullable|string',
        ]);

        foreach ($validated['itens'] as $itemData) {
            $item = ProcessoItem::find($itemData['id']);
            if ($item && $item->processo_id === $processo->id) {
                $item->update([
                    'status_item' => $itemData['status_item'],
                    'valor_negociado' => $itemData['valor_negociado'] ?? null,
                    'chance_arremate' => $itemData['chance_arremate'] ?? null,
                    'chance_percentual' => $itemData['chance_percentual'] ?? null,
                    'lembretes' => $itemData['lembretes'] ?? null,
                ]);
            }
        }

        $processo->load('itens');

        return response()->json([
            'message' => 'Julgamento atualizado com sucesso!',
            'processo' => $processo,
        ]);
    }
}

