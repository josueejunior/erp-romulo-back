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
            'uasg' => $this->uasg,
            'razao_social' => $this->razao_social,
            'cnpj' => $this->cnpj,
            'email' => $this->email,
        ];
    }
}
