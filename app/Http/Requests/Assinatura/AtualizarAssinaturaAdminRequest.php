<?php

namespace App\Http\Requests\Assinatura;

use Illuminate\Foundation\Http\FormRequest;

class AtualizarAssinaturaAdminRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Validação de autorização feita no middleware
    }

    public function rules(): array
    {
        return [
            'plano_id' => 'sometimes|exists:planos,id',
            'status' => 'sometimes|string|in:ativa,suspensa,expirada,cancelada',
            'data_inicio' => 'sometimes|date',
            'data_fim' => 'sometimes|date|after_or_equal:data_inicio',
            'valor_pago' => 'sometimes|numeric|min:0',
            'metodo_pagamento' => 'sometimes|nullable|string|in:gratuito,credit_card,pix,boleto',
            'dias_grace_period' => 'sometimes|integer|min:0',
        ];
    }

    public function messages(): array
    {
        return [
            'plano_id.exists' => 'O plano selecionado não existe.',
            'status.in' => 'O status deve ser: ativa, suspensa, expirada ou cancelada.',
            'data_fim.after_or_equal' => 'A data de término deve ser posterior ou igual à data de início.',
            'valor_pago.numeric' => 'O valor pago deve ser um número.',
            'valor_pago.min' => 'O valor pago não pode ser negativo.',
            'metodo_pagamento.in' => 'O método de pagamento deve ser: gratuito, credit_card, pix ou boleto.',
            'dias_grace_period.integer' => 'O período de graça deve ser um número inteiro.',
            'dias_grace_period.min' => 'O período de graça não pode ser negativo.',
        ];
    }
}



