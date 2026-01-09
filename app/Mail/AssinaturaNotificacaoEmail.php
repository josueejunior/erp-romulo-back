<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Email enviado quando uma assinatura é criada ou atualizada
 */
class AssinaturaNotificacaoEmail extends Mailable
{
    use Queueable, SerializesModels;

    public array $assinatura;
    public array $plano;
    public array $empresa;
    public bool $isNovaAssinatura;

    /**
     * Create a new message instance.
     */
    public function __construct(array $assinatura, array $plano, array $empresa, bool $isNovaAssinatura = true)
    {
        $this->assinatura = $assinatura;
        $this->plano = $plano;
        $this->empresa = $empresa;
        $this->isNovaAssinatura = $isNovaAssinatura;
    }

    /**
     * Build the message.
     */
    public function build()
    {
        $subject = $this->isNovaAssinatura 
            ? 'Assinatura Criada com Sucesso - Sistema ERP'
            : 'Assinatura Atualizada - Sistema ERP';

        return $this->subject($subject)
            ->view('emails.assinatura-notificacao')
            ->with([
                'assinatura' => $this->assinatura,
                'plano' => $this->plano,
                'empresa' => $this->empresa,
                'isNovaAssinatura' => $this->isNovaAssinatura,
            ]);
    }
}


