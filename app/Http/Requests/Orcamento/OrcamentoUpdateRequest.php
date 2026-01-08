<?php

namespace App\Http\Requests\Orcamento;

use Illuminate\Foundation\Http\FormRequest;

class OrcamentoUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'fornecedor_id' => 'sometimes|required|integer|exists:fornecedores,id',
            'transportadora_id' => 'nullable|integer|exists:fornecedores,id',
            'custo_produto' => 'sometimes|required|numeric|min:0',
            'marca_modelo' => 'nullable|string|max:255',
            'ajustes_especificacao' => 'nullable|string',
            'frete' => 'nullable|numeric|min:0',
            'frete_incluido' => 'sometimes|boolean',
            'fornecedor_escolhido' => 'sometimes|boolean',
            'observacoes' => 'nullable|string',
        ];
    }
}

