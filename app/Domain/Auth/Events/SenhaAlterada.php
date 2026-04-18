<?php

namespace App\Domain\Auth\Events;

use App\Domain\Shared\Events\DomainEvent;
use DateTimeImmutable;

/**
 * Domain Event: SenhaAlterada
 * Disparado quando a senha de um usuário é alterada
 */
readonly class SenhaAlterada implements DomainEvent
{
    public function __construct(
        public int $userId,
        public string $email,
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




