<?php

namespace App\Infrastructure\Events;

use App\Domain\Shared\Events\DomainEvent;
use App\Domain\Shared\Events\EventDispatcherInterface;
use Illuminate\Support\Facades\Event;

/**
 * Implementação do Event Dispatcher usando Laravel Events
 * Conhece detalhes de infraestrutura (Laravel)
 */
class LaravelEventDispatcher implements EventDispatcherInterface
{
    public function dispatch(DomainEvent $event): void
    {
        // Mapear Domain Events para Laravel Events
        $laravelEvent = $this->mapToLaravelEvent($event);
        Event::dispatch($laravelEvent);
    }

    public function dispatchAll(array $events): void
    {
        foreach ($events as $event) {
            $this->dispatch($event);
        }
    }

    /**
     * Mapear Domain Event para Laravel Event
     */
    private function mapToLaravelEvent(DomainEvent $event): object
    {
        // Por enquanto, usar o próprio evento
        // Pode criar wrappers específicos se necessário
        return $event;
    }
}



