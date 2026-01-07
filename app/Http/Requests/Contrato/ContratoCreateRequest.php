<?php

namespace App\Http\Requests\Contrato;

use Illuminate\Foundation\Http\FormRequest;

class ContratoCreateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'numero' => 'nullable|string|max:255',
            'data_inicio' => 'nullable|date',
            'data_fim' => 'nullable|date|after_or_equal:data_inicio',
            'data_assinatura' => 'nullable|date',
            'valor_total' => 'nullable|numeric|min:0',
            'condicoes_comerciais' => 'nullable|string',
            'condicoes_tecnicas' => 'nullable|string',
            'locais_entrega' => 'nullable|string',
            'prazos_contrato' => 'nullable|string',
            'regras_contrato' => 'nullable|string',
            'situacao' => 'nullable|string',
            'vigente' => 'nullable|boolean',
            'observacoes' => 'nullable|string',
            'arquivo_contrato' => 'nullable|string',
            'numero_cte' => 'nullable|string|max:255',
        ];
    }
}



