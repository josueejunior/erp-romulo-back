<?php

namespace App\Application\Setor\DTOs;

/**
 * DTO para atualização de setor
 * O empresaId é obtido do TenantContext pelo Use Case, não vem do controller
 */
class AtualizarSetorDTO
{
    public function __construct(
        public readonly ?int $orgaoId = null,
        public readonly string $nome = '',
        public readonly ?string $email = null,
        public readonly ?string $telefone = null,
        public readonly ?string $observacoes = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            orgaoId: $data['orgao_id'] ?? $data['orgaoId'] ?? null,
            nome: $data['nome'] ?? '',
            email: $data['email'] ?? null,
            telefone: $data['telefone'] ?? null,
            observacoes: $data['observacoes'] ?? null,
        );
    }
}

