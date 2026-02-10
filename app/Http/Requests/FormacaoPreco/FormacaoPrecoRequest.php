<?php

namespace App\Http\Requests\FormacaoPreco;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Log;

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
        Log::debug('FormacaoPrecoRequest::authorize - Verificando autorização');
        return true;
    }

    /**
     * Handle a failed validation attempt.
     *
     * @param  \Illuminate\Contracts\Validation\Validator  $validator
     * @return void
     *
     * @throws \Illuminate\Http\Exceptions\HttpResponseException
     */
    protected function failedValidation(Validator $validator)
    {
        Log::error('FormacaoPrecoRequest::failedValidation - Erro de validação', [
            'errors' => $validator->errors()->toArray(),
            'request_data' => $this->all(),
        ]);

        throw new HttpResponseException(
            response()->json([
                'message' => 'Erro de validação',
                'errors' => $validator->errors(),
            ], 422)
        );
    }

    public function rules(): array
    {
        return [
            'custo_produto' => ['required', 'numeric', 'min:0'],
            'frete' => ['required', 'numeric', 'min:0'],
            'percentual_impostos' => ['required', 'numeric', 'min:0', 'max:100'],
            'percentual_margem' => ['required', 'numeric', 'min:0', 'max:99'],
            'preco_minimo' => ['nullable', 'numeric', 'min:0'], // Campo calculado pelo frontend, opcional
            'preco_recomendado' => ['nullable', 'numeric', 'min:0'], // Campo opcional do frontend
            'observacoes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        Log::info('FormacaoPrecoRequest::prepareForValidation - EXECUTANDO', [
            'url' => $this->fullUrl(),
            'method' => $this->method(),
            'route_name' => $this->route()?->getName(),
            'route_parameters' => $this->route()?->parameters(),
            'original_data' => $this->all(),
        ]);
        
        // Converter strings vazias para null e garantir que valores numéricos sejam válidos
        $data = $this->all();
        
        // Converter strings vazias para null
        foreach (['custo_produto', 'frete', 'percentual_impostos', 'percentual_margem', 'preco_minimo', 'preco_recomendado'] as $field) {
            if (isset($data[$field]) && $data[$field] === '') {
                $data[$field] = null;
            }
            // Converter NaN para null
            if (isset($data[$field]) && (is_nan($data[$field]) || $data[$field] === 'NaN')) {
                $data[$field] = null;
            }
        }
        
        // Converter null para 0 nos campos obrigatórios
        if (!isset($data['custo_produto']) || $data['custo_produto'] === null) {
            $data['custo_produto'] = 0;
        }
        if (!isset($data['frete']) || $data['frete'] === null) {
            $data['frete'] = 0;
        }
        if (!isset($data['percentual_impostos']) || $data['percentual_impostos'] === null) {
            $data['percentual_impostos'] = 0;
        }
        if (!isset($data['percentual_margem']) || $data['percentual_margem'] === null) {
            $data['percentual_margem'] = 0;
        }
        
        $this->merge($data);
        
        Log::debug('FormacaoPrecoRequest::prepareForValidation - Dados preparados', [
            'original_data' => $this->all(),
            'prepared_data' => $data,
        ]);
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









