<?php

namespace App\Application\Suporte\DTOs;

class CriarTicketDTO
{
    public function __construct(
        public readonly int $userId,
        public readonly int $empresaId,
        public readonly string $descricao,
        public readonly ?string $anexoUrl = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            userId: (int) ($data['user_id'] ?? $data['userId'] ?? 0),
            empresaId: (int) ($data['empresa_id'] ?? $data['empresaId'] ?? 0),
            descricao: (string) ($data['descricao'] ?? ''),
            anexoUrl: $data['anexo_url'] ?? $data['anexoUrl'] ?? null,
        );
    }
}
