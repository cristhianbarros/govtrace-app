<?php

namespace App\Domain\Sealing\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * US-021: evidencias con más de 2 horas sin sellar. Al Super Administrador,
 * con las organizaciones afectadas; a cada Administrador, por la suya.
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
            ->subject('GovTrace: evidencias estancadas en la cola de sellado')
            ->line('⚠️ Alerta de Sistema: Hay evidencias con más de 2 horas estancadas en la cola de sellado. Revisa el estado de la red o del proveedor RPC.')
            ->line('Organizaciones: '.implode(', ', $this->organizations));
    }
}
