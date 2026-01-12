<?php

namespace App\Http\Requests\FormacaoPreco;

use Illuminate\Foundation\Http\FormRequest;

/**
 * FormRequest para validação de formação de preço
 * 
 * ✅ DDD: Validação estrutural de entrada (HTTP layer)
 * Service assume que dados já são válidos
 */
class FormacaoPrecoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'custo_produto' => ['required', 'numeric', 'min:0'],
            'frete' => ['required', 'numeric', 'min:0'],
            'percentual_impostos' => ['required', 'numeric', 'min:0', 'max:100'],
            'percentual_margem' => ['required', 'numeric', 'min:0', 'max:99'],
            'observacoes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'custo_produto.required' => 'O custo do produto é obrigatório.',
            'custo_produto.numeric' => 'O custo do produto deve ser um número.',
            'custo_produto.min' => 'O custo do produto não pode ser negativo.',
            'frete.required' => 'O frete é obrigatório.',
            'frete.numeric' => 'O frete deve ser um número.',
            'frete.min' => 'O frete não pode ser negativo.',
            'percentual_impostos.required' => 'O percentual de impostos é obrigatório.',
            'percentual_impostos.numeric' => 'O percentual de impostos deve ser um número.',
            'percentual_impostos.min' => 'O percentual de impostos não pode ser negativo.',
            'percentual_impostos.max' => 'O percentual de impostos não pode ser maior que 100%.',
            'percentual_margem.required' => 'O percentual de margem é obrigatório.',
            'percentual_margem.numeric' => 'O percentual de margem deve ser um número.',
            'percentual_margem.min' => 'O percentual de margem não pode ser negativo.',
            'percentual_margem.max' => 'O percentual de margem não pode ser maior que 99%.',
        ];
    }
}






