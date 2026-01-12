<?php

declare(strict_types=1);

namespace App\Http\Requests\Onboarding;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Form Request para marcar item do checklist de onboarding
 */
class MarcarChecklistItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Pode ser público ou autenticado
    }

    public function rules(): array
    {
        return [
            'item' => 'required|string|max:100',
            'onboarding_id' => 'nullable|integer|min:1|exists:onboarding_progress,id',
            'tenant_id' => 'nullable|integer|min:1',
            'user_id' => 'nullable|integer|min:1',
            'session_id' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
        ];
    }

    public function messages(): array
    {
        return [
            'item.required' => 'O item é obrigatório.',
            'item.string' => 'O item deve ser uma string.',
            'item.max' => 'O item não pode ter mais de 100 caracteres.',
            'onboarding_id.integer' => 'O onboarding_id deve ser um número inteiro.',
            'onboarding_id.min' => 'O onboarding_id deve ser maior que zero.',
            'onboarding_id.exists' => 'O onboarding especificado não foi encontrado.',
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
     * Validação customizada: se não tem onboarding_id, precisa de pelo menos um identificador
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $data = $this->all();
            
            // Se não tem onboarding_id, precisa de pelo menos um identificador
            if (empty($data['onboarding_id'])) {
                if (empty($data['tenant_id']) && empty($data['user_id']) && empty($data['session_id']) && empty($data['email'])) {
                    $validator->errors()->add('base', 'É necessário fornecer onboarding_id ou pelo menos uma forma de identificação (tenant_id, user_id, session_id ou email).');
                }
            }
        });
    }
}




