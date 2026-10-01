<?php

namespace App\Domain\Organization\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * US-060-MON (it. 43i): once a day, how many evidences wait for the
 * Administrador's review, with a button to the inbox. Only when there are.
 */
class ReviewDigest extends Notification
{
    public function __construct(
        public readonly int $pending,
        public readonly string $organization,
        public readonly string $inboxUrl,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $one = $this->pending === 1;

        return (new MailMessage)
            ->subject("GovTrace: {$this->pending} ".($one ? 'evidencia' : 'evidencias')." por revisar en {$this->organization}")
            ->greeting("Hola, {$notifiable->name}")
            ->line($one
                ? '1 evidencia de sus veedores espera su revisión. Mientras no la revise, no aparece en el mapa público.'
                : "{$this->pending} evidencias de sus veedores esperan su revisión. Mientras no las revise, no aparecen en el mapa público.")
            ->action('Revisar la Bandeja', $this->inboxUrl)
            ->line('Le llega este resumen una vez al día, solo cuando hay evidencias por revisar.');
    }
}
