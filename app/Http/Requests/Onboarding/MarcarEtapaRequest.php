<?php

declare(strict_types=1);

namespace App\Http\Requests\Onboarding;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Form Request para marcar etapa de onboarding
 */
class MarcarEtapaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Pode ser público ou autenticado
    }

    public function rules(): array
    {
        return [
            'etapa' => 'required|string|max:100',
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
            'etapa.required' => 'A etapa é obrigatória.',
            'etapa.string' => 'A etapa deve ser uma string.',
            'etapa.max' => 'A etapa não pode ter mais de 100 caracteres.',
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
     * 
     * 🔥 IMPORTANTE: Se o usuário está autenticado, usar dados do contexto automaticamente
     * Verifica dados do request body, usuário autenticado, atributos do request (setados por middlewares)
     * e contexto de tenancy.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $data = $this->all();
            
            // Se não tem onboarding_id, precisa de pelo menos um identificador
            if (empty($data['onboarding_id'])) {
                // Verificar dados do request body
                $temTenantIdNoBody = !empty($data['tenant_id']);
                $temUserIdNoBody = !empty($data['user_id']);
                $temSessionIdNoBody = !empty($data['session_id']);
                $temEmailNoBody = !empty($data['email']);
                
                // Verificar dados do contexto autenticado (middleware)
                $user = $this->user(); // Usuário autenticado via middleware (auth.optional ou jwt.auth)
                $userId = $user?->id ?? null;
                $email = $user?->email ?? null;
                
                // Verificar atributos do request (setados por OptionalAuthenticate ou AuthenticateJWT)
                $tenantIdFromAttributes = $this->attributes->get('tenant_id') ?? null;
                $userIdFromAttributes = $this->attributes->get('user_id') ?? null;
                
                // Verificar contexto de tenancy (pode estar inicializado pelo middleware)
                $tenantIdFromTenancy = null;
                try {
                    $tenantIdFromTenancy = tenancy()->tenant?->id ?? null;
                } catch (\Exception $e) {
                    // Tenancy pode não estar inicializado, isso é OK
                }
                
                // Combinar todas as fontes de identificação
                $tenantId = $temTenantIdNoBody 
                    ? (int) $data['tenant_id'] 
                    : ($tenantIdFromAttributes ?? $tenantIdFromTenancy);
                    
                $userIdFinal = $temUserIdNoBody 
                    ? (int) $data['user_id'] 
                    : ($userIdFromAttributes ?? $userId);
                
                $emailFinal = $temEmailNoBody ? $data['email'] : $email;
                
                // Verificar se temos pelo menos um identificador
                $temIdentificacao = !empty($tenantId) 
                    || !empty($userIdFinal) 
                    || $temSessionIdNoBody
                    || !empty($emailFinal);
                
                if (!$temIdentificacao) {
                    $validator->errors()->add('base', 'É necessário fornecer onboarding_id ou pelo menos uma forma de identificação (tenant_id, user_id, session_id ou email).');
                }
            }
        });
    }
}



