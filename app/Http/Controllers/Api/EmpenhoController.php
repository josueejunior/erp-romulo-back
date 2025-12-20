<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\Processo;
use App\Models\Empenho;
use Illuminate\Http\Request;

class EmpenhoController extends BaseApiController
{
    public function index(Processo $processo)
    {
        $empresa = $this->getEmpresaAtivaOrFail();
        
        if ($processo->empresa_id !== $empresa->id) {
            return response()->json([
                'message' => 'Processo não encontrado ou não pertence à empresa ativa.'
            ], 404);
        }
        
        $empenhos = $processo->empenhos()->where('empresa_id', $empresa->id)->with(['contrato', 'autorizacaoFornecimento'])->get();
        return response()->json($empenhos);
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
                'message' => 'Empenhos só podem ser criados para processos em execução.'
            ], 403);
        }

        $validated = $request->validate([
            'contrato_id' => 'nullable|exists:contratos,id',
            'autorizacao_fornecimento_id' => 'nullable|exists:autorizacoes_fornecimento,id',
            'numero' => 'required|string|max:255',
            'data' => 'required|date',
            'valor' => 'required|numeric|min:0',
            'data_entrega' => 'nullable|date',
            'observacoes' => 'nullable|string',
            'numero_cte' => 'nullable|string|max:255',
        ]);

        if ($validated['contrato_id']) {
            $contrato = \App\Models\Contrato::find($validated['contrato_id']);
            if (!$contrato || $contrato->processo_id !== $processo->id) {
                return response()->json(['message' => 'Contrato inválido.'], 400);
            }
        }

        if ($validated['autorizacao_fornecimento_id']) {
            $af = \App\Models\AutorizacaoFornecimento::find($validated['autorizacao_fornecimento_id']);
            if (!$af || $af->processo_id !== $processo->id) {
                return response()->json(['message' => 'Autorização de Fornecimento inválida.'], 400);
            }
        }

        $validated['empresa_id'] = $empresa->id;
        $validated['processo_id'] = $processo->id;
        $validated['concluido'] = false;

        $empenho = Empenho::create($validated);

        if ($empenho->contrato_id) {
            $empenho->contrato->atualizarSaldo();
        }
        if ($empenho->autorizacao_fornecimento_id) {
            $empenho->autorizacaoFornecimento->atualizarSaldo();
        }

        $empenho->load(['contrato', 'autorizacaoFornecimento']);

        return response()->json($empenho, 201);
    }

    public function show(Processo $processo, Empenho $empenho)
    {
        $empresa = $this->getEmpresaAtivaOrFail();
        
        if ($processo->empresa_id !== $empresa->id || $empenho->empresa_id !== $empresa->id) {
            return response()->json(['message' => 'Empenho não encontrado ou não pertence à empresa ativa.'], 404);
        }
        
        if ($empenho->processo_id !== $processo->id) {
            return response()->json(['message' => 'Empenho não pertence a este processo.'], 404);
        }

        $empenho->load(['contrato', 'autorizacaoFornecimento', 'notasFiscais']);
        return response()->json($empenho);
    }

    public function update(Request $request, Processo $processo, Empenho $empenho)
    {
        $empresa = $this->getEmpresaAtivaOrFail();
        
        if ($processo->empresa_id !== $empresa->id || $empenho->empresa_id !== $empresa->id) {
            return response()->json(['message' => 'Empenho não encontrado ou não pertence à empresa ativa.'], 404);
        }
        
        if ($empenho->processo_id !== $processo->id) {
            return response()->json(['message' => 'Empenho não pertence a este processo.'], 404);
        }

        $validated = $request->validate([
            'contrato_id' => 'nullable|exists:contratos,id',
            'autorizacao_fornecimento_id' => 'nullable|exists:autorizacoes_fornecimento,id',
            'numero' => 'required|string|max:255',
            'data' => 'required|date',
            'valor' => 'required|numeric|min:0',
            'concluido' => 'boolean',
            'data_entrega' => 'nullable|date',
            'observacoes' => 'nullable|string',
            'numero_cte' => 'nullable|string|max:255',
        ]);

        if ($validated['contrato_id']) {
            $contrato = \App\Models\Contrato::find($validated['contrato_id']);
            if (!$contrato || $contrato->processo_id !== $processo->id) {
                return response()->json(['message' => 'Contrato inválido.'], 400);
            }
        }

        if ($validated['autorizacao_fornecimento_id']) {
            $af = \App\Models\AutorizacaoFornecimento::find($validated['autorizacao_fornecimento_id']);
            if (!$af || $af->processo_id !== $processo->id) {
                return response()->json(['message' => 'Autorização de Fornecimento inválida.'], 400);
            }
        }

        $valorAnterior = $empenho->valor;
        $contratoAnterior = $empenho->contrato_id;
        $afAnterior = $empenho->autorizacao_fornecimento_id;

        $validated['concluido'] = $request->has('concluido');
        if ($validated['concluido'] && !$empenho->concluido) {
            $validated['data_entrega'] = $validated['data_entrega'] ?? now();
        }

        DB::transaction(function () use ($empenho, $validated, $contratoAnterior, $afAnterior) {
            $empenho->update($validated);

            // Atualizar saldos
            if ($contratoAnterior) {
                \App\Models\Contrato::find($contratoAnterior)?->atualizarSaldo();
            }
            if ($afAnterior) {
                \App\Models\AutorizacaoFornecimento::find($afAnterior)?->atualizarSaldo();
            }
            if ($empenho->contrato_id) {
                $empenho->contrato->atualizarSaldo();
            }
            if ($empenho->autorizacao_fornecimento_id) {
                $empenho->autorizacaoFornecimento->atualizarSaldo();
            }
        });

        $empenho->load(['contrato', 'autorizacaoFornecimento']);

        return response()->json($empenho);
    }

    public function destroy(Processo $processo, Empenho $empenho)
    {
        $empresa = $this->getEmpresaAtivaOrFail();
        
        if ($processo->empresa_id !== $empresa->id || $empenho->empresa_id !== $empresa->id) {
            return response()->json(['message' => 'Empenho não encontrado ou não pertence à empresa ativa.'], 404);
        }
        
        if ($empenho->processo_id !== $processo->id) {
            return response()->json(['message' => 'Empenho não pertence a este processo.'], 404);
        }

        $contratoId = $empenho->contrato_id;
        $afId = $empenho->autorizacao_fornecimento_id;

        $empenho->forceDelete();

        if ($contratoId) {
            \App\Models\Contrato::find($contratoId)?->atualizarSaldo();
        }
        if ($afId) {
            \App\Models\AutorizacaoFornecimento::find($afId)?->atualizarSaldo();
        }

        return response()->json(null, 204);
    }
}








