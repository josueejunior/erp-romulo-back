<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrcamentoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'processo_id' => $this->processo_id,
            'processo_item_id' => $this->processo_item_id, // Mantido para compatibilidade
            'fornecedor' => new FornecedorResource($this->whenLoaded('fornecedor')),
            'transportadora' => new TransportadoraResource($this->whenLoaded('transportadora')),
            'custo_produto' => (float) $this->custo_produto, // Mantido para compatibilidade
            'frete' => (float) $this->frete, // Mantido para compatibilidade
            'frete_incluido' => $this->frete_incluido, // Mantido para compatibilidade
            'custo_total' => (float) $this->custo_total,
            'marca_modelo' => $this->marca_modelo, // Mantido para compatibilidade
            'ajustes_especificacao' => $this->ajustes_especificacao, // Mantido para compatibilidade
            'fornecedor_escolhido' => $this->fornecedor_escolhido, // Mantido para compatibilidade
            'observacoes' => $this->observacoes,
            'formacao_preco' => new FormacaoPrecoResource($this->whenLoaded('formacaoPreco')),
            'itens' => $this->when($this->relationLoaded('itens'), function () {
                return $this->itens->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'processo_item_id' => $item->processo_item_id,
                        'processo_item' => $item->processoItem ? [
                            'id' => $item->processoItem->id,
                            'numero_item' => $item->processoItem->numero_item,
                            'especificacao_tecnica' => $item->processoItem->especificacao_tecnica,
                            'quantidade' => $item->processoItem->quantidade,
                            'unidade' => $item->processoItem->unidade,
                        ] : null,
                        'custo_produto' => (float) $item->custo_produto,
                        'marca_modelo' => $item->marca_modelo,
                        'ajustes_especificacao' => $item->ajustes_especificacao,
                        'frete' => (float) $item->frete,
                        'frete_incluido' => $item->frete_incluido,
                        'fornecedor_escolhido' => $item->fornecedor_escolhido,
                        'observacoes' => $item->observacoes,
                        'custo_total' => (float) $item->custo_total,
                        'formacao_preco' => $item->formacaoPreco ? [
                            'id' => $item->formacaoPreco->id,
                            'preco_minimo' => (float) $item->formacaoPreco->preco_minimo,
                            'preco_recomendado' => $item->formacaoPreco->preco_recomendado ? (float) $item->formacaoPreco->preco_recomendado : null,
                        ] : null,
                    ];
                });
            }),
            'has_multiple_items' => $this->when($this->relationLoaded('itens'), function () {
                return $this->itens->count() > 1;
            }),
        ];
    }
}
