<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FornecedorResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'razao_social' => $this->razao_social,
            'cnpj' => $this->cnpj,
            'nome_fantasia' => $this->nome_fantasia,
            'email' => $this->email,
            'telefone' => $this->telefone,
        ];
    }
}
