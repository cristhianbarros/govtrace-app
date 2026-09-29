<?php

namespace App\Domain\Sealing\Notifications;

use App\Infrastructure\Notifications\WebhookChannel;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * US-022: una alerta para el Super Administrador, por Email a cada uno y
 * una sola vez al webhook del equipo (SuperAdminAlerts::send).
 */
abstract class CriticalAlert extends Notification
{
    abstract public function subject(): string;

    abstract public function message(): string;

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return $notifiable instanceof AnonymousNotifiable ? [WebhookChannel::class] : ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->error()->subject($this->subject())->line($this->message());
    }

    public function toWebhook(object $notifiable): string
    {
        return $this->message();
    }
}
