<?php

namespace App\Listeners;

use App\Domain\Auth\Events\UsuarioCriado;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Listener para evento UsuarioCriado
 * Ações secundárias desacopladas do domínio
 */
class UsuarioCriadoListener
{
    /**
     * Handle the event.
     */
    public function handle(UsuarioCriado $event): void
    {
        // Log de auditoria
        Log::info('Usuário criado', [
            'user_id' => $event->userId,
            'email' => $event->email,
            'tenant_id' => $event->tenantId,
            'empresa_id' => $event->empresaId,
        ]);

        // Enviar e-mail de boas-vindas (opcional, pode ser feito em queue)
        // Mail::to($event->email)->send(new WelcomeEmail($event->nome));

        // Notificações, webhooks, etc.
    }
}

