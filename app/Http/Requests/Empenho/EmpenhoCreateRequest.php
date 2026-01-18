<?php

namespace App\Http\Requests\Empenho;

use Illuminate\Foundation\Http\FormRequest;

class EmpenhoCreateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'contrato_id' => 'nullable|integer|exists:contratos,id',
            'autorizacao_fornecimento_id' => 'nullable|integer|exists:autorizacoes_fornecimento,id',
            'numero' => 'nullable|string|max:255',
            'data' => 'nullable|date',
            'data_recebimento' => 'nullable|date',
            'prazo_entrega_calculado' => 'nullable|date',
            'valor' => 'nullable|numeric|min:0',
            'situacao' => 'nullable|string',
            'observacoes' => 'nullable|string',
            'numero_cte' => 'nullable|string|max:255',
            'itens' => 'nullable|array',
            'itens.*.processo_item_id' => 'required|integer|exists:processo_itens,id',
            'itens.*.quantidade' => 'required|numeric|min:0',
            'itens.*.valor_unitario' => 'required|numeric|min:0',
        ];
    }
}



