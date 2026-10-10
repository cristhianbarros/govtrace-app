<?php

use App\Application\Organization\AssignInitialAdministrator;
use App\Application\Organization\InviteObserver;
use App\Application\Organization\RegisterOrganization;
use App\Application\Organization\ResendInvitation;
use App\Domain\Auth\Notifications\ResetPasswordLink;
use App\Domain\Organization\Notifications\WelcomeNotification;
use App\Domain\Organization\Roles;
use App\Domain\Organization\User;
use App\Infrastructure\Tenancy\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/*
 * Iteración 38 — los enlaces de los correos (bienvenida, invitación y
 * recuperación de contraseña) llevan el esquema y el puerto de APP_URL: en
 * local, http://…:8080; en producción, https. Antes eran siempre http y sin
 * puerto, y el enlace de un correo de desarrollo no abría.
 *
 * Sin RefreshDatabase — registrar la organización ejecuta CREATE DATABASE.
 */

beforeEach(function () {
    $this->artisan('migrate');
    $this->tenant = (new RegisterOrganization)->handle('900123456-8', 'Veeduría Ciudadana Santa Marta', 'veeduria-smr');
});

afterEach(function () {
    if (tenant()) {
        tenancy()->end();
    }

    Tenant::query()->get()->each->delete();
    // La auditoría vive en la base central, que no se reinicia entre archivos: el reenvío deja su entrada.
    DB::table('audit_logs')->delete();
});

it('takes the scheme and the port of APP_URL for the Administrador inicial welcome link', function (string $appUrl, string $prefix) {
    config(['app.url' => $appUrl]);
    Notification::fake();

    $administrator = (new AssignInitialAdministrator)->handle($this->tenant, 'Ana Administradora', 'ana@veeduria-smr.org');

    Notification::assertSentTo($administrator, WelcomeNotification::class, fn (WelcomeNotification $mail) => str_starts_with($mail->url, "{$prefix}/set-password/{$administrator->public_id}?token="));
})->with([
    'local, con puerto' => ['http://govtrace.localhost:8080', 'http://veeduria-smr.govtrace.localhost:8080'],
    'producción, con https' => ['https://govtrace.localhost', 'https://veeduria-smr.govtrace.localhost'],
    'sin puerto ni https' => ['http://govtrace.localhost', 'http://veeduria-smr.govtrace.localhost'],
]);

it('takes the scheme and the port of APP_URL for a veedor invitation and its resend', function (string $appUrl, string $prefix) {
    config(['app.url' => $appUrl]);
    Notification::fake();
    tenancy()->initialize($this->tenant);

    $administrator = reportingMember($this->tenant, 'admin@veeduria-smr.org', Roles::Administrator);
    $veedor = (new InviteObserver)->handle('carlos@correo.co');
    (new ResendInvitation)->handle($administrator, $veedor);

    Notification::assertSentToTimes($veedor, WelcomeNotification::class, 2);
    Notification::assertSentTo($veedor, WelcomeNotification::class, fn (WelcomeNotification $mail) => str_starts_with($mail->url, "{$prefix}/set-password/{$veedor->public_id}?token="));
})->with([
    'local, con puerto' => ['http://govtrace.localhost:8080', 'http://veeduria-smr.govtrace.localhost:8080'],
    'producción, con https' => ['https://govtrace.localhost', 'https://veeduria-smr.govtrace.localhost'],
]);

it('takes the scheme and the port of APP_URL for the password recovery link of a member', function (string $appUrl, string $prefix) {
    config(['app.url' => $appUrl]);
    Notification::fake();
    tenancy()->initialize($this->tenant);
    $veedor = User::create(['name' => 'Carlos', 'email' => 'carlos@correo.co', 'password' => 'Veeduria#2026']);
    $veedor->assignRole(Roles::Observer->value);

    $veedor->sendPasswordResetNotification('el-token');

    Notification::assertSentTo($veedor, ResetPasswordLink::class, fn (ResetPasswordLink $mail) => str_starts_with($mail->url, "{$prefix}/reset-password/el-token?email="));
})->with([
    'local, con puerto' => ['http://govtrace.localhost:8080', 'http://veeduria-smr.govtrace.localhost:8080'],
    'producción, con https' => ['https://govtrace.localhost', 'https://veeduria-smr.govtrace.localhost'],
]);
