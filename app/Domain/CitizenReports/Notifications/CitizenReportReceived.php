<?php

namespace App\Domain\CitizenReports\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** US-059-LEG: the veeduría got the citizen's report — with its number, to refer to it. */
class CitizenReportReceived extends Notification
{
    public function __construct(
        public readonly int $number,
        public readonly string $organization,
        public readonly string $worksite,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Recibimos su informe n.º {$this->number}")
            ->greeting('Hola')
            ->line("Su informe n.º {$this->number} sobre «{$this->worksite}» llegó a {$this->organization}.")
            ->line('Si lo atiende, la veeduría le responde a este correo. Su correo no se le muestra: la respuesta le llega desde GovTrace.');
    }
}
