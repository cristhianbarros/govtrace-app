<?php

namespace App\Domain\Sealing\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * US-020b: la alerta crítica al Super Administrador cuando la cuenta
 * patrocinadora se queda sin XLM y el sellado entra en pausa.
 */
class SponsorOutOfFunds extends Notification
{
    public function __construct(public readonly string $sponsorAddress) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->error()
            ->subject('[Crítico] GovTrace: la cuenta patrocinadora se quedó sin XLM')
            ->line('El sellado de evidencias en Stellar está en pausa: la cuenta patrocinadora no tiene XLM para pagar las comisiones.')
            ->line("Cuenta patrocinadora: {$this->sponsorAddress}")
            ->line('Fondéala y el sellado se reanuda solo. Ninguna evidencia se pierde: esperan "En Cola".');
    }
}
