<?php

namespace App\Domain\Tenant\Events;

use App\Domain\Shared\Events\DomainEvent;
use DateTimeImmutable;

/**
 * Domain Event: EmpresaVinculada
 * Disparado quando uma empresa é vinculada a um usuário
 */
readonly class EmpresaVinculada implements DomainEvent
{
    public function __construct(
        public int $userId,
        public int $empresaId,
        public int $tenantId,
        public string $perfil,
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



