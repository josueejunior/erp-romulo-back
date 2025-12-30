<?php

namespace App\Http\Requests\Payment;

use Illuminate\Foundation\Http\FormRequest;

class ProcessarAssinaturaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'plano_id' => 'required|integer|exists:planos,id',
            'periodo' => 'required|string|in:mensal,anual',
            'card_token' => 'required|string',
            'payer_email' => 'required|email',
            'payer_cpf' => 'nullable|string',
            'installments' => 'nullable|integer|min:1|max:12',
        ];
    }
}

