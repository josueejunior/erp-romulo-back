<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\Processo;
use App\Models\AutorizacaoFornecimento;
use Illuminate\Http\Request;

class AutorizacaoFornecimentoController extends BaseApiController
{
    public function index(Processo $processo)
    {
        $empresa = $this->getEmpresaAtivaOrFail();
        
        if ($processo->empresa_id !== $empresa->id) {
            return response()->json([
                'message' => 'Processo não encontrado ou não pertence à empresa ativa.'
            ], 404);
        }
        
        $afs = $processo->autorizacoesFornecimento()->with(['empenhos', 'contrato'])->get();
        return response()->json($afs);
    }

    public function store(Request $request, Processo $processo)
    {
        $empresa = $this->getEmpresaAtivaOrFail();
        
        if ($processo->empresa_id !== $empresa->id) {
            return response()->json([
                'message' => 'Processo não encontrado ou não pertence à empresa ativa.'
            ], 404);
        }
        
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
            'numero_cte' => 'nullable|string|max:255',
        ]);

        if ($validated['contrato_id']) {
            $contrato = \App\Models\Contrato::find($validated['contrato_id']);
            if (!$contrato || $contrato->processo_id !== $processo->id) {
                return response()->json(['message' => 'Contrato inválido.'], 400);
            }
        }

        $validated['empresa_id'] = $empresa->id;
        $validated['processo_id'] = $processo->id;
        $validated['saldo'] = $validated['valor'];

        $af = AutorizacaoFornecimento::create($validated);

        return response()->json($af, 201);
    }

    public function show(Processo $processo, AutorizacaoFornecimento $autorizacaoFornecimento)
    {
        $empresa = $this->getEmpresaAtivaOrFail();
        
        if ($processo->empresa_id !== $empresa->id || $autorizacaoFornecimento->empresa_id !== $empresa->id) {
            return response()->json(['message' => 'Autorização de Fornecimento não encontrada ou não pertence à empresa ativa.'], 404);
        }
        
        if ($autorizacaoFornecimento->processo_id !== $processo->id) {
            return response()->json(['message' => 'AF não pertence a este processo.'], 404);
        }

        $autorizacaoFornecimento->load(['empenhos', 'contrato']);
        return response()->json($autorizacaoFornecimento);
    }

    public function update(Request $request, Processo $processo, AutorizacaoFornecimento $autorizacaoFornecimento)
    {
        $empresa = $this->getEmpresaAtivaOrFail();
        
        if ($processo->empresa_id !== $empresa->id || $autorizacaoFornecimento->empresa_id !== $empresa->id) {
            return response()->json(['message' => 'Autorização de Fornecimento não encontrada ou não pertence à empresa ativa.'], 404);
        }
        
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
            'numero_cte' => 'nullable|string|max:255',
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
        $empresa = $this->getEmpresaAtivaOrFail();
        
        if ($processo->empresa_id !== $empresa->id || $autorizacaoFornecimento->empresa_id !== $empresa->id) {
            return response()->json(['message' => 'Autorização de Fornecimento não encontrada ou não pertence à empresa ativa.'], 404);
        }
        
        if ($autorizacaoFornecimento->processo_id !== $processo->id) {
            return response()->json(['message' => 'AF não pertence a este processo.'], 404);
        }

        if ($autorizacaoFornecimento->empenhos()->count() > 0) {
            return response()->json([
                'message' => 'Não é possível excluir uma AF que possui empenhos vinculados.'
            ], 403);
        }

        $autorizacaoFornecimento->forceDelete();

        return response()->json(null, 204);
    }
}




