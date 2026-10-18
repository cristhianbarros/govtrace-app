<?php

namespace App\Domain\Organization\Notifications;

use App\Infrastructure\Notifications\QueuedNotification;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * US-060-MON (it. 43i): once a day, how many evidences wait for the
 * Administrador's review, with a button to the inbox. Only when there are.
 * It. 65a (US-068): and how many citizen reports wait for an answer; with
 * only those, the button goes to them.
 */
class ReviewDigest extends QueuedNotification
{
    public function __construct(
        public readonly int $pending,
        public readonly string $organization,
        public readonly string $inboxUrl,
        public readonly int $citizenReports = 0,
        public readonly ?string $citizenReportsUrl = null,
        public readonly int $dueSoon = 0, // it. 66a: informes ciudadanos que vencen en los próximos días
        public readonly int $overdue = 0, // it. 66a: y los que ya vencieron sin respuesta
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $evidences = $this->pending === 1
            ? '1 evidencia de sus veedores espera su revisión. Mientras no la revise, no aparece en el mapa público.'
            : "{$this->pending} evidencias de sus veedores esperan su revisión. Mientras no las revise, no aparecen en el mapa público.";
        $reports = $this->citizenReports === 1 ? '1 informe ciudadano espera respuesta' : "{$this->citizenReports} informes ciudadanos esperan respuesta";

        $mail = (new MailMessage)
            ->subject($this->pending > 0
                ? "GovTrace: {$this->pending} ".($this->pending === 1 ? 'evidencia' : 'evidencias')." por revisar en {$this->organization}"
                : "GovTrace: {$reports} en {$this->organization}")
            ->greeting("Hola, {$notifiable->name}");
        if ($this->pending > 0) {
            $mail->line($evidences);
        }
        if ($this->citizenReports > 0) {
            $mail->line("{$reports}.");
        }
        if ($this->dueSoon > 0) {
            $mail->line($this->dueSoon === 1 ? '1 informe ciudadano vence en los próximos 3 días.' : "{$this->dueSoon} informes ciudadanos vencen en los próximos 3 días.");
        }
        if ($this->overdue > 0) {
            $mail->line($this->overdue === 1 ? '1 informe ciudadano ya venció sin respuesta.' : "{$this->overdue} informes ciudadanos ya vencieron sin respuesta.");
        }

        return ($this->pending > 0 ? $mail->action('Revisar la Bandeja', $this->inboxUrl) : $mail->action('Ver los informes ciudadanos', (string) $this->citizenReportsUrl))
            ->line('Le llega este resumen una vez al día, solo cuando hay algo por revisar.');
    }
}
