<?php

namespace App\Domain\Organization\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** US-062-ALT (it. 43k): why the alta a veeduría asked for was not approved. */
class OrganizationRequestRejected extends Notification
{
    public function __construct(public readonly string $organization, public readonly string $reason) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('GovTrace: su solicitud de alta no fue aprobada')
            ->greeting('Hola')
            ->line("Revisamos la solicitud de alta de {$this->organization} en GovTrace, y no fue aprobada.")
            ->line("Motivo: {$this->reason}")
            ->line('Si puede corregirlo, envíe una solicitud nueva desde el Inicio de GovTrace.');
    }
}
