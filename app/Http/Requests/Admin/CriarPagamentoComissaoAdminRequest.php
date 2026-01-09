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
            'comprovante' => 'nullable|string|max:255',
            'observacoes' => 'nullable|string',
            'data_pagamento' => 'nullable|date',
        ];
    }
}

