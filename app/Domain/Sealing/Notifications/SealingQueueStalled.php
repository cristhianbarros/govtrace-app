<?php

namespace App\Domain\Sealing\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * US-021: evidencias con más de 2 horas sin sellar. Al Super Administrador,
 * con las organizaciones afectadas; a cada Administrador, por la suya. It.
 * 45e (enmienda): de usted y sin jerga técnica.
 */
class SealingQueueStalled extends Notification
{
    /** @param  list<string>  $organizations */
    public function __construct(public readonly array $organizations) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->error()
            ->subject('GovTrace: evidencias en fila para ser certificadas')
            ->line('⚠️ Aviso: hay evidencias que llevan más de 2 horas en fila para ser certificadas de forma segura. GovTrace sigue intentándolo solo; ninguna se pierde.')
            ->line('Organizaciones: '.implode(', ', $this->organizations));
    }
}
