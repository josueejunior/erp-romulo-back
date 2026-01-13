<?php

declare(strict_types=1);

namespace App\Http\Requests\Onboarding;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Form Request para iniciar onboarding
 */
class IniciarOnboardingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Pode ser público ou autenticado
    }

    public function rules(): array
    {
        return [
            'tenant_id' => 'nullable|integer|min:1',
            'user_id' => 'nullable|integer|min:1',
            'session_id' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
        ];
    }

    public function messages(): array
    {
        return [
            'tenant_id.integer' => 'O tenant_id deve ser um número inteiro.',
            'tenant_id.min' => 'O tenant_id deve ser maior que zero.',
            'user_id.integer' => 'O user_id deve ser um número inteiro.',
            'user_id.min' => 'O user_id deve ser maior que zero.',
            'session_id.string' => 'O session_id deve ser uma string.',
            'session_id.max' => 'O session_id não pode ter mais de 255 caracteres.',
            'email.email' => 'O email deve ser um endereço de email válido.',
            'email.max' => 'O email não pode ter mais de 255 caracteres.',
        ];
    }

    /**
     * Validação customizada: pelo menos um identificador deve ser fornecido
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $data = $this->all();
            
            if (empty($data['tenant_id']) && empty($data['user_id']) && empty($data['session_id']) && empty($data['email'])) {
                $validator->errors()->add('base', 'É necessário fornecer pelo menos uma forma de identificação (tenant_id, user_id, session_id ou email).');
            }
        });
    }
}





