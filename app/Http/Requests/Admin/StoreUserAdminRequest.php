<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * 🔥 DDD: FormRequest para criar usuário no admin
 * Encapsula toda validação complexa condicional
 */
class StoreUserAdminRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Middleware já valida autorização admin
    }

    public function rules(): array
    {
        $rules = [
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'password' => 'required|string|min:1', // Formato básico - força validada no Domain
            'role' => 'nullable|string|in:Administrador,Operacional,Financeiro,Consulta',
        ];

        // Lógica condicional: empresas (array) OU empresa_id OU empresa_ativa_id
        $empresasInput = $this->input('empresas');
        $hasEmpresasArray = ($this->has('empresas') || array_key_exists('empresas', $this->all())) 
            && is_array($empresasInput) 
            && count($empresasInput) > 0;

        if ($hasEmpresasArray) {
            // Se empresas array foi fornecido
            $rules['empresas'] = 'required|array|min:1';
            $rules['empresas.*'] = 'integer';
            
            if ($this->has('empresa_ativa_id') || array_key_exists('empresa_ativa_id', $this->all())) {
                $rules['empresa_ativa_id'] = 'nullable|integer';
            }
        } else {
            // Se não tem empresas array, precisa de empresa_id OU empresa_ativa_id
            $hasEmpresaId = $this->has('empresa_id') || array_key_exists('empresa_id', $this->all());
            $hasEmpresaAtivaId = $this->has('empresa_ativa_id') || array_key_exists('empresa_ativa_id', $this->all());

            if (!$hasEmpresaId && !$hasEmpresaAtivaId) {
                // Nenhum dos dois foi fornecido, exigir pelo menos um
                $rules['empresa_id'] = 'required_without:empresa_ativa_id|integer';
                $rules['empresa_ativa_id'] = 'required_without:empresa_id|integer';
            } else {
                // Pelo menos um foi fornecido, validar o que foi enviado
                if ($hasEmpresaId) {
                    $rules['empresa_id'] = 'required|integer';
                }
                if ($hasEmpresaAtivaId) {
                    $rules['empresa_ativa_id'] = 'required|integer';
                }
            }
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'name.required' => 'O nome é obrigatório.',
            'email.required' => 'O e-mail é obrigatório.',
            'password.required' => 'A senha é obrigatória.',
            'empresa_id.required' => 'A empresa é obrigatória.',
            'empresas.required' => 'Selecione pelo menos uma empresa.',
            'empresas.min' => 'Selecione pelo menos uma empresa.',
            'role.in' => 'O perfil deve ser: Administrador, Operacional, Financeiro ou Consulta.',
        ];
    }
}





