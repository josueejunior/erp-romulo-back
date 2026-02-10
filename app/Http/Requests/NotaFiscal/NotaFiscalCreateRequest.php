<?php

namespace App\Http\Requests\NotaFiscal;

use Illuminate\Foundation\Http\FormRequest;

class NotaFiscalCreateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'empenho_id' => 'nullable|integer|exists:empenhos,id',
            'contrato_id' => 'nullable|integer|exists:contratos,id',
            'autorizacao_fornecimento_id' => 'nullable|integer|exists:autorizacao_fornecimentos,id',
            'tipo' => 'required|string|max:255',
            'numero' => 'required|string|max:255',
            'serie' => 'nullable|string|max:255',
            'data_emissao' => 'required|date',
            'fornecedor_id' => 'nullable|integer|exists:fornecedores,id',
            // 🔥 Campos de Logística obrigatórios
            'transportadora' => 'required|string|max:255',
            'numero_cte' => 'nullable|string|max:255',
            'data_entrega_prevista' => 'nullable|date',
            'data_entrega_realizada' => 'nullable|date',
            'situacao_logistica' => 'required|string|in:aguardando_envio,em_transito,entregue,atrasada',
            'valor' => 'required|numeric|min:0',
            'custo_produto' => 'nullable|numeric|min:0',
            'custo_frete' => 'nullable|numeric|min:0',
            'comprovante_pagamento' => 'nullable|string',
            'arquivo' => 'nullable|string',
            // 🔥 Campos de Pagamento obrigatórios
            // Situação sempre obrigatória; data_pagamento obrigatória quando situacao = paga
            'situacao' => 'required|string|in:pendente,paga,cancelada',
            'data_pagamento' => 'required_if:situacao,paga|nullable|date',
            'observacoes' => 'nullable|string',
            'itens' => 'nullable|array',
            'itens.*.processo_item_id' => 'required|integer|exists:processo_itens,id',
            'itens.*.quantidade' => 'required|numeric|min:0',
            'itens.*.valor_unitario' => 'required|numeric|min:0',
        ];
    }

    /**
     * Mensagens personalizadas de validação
     * para evitar retornar apenas \"validation.required\".
     */
    public function messages(): array
    {
        return [
            'tipo.required' => 'O tipo da nota fiscal é obrigatório.',
            'numero.required' => 'O número da nota fiscal é obrigatório.',
            'data_emissao.required' => 'A data de emissão da nota fiscal é obrigatória.',

            'transportadora.required' => 'O campo Transportadora é obrigatório.',
            'situacao_logistica.required' => 'A situação logística é obrigatória.',

            'valor.required' => 'O valor da nota fiscal é obrigatório.',

            'situacao.required' => 'A situação do pagamento é obrigatória.',
            'situacao.in' => 'A situação do pagamento deve ser Pendente, Paga ou Cancelada.',
            'data_pagamento.required_if' => 'A data de pagamento é obrigatória quando a situação for Paga.',

            'itens.*.processo_item_id.required' => 'Selecione ao menos um item do processo para vincular à nota fiscal.',
            'itens.*.quantidade.required' => 'Informe a quantidade para cada item vinculado.',
            'itens.*.valor_unitario.required' => 'Informe o valor unitário para cada item vinculado.',
        ];
    }
}



