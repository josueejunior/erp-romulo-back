<?php

namespace App\Domain\Shared\Events;

use DateTimeImmutable;

/**
 * Interface base para Domain Events
 * Todos os eventos do domínio devem implementar esta interface
 */
interface DomainEvent
{
    /**
     * Ocorreu em
     */
    public function ocorreuEm(): DateTimeImmutable;

    /**
     * ID do agregado que gerou o evento
     */
    public function agregadoId(): string;
}

