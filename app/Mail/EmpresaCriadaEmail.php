<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Email enviado quando uma empresa é criada no sistema
 */
class EmpresaCriadaEmail extends Mailable
{
    use Queueable, SerializesModels;

    public $tenant;
    public $empresa;

    /**
     * Create a new message instance.
     */
    public function __construct($tenant, $empresa)
    {
        $this->tenant = $tenant;
        $this->empresa = $empresa;
    }

    /**
     * Build the message.
     */
    public function build()
    {
        return $this->subject('Empresa Cadastrada com Sucesso - Sistema ERP')
            ->view('emails.empresa-criada')
            ->with([
                'tenant' => $this->tenant,
                'empresa' => $this->empresa,
            ]);
    }
}

