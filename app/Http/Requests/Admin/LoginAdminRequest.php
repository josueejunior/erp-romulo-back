<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Form Request para Login de Admin
 * 
 * 🔥 DDD: Validação de entrada isolada do controller
 */
class LoginAdminRequest extends FormRequest
{
    /**
     * Determinar se o usuário está autorizado a fazer esta requisição
     */
    public function authorize(): bool
    {
        return true; // Login é público
    }

    /**
     * Obter regras de validação
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:6'],
        ];
    }

    /**
     * Obter mensagens de validação customizadas
     */
    public function messages(): array
    {
        return [
            'email.required' => 'O e-mail é obrigatório.',
            'email.email' => 'O e-mail deve ser um endereço válido.',
            'password.required' => 'A senha é obrigatória.',
            'password.min' => 'A senha deve ter no mínimo :min caracteres.',
        ];
    }
}

