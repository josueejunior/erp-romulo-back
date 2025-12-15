<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FormacaoPrecoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'custo_produto' => (float) $this->custo_produto,
            'frete' => (float) $this->frete,
            'percentual_impostos' => (float) $this->percentual_impostos,
            'valor_impostos' => (float) $this->valor_impostos,
            'percentual_margem' => (float) $this->percentual_margem,
            'valor_margem' => (float) $this->valor_margem,
            'preco_minimo' => (float) $this->preco_minimo,
            'preco_recomendado' => $this->preco_recomendado ? (float) $this->preco_recomendado : null,
            'observacoes' => $this->observacoes,
        ];
    }
}
