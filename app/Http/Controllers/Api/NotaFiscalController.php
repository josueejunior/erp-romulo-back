<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\Processo;
use App\Models\NotaFiscal;
use App\Rules\ValidarVinculoProcesso;
use App\Rules\ValidarValorTotal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;

class NotaFiscalController extends BaseApiController
{
    public function index(Processo $processo)
    {
        $empresa = $this->getEmpresaAtivaOrFail();
        
        if ($processo->empresa_id !== $empresa->id) {
            return response()->json([
                'message' => 'Processo não encontrado ou não pertence à empresa ativa.'
            ], 404);
        }
        
        $notasFiscais = $processo->notasFiscais()->where('empresa_id', $empresa->id)
            ->with(['empenho', 'contrato', 'autorizacaoFornecimento', 'fornecedor'])
            ->get();
        return response()->json($notasFiscais);
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
                'message' => 'Notas fiscais só podem ser criadas para processos em execução.'
            ], 403);
        }

        // Validar que pelo menos um vínculo existe
        $temVinculo = $request->has('empenho_id') || 
                      $request->has('contrato_id') || 
                      $request->has('autorizacao_fornecimento_id');
        
        if (!$temVinculo) {
            return response()->json([
                'message' => 'Nota fiscal deve estar vinculada a um Empenho, Contrato ou Autorização de Fornecimento.'
            ], 400);
        }

        $validated = $request->validate([
            'empenho_id' => [
                'nullable',
                'exists:empenhos,id',
                new ValidarVinculoProcesso($processo->id, 'empenho')
            ],
            'contrato_id' => [
                'nullable',
                'exists:contratos,id',
                new ValidarVinculoProcesso($processo->id, 'contrato')
            ],
            'autorizacao_fornecimento_id' => [
                'nullable',
                'exists:autorizacoes_fornecimento,id',
                new ValidarVinculoProcesso($processo->id, 'af')
            ],
            'tipo' => 'required|in:entrada,saida',
            'numero' => 'required|string|max:255',
            'serie' => 'nullable|string|max:10',
            'data_emissao' => 'required|date',
            'fornecedor_id' => 'nullable|exists:fornecedores,id',
            'transportadora' => 'nullable|string|max:255',
            'numero_cte' => 'nullable|string|max:255',
            'data_entrega_prevista' => 'nullable|date',
            'data_entrega_realizada' => 'nullable|date',
            'situacao_logistica' => 'nullable|in:aguardando_envio,em_transito,entregue,atrasada',
            'valor' => 'required|numeric|min:0',
            'custo_produto' => 'nullable|numeric|min:0',
            'custo_frete' => 'nullable|numeric|min:0',
            'custo_total' => [
                'nullable',
                'numeric|min:0',
                new ValidarValorTotal($request->input('custo_produto'), $request->input('custo_frete'))
            ],
            'comprovante_pagamento' => 'nullable|string|max:255',
            'arquivo' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
            'situacao' => 'required|in:pendente,paga,cancelada',
            'data_pagamento' => 'nullable|date',
            'observacoes' => 'nullable|string',
        ]);

        $validated['processo_id'] = $processo->id;
        
        // Calcular custo_total automaticamente se não fornecido
        if (!isset($validated['custo_total']) || $validated['custo_total'] === null) {
            $validated['custo_total'] = ($validated['custo_produto'] ?? 0) + ($validated['custo_frete'] ?? 0);
        }

        $notaFiscal = DB::transaction(function () use ($validated, $request) {
            if ($request->hasFile('arquivo')) {
                $arquivo = $request->file('arquivo');
                $nomeArquivo = time() . '_' . $arquivo->getClientOriginalName();
                $arquivo->storeAs('notas-fiscais', $nomeArquivo, 'public');
                $validated['arquivo'] = $nomeArquivo;
            }

            $validated['empresa_id'] = $empresa->id;
            $notaFiscal = NotaFiscal::create($validated);
            
            // Atualizar saldo do documento vinculado (será feito pelo Observer também)
            if ($notaFiscal->contrato_id && $notaFiscal->contrato) {
                $notaFiscal->contrato->atualizarSaldo();
            }
            
            if ($notaFiscal->autorizacao_fornecimento_id && $notaFiscal->autorizacaoFornecimento) {
                $notaFiscal->autorizacaoFornecimento->atualizarSaldo();
            }
            
            return $notaFiscal;
        });

        $notaFiscal->load(['empenho', 'contrato', 'autorizacaoFornecimento', 'fornecedor']);

        return response()->json($notaFiscal, 201);
    }

    public function show(Processo $processo, NotaFiscal $notaFiscal)
    {
        $empresa = $this->getEmpresaAtivaOrFail();
        
        if ($processo->empresa_id !== $empresa->id || $notaFiscal->empresa_id !== $empresa->id) {
            return response()->json(['message' => 'Nota fiscal não encontrada ou não pertence à empresa ativa.'], 404);
        }
        
        if ($notaFiscal->processo_id !== $processo->id) {
            return response()->json(['message' => 'Nota fiscal não pertence a este processo.'], 404);
        }

        $notaFiscal->load(['empenho', 'contrato', 'autorizacaoFornecimento', 'fornecedor']);
        return response()->json($notaFiscal);
    }

    public function update(Request $request, Processo $processo, NotaFiscal $notaFiscal)
    {
        $empresa = $this->getEmpresaAtivaOrFail();
        
        if ($processo->empresa_id !== $empresa->id || $notaFiscal->empresa_id !== $empresa->id) {
            return response()->json(['message' => 'Nota fiscal não encontrada ou não pertence à empresa ativa.'], 404);
        }
        
        if ($notaFiscal->processo_id !== $processo->id) {
            return response()->json(['message' => 'Nota fiscal não pertence a este processo.'], 404);
        }

        $validated = $request->validate([
            'empenho_id' => [
                'nullable',
                'exists:empenhos,id',
                new ValidarVinculoProcesso($processo->id, 'empenho')
            ],
            'contrato_id' => [
                'nullable',
                'exists:contratos,id',
                new ValidarVinculoProcesso($processo->id, 'contrato')
            ],
            'autorizacao_fornecimento_id' => [
                'nullable',
                'exists:autorizacoes_fornecimento,id',
                new ValidarVinculoProcesso($processo->id, 'af')
            ],
            'tipo' => 'required|in:entrada,saida',
            'numero' => 'required|string|max:255',
            'serie' => 'nullable|string|max:10',
            'data_emissao' => 'required|date',
            'fornecedor_id' => 'nullable|exists:fornecedores,id',
            'transportadora' => 'nullable|string|max:255',
            'numero_cte' => 'nullable|string|max:255',
            'data_entrega_prevista' => 'nullable|date',
            'data_entrega_realizada' => 'nullable|date',
            'situacao_logistica' => 'nullable|in:aguardando_envio,em_transito,entregue,atrasada',
            'valor' => 'required|numeric|min:0',
            'custo_produto' => 'nullable|numeric|min:0',
            'custo_frete' => 'nullable|numeric|min:0',
            'custo_total' => [
                'nullable',
                'numeric|min:0',
                new ValidarValorTotal($request->input('custo_produto'), $request->input('custo_frete'))
            ],
            'comprovante_pagamento' => 'nullable|string|max:255',
            'arquivo' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
            'situacao' => 'required|in:pendente,paga,cancelada',
            'data_pagamento' => 'nullable|date',
            'observacoes' => 'nullable|string',
        ]);

        // Calcular custo_total automaticamente se não fornecido
        if (!isset($validated['custo_total']) || $validated['custo_total'] === null) {
            $validated['custo_total'] = ($validated['custo_produto'] ?? 0) + ($validated['custo_frete'] ?? 0);
        }

        DB::transaction(function () use ($notaFiscal, $validated, $request) {
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
            
            // Atualizar saldo do documento vinculado
            if ($notaFiscal->contrato_id && $notaFiscal->contrato) {
                $notaFiscal->contrato->atualizarSaldo();
            }
            
            if ($notaFiscal->autorizacao_fornecimento_id && $notaFiscal->autorizacaoFornecimento) {
                $notaFiscal->autorizacaoFornecimento->atualizarSaldo();
            }
        });

        $notaFiscal->load(['empenho', 'contrato', 'autorizacaoFornecimento', 'fornecedor']);

        return response()->json($notaFiscal);
    }

    public function destroy(Processo $processo, NotaFiscal $notaFiscal)
    {
        $empresa = $this->getEmpresaAtivaOrFail();
        
        if ($processo->empresa_id !== $empresa->id || $notaFiscal->empresa_id !== $empresa->id) {
            return response()->json(['message' => 'Nota fiscal não encontrada ou não pertence à empresa ativa.'], 404);
        }
        
        if ($notaFiscal->processo_id !== $processo->id) {
            return response()->json(['message' => 'Nota fiscal não pertence a este processo.'], 404);
        }

        if ($notaFiscal->arquivo) {
            Storage::disk('public')->delete('notas-fiscais/' . $notaFiscal->arquivo);
        }

        $notaFiscal->forceDelete();

        return response()->json(null, 204);
    }
}




