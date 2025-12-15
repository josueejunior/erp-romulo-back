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
        $itens = $processo->itens()->with([
            'orcamentos.fornecedor',
            'orcamentos.transportadora',
            'formacoesPreco',
            'vinculos.contrato',
            'vinculos.autorizacaoFornecimento',
            'vinculos.empenho',
        ])->get();
        
        // Atualizar valores financeiros de cada item
        foreach ($itens as $item) {
            $item->atualizarValoresFinanceiros();
        }
        
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
            'codigo_interno' => 'nullable|string|max:100',
            'quantidade' => 'required|numeric|min:0.01',
            'unidade' => 'required|string|max:50',
            'especificacao_tecnica' => 'required|string',
            'marca_modelo_referencia' => 'nullable|string|max:255',
            'observacoes_edital' => 'nullable|string',
            'exige_atestado' => 'boolean',
            'quantidade_minima_atestado' => 'nullable|integer|min:1|required_if:exige_atestado,1',
            'quantidade_atestado_cap_tecnica' => 'nullable|integer|min:0',
            'valor_estimado' => 'nullable|numeric|min:0',
            'valor_estimado_total' => 'nullable|numeric|min:0',
            'fonte_valor' => 'nullable|in:edital,pesquisa',
            'observacoes' => 'nullable|string',
        ]);

        $validated['processo_id'] = $processo->id;
        $validated['exige_atestado'] = $request->has('exige_atestado');
        $validated['status_item'] = 'pendente';
        
        // Calcular valor estimado total se não fornecido
        if (!isset($validated['valor_estimado_total']) && isset($validated['valor_estimado']) && isset($validated['quantidade'])) {
            $validated['valor_estimado_total'] = $validated['valor_estimado'] * $validated['quantidade'];
        }

        $item = ProcessoItem::create($validated);

        return new ProcessoItemResource($item);
    }

    public function show(Processo $processo, ProcessoItem $item)
    {
        if ($item->processo_id !== $processo->id) {
            return response()->json(['message' => 'Item não pertence a este processo.'], 404);
        }

        $item->load([
            'orcamentos.fornecedor',
            'orcamentos.transportadora',
            'formacoesPreco',
            'vinculos.contrato',
            'vinculos.autorizacaoFornecimento',
            'vinculos.empenho',
        ]);
        
        // Atualizar valores financeiros
        $item->atualizarValoresFinanceiros();
        
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
            'numero_item' => 'sometimes|required|integer|min:1',
            'codigo_interno' => 'nullable|string|max:100',
            'quantidade' => 'sometimes|required|numeric|min:0.01',
            'unidade' => 'sometimes|required|string|max:50',
            'especificacao_tecnica' => 'sometimes|required|string',
            'marca_modelo_referencia' => 'nullable|string|max:255',
            'observacoes_edital' => 'nullable|string',
            'exige_atestado' => 'boolean',
            'quantidade_minima_atestado' => 'nullable|integer|min:1|required_if:exige_atestado,1',
            'quantidade_atestado_cap_tecnica' => 'nullable|integer|min:0',
            'valor_estimado' => 'nullable|numeric|min:0',
            'valor_estimado_total' => 'nullable|numeric|min:0',
            'fonte_valor' => 'nullable|in:edital,pesquisa',
            'valor_final_sessao' => 'nullable|numeric|min:0',
            'data_disputa' => 'nullable|date',
            'valor_negociado' => 'nullable|numeric|min:0',
            'classificacao' => 'nullable|integer|min:1',
            'status_item' => 'nullable|in:pendente,aceito,aceito_habilitado,desclassificado,inabilitado',
            'situacao_final' => 'nullable|in:vencido,perdido',
            'chance_arremate' => 'nullable|in:baixa,media,alta',
            'chance_percentual' => 'nullable|integer|min:0|max:100',
            'lembretes' => 'nullable|string',
            'observacoes' => 'nullable|string',
        ]);

        if (isset($validated['exige_atestado'])) {
            $validated['exige_atestado'] = $request->boolean('exige_atestado');
        }

        // Recalcular valor estimado total se quantidade ou valor unitário mudou
        if (isset($validated['quantidade']) || isset($validated['valor_estimado'])) {
            $quantidade = $validated['quantidade'] ?? $item->quantidade;
            $valorUnitario = $validated['valor_estimado'] ?? $item->valor_estimado;
            if ($quantidade && $valorUnitario) {
                $validated['valor_estimado_total'] = $quantidade * $valorUnitario;
            }
        }

        $item->update($validated);
        
        // Atualizar valores financeiros após atualização
        $item->atualizarValoresFinanceiros();

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




