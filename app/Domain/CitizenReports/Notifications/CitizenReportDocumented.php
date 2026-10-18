<?php

namespace App\Domain\CitizenReports\Notifications;

use App\Infrastructure\Notifications\QueuedNotification;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * It. 65b (US-068): the evidence of the veedor who documented the citizen's
 * report is public — with the link to see it, sealed. The veedor is not named.
 */
class CitizenReportDocumented extends QueuedNotification
{
    public function __construct(
        public readonly string $number,
        public readonly string $organization,
        public readonly string $worksite,
        public readonly string $worksiteUrl,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Su informe n.º {$this->number}: la veeduría publicó la evidencia")
            ->greeting('Hola')
            ->line("{$this->organization} documentó lo que usted informó sobre «{$this->worksite}» (informe n.º {$this->number}). Un veedor fue a la obra, y su evidencia ya está publicada: nadie la puede cambiar.")
            ->action('Ver la evidencia', $this->worksiteUrl)
            ->line('Puede compartir este enlace: cualquiera puede comprobar que la evidencia es auténtica.');
    }
}
