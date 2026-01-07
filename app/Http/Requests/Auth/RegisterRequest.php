<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'password' => 'required|string|min:8',
            'tenant_id' => 'required|string',
            'empresa_id' => 'required|integer',
            'role' => 'nullable|string',
            'empresas' => 'nullable|array',
            'empresas.*' => 'integer|exists:empresas,id',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'O nome é obrigatório.',
            'email.required' => 'O e-mail é obrigatório.',
            'email.email' => 'O e-mail deve ser válido.',
            'password.required' => 'A senha é obrigatória.',
            'password.min' => 'A senha deve ter no mínimo 8 caracteres.',
            'tenant_id.required' => 'O tenant é obrigatório.',
            'empresa_id.required' => 'A empresa é obrigatória.',
        ];
    }
}



