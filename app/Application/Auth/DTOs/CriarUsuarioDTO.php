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
        public readonly string $nome,
        public readonly string $email,
        public readonly string $senha,
        public readonly int $empresaId,
        public readonly string $role = 'Consulta',
    ) {}

    /**
     * Criar DTO a partir de Request
     * TenantContext é passado separadamente
     */
    public static function fromRequest(Request $request): self
    {
        // Normalizar role (trim e usar default se não fornecido)
        $role = $request->input('role');
        $role = $role ? trim($role) : 'Consulta';

        return new self(
            nome: $request->input('name'),
            email: $request->input('email'),
            senha: $request->input('password'),
            empresaId: $request->input('empresa_id'),
            role: $role,
        );
    }
}

