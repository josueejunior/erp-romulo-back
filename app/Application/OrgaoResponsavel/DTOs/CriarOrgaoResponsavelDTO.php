<?php

namespace App\Application\OrgaoResponsavel\DTOs;

/**
 * DTO para criação de responsável de órgão
 * O empresaId é obtido do TenantContext pelo Use Case, não vem do controller
 */
class CriarOrgaoResponsavelDTO
{
    public function __construct(
        public readonly int $orgaoId,
        public readonly string $nome,
        public readonly ?string $cargo = null,
        public readonly ?array $emails = null,
        public readonly ?array $telefones = null,
        public readonly ?string $observacoes = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            orgaoId: $data['orgao_id'] ?? $data['orgaoId'] ?? 0,
            nome: $data['nome'] ?? '',
            cargo: $data['cargo'] ?? null,
            emails: $data['emails'] ?? null,
            telefones: $data['telefones'] ?? null,
            observacoes: $data['observacoes'] ?? null,
        );
    }
}

