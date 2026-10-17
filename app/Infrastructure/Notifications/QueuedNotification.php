<?php

namespace App\Infrastructure\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Notifications\Notification;

/**
 * It. 64: todos los correos de GovTrace salen por la cola "mail", no dentro de
 * la petición: la pantalla no espera al servidor de correo (con Gmail, de 1 a
 * 3 s por correo), y si el servidor falla, el correo se reintenta en vez de
 * mostrar un error cuando lo importante ya se guardó.
 *
 * - Después de guardar (ShouldQueueAfterCommit): no sale un correo de algo que
 *   se deshizo, ni lo envía el worker antes de que exista lo que nombra.
 * - Cifrados en la cola (ShouldBeEncrypted, con APP_KEY): llevan códigos,
 *   enlaces con su token y correos de personas.
 * - 5 intentos, a los 10 s, 30 s, 1 min y 2 min: menos que los 10 minutos que
 *   vale el código del ciudadano.
 *
 * En producción los envía su propio worker (mail-worker), para no esperar
 * detrás de la sincronización con el SECOP ni del sellado. Las alertas al
 * webhook del equipo van por la misma cola.
 */
abstract class QueuedNotification extends Notification implements ShouldBeEncrypted, ShouldQueueAfterCommit
{
    use Queueable;

    public int $tries = 5;

    /** @return list<int> */
    public function backoff(): array
    {
        return [10, 30, 60, 120];
    }

    /** @return array<string, string> */
    public function viaQueues(): array
    {
        return ['mail' => 'mail', WebhookChannel::class => 'mail'];
    }
}
