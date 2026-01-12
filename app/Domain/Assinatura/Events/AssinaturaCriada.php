<?php

declare(strict_types=1);

namespace App\Domain\Assinatura\Events;

use App\Domain\Shared\Events\DomainEvent;
use DateTimeImmutable;

/**
 * Domain Event: AssinaturaCriada
 * Disparado quando uma nova assinatura é criada
 */
readonly class AssinaturaCriada implements DomainEvent
{
    public function __construct(
        public int $assinaturaId,
        public int $tenantId,
        public int $empresaId,
        public ?int $userId,
        public int $planoId,
        public string $status,
        public ?string $emailDestino = null,
        public DateTimeImmutable $ocorreuEm = new DateTimeImmutable()
    ) {}

    public function ocorreuEm(): DateTimeImmutable
    {
        return $this->ocorreuEm;
    }

    public function agregadoId(): string
    {
        return (string) $this->assinaturaId;
    }
}



