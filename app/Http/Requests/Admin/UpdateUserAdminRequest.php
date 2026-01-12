<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * 🔥 DDD: FormRequest para atualizar usuário no admin
 * Inclui normalização de senha vazia
 */
class UpdateUserAdminRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * 🔥 DDD: Normalizar dados antes de validar
     * Remove password se estiver vazio
     */
    protected function prepareForValidation(): void
    {
        $data = $this->all();
        
        // Se password existir mas estiver vazio → remove completamente
        if (array_key_exists('password', $data)) {
            if (trim((string) $data['password']) === '') {
                unset($data['password']);
            }
        }
        
        // Recriar request com dados normalizados
        $this->replace($data);
    }

    public function rules(): array
    {
        $rules = [
            'name' => 'sometimes|required|string|max:255',
            'email' => 'sometimes|required|email|max:255',
            'role' => 'nullable|string|in:Administrador,Operacional,Financeiro,Consulta',
        ];

        // Senha em update: opcional, validada apenas se existir (já normalizada acima)
        if ($this->has('password')) {
            $rules['password'] = ['string', 'min:1']; // Apenas formato básico
        }

        // Aceitar empresas (array) OU empresa_id OU empresa_ativa_id
        $empresasInput = $this->input('empresas');
        $hasEmpresasArray = $this->has('empresas') 
            && is_array($empresasInput) 
            && !empty($empresasInput);

        if ($hasEmpresasArray) {
            $rules['empresas'] = 'sometimes|required|array|min:1';
            $rules['empresas.*'] = 'integer';
            if ($this->has('empresa_ativa_id')) {
                $rules['empresa_ativa_id'] = 'sometimes|nullable|integer';
            }
        } else {
            // Se não tem empresas array, aceitar empresa_id OU empresa_ativa_id (opcional em update)
            if ($this->has('empresa_id')) {
                $rules['empresa_id'] = 'sometimes|required|integer';
            }
            if ($this->has('empresa_ativa_id')) {
                $rules['empresa_ativa_id'] = 'sometimes|required|integer';
            }
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'role.in' => 'O perfil deve ser: Administrador, Operacional, Financeiro ou Consulta.',
        ];
    }
}




