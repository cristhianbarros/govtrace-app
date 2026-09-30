<?php

namespace App\Domain\CitizenReports\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** US-059-LEG: the code a citizen types to send their report — the proof that the email is theirs. */
class CitizenReportCode extends Notification
{
    public function __construct(
        public readonly string $code,
        public readonly string $organization,
        public readonly int $validMinutes,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Su código para informar a {$this->organization}: {$this->code}")
            ->greeting('Hola')
            ->line("Su código para informar a {$this->organization} es: {$this->code}")
            ->line("Escríbalo en la página de la obra. Vence en {$this->validMinutes} minutos.")
            ->line('Si usted no lo pidió, ignore este correo.');
    }
}
