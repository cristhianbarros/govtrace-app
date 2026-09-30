<?php

use App\Domain\Auth\Notifications\ResetPasswordLink;
use App\Domain\CitizenReports\Notifications\CitizenReportCode;
use App\Domain\Organization\Notifications\WelcomeNotification;
use App\Domain\Organization\User;
use App\Infrastructure\Mail\SentLinks;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;

/*
 * Iteración 38 — en local el correo sale a un archivo (MAIL_MAILER=log), no a
 * un buzón. Para una demostración en vivo, make invites lee ese archivo y
 * muestra los enlaces de los correos más recientes: quién los recibe, qué
 * dicen y a dónde llevan. El archivo es propio (storage/logs/mail.log), no el
 * log de la aplicación, que puede pesar cientos de MB.
 */

beforeEach(function () {
    $this->mailLog = tempnam(sys_get_temp_dir(), 'govtrace-mail-');
    config([
        'mail.default' => 'log',
        'logging.channels.mail-test' => ['driver' => 'single', 'path' => $this->mailLog, 'level' => 'debug'],
        'mail.mailers.log.channel' => 'mail-test',
    ]);
    Mail::purge();
});

afterEach(function () {
    @unlink($this->mailLog);
});

function mailTo(string $email, string $name, string $url): void
{
    (new User(['name' => $name, 'email' => $email]))->notify(new WelcomeNotification($url, 48));
}

it('sends the mail of the log mailer to its own file, not to the application log', function () {
    // La configuración de fábrica, sin los cambios de este test: un canal "mail" propio,
    // y el mailer "log" apuntándole (a menos que MAIL_LOG_CHANNEL diga otra cosa).
    $logging = require base_path('config/logging.php');
    $mail = require base_path('config/mail.php');

    expect($logging['channels']['mail']['path'])->toEndWith('storage/logs/mail.log')
        ->and($mail['mailers']['log']['channel'])->toBe(env('MAIL_LOG_CHANNEL', 'mail'));
});

it('reads the links of the mails sent, the newest first, with who got them', function () {
    mailTo('ana@veeduria.org', 'Ana Administradora', 'http://veeduria-smr.govtrace.localhost:8080/set-password/1?token=aaa111');
    mailTo('carlos@correo.co', 'Carlos Veedor', 'http://veeduria-smr.govtrace.localhost:8080/set-password/2?token=bbb222');

    $links = SentLinks::fromFile($this->mailLog);

    expect($links)->toHaveCount(2)
        ->and($links[0]->to)->toBe('carlos@correo.co')
        ->and($links[0]->url)->toBe('http://veeduria-smr.govtrace.localhost:8080/set-password/2?token=bbb222')
        ->and($links[0]->subject)->toBe('Bienvenido a GovTrace')
        ->and($links[1]->to)->toBe('ana@veeduria.org')
        ->and($links[1]->url)->toBe('http://veeduria-smr.govtrace.localhost:8080/set-password/1?token=aaa111');
});

it('keeps a long link whole, reads a password recovery link, and decodes the subject', function () {
    $long = 'http://organizacion-con-un-subdominio-muy-largo.govtrace.localhost:8080/set-password/123456?token='.str_repeat('abcdef0123456789', 4);
    mailTo('ana@veeduria.org', 'Ana', $long);
    (new User(['name' => 'Carlos', 'email' => 'carlos@correo.co']))->notify(new ResetPasswordLink('http://veeduria-smr.govtrace.localhost:8080/reset-password/tok?email=carlos%40correo.co', 'tok'));

    $links = SentLinks::fromFile($this->mailLog);

    expect($links[0]->url)->toBe('http://veeduria-smr.govtrace.localhost:8080/reset-password/tok?email=carlos%40correo.co')
        ->and($links[0]->subject)->toBe('Restablecer su contraseña de GovTrace')
        ->and($links[1]->url)->toBe($long);
});

it('shows only the last ones asked for, and nothing when there is no mail yet', function () {
    expect(SentLinks::fromFile($this->mailLog))->toBe([])
        ->and(SentLinks::fromFile($this->mailLog.'-no-existe'))->toBe([]);

    foreach (range(1, 4) as $n) {
        mailTo("veedor{$n}@correo.co", "Veedor {$n}", "http://veeduria-smr.govtrace.localhost:8080/set-password/{$n}?token=t{$n}");
    }

    $links = SentLinks::fromFile($this->mailLog, limit: 2);
    expect(array_map(fn ($link) => $link->to, $links))->toBe(['veedor4@correo.co', 'veedor3@correo.co']);
});

it('reads only the end of a huge file', function () {
    mailTo('vieja@veeduria.org', 'Vieja', 'http://veeduria-smr.govtrace.localhost:8080/set-password/1?token=vieja');
    file_put_contents($this->mailLog, str_repeat("[2026-01-01 00:00:00] local.DEBUG: ruido\n", 200_000), FILE_APPEND);
    mailTo('ana@veeduria.org', 'Ana', 'http://veeduria-smr.govtrace.localhost:8080/set-password/2?token=aaa111');

    $links = SentLinks::fromFile($this->mailLog, limit: 5, tailBytes: 65_536);

    // El correo de antes del ruido queda fuera de lo que se lee.
    expect($links)->toHaveCount(1)->and($links[0]->to)->toBe('ana@veeduria.org');
});

it('make invites: lists them, or says there are none', function () {
    $this->artisan('invitations:latest', ['--file' => $this->mailLog])
        ->expectsOutputToContain('Todavía no salió ningún correo')
        ->assertSuccessful();

    mailTo('carlos@correo.co', 'Carlos', 'http://veeduria-smr.govtrace.localhost:8080/set-password/2?token=bbb222');

    $this->artisan('invitations:latest', ['--file' => $this->mailLog])
        ->expectsOutputToContain('carlos@correo.co')
        ->expectsOutputToContain('http://veeduria-smr.govtrace.localhost:8080/set-password/2?token=bbb222')
        ->assertSuccessful();
});

it('make invites: shows the code a citizen got to inform a veeduría, for a live demonstration (it. 44f)', function () {
    Notification::route('mail', 'vecina@correo.co')->notify(new CitizenReportCode('042917', 'Veeduría Ciudadana Comuna 13 (demo)', 10));

    [$code] = SentLinks::fromFile($this->mailLog);

    expect($code->to)->toBe('vecina@correo.co')
        ->and($code->url)->toBe('Código: 042917')
        ->and($code->subject)->toBe('Su código para informar a Veeduría Ciudadana Comuna 13 (demo): 042917');
});
