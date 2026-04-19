<?php

namespace App\Domain\Suporte\Entities;

class TicketResponse
{
    public function __construct(
        public readonly ?int $id,
        public readonly int $ticketId,
        public readonly ?int $userId,
        public readonly string $authorType,
        public readonly string $mensagem,
        public readonly ?string $createdAt = null,
        public readonly ?string $updatedAt = null,
    ) {}
}
