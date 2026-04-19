<?php

namespace App\Http\Requests\Assinatura;

use Illuminate\Foundation\Http\FormRequest;

class SalvarCartaoAssinaturaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'card_token' => 'required|string',
            'payer_email' => 'required|email',
            'payer_cpf' => 'nullable|string|max:20',
        ];
    }

    public function messages(): array
    {
        return [
            'card_token.required' => 'O token do cartão é obrigatório.',
            'payer_email.required' => 'O e-mail do titular é obrigatório.',
            'payer_email.email' => 'O e-mail deve ser válido.',
        ];
    }
}
