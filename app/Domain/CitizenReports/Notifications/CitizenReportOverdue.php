<?php

namespace App\Domain\CitizenReports\Notifications;

use App\Domain\CitizenReports\ContraloriaChannels;
use App\Infrastructure\Notifications\QueuedNotification;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * It. 66a (US-069, R-LEG-12): the veeduría did not act on the report within
 * the term it set. The citizen may take the case to the Contraloría, which
 * already watches works with the community: the mail brings what to tell it.
 * Without blaming anyone: the term is a GovTrace suggestion, not a legal one.
 */
class CitizenReportOverdue extends QueuedNotification
{
    public function __construct(
        public readonly string $number,
        public readonly string $organization,
        public readonly string $worksite,
        public readonly ?string $contract,
        public readonly ?string $entity,
        public readonly string $message,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $case = array_filter([
            "La obra: {$this->worksite}",
            $this->contract ? "Su contrato en el SECOP: {$this->contract}" : null,
            $this->entity ? "La entidad: {$this->entity}" : null,
            "Lo que usted informó: «{$this->message}»",
        ]);

        return (new MailMessage)
            ->subject("Su informe n.º {$this->number}: la veeduría aún no ha respondido")
            ->greeting('Hola')
            ->line("{$this->organization} aún no ha respondido su informe n.º {$this->number}, y ya pasó la fecha en que se comprometió a hacerlo.")
            ->line('Puede llevar su caso a la Contraloría General de la República, que vigila las obras con la comunidad. Estos datos le sirven para contarlo:')
            ->lines($case)
            ->line('La Contraloría lo atiende en la '.implode(' o en el ', ContraloriaChannels::PHONES).', o en línea:')
            ->action('Denunciar en línea ante la Contraloría', ContraloriaChannels::ONLINE)
            ->line('Todos sus canales: '.ContraloriaChannels::ALL)
            ->line('Si la veeduría le responde, le llega a este correo.');
    }
}
