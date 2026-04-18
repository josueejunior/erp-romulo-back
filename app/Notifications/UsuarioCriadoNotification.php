<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notificação enviada quando um usuário é criado no painel admin
 * Inclui as credenciais de acesso (email e senha)
 */
class UsuarioCriadoNotification extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public string $nome,
        public string $email,
        public string $senha,
        public ?string $role = null,
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $frontendUrl = config('app.frontend_url') ?? env('FRONTEND_URL') ?? 'https://gestor.addsimp.com';
        
        // Se a URL não contém 'gestor.', forçar uso de gestor.addsimp.com
        if (!str_contains($frontendUrl, 'gestor.')) {
            $frontendUrl = 'https://gestor.addsimp.com';
        }

        $loginUrl = "{$frontendUrl}/login";

        $message = (new MailMessage)
            ->subject('Conta Criada - Sistema ERP - Gestão de Licitações')
            ->greeting("Olá, {$this->nome}!")
            ->line('Sua conta foi criada com sucesso no Sistema ERP - Gestão de Licitações.')
            ->line('**Abaixo estão suas credenciais de acesso:**')
            ->line("**Email:** {$this->email}")
            ->line("**Senha:** {$this->senha}")
            ->action('Acessar Sistema', $loginUrl)
            ->line('⚠️ **Importante:** Por segurança, recomendamos que você altere sua senha após o primeiro acesso.')
            ->line('Se você não solicitou a criação desta conta, entre em contato conosco imediatamente.')
            ->salutation('Atenciosamente, Equipe Sistema ERP');

        if ($this->role) {
            $message->line("**Perfil:** {$this->role}");
        }

        return $message;
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'nome' => $this->nome,
            'email' => $this->email,
            'role' => $this->role,
        ];
    }
}

