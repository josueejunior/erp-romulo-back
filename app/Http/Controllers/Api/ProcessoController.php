<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProcessoResource;
use App\Models\Processo;
use App\Models\Orgao;
use Illuminate\Http\Request;

class ProcessoController extends Controller
{
    public function index(Request $request)
    {
        $query = Processo::with(['orgao', 'setor']);

        if ($request->status) {
            $query->where('status', $request->status);
        }

        if ($request->search) {
            $query->where(function($q) use ($request) {
                $q->where('numero_modalidade', 'like', "%{$request->search}%")
                  ->orWhere('objeto_resumido', 'like', "%{$request->search}%");
            });
        }

        $processos = $query->orderBy('created_at', 'desc')->paginate(15);

        return ProcessoResource::collection($processos);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'orgao_id' => 'required|exists:orgaos,id',
            'setor_id' => 'required|exists:setors,id',
            'modalidade' => 'required|in:dispensa,pregao',
            'numero_modalidade' => 'required|string',
            'numero_processo_administrativo' => 'nullable|string',
            'srp' => 'boolean',
            'objeto_resumido' => 'required|string',
            'data_hora_sessao_publica' => 'required|date',
            'endereco_entrega' => 'nullable|string',
            'forma_prazo_entrega' => 'nullable|string',
            'prazo_pagamento' => 'nullable|string',
            'validade_proposta' => 'nullable|string',
            'tipo_selecao_fornecedor' => 'nullable|string',
            'tipo_disputa' => 'nullable|string',
            'observacoes' => 'nullable|string',
        ]);

        // Com Tenancy, não precisamos mais de empresa_id - cada tenant tem seu próprio banco
        $validated['status'] = 'participacao';
        $validated['srp'] = $request->has('srp');

        $processo = Processo::create($validated);

        return new ProcessoResource($processo->load(['orgao', 'setor']));
    }

    public function show(Processo $processo)
    {
        $processo->load([
            'orgao',
            'setor',
            'itens.orcamentos.fornecedor',
            'itens.formacoesPreco',
            'documentos.documentoHabilitacao',
            'contratos',
            'autorizacoesFornecimento',
            'empenhos',
        ]);

        return new ProcessoResource($processo);
    }

    public function update(Request $request, Processo $processo)
    {
        if ($processo->isEmExecucao()) {
            return response()->json([
                'message' => 'Processos em execução não podem ser editados.'
            ], 403);
        }

        $validated = $request->validate([
            'orgao_id' => 'required|exists:orgaos,id',
            'setor_id' => 'required|exists:setors,id',
            'modalidade' => 'required|in:dispensa,pregao',
            'numero_modalidade' => 'required|string',
            'numero_processo_administrativo' => 'nullable|string',
            'srp' => 'boolean',
            'objeto_resumido' => 'required|string',
            'data_hora_sessao_publica' => 'required|date',
            'endereco_entrega' => 'nullable|string',
            'forma_prazo_entrega' => 'nullable|string',
            'prazo_pagamento' => 'nullable|string',
            'validade_proposta' => 'nullable|string',
            'tipo_selecao_fornecedor' => 'nullable|string',
            'tipo_disputa' => 'nullable|string',
            'observacoes' => 'nullable|string',
        ]);

        $validated['srp'] = $request->has('srp');

        $processo->update($validated);

        return new ProcessoResource($processo->load(['orgao', 'setor']));
    }

    public function destroy(Processo $processo)
    {
        if ($processo->isEmExecucao()) {
            return response()->json([
                'message' => 'Processos em execução não podem ser excluídos.'
            ], 403);
        }

        $processo->delete();

        return response()->json(null, 204);
    }

    public function marcarVencido(Processo $processo)
    {
        $processo->status = 'execucao';
        $processo->save();

        return new ProcessoResource($processo);
    }

    public function marcarPerdido(Processo $processo)
    {
        $processo->status = 'perdido';
        $processo->save();

        return new ProcessoResource($processo);
    }
}
