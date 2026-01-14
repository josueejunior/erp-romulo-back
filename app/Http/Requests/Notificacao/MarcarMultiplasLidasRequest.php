<?php

namespace App\Http\Requests\Notificacao;

use Illuminate\Foundation\Http\FormRequest;

class MarcarMultiplasLidasRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['required', 'integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return [
            'ids.required' => 'O campo IDs é obrigatório.',
            'ids.array' => 'IDs deve ser um array.',
            'ids.min' => 'É necessário informar pelo menos um ID.',
            'ids.*.required' => 'Cada ID é obrigatório.',
            'ids.*.integer' => 'Cada ID deve ser um número inteiro.',
            'ids.*.min' => 'Cada ID deve ser maior que zero.',
        ];
    }
}









