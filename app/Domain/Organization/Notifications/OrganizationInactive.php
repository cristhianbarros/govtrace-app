<?php

namespace App\Domain\Organization\Notifications;

use Carbon\CarbonImmutable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** US-054-RPT: una organización lleva 30 días sin recibir ni publicar evidencias. Por Email. */
class OrganizationInactive extends Notification
{
    public function __construct(
        public readonly string $organization,
        public readonly int $days,
        public readonly CarbonImmutable $lastActivityAt,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $since = $this->lastActivityAt->timezone('America/Bogota')->format('d/m/Y');

        return (new MailMessage)
            ->subject("GovTrace: {$this->organization} lleva {$this->days} días sin actividad")
            ->line("La organización {$this->organization} no recibe ni publica evidencias desde hace {$this->days} días (última actividad: {$since}).")
            ->line('Puede ser una organización que está dejando la plataforma: vale la pena contactarla.');
    }
}
