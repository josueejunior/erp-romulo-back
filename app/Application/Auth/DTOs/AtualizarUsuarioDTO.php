<?php

namespace App\Application\Auth\DTOs;

use Illuminate\Http\Request;

/**
 * DTO para atualização de usuário
 */
class AtualizarUsuarioDTO
{
    public function __construct(
        public readonly int $tenantId,
        public readonly int $userId,
        public readonly ?string $nome = null,
        public readonly ?string $email = null,
        public readonly ?string $senha = null,
        public readonly ?int $empresaId = null,
        public readonly ?string $role = null,
    ) {}

    /**
     * Criar DTO a partir de Request
     */
    public static function fromRequest(Request $request, int $tenantId, int $userId): self
    {
        return new self(
            tenantId: $tenantId,
            userId: $userId,
            nome: $request->input('name'),
            email: $request->input('email'),
            senha: $request->input('password'),
            empresaId: $request->input('empresa_id'),
            role: $request->input('role'),
        );
    }
}

