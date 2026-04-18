<?php

namespace App\Application\Auth\DTOs;

use Illuminate\Http\Request;

/**
 * DTO para login de usuário
 * Transporta dados entre camadas sem expor entidades
 */
class LoginDTO
{
    public function __construct(
        public readonly string $email,
        public readonly string $password,
        public readonly ?string $tenantId = null,
    ) {}

    /**
     * Criar DTO a partir de Request
     */
    public static function fromRequest(Request $request): self
    {
        return new self(
            email: $request->input('email'),
            password: $request->input('password'),
            tenantId: $request->input('tenant_id'), // Opcional - será detectado automaticamente se não fornecido
        );
    }
}

