<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

class ResetPasswordNotification extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public string $token
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
        // URL do frontend para redefinir senha
        // 🔥 GARANTIR: Sempre usar gestor.addsimp.com (domínio correto)
        $frontendUrl = config('app.frontend_url') ?? env('FRONTEND_URL') ?? 'https://gestor.addsimp.com';
        
        // Se a URL não contém 'gestor.', forçar uso de gestor.addsimp.com
        if (!str_contains($frontendUrl, 'gestor.')) {
            $frontendUrl = 'https://gestor.addsimp.com';
        }
        
        $resetUrl = "{$frontendUrl}/resetar-senha?token={$this->token}&email=" . urlencode($notifiable->email);

        return (new MailMessage)
            ->subject('Redefinição de Senha - Sistema ERP')
            ->greeting('Olá!')
            ->line('Você está recebendo este e-mail porque recebemos uma solicitação de redefinição de senha para sua conta.')
            ->action('Redefinir Senha', $resetUrl)
            ->line('Este link de redefinição de senha expirará em 60 minutos.')
            ->line('Se você não solicitou uma redefinição de senha, nenhuma ação adicional é necessária.')
            ->salutation('Atenciosamente, Equipe Sistema ERP');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'token' => $this->token,
        ];
    }
}



