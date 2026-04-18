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
        \Illuminate\Support\Facades\Log::info('LaravelEventDispatcher::dispatch - Disparando evento', [
            'event_class' => get_class($event),
            'event_data' => $event instanceof \App\Domain\Tenant\Events\EmpresaCriada ? [
                'tenant_id' => $event->tenantId,
                'empresa_id' => $event->empresaId,
                'email' => $event->email,
            ] : ['data' => 'outro_evento'],
        ]);

        // Mapear Domain Events para Laravel Events
        $laravelEvent = $this->mapToLaravelEvent($event);
        
        try {
            // 🔥 LARAVEL EVENTS: Event::dispatch() dispara todos os listeners registrados
            // O listener EmpresaCriadaListener está registrado no AppServiceProvider::boot()
            Event::dispatch($laravelEvent);
            
            \Illuminate\Support\Facades\Log::info('LaravelEventDispatcher::dispatch - Evento disparado com sucesso', [
                'event_class' => get_class($event),
                'laravel_event_class' => get_class($laravelEvent),
                'listeners_registered' => count(\Illuminate\Support\Facades\Event::getListeners(get_class($laravelEvent)) ?? []),
            ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('LaravelEventDispatcher::dispatch - Erro ao disparar evento', [
                'event_class' => get_class($event),
                'error' => $e->getMessage(),
                'error_class' => get_class($e),
                'trace' => $e->getTraceAsString(),
            ]);
            // Não re-lançar exceção - permitir que o cadastro continue mesmo se houver erro no evento
            // O erro já foi logado e o listener tem tratamento de erro próprio
        }
    }

    public function dispatchAll(array $events): void
    {
        foreach ($events as $event) {
            $this->dispatch($event);
        }
    }

    /**
     * Mapear Domain Event para Laravel Event
     * 
     * 🔥 IMPORTANTE: Laravel Event::dispatch() funciona com qualquer objeto
     * Ele verifica listeners registrados via Event::listen() baseado na classe do objeto
     */
    private function mapToLaravelEvent(DomainEvent $event): object
    {
        // Usar o próprio evento - Laravel Event::dispatch() funciona com qualquer objeto
        // O listener está registrado no AppServiceProvider via Event::listen() usando a classe do evento
        return $event;
    }
}




