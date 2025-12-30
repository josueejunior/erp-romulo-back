<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource para padronizar resposta de autenticação
 * Garante estrutura consistente independente de ser admin ou usuário comum
 */
class AuthResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'user' => [
                'id' => $this->resource['user']['id'] ?? null,
                'name' => $this->resource['user']['name'] ?? null,
                'email' => $this->resource['user']['email'] ?? null,
                'empresa_ativa_id' => $this->resource['user']['empresa_ativa_id'] ?? null,
                'foto_perfil' => $this->resource['user']['foto_perfil'] ?? null,
            ],
            'tenant' => $this->resource['tenant'] ?? null,
            'empresa' => $this->resource['empresa'] ?? null,
            'token' => $this->resource['token'] ?? null,
            'is_admin' => $this->resource['is_admin'] ?? false,
        ];
    }
}




