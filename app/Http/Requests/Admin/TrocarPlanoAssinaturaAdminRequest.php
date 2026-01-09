<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

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

    /**
     * Customizar mensagens de validação
     */
    public function messages(): array
    {
        return [
            'novo_plano_id.required' => 'O ID do novo plano é obrigatório.',
            'novo_plano_id.integer' => 'O ID do novo plano deve ser um número inteiro.',
            'novo_plano_id.exists' => 'O plano selecionado não existe.',
            'periodo.in' => 'O período deve ser "mensal" ou "anual".',
        ];
    }

    /**
     * Tratar erros de validação para retornar JSON padronizado
     */
    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(
            response()->json([
                'success' => false,
                'message' => 'Erro de validação ao trocar plano.',
                'errors' => $validator->errors(),
            ], 422)
        );
    }
}

