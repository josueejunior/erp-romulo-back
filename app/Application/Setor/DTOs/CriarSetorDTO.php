<?php

namespace App\Application\Setor\DTOs;

/**
 * DTO para criação de setor
 */
class CriarSetorDTO
{
    public function __construct(
        public readonly int $empresaId,
        public readonly ?int $orgaoId = null,
        public readonly string $nome = '',
        public readonly ?string $email = null,
        public readonly ?string $telefone = null,
        public readonly ?string $observacoes = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            empresaId: $data['empresa_id'] ?? $data['empresaId'] ?? 0,
            orgaoId: $data['orgao_id'] ?? $data['orgaoId'] ?? null,
            nome: $data['nome'] ?? '',
            email: $data['email'] ?? null,
            telefone: $data['telefone'] ?? null,
            observacoes: $data['observacoes'] ?? null,
        );
    }
}


