<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class FixUserRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'role' => 'nullable|string|in:Administrador,Operacional,Financeiro,Consulta',
        ];
    }

    public function messages(): array
    {
        return [
            'role.in' => 'A role deve ser: Administrador, Operacional, Financeiro ou Consulta.',
        ];
    }
}

