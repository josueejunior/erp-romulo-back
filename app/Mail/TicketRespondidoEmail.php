<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Email enviado para o usuário/empresa quando o suporte (admin) responde a um ticket.
 */
class TicketRespondidoEmail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $numero,
        public readonly string $empresaNome,
        public readonly string $nomeUsuario,
        public readonly string $mensagem,
    ) {}

    public function build(): static
    {
        return $this->subject("Suporte respondeu ao seu ticket {$this->numero}")
            ->view('emails.ticket-respondido')
            ->with([
                'numero' => $this->numero,
                'empresaNome' => $this->empresaNome,
                'nomeUsuario' => $this->nomeUsuario,
                'mensagem' => $this->mensagem,
            ]);
    }
}
