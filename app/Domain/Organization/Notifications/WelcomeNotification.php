<?php

namespace App\Domain\Organization\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent once, when the Super Administrator assigns an organization's
 * Administrador inicial (US-002). Reused as-is by the veedor invitation
 * flow (US-005/US-030, it. 5) — same "set your password" link, same
 * 48-hour token.
 */
class WelcomeNotification extends Notification
{
    public function __construct(
        public readonly string $url,
        public readonly int $validityHours = 48,
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
            ->subject('Bienvenido a GovTrace')
            ->greeting("Hola, {$notifiable->name}")
            ->line('Se creó tu cuenta en GovTrace.')
            ->action('Establecer mi contraseña', $this->url)
            ->line("Este enlace expira en {$this->validityHours} horas.");
    }
}
