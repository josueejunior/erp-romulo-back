<?php

namespace App\Http\Requests\Assinatura;

use Illuminate\Foundation\Http\FormRequest;

class TrocarPlanoRequest extends FormRequest
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
            'payment_data' => 'nullable|array',
            'payment_data.payment_method_id' => 'required_with:payment_data|string|in:credit_card,pix',
            'payment_data.card_token' => 'required_if:payment_data.payment_method_id,credit_card|string',
            'payment_data.payer_email' => 'required_with:payment_data|email',
            'payment_data.payer_cpf' => 'nullable|string',
            'payment_data.installments' => 'nullable|integer|min:1|max:12',
        ];
    }

    public function messages(): array
    {
        return [
            'plano_id.required' => 'O plano é obrigatório.',
            'plano_id.exists' => 'O plano selecionado não existe.',
            'periodo.required' => 'O período é obrigatório.',
            'periodo.in' => 'O período deve ser mensal ou anual.',
            'payment_data.required_if' => 'Dados de pagamento são obrigatórios quando há valor a cobrar.',
        ];
    }
}

