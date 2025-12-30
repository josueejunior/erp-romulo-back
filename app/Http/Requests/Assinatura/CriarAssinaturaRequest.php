<?php

namespace App\Http\Requests\Assinatura;

use Illuminate\Foundation\Http\FormRequest;

class CriarAssinaturaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Validação de autorização feita no controller/middleware
    }

    public function rules(): array
    {
        return [
            'plano_id' => 'required|exists:planos,id',
            'data_inicio' => 'nullable|date',
            'data_fim' => 'required|date|after:data_inicio',
            'valor_pago' => 'nullable|numeric|min:0',
            'metodo_pagamento' => 'nullable|string|in:gratuito,credit_card,pix',
            'status' => 'nullable|string|in:ativa,suspensa,expirada',
        ];
    }

    public function messages(): array
    {
        return [
            'plano_id.required' => 'O plano é obrigatório.',
            'plano_id.exists' => 'O plano selecionado não existe.',
            'data_fim.required' => 'A data de término é obrigatória.',
            'data_fim.date' => 'A data de término deve ser uma data válida.',
            'data_fim.after' => 'A data de término deve ser posterior à data de início.',
            'valor_pago.numeric' => 'O valor pago deve ser um número.',
            'valor_pago.min' => 'O valor pago não pode ser negativo.',
            'metodo_pagamento.in' => 'O método de pagamento deve ser: gratuito, credit_card ou pix.',
            'status.in' => 'O status deve ser: ativa, suspensa ou expirada.',
        ];
    }
}

