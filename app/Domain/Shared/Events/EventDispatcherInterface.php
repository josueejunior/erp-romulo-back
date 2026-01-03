<?php

namespace App\Domain\Shared\Events;

/**
 * Interface para dispatcher de eventos
 * O domínio não sabe como os eventos são disparados (Laravel Events, RabbitMQ, etc.)
 */
interface EventDispatcherInterface
{
    /**
     * Disparar um evento de domínio
     */
    public function dispatch(DomainEvent $event): void;

    /**
     * Disparar múltiplos eventos
     */
    public function dispatchAll(array $events): void;
}



