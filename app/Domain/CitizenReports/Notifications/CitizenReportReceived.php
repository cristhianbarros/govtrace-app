<?php

namespace App\Domain\CitizenReports\Notifications;

use App\Infrastructure\Notifications\QueuedNotification;
use Illuminate\Notifications\Messages\MailMessage;

/** US-059-LEG: the veeduría got the citizen's report — with its number, to refer to it. */
class CitizenReportReceived extends QueuedNotification
{
    public function __construct(
        public readonly string $number, // it. 46c: CitizenReport::reference()
        public readonly string $organization,
        public readonly string $worksite,
        public readonly ?string $commitment = null, // it. 66a: hasta cuándo se compromete a responder la veeduría
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject("Recibimos su informe n.º {$this->number}")
            ->greeting('Hola')
            ->line("Su informe n.º {$this->number} sobre «{$this->worksite}» llegó a {$this->organization}.");
        if ($this->commitment !== null) {
            $mail->line($this->commitment);
        }

        return $mail->line('Si lo atiende, la veeduría le responde a este correo. Su correo no se le muestra: la respuesta le llega desde GovTrace.');
    }
}
