<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * 🔥 DDD: FormRequest para criar pagamento de comissão no admin
 */
class CriarPagamentoComissaoAdminRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'afiliado_id' => 'required|exists:afiliados,id',
            'periodo_competencia' => 'required|date',
            'comissao_ids' => 'required|array',
            'comissao_ids.*' => 'exists:afiliado_comissoes_recorrentes,id',
            'metodo_pagamento' => 'nullable|string|max:50',
            'comprovante' => 'nullable|string|max:255', // URL ou referência (se não houver arquivo)
            'comprovante_arquivo' => 'nullable|file|mimes:pdf,jpg,jpeg,png,doc,docx|max:10240', // 10MB
            'observacoes' => 'nullable|string',
            'data_pagamento' => 'nullable|date',
        ];
    }

    /**
     * Preparar dados antes da validação
     * Converte comissao_ids de JSON string para array se necessário (quando enviado via FormData)
     */
    protected function prepareForValidation(): void
    {
        // Se comissao_ids vier como string JSON (quando enviado via FormData), converter para array
        if ($this->has('comissao_ids') && is_string($this->input('comissao_ids'))) {
            $comissaoIds = json_decode($this->input('comissao_ids'), true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($comissaoIds)) {
                $this->merge(['comissao_ids' => $comissaoIds]);
            }
        }
    }

    /**
     * Validação customizada: comprovante ou comprovante_arquivo deve ser fornecido (mas não ambos obrigatórios)
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Não é obrigatório ter comprovante ou arquivo
            // A validação padrão já cobre que arquivo é opcional e válido se fornecido
        });
    }
}





