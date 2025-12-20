<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrgaoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'empresa_id' => $this->empresa_id, // Adicionado para debug e validação
            'uasg' => $this->uasg,
            'razao_social' => $this->razao_social,
            'cnpj' => $this->cnpj,
            'email' => $this->email,
            'telefone' => $this->telefone,
            'endereco' => $this->endereco,
            'observacoes' => $this->observacoes,
            'setors' => $this->whenLoaded('setors'),
        ];
    }
}
