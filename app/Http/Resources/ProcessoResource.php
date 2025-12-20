<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProcessoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'identificador' => $this->identificador,
            'numero_modalidade' => $this->numero_modalidade,
            'modalidade' => $this->modalidade,
            'objeto_resumido' => $this->objeto_resumido,
            'status' => $this->status,
            'data_hora_sessao_publica' => $this->data_hora_sessao_publica?->format('Y-m-d H:i:s'),
            'orgao' => new OrgaoResource($this->whenLoaded('orgao')),
            'setor' => new SetorResource($this->whenLoaded('setor')),
            'itens' => ProcessoItemResource::collection($this->whenLoaded('itens')),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
