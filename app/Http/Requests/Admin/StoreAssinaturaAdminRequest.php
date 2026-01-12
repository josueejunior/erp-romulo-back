<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * 🔥 DDD: FormRequest para criar assinatura no admin
 */
class StoreAssinaturaAdminRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tenant_id' => 'required|integer|exists:tenants,id',
            'plano_id' => 'required|integer|exists:planos,id',
            'empresa_id' => 'nullable|integer',
            'user_id' => 'nullable|integer',
            'status' => 'nullable|string|in:ativa,suspensa,expirada,cancelada',
            'data_inicio' => 'nullable|date',
            'data_fim' => 'nullable|date',
            'valor_pago' => 'nullable|numeric|min:0',
            'metodo_pagamento' => 'nullable|string|in:gratuito,credit_card,pix,boleto',
            'transacao_id' => 'nullable|string|max:255',
            'dias_grace_period' => 'nullable|integer|min:0|max:90',
            'observacoes' => 'nullable|string|max:5000',
            'periodo' => 'nullable|string|in:mensal,anual',
        ];
    }
}



