<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Form Request para trocar empresa ativa
 * Centraliza validação e mantém Controller limpo
 */
class SwitchEmpresaRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Usuário autenticado pode trocar sua própria empresa
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'empresa_ativa_id' => 'required|integer|exists:empresas,id',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'empresa_ativa_id.required' => 'A empresa ativa é obrigatória.',
            'empresa_ativa_id.exists' => 'A empresa selecionada não existe.',
        ];
    }
}

