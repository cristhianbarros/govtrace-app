<?php

namespace App\Domain\CitizenReports\Notifications;

use App\Infrastructure\Notifications\QueuedNotification;
use Illuminate\Notifications\Messages\MailMessage;

/** US-059-LEG: the veeduría answers the citizen, without ever seeing their email. */
class CitizenReportAnswered extends QueuedNotification
{
    public function __construct(
        public readonly string $number, // it. 46c: CitizenReport::reference()
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
