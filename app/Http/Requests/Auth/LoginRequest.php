<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Log;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        \Log::debug('LoginRequest::authorize - Verificando autorização');
        return true;
    }

    public function rules(): array
    {
        \Log::debug('LoginRequest::rules - Definindo regras de validação', [
            'input' => $this->all(),
        ]);
        return [
            'email' => 'required|email',
            'password' => 'required|string',
            'tenant_id' => 'nullable',
        ];
    }
    
    protected function prepareForValidation(): void
    {
        $tenantId = $this->input('tenant_id');

        // Fluxos diferentes podem enviar tenant_id como inteiro, string numérica
        // ou objeto de select ({id, value, tenant_id}).
        if (is_array($tenantId)) {
            $candidate = $tenantId['tenant_id'] ?? $tenantId['id'] ?? $tenantId['value'] ?? null;
            $tenantId = is_scalar($candidate) ? (string) $candidate : null;
        } elseif (is_scalar($tenantId) && !is_string($tenantId)) {
            $tenantId = (string) $tenantId;
        }

        if ($tenantId !== null) {
            $this->merge([
                'tenant_id' => $tenantId,
            ]);
        }

        \Log::debug('LoginRequest::prepareForValidation - Preparando para validação', [
            'input' => $this->all(),
        ]);
    }
    
    protected function failedValidation(\Illuminate\Contracts\Validation\Validator $validator): void
    {
        \Log::error('LoginRequest::failedValidation - Validação falhou', [
            'errors' => $validator->errors()->toArray(),
            'input' => $this->all(),
        ]);
        parent::failedValidation($validator);
    }

    public function messages(): array
    {
        return [
            'email.required' => 'O e-mail é obrigatório.',
            'password.required' => 'A senha é obrigatória.',
            'password.string' => 'A senha deve ser um texto.',
            'tenant_id.string' => 'O tenant selecionado deve ser texto ou número.',
        ];
    }
}
