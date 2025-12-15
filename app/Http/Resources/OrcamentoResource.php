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
            'fornecedor' => new FornecedorResource($this->whenLoaded('fornecedor')),
            'transportadora' => new TransportadoraResource($this->whenLoaded('transportadora')),
            'custo_produto' => (float) $this->custo_produto,
            'frete' => (float) $this->frete,
            'frete_incluido' => $this->frete_incluido,
            'custo_total' => (float) $this->custo_total,
            'marca_modelo' => $this->marca_modelo,
            'ajustes_especificacao' => $this->ajustes_especificacao,
            'fornecedor_escolhido' => $this->fornecedor_escolhido,
            'observacoes' => $this->observacoes,
            'formacao_preco' => new FormacaoPrecoResource($this->whenLoaded('formacaoPreco')),
        ];
    }
}
