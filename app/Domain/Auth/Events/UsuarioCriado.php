<?php

namespace App\Domain\Auth\Events;

use App\Domain\Shared\Events\DomainEvent;
use DateTimeImmutable;

/**
 * Domain Event: UsuarioCriado
 * Disparado quando um novo usuário é criado
 */
readonly class UsuarioCriado implements DomainEvent
{
    public function __construct(
        public int $userId,
        public string $email,
        public string $nome,
        public int $tenantId,
        public int $empresaId,
        public DateTimeImmutable $ocorreuEm = new DateTimeImmutable()
    ) {}

    public function ocorreuEm(): DateTimeImmutable
    {
        return $this->ocorreuEm;
    }

    public function agregadoId(): string
    {
        return (string) $this->userId;
    }
}

