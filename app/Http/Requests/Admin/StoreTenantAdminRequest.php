<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreTenantAdminRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Admin sempre autorizado (middleware já valida)
        return true;
    }

    public function rules(): array
    {
        return [
            'razao_social' => 'required|string|max:255',
            'cnpj' => ['nullable', 'string', 'max:18', 'unique:tenants,cnpj', new \App\Rules\CnpjValido()],
            'email' => 'nullable|email|max:255',
            'status' => 'nullable|string|in:ativa,inativa',
            'endereco' => 'nullable|string|max:255',
            'cidade' => 'nullable|string|max:255',
            'estado' => 'nullable|string|max:2',
            'cep' => 'nullable|string|max:10',
            'telefones' => 'nullable|array',
            'emails_adicionais' => 'nullable|array',
            'banco' => 'nullable|string|max:255',
            'agencia' => 'nullable|string|max:255',
            'conta' => 'nullable|string|max:255',
            'tipo_conta' => 'nullable|string|in:corrente,poupanca',
            'pix' => 'nullable|string|max:255',
            'representante_legal_nome' => 'nullable|string|max:255',
            'representante_legal_cpf' => ['nullable', 'string', 'max:14', new \App\Rules\CpfValido()],
            'representante_legal_cargo' => 'nullable|string|max:255',
            'logo' => 'nullable|string|max:255',
            // Dados do administrador (opcional no admin)
            'admin_name' => 'nullable|string|max:255',
            'admin_email' => 'nullable|email|max:255',
            'admin_password' => 'nullable|string|min:8',
        ];
    }

    public function messages(): array
    {
        return [
            'razao_social.required' => 'A razão social da empresa é obrigatória.',
            'cnpj.unique' => 'Este CNPJ já está cadastrado no sistema.',
        ];
    }
}




