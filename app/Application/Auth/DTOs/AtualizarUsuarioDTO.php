<?php

namespace App\Application\Auth\DTOs;

use Illuminate\Http\Request;

/**
 * DTO para atualização de usuário
 */
class AtualizarUsuarioDTO
{
    public function __construct(
        public readonly int $userId,
        public readonly ?string $nome = null,
        public readonly ?string $email = null,
        public readonly ?string $senha = null,
        public readonly ?int $empresaId = null,
        public readonly ?string $role = null,
    ) {}

    /**
     * Criar DTO a partir de Request
     * TenantContext é passado separadamente
     */
    public static function fromRequest(Request $request, int $userId): self
    {
        // Normalizar role (trim e garantir que está vazio se null)
        $role = $request->input('role');
        $role = $role ? trim($role) : null;

        // Normalizar senha: string vazia vira null
        $senha = $request->input('password');
        $senha = ($senha && trim($senha) !== '') ? trim($senha) : null;

        return new self(
            userId: $userId,
            nome: $request->input('name'),
            email: $request->input('email'),
            senha: $senha, // Pode ser null no update
            empresaId: $request->input('empresa_id'),
            role: $role,
        );
    }
}

