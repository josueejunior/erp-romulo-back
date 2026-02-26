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
            'nome_fantasia' => 'nullable|string|max:255',
            'cnpj' => ['nullable', 'string', 'max:18', 'unique:tenants,cnpj', new \App\Rules\CnpjValido()],
            'email' => 'nullable|email|max:255',
            'email_financeiro' => 'nullable|email|max:255',
            'email_licitacao' => 'nullable|email|max:255',
            'status' => 'nullable|string|in:ativa,inativa',
            'endereco' => 'nullable|string|max:255',
            'cidade' => 'nullable|string|max:255',
            'estado' => 'nullable|string|max:2',
            'cep' => 'nullable|string|max:10',
            'telefones' => 'nullable|array',
            'telefone_fixo' => 'nullable|string|max:20',
            'emails_adicionais' => 'nullable|array',
            'banco' => 'nullable|string|max:255',
            'agencia' => 'nullable|string|max:255',
            'conta' => 'nullable|string|max:255',
            'tipo_conta' => 'nullable|string|in:corrente,poupanca,pagamento',
            'pix' => 'nullable|string|max:255',
            'favorecido_razao_social' => 'nullable|string|max:255',
            'favorecido_cnpj' => 'nullable|string|max:18',
            'representante_legal_nome' => 'nullable|string|max:255',
            'representante_legal_cpf' => ['nullable', 'string', 'max:14', new \App\Rules\CpfValido()],
            'representante_legal_rg' => 'nullable|string|max:50',
            'representante_legal_telefone' => 'nullable|string|max:20',
            'representante_legal_email' => 'nullable|email|max:255',
            'representante_legal_cargo' => 'nullable|string|max:255',
            'inscricao_estadual' => 'nullable|string|max:50',
            'inscricao_municipal' => 'nullable|string|max:50',
            'cnae_principal' => 'nullable|string|max:32',
            'data_abertura' => 'nullable|date',
            'responsavel_comercial' => 'nullable|string|max:255',
            'responsavel_financeiro' => 'nullable|string|max:255',
            'responsavel_licitacoes' => 'nullable|string|max:255',
            'ramo_atuacao' => 'nullable|string|max:255',
            'principais_produtos_servicos' => 'nullable|string',
            'marcas_trabalhadas' => 'nullable|string',
            'observacoes' => 'nullable|string',
            'site' => 'nullable|string|max:255',
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






