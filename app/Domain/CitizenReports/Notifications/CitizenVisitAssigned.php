<?php

namespace App\Domain\CitizenReports\Notifications;

use App\Infrastructure\Notifications\QueuedNotification;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * It. 65b (US-068): the veeduría asked a veedor to document what the citizen
 * reported. Once, the first time: changing the veedor sends nothing else. The
 * veedor is not named.
 */
class CitizenVisitAssigned extends QueuedNotification
{
    public function __construct(
        public readonly string $number,
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
            ->subject("Su informe n.º {$this->number}: un veedor va a visitar la obra")
            ->greeting('Hola')
            ->line("Un veedor de {$this->organization} va a visitar la obra «{$this->worksite}» para documentar lo que usted informó (informe n.º {$this->number}).")
            ->line('Cuando la veeduría publique la evidencia, le llega otro correo con el enlace para verla.');
    }
}
