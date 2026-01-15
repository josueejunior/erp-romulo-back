<?php

namespace App\Modules\Processo\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\OrcamentoResource;
use App\Http\Resources\FormacaoPrecoResource;

class ProcessoItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'processo_id' => $this->processo_id,
            'fornecedor_id' => $this->fornecedor_id,
            'transportadora_id' => $this->transportadora_id,
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
            
            // Valores financeiros (garantir que valores 0 são retornados, não null)
            'valor_vencido' => $this->valor_vencido !== null ? (float) $this->valor_vencido : 0.0,
            'valor_empenhado' => $this->valor_empenhado !== null ? (float) $this->valor_empenhado : 0.0,
            'valor_faturado' => $this->valor_faturado !== null ? (float) $this->valor_faturado : 0.0,
            'valor_pago' => $this->valor_pago !== null ? (float) $this->valor_pago : 0.0,
            'saldo_aberto' => $this->saldo_aberto !== null ? (float) $this->saldo_aberto : 0.0,
            'lucro_bruto' => $this->lucro_bruto !== null ? (float) $this->lucro_bruto : 0.0,
            'lucro_liquido' => $this->lucro_liquido !== null ? (float) $this->lucro_liquido : 0.0,
            
            // Quantidades
            'quantidade_empenhada' => (float) $this->quantidade_empenhada,
            'quantidade_restante' => (float) $this->quantidade_restante,
            
            // Relacionamentos
            'fornecedor' => $this->when(
                $this->relationLoaded('fornecedor'),
                fn() => [
                    'id' => $this->fornecedor->id,
                    'razao_social' => $this->fornecedor->razao_social,
                    'cnpj' => $this->fornecedor->cnpj,
                ]
            ),
            'transportadora' => $this->when(
                $this->relationLoaded('transportadora'),
                fn() => [
                    'id' => $this->transportadora->id,
                    'razao_social' => $this->transportadora->razao_social,
                    'cnpj' => $this->transportadora->cnpj,
                ]
            ),
            'orcamentos' => $this->when(
                true, // Sempre executar para ter logs
                function () {
                    $relationLoaded = $this->relationLoaded('orcamentos');
                    $orcamentos = $relationLoaded ? $this->orcamentos : null;
                    
                    \Log::debug('ProcessoItemResource::toArray - Verificando orçamentos', [
                        'tenant_id' => tenancy()->tenant?->id,
                        'empresa_id' => $this->empresa_id ?? null,
                        'processo_item_id' => $this->id,
                        'relation_loaded' => $relationLoaded,
                        'orcamentos_is_null' => $orcamentos === null,
                        'orcamentos_is_collection' => $orcamentos instanceof \Illuminate\Support\Collection,
                        'total_orcamentos' => $orcamentos ? $orcamentos->count() : 0,
                        'orcamento_ids' => $orcamentos ? $orcamentos->pluck('id')->toArray() : [],
                        'relations_loaded' => array_keys($this->getRelations()),
                    ]);
                    
                    if ($relationLoaded && $orcamentos && $orcamentos->count() > 0) {
                        return OrcamentoResource::collection($orcamentos);
                    }
                    
                    // Se não está carregado, retornar array vazio mas logar
                    if (!$relationLoaded) {
                        \Log::warning('ProcessoItemResource::toArray - Relacionamento orcamentos NÃO carregado', [
                            'tenant_id' => tenancy()->tenant?->id,
                            'empresa_id' => $this->empresa_id ?? null,
                            'processo_item_id' => $this->id,
                        ]);
                    } else if ($orcamentos && $orcamentos->count() === 0) {
                        \Log::info('ProcessoItemResource::toArray - Relacionamento carregado mas vazio', [
                            'tenant_id' => tenancy()->tenant?->id,
                            'empresa_id' => $this->empresa_id ?? null,
                            'processo_item_id' => $this->id,
                        ]);
                    }
                    
                    return [];
                }
            ),
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
