<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * 🔥 DDD: FormRequest para trocar plano de assinatura no admin
 */
class TrocarPlanoAssinaturaAdminRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'novo_plano_id' => 'required|integer|exists:planos,id',
            'periodo' => 'nullable|string|in:mensal,anual',
        ];
    }
}

