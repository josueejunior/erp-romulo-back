<?php

namespace App\Http\Requests\NotaFiscal;

use Illuminate\Foundation\Http\FormRequest;

class NotaFiscalCreateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'empenho_id' => 'nullable|integer|exists:empenhos,id',
            'contrato_id' => 'nullable|integer|exists:contratos,id',
            'autorizacao_fornecimento_id' => 'nullable|integer|exists:autorizacao_fornecimentos,id',
            'tipo' => 'nullable|string|max:255',
            'numero' => 'nullable|string|max:255',
            'serie' => 'nullable|string|max:255',
            'data_emissao' => 'nullable|date',
            'fornecedor_id' => 'nullable|integer|exists:fornecedores,id',
            'transportadora' => 'nullable|string|max:255',
            'numero_cte' => 'nullable|string|max:255',
            'data_entrega_prevista' => 'nullable|date',
            'data_entrega_realizada' => 'nullable|date',
            'situacao_logistica' => 'nullable|string|in:aguardando_envio,em_transito,entregue,atrasada',
            'valor' => 'nullable|numeric|min:0',
            'custo_produto' => 'nullable|numeric|min:0',
            'custo_frete' => 'nullable|numeric|min:0',
            'comprovante_pagamento' => 'nullable|string',
            'arquivo' => 'nullable|string',
            'situacao' => 'nullable|string',
            'data_pagamento' => 'nullable|date',
            'observacoes' => 'nullable|string',
        ];
    }
}



