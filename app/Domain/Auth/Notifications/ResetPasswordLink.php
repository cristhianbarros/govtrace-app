<?php

namespace App\Domain\Auth\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * US-039-USR: the link to choose a new password. It lasts 60 minutes and
 * works once; the token only travels here, the database keeps its hash.
 */
class ResetPasswordLink extends Notification
{
    public function __construct(
        public readonly string $url,
        #[\SensitiveParameter] public readonly string $token,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Restablecer su contraseña de GovTrace')
            ->greeting("Hola, {$notifiable->name}")
            ->line('Recibimos una solicitud para restablecer su contraseña.')
            ->action('Elegir una nueva contraseña', $this->url)
            ->line('Este enlace vence en 60 minutos y sirve una sola vez. Si usted no lo pidió, ignore este correo: su contraseña no cambia.');
    }
}
