<?php

namespace App\Application\Sealing;

use App\Domain\Sealing\Notifications\CriticalAlert;
use App\Infrastructure\Notifications\WebhookChannel;
use App\Models\User as SuperAdmin;
use Illuminate\Support\Facades\Notification;

/**
 * US-022: una alerta crítica, por Email a cada Super Administrador y una
 * vez al webhook del equipo, si está configurado (ALERT_WEBHOOK_URL).
 */
final class SuperAdminAlerts
{
    public static function send(CriticalAlert $alert): void
    {
        Notification::send(SuperAdmin::all(), $alert);

        if ($webhook = config('services.alerts.webhook_url')) {
            Notification::route(WebhookChannel::class, $webhook)->notify($alert);
        }
    }
}
