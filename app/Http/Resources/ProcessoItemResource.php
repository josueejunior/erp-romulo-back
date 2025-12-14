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
            'numero_item' => $this->numero_item,
            'quantidade' => (float) $this->quantidade,
            'unidade' => $this->unidade,
            'especificacao_tecnica' => $this->especificacao_tecnica,
            'marca_modelo_referencia' => $this->marca_modelo_referencia,
            'valor_estimado' => $this->valor_estimado ? (float) $this->valor_estimado : null,
            'valor_final_sessao' => $this->valor_final_sessao ? (float) $this->valor_final_sessao : null,
            'valor_negociado' => $this->valor_negociado ? (float) $this->valor_negociado : null,
            'status_item' => $this->status_item,
            'classificacao' => $this->classificacao,
            'orcamentos' => OrcamentoResource::collection($this->whenLoaded('orcamentos')),
            'formacoes_preco' => FormacaoPrecoResource::collection($this->whenLoaded('formacoesPreco')),
        ];
    }
}
