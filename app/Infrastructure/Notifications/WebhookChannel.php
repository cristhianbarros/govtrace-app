<?php

namespace App\Infrastructure\Notifications;

use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * US-022: las alertas críticas, también a un webhook de Slack o Discord
 * (ALERT_WEBHOOK_URL). Un solo JSON sirve a los dos: Slack lee "text" y
 * Discord, "content". La notificación dice qué enviar con toWebhook().
 */
class WebhookChannel
{
    public function send(object $notifiable, Notification $notification): void
    {
        $url = $notifiable->routeNotificationFor(self::class, $notification);
        $message = $notification->toWebhook($notifiable);

        try {
            Http::timeout(10)->post($url, ['text' => $message, 'content' => $message])->throw();
        } catch (Throwable $e) {
            // El correo ya salió: un webhook caído no debe repetir la alerta con un reintento del trabajo.
            report($e);
        }
    }
}
