<?php

declare(strict_types=1);

namespace App\Http\Requests\Configuracoes;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Form Request para atualizar preferências de notificações
 */
class AtualizarNotificacoesRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Usuário deve estar autenticado (validado no middleware)
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'email_notificacoes' => 'nullable|boolean',
            'push_notificacoes' => 'nullable|boolean',
            'notificar_processos_novos' => 'nullable|boolean',
            'notificar_documentos_vencendo' => 'nullable|boolean',
            'notificar_prazos' => 'nullable|boolean',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'email_notificacoes.boolean' => 'O campo "Notificações por Email" deve ser verdadeiro ou falso.',
            'push_notificacoes.boolean' => 'O campo "Notificações Push" deve ser verdadeiro ou falso.',
            'notificar_processos_novos.boolean' => 'O campo "Notificar Processos Novos" deve ser verdadeiro ou falso.',
            'notificar_documentos_vencendo.boolean' => 'O campo "Notificar Documentos Vencendo" deve ser verdadeiro ou falso.',
            'notificar_prazos.boolean' => 'O campo "Notificar Prazos" deve ser verdadeiro ou falso.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Converter strings 'true'/'false' para boolean
        $this->merge([
            'email_notificacoes' => $this->toBoolean($this->email_notificacoes),
            'push_notificacoes' => $this->toBoolean($this->push_notificacoes),
            'notificar_processos_novos' => $this->toBoolean($this->notificar_processos_novos),
            'notificar_documentos_vencendo' => $this->toBoolean($this->notificar_documentos_vencendo),
            'notificar_prazos' => $this->toBoolean($this->notificar_prazos),
        ]);
    }

    /**
     * Convert value to boolean
     */
    private function toBoolean($value): ?bool
    {
        if ($value === null) {
            return null;
        }

        if (is_bool($value)) {
            return $value;
        }

        if (is_string($value)) {
            return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        }

        return (bool) $value;
    }
}

