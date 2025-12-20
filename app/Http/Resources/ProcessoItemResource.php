<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProcessoItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'processo_id' => $this->processo_id,
            'numero_item' => $this->numero_item,
            'codigo_interno' => $this->codigo_interno,
            'quantidade' => (float) $this->quantidade,
            'unidade' => $this->unidade,
            'especificacao_tecnica' => $this->especificacao_tecnica,
            'marca_modelo_referencia' => $this->marca_modelo_referencia,
            'observacoes_edital' => $this->observacoes_edital,
            'exige_atestado' => $this->exige_atestado,
            'quantidade_minima_atestado' => $this->quantidade_minima_atestado,
            'quantidade_atestado_cap_tecnica' => $this->quantidade_atestado_cap_tecnica,
            
            // Valores
            'valor_estimado' => $this->valor_estimado ? (float) $this->valor_estimado : null,
            'valor_estimado_total' => $this->valor_estimado_total ? (float) $this->valor_estimado_total : null,
            'fonte_valor' => $this->fonte_valor,
            'valor_minimo_venda' => $this->valor_minimo_venda ? (float) $this->valor_minimo_venda : null,
            'valor_final_sessao' => $this->valor_final_sessao ? (float) $this->valor_final_sessao : null,
            'data_disputa' => $this->data_disputa?->format('Y-m-d'),
            'valor_negociado' => $this->valor_negociado ? (float) $this->valor_negociado : null,
            
            // Status e classificação
            'status_item' => $this->status_item,
            'status_descricao' => $this->status_descricao,
            'situacao_final' => $this->situacao_final,
            'classificacao' => $this->classificacao,
            'chance_arremate' => $this->chance_arremate,
            'chance_percentual' => $this->chance_percentual,
            
            // Histórico e observações
            'lembretes' => $this->lembretes,
            'observacoes' => $this->observacoes,
            'historico_valores' => $this->historico_valores,
            
            // Valores financeiros
            'valor_vencido' => (float) $this->valor_vencido,
            'valor_empenhado' => (float) $this->valor_empenhado,
            'valor_faturado' => (float) $this->valor_faturado,
            'valor_pago' => (float) $this->valor_pago,
            'saldo_aberto' => (float) $this->saldo_aberto,
            'lucro_bruto' => (float) $this->lucro_bruto,
            'lucro_liquido' => (float) $this->lucro_liquido,
            
            // Quantidades
            'quantidade_empenhada' => (float) $this->quantidade_empenhada,
            'quantidade_restante' => (float) $this->quantidade_restante,
            
            // Relacionamentos
            'orcamentos' => OrcamentoResource::collection($this->whenLoaded('orcamentos')),
            'orcamento_escolhido' => $this->when(
                $this->relationLoaded('orcamentos'),
                fn() => new OrcamentoResource($this->orcamentoEscolhido)
            ),
            'formacoes_preco' => FormacaoPrecoResource::collection($this->whenLoaded('formacoesPreco')),
            'formacao_preco_ativa' => $this->when(
                $this->relationLoaded('formacoesPreco'),
                fn() => $this->formacaoPrecoAtiva ? new FormacaoPrecoResource($this->formacaoPrecoAtiva) : null
            ),
            'vinculos' => $this->whenLoaded('vinculos'),
            'vinculos_contrato' => $this->whenLoaded('vinculosContrato'),
            'vinculos_af' => $this->whenLoaded('vinculosAF'),
            'vinculos_empenho' => $this->whenLoaded('vinculosEmpenho'),
            
            // Flags
            'is_vencido' => $this->isVencido(),
            'is_perdido' => $this->isPerdido(),
            'custo_total' => $this->getCustoTotal(),
        ];
    }
}
