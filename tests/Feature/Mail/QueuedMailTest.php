<?php

use App\Infrastructure\Notifications\WebhookChannel;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/*
 * Iteración 64 — todos los correos de GovTrace salen por la cola de correos
 * (features/US-059-LEG.feature): la pantalla no espera al servidor de correo,
 * un fallo se reintenta, y en la cola van cifrados (llevan códigos, enlaces
 * con su token y correos de personas). Salen después de guardar.
 */

/** @return list<class-string<Notification>> every mail GovTrace sends */
function govtraceNotifications(): array
{
    return collect(File::allFiles(app_path()))
        ->filter(fn ($file) => str_ends_with($file->getPath(), 'Notifications'))
        ->map(fn ($file) => 'App\\'.str_replace(['/', '.php'], ['\\', ''], Str::after($file->getPathname(), app_path().'/')))
        ->filter(fn (string $class) => is_subclass_of($class, Notification::class) && ! (new ReflectionClass($class))->isAbstract())
        ->values()
        ->all();
}

it('sends every GovTrace mail through the mail queue, encrypted, after saving, with 5 attempts', function () {
    $notifications = govtraceNotifications();
    expect(count($notifications))->toBeGreaterThanOrEqual(12);

    foreach ($notifications as $class) {
        $notification = (new ReflectionClass($class))->newInstanceWithoutConstructor();
        expect($notification)->toBeInstanceOf(ShouldQueueAfterCommit::class, $class)
            ->toBeInstanceOf(ShouldBeEncrypted::class, $class)
            ->and($notification->viaQueues())->toBe(['mail' => 'mail', WebhookChannel::class => 'mail'], $class)
            ->and($notification->tries)->toBe(5, $class)
            ->and($notification->backoff())->toBe([10, 30, 60, 120], $class);
    }
});
