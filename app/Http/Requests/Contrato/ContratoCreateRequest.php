<?php

namespace App\Http\Requests\Contrato;

use Illuminate\Foundation\Http\FormRequest;

class ContratoCreateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'numero' => 'nullable|string|max:255',
            'data_inicio' => 'nullable|date',
            'data_fim' => 'nullable|date|after_or_equal:data_inicio',
            'data_assinatura' => 'nullable|date',
            'valor_total' => 'nullable|numeric|min:0',
            'condicoes_comerciais' => 'nullable|string',
            'condicoes_tecnicas' => 'nullable|string',
            'locais_entrega' => 'nullable|string',
            'prazos_contrato' => 'nullable|string',
            'regras_contrato' => 'nullable|string',
            'situacao' => 'nullable|string|in:vigente,encerrado,cancelado,ativo,suspenso',
            'status' => 'nullable|string|in:vigente,encerrado,cancelado,ativo,suspenso',
            'vigente' => 'nullable|boolean',
            'observacoes' => 'nullable|string',
            'arquivo_contrato' => 'nullable|file|mimes:pdf,doc,docx|max:10240', // 10MB
            'numero_cte' => 'nullable|string|max:255',
        ];
    }

    /**
     * Mensagens de erro customizadas
     */
    public function messages(): array
    {
        return [
            'arquivo_contrato.file' => 'O arquivo do contrato deve ser um arquivo válido.',
            'arquivo_contrato.mimes' => 'O arquivo do contrato deve ser PDF, DOC ou DOCX.',
            'arquivo_contrato.max' => 'O arquivo do contrato não pode exceder 10MB.',
            'data_fim.after_or_equal' => 'A data de fim deve ser igual ou posterior à data de início.',
            'valor_total.numeric' => 'O valor total deve ser um número.',
            'valor_total.min' => 'O valor total não pode ser negativo.',
        ];
    }
}



