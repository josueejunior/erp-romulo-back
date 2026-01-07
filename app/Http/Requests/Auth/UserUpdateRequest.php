<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Form Request para atualização de usuário
 * Centraliza validação e mantém Controller limpo
 */
class UserUpdateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Autorização é feita no Controller/Policy
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $userId = $this->route('id') ?? $this->route('user');
        
        return [
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:users,email,' . $userId,
            'password' => 'sometimes|string|min:8',
            'empresa_id' => 'sometimes|integer|exists:empresas,id',
            'role' => 'sometimes|string',
            'empresas' => 'nullable|array',
            'empresas.*' => 'integer|exists:empresas,id',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'email.email' => 'O e-mail deve ser válido.',
            'email.unique' => 'Este e-mail já está cadastrado.',
            'password.min' => 'A senha deve ter no mínimo 8 caracteres.',
            'empresa_id.exists' => 'A empresa selecionada não existe.',
        ];
    }
}



