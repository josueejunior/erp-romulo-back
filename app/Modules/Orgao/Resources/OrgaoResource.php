<?php

namespace App\Modules\Orgao\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrgaoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'empresa_id' => $this->empresa_id,
            'uasg' => $this->uasg,
            'razao_social' => $this->razao_social,
            'cnpj' => $this->cnpj,
            'email' => $this->email,
            'telefone' => $this->telefone,
            'telefones' => $this->telefones ?? [],
            'emails' => $this->emails ?? [],
            // Campos de endereço separados
            'cep' => $this->cep,
            'logradouro' => $this->logradouro,
            'numero' => $this->numero,
            'bairro' => $this->bairro,
            'complemento' => $this->complemento,
            'cidade' => $this->cidade,
            'estado' => $this->estado,
            'observacoes' => $this->observacoes,
            'setors' => $this->whenLoaded('setors'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}


