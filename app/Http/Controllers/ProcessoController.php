<?php

namespace App\Http\Controllers;

use App\Models\Processo;
use App\Models\Orgao;
use App\Models\Setor;
use Illuminate\Http\Request;

class ProcessoController extends Controller
{
    public function index(Request $request)
    {
        $empresa = $this->getEmpresaAtivaOrFail();

        $query = Processo::where('empresa_id', $empresa->id)
            ->with(['orgao', 'setor']);

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

        return view('processos.index', compact('processos'));
    }

    public function create()
    {
        $empresa = $this->getEmpresaAtivaOrFail();
        
        if (!auth()->user()->pode('criar-processo')) {
            abort(403, 'Você não tem permissão para criar processos.');
        }
        
        $orgaos = Orgao::with('setors')->get();

        return view('processos.create', compact('orgaos'));
    }

    public function store(Request $request)
    {
        $empresa = $this->getEmpresaAtivaOrFail();

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

        $validated['empresa_id'] = $empresa->id;
        $validated['status'] = 'participacao';
        $validated['srp'] = $request->has('srp');

        $processo = Processo::create($validated);

        return redirect()->route('processos.show', $processo)
            ->with('success', 'Processo criado com sucesso!');
    }

    public function show(Processo $processo)
    {
        $empresa = $this->getEmpresaAtivaOrFail();

        if ($processo->empresa_id !== $empresa->id) {
            abort(403);
        }

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

        return view('processos.show', compact('processo'));
    }

    public function edit(Processo $processo)
    {
        $empresa = $this->getEmpresaAtivaOrFail();

        if ($processo->empresa_id !== $empresa->id) {
            abort(403);
        }

        if ($processo->isEmExecucao()) {
            return redirect()->route('processos.show', $processo)
                ->with('error', 'Processos em execução não podem ser editados.');
        }

        $orgaos = Orgao::with('setors')->get();

        return view('processos.edit', compact('processo', 'orgaos'));
    }

    public function update(Request $request, Processo $processo)
    {
        $empresa = $this->getEmpresaAtivaOrFail();

        if ($processo->empresa_id !== $empresa->id) {
            abort(403);
        }

        if ($processo->isEmExecucao()) {
            return redirect()->route('processos.show', $processo)
                ->with('error', 'Processos em execução não podem ser editados.');
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

        return redirect()->route('processos.show', $processo)
            ->with('success', 'Processo atualizado com sucesso!');
    }

    public function destroy(Processo $processo)
    {
        $empresa = $this->getEmpresaAtivaOrFail();

        if ($processo->empresa_id !== $empresa->id) {
            abort(403);
        }

        if ($processo->isEmExecucao()) {
            return redirect()->route('processos.show', $processo)
                ->with('error', 'Processos em execução não podem ser excluídos.');
        }

        $processo->delete();

        return redirect()->route('processos.index')
            ->with('success', 'Processo excluído com sucesso!');
    }

    public function marcarVencido(Processo $processo)
    {
        $empresa = $this->getEmpresaAtivaOrFail();

        if ($processo->empresa_id !== $empresa->id) {
            abort(403);
        }

        $processo->status = 'execucao';
        $processo->save();

        return redirect()->route('processos.show', $processo)
            ->with('success', 'Processo marcado como vencido e movido para execução!');
    }

    public function marcarPerdido(Processo $processo)
    {
        $empresa = $this->getEmpresaAtivaOrFail();

        if ($processo->empresa_id !== $empresa->id) {
            abort(403);
        }

        $processo->status = 'perdido';
        $processo->save();

        return redirect()->route('processos.show', $processo)
            ->with('success', 'Processo marcado como perdido!');
    }
}
