<?php

namespace App\Domain\CitizenReports\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** US-059-LEG: the veeduría answers the citizen, without ever seeing their email. */
class CitizenReportAnswered extends Notification
{
    public function __construct(
        public readonly int $number,
        public readonly string $organization,
        public readonly string $answer,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Respuesta a su informe n.º {$this->number}")
            ->greeting('Hola')
            ->line("{$this->organization} respondió su informe n.º {$this->number}:")
            ->line($this->answer);
    }
}
