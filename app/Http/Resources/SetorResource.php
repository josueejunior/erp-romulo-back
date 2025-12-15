<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SetorResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'orgao_id' => $this->orgao_id,
            'nome' => $this->nome,
            'email' => $this->email,
            'telefone' => $this->telefone,
            'observacoes' => $this->observacoes,
            'orgao' => $this->whenLoaded('orgao'),
        ];
    }
}
