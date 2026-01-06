<?php

namespace App\Http\Requests\Orcamento;

use Illuminate\Foundation\Http\FormRequest;

class OrcamentoCreateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'fornecedor_id' => 'nullable|integer|exists:fornecedores,id',
            'transportadora_id' => 'nullable|integer|exists:fornecedores,id',
            'custo_produto' => 'nullable|numeric|min:0',
            'marca_modelo' => 'nullable|string|max:255',
            'ajustes_especificacao' => 'nullable|string',
            'frete' => 'nullable|numeric|min:0',
            'frete_incluido' => 'nullable|boolean',
            'fornecedor_escolhido' => 'nullable|boolean',
            'observacoes' => 'nullable|string',
        ];
    }
}


