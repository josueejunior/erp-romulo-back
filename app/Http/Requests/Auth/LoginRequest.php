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
            'tenant_id' => 'nullable|string',
        ];
    }
    
    protected function prepareForValidation(): void
    {
        \Log::debug('LoginRequest::prepareForValidation - Preparando para validação', [
            'input' => $this->all(),
        ]);

        // 🔥 Converter tenant_id para string se veio como inteiro
        // O frontend pode enviar como número (ex: da tela de seleção de tenant)
        if ($this->has('tenant_id') && $this->tenant_id !== null) {
            $this->merge([
                'tenant_id' => (string) $this->tenant_id,
            ]);
        }
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
        ];
    }
}


