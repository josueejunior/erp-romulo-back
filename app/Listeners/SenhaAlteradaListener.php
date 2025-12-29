<?php

namespace App\Listeners;

use App\Domain\Auth\Events\SenhaAlterada;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Listener para evento SenhaAlterada
 * Ações de segurança desacopladas
 */
class SenhaAlteradaListener
{
    /**
     * Handle the event.
     */
    public function handle(SenhaAlterada $event): void
    {
        // Log de segurança
        Log::warning('Senha alterada', [
            'user_id' => $event->userId,
            'email' => $event->email,
            'ocorreu_em' => $event->ocorreuEm()->format('Y-m-d H:i:s'),
        ]);

        // Enviar e-mail de notificação de segurança
        // Mail::to($event->email)->send(new PasswordChangedNotification());

        // Pode invalidar tokens antigos, notificar admin, etc.
    }
}

