<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class BemVindoEmail extends Mailable
{
    use Queueable, SerializesModels;

    public $user;
    public $tenant;
    public $assinatura;
    public $statusCobranca;

    /**
     * Create a new message instance.
     */
    public function __construct($user, $tenant, $assinatura = null)
    {
        $this->user = $user;
        $this->tenant = $tenant;
        $this->assinatura = $assinatura;
        
        // Verificar status da cobrança
        if ($assinatura) {
            $this->statusCobranca = $this->verificarStatusCobranca($assinatura);
        } else {
            $this->statusCobranca = null;
        }
    }

    /**
     * Verificar se a cobrança foi processada corretamente
     */
    private function verificarStatusCobranca($assinatura)
    {
        // Se a assinatura está ativa e tem método de pagamento válido
        if ($assinatura->status === 'ativa') {
            // Se tem transação ID, significa que o pagamento foi processado
            if ($assinatura->transacao_id) {
                return [
                    'status' => 'sucesso',
                    'mensagem' => 'Sua assinatura está ativa e o pagamento foi processado com sucesso!',
                ];
            } elseif ($assinatura->metodo_pagamento === 'gratuito') {
                return [
                    'status' => 'gratuito',
                    'mensagem' => 'Você está usando o plano gratuito de teste.',
                ];
            } else {
                return [
                    'status' => 'pendente',
                    'mensagem' => 'Seu pagamento está pendente. Verifique o status da sua assinatura.',
                ];
            }
        } elseif ($assinatura->status === 'suspensa' || $assinatura->status === 'pendente') {
            return [
                'status' => 'pendente',
                'mensagem' => 'Seu pagamento está pendente de aprovação. Você receberá uma notificação quando for aprovado.',
            ];
        } else {
            return [
                'status' => 'erro',
                'mensagem' => 'Houve um problema com o processamento do seu pagamento. Entre em contato conosco.',
            ];
        }
    }

    /**
     * Build the message.
     */
    public function build()
    {
        return $this->subject('Bem-vindo ao Sistema ERP - Gestão de Licitações')
                    ->view('emails.bem-vindo')
                    ->with([
                        'user' => $this->user,
                        'tenant' => $this->tenant,
                        'assinatura' => $this->assinatura,
                        'statusCobranca' => $this->statusCobranca,
                    ]);
    }
}

