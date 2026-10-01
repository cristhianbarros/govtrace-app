<?php

use App\Domain\Organization\Notifications\WelcomeNotification;
use App\Domain\Organization\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/*
 * Iteración 38b — en desarrollo, cada correo también llega a Mailpit, un buzón
 * local (http://mailpit.govtrace.localhost:8080) donde se lee como lo vería
 * quien lo recibe. El correo sigue saliendo a su mailer: en local, el archivo
 * que leen make invites y make e2e. En producción nunca se copia.
 */

beforeEach(function () {
    config([
        'mail.default' => 'array',
        'mail.mailers.mailpit' => ['transport' => 'array'],
        'mail.copy_to_mailpit' => true,
    ]);
    Mail::purge();
});

function sendWelcome(): void
{
    (new User(['name' => 'Carlos Veedor', 'email' => 'carlos@correo.co']))
        ->notify(new WelcomeNotification('http://veeduria-smr.govtrace.localhost:8080/set-password/2?token=bbb222', 48));
}

function mailpitCopies(): Collection
{
    return Mail::mailer('mailpit')->getSymfonyTransport()->messages();
}

it('copies each mail to Mailpit, to the same person, and still sends it', function () {
    sendWelcome();

    expect(mailpitCopies())->toHaveCount(1)
        ->and(mailpitCopies()[0]->getEnvelope()->getRecipients()[0]->getAddress())->toBe('carlos@correo.co')
        ->and(mailpitCopies()[0]->getOriginalMessage()->getSubject())->toBe('Bienvenido a GovTrace')
        ->and(Mail::mailer('array')->getSymfonyTransport()->messages())->toHaveCount(1);
});

it('copies nothing without MAIL_COPY_TO_MAILPIT', function () {
    config(['mail.copy_to_mailpit' => false]);

    sendWelcome();

    expect(mailpitCopies())->toHaveCount(0);
});

it('never copies in production, even if the setting says so', function () {
    app()->detectEnvironment(fn () => 'production');

    sendWelcome();

    expect(mailpitCopies())->toHaveCount(0);
});

it('sends the mail anyway when Mailpit does not answer, and says so in the log', function () {
    config(['mail.mailers.mailpit' => ['transport' => 'smtp', 'scheme' => 'smtp', 'host' => '127.0.0.1', 'port' => 9, 'timeout' => 1]]);
    Mail::purge('mailpit');
    Log::spy();

    sendWelcome();

    expect(Mail::mailer('array')->getSymfonyTransport()->messages())->toHaveCount(1);
    Log::shouldHaveReceived('warning')->once()->withArgs(fn (string $message) => str_starts_with($message, 'No se pudo copiar el correo a Mailpit'));
});

it('is a development mailbox: in docker-compose.yml and .env.example, not in production', function () {
    $mail = require base_path('config/mail.php');

    expect(file_get_contents(base_path('docker-compose.yml')))->toContain('image: axllent/mailpit:')
        ->and(file_get_contents(base_path('docker/proxy/nginx.conf')))->toContain('server_name mailpit.govtrace.localhost;')
        ->and(file_get_contents(base_path('.env.example')))->toContain("\nMAIL_COPY_TO_MAILPIT=true")
        ->and(file_get_contents(base_path('docker-compose.prod.yml')))->not->toContain('mailpit')
        ->and(file_get_contents(base_path('.env.production.example')))->not->toContain('MAIL_COPY_TO_MAILPIT')
        ->and(file_get_contents(base_path('.env.staging.example')))->not->toContain('MAIL_COPY_TO_MAILPIT')
        ->and($mail['mailers']['mailpit'])->toMatchArray(['transport' => 'smtp', 'host' => 'mailpit', 'port' => 1025]);
});
