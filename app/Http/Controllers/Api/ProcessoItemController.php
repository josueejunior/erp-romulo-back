<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProcessoItemResource;
use App\Models\Processo;
use App\Models\ProcessoItem;
use Illuminate\Http\Request;

class ProcessoItemController extends Controller
{
    public function index(Processo $processo)
    {
        $itens = $processo->itens()->with(['orcamentos', 'formacoesPreco'])->get();
        return ProcessoItemResource::collection($itens);
    }

    public function store(Request $request, Processo $processo)
    {
        if ($processo->isEmExecucao()) {
            return response()->json([
                'message' => 'Não é possível adicionar itens a processos em execução.'
            ], 403);
        }

        $validated = $request->validate([
            'numero_item' => 'required|integer|min:1',
            'quantidade' => 'required|numeric|min:0.01',
            'unidade' => 'required|string|max:50',
            'especificacao_tecnica' => 'required|string',
            'marca_modelo_referencia' => 'nullable|string|max:255',
            'exige_atestado' => 'boolean',
            'quantidade_minima_atestado' => 'nullable|integer|min:1|required_if:exige_atestado,1',
            'valor_estimado' => 'nullable|numeric|min:0',
            'observacoes' => 'nullable|string',
        ]);

        $validated['processo_id'] = $processo->id;
        $validated['exige_atestado'] = $request->has('exige_atestado');
        $validated['status_item'] = 'pendente';

        $item = ProcessoItem::create($validated);

        return new ProcessoItemResource($item);
    }

    public function show(Processo $processo, ProcessoItem $item)
    {
        if ($item->processo_id !== $processo->id) {
            return response()->json(['message' => 'Item não pertence a este processo.'], 404);
        }

        $item->load(['orcamentos', 'formacoesPreco']);
        return new ProcessoItemResource($item);
    }

    public function update(Request $request, Processo $processo, ProcessoItem $item)
    {
        if ($item->processo_id !== $processo->id) {
            return response()->json(['message' => 'Item não pertence a este processo.'], 404);
        }

        if ($processo->isEmExecucao()) {
            return response()->json([
                'message' => 'Não é possível editar itens de processos em execução.'
            ], 403);
        }

        $validated = $request->validate([
            'numero_item' => 'required|integer|min:1',
            'quantidade' => 'required|numeric|min:0.01',
            'unidade' => 'required|string|max:50',
            'especificacao_tecnica' => 'required|string',
            'marca_modelo_referencia' => 'nullable|string|max:255',
            'exige_atestado' => 'boolean',
            'quantidade_minima_atestado' => 'nullable|integer|min:1|required_if:exige_atestado,1',
            'valor_estimado' => 'nullable|numeric|min:0',
            'observacoes' => 'nullable|string',
        ]);

        $validated['exige_atestado'] = $request->has('exige_atestado');

        $item->update($validated);

        return new ProcessoItemResource($item);
    }

    public function destroy(Processo $processo, ProcessoItem $item)
    {
        if ($item->processo_id !== $processo->id) {
            return response()->json(['message' => 'Item não pertence a este processo.'], 404);
        }

        if ($processo->isEmExecucao()) {
            return response()->json([
                'message' => 'Não é possível excluir itens de processos em execução.'
            ], 403);
        }

        $item->delete();

        return response()->json(null, 204);
    }
}




