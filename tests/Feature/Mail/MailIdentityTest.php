<?php

use App\Domain\Auth\Notifications\ResetPasswordLink;
use App\Domain\CitizenReports\Notifications\CitizenReportAnswered;
use App\Domain\CitizenReports\Notifications\CitizenReportCode;
use App\Domain\CitizenReports\Notifications\CitizenReportReceived;
use App\Domain\Organization\Notifications\OrganizationInactive;
use App\Domain\Organization\Notifications\WelcomeNotification;
use App\Domain\Organization\User;
use App\Domain\Platform\Notifications\OnlyOneSuperAdministrator;
use App\Domain\Sealing\Notifications\SealingQueueStalled;
use App\Domain\Sealing\Notifications\SponsorOutOfFunds;
use Carbon\CarbonImmutable;
use Illuminate\Notifications\Notification;

/*
 * Iteración 45b — los correos de GovTrace tratan de usted, como toda la
 * aplicación, y llevan su identidad (it. 40e): el verde pino, su nombre y su
 * frase, y el enlace a la política de datos. Antes salían con la plantilla de
 * Laravel, en negro, y la bienvenida tuteaba (lo mostró Mailpit, it. 38b).
 */

function mailFor(Notification $notification): string
{
    return (string) $notification->toMail(new User(['name' => 'Ana Torres', 'email' => 'ana@correo.co']))->render();
}

dataset('mails to people', [
    'la bienvenida y la invitación' => [fn () => new WelcomeNotification('https://veeduria-smr.govtrace.example/set-password/1?token=abc', 48)],
    'la recuperación de contraseña' => [fn () => new ResetPasswordLink('https://veeduria-smr.govtrace.example/reset-password/abc?email=ana%40correo.co', 'abc')],
    'el código del ciudadano' => [fn () => new CitizenReportCode('482913', 'Veeduría Ciudadana Comuna 13', 15)],
    'el informe recibido' => [fn () => new CitizenReportReceived(7, 'Veeduría Ciudadana Comuna 13', 'Escuela San Javier')],
    'la respuesta de la veeduría' => [fn () => new CitizenReportAnswered(7, 'Veeduría Ciudadana Comuna 13', 'Gracias. Esta semana va un veedor.')],
    'la organización sin actividad' => [fn () => new OrganizationInactive('Veeduría Ciudadana Comuna 13', 30, CarbonImmutable::parse('2026-08-31'))],
    'la patrocinadora sin saldo' => [fn () => new SponsorOutOfFunds('GABC')],
    'la cola de sellado (it. 45e, enmienda de US-021)' => [fn () => new SealingQueueStalled(['Veeduría Ciudadana Santa Marta'])],
    'un solo Super Administrador activo (it. 46a)' => [fn () => new OnlyOneSuperAdministrator],
]);

it('speaks to the person as "usted", like the rest of GovTrace', function (Notification $notification) {
    $text = strip_tags(mailFor($notification));

    expect($text)->not->toMatch('/\b(tu|tus|tú|te|ti|contigo|puedes|tienes|haz|revisa|fondéala)\b/iu');
})->with('mails to people');

it('welcomes with "su cuenta", not "tu cuenta"', function () {
    expect(mailFor(new WelcomeNotification('https://veeduria-smr.govtrace.example/set-password/1?token=abc', 48)))->toContain('Se creó su cuenta en GovTrace.');
});

it('carries the identity of GovTrace: its pine green, its name and phrase, and the data policy', function (Notification $notification) {
    $mail = mailFor($notification);

    expect($mail)->toContain('GovTrace')
        ->toContain('Veeduría ciudadana de obras públicas')
        ->toContain('Evidencia ciudadana que nadie puede cambiar.')
        ->toContain(config('app.url').'/privacidad')
        ->toMatch('/class="brand"[^>]*color: #0f5047/') // el nombre, en verde pino
        ->not->toContain('#18181b') // el negro de la plantilla de Laravel
        ->not->toContain('All rights reserved');
})->with('mails to people');

it('paints the main button of a mail in pine green', function () {
    expect(mailFor(new WelcomeNotification('https://veeduria-smr.govtrace.example/set-password/1?token=abc', 48)))
        ->toMatch('/<a href="https:\/\/veeduria-smr\.govtrace\.example\/set-password\/1\?token=abc" class="button button-primary"[^>]*background-color: #15665a/');
});

it('sends the mail of development from a GovTrace sender, not hello@example.com', function () {
    $template = file_get_contents(base_path('.env.example'));

    expect($template)->toContain('MAIL_FROM_ADDRESS="no-responder@govtrace.localhost"')
        ->toContain('MAIL_FROM_NAME="GovTrace"')
        ->not->toContain('hello@example.com');
});
