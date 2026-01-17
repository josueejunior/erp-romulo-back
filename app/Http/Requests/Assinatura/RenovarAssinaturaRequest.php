<?php

namespace App\Http\Requests\Assinatura;

use Illuminate\Foundation\Http\FormRequest;

class RenovarAssinaturaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'meses' => 'required|integer|in:1,12',
            'payment_method_id' => 'required|string|in:credit_card,pix',
            'card_token' => 'required_if:payment_method_id,credit_card|string',
            'payer_email' => 'required|email',
            'payer_cpf' => 'nullable|string',
            'installments' => 'nullable|integer|min:1|max:12',
        ];
    }

    public function messages(): array
    {
        return [
            'meses.required' => 'O período de renovação é obrigatório.',
            'meses.in' => 'O período deve ser 1 ou 12 meses.',
            'card_token.required' => 'O token do cartão é obrigatório.',
            'payer_email.required' => 'O e-mail do pagador é obrigatório.',
            'payer_email.email' => 'O e-mail deve ser válido.',
        ];
    }
}



