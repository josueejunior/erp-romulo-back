<?php

namespace App\Application\Auth\DTOs;

use Illuminate\Http\Request;

/**
 * DTO para criação de usuário
 * Transporta dados entre camadas sem expor entidades
 */
class CriarUsuarioDTO
{
    public function __construct(
        public readonly int $tenantId,
        public readonly string $nome,
        public readonly string $email,
        public readonly string $senha,
        public readonly int $empresaId,
        public readonly string $role = 'Usuário',
    ) {}

    /**
     * Criar DTO a partir de Request
     */
    public static function fromRequest(Request $request, int $tenantId): self
    {
        return new self(
            tenantId: $tenantId,
            nome: $request->input('name'),
            email: $request->input('email'),
            senha: $request->input('password'),
            empresaId: $request->input('empresa_id'),
            role: $request->input('role', 'Usuário'),
        );
    }
}

