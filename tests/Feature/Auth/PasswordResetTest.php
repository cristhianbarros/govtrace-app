<?php

use App\Application\Organization\InviteObserver;
use App\Application\Organization\RegisterOrganization;
use App\Domain\Auth\Notifications\ResetPasswordLink;
use App\Domain\Organization\User as OrganizationUser;
use App\Infrastructure\Tenancy\Tenant;
use App\Models\User as SuperAdmin;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * Iteración 20 — Restablecer la contraseña por correo (specs/PLAN.md).
 * Traduce features/US-039-USR.feature (5 casos) contra las rutas reales:
 * GET/POST /forgot-password y GET/POST /reset-password/{token}, en el
 * subdominio de la organización. El enlace vence a los 60 minutos y sirve
 * una sola vez.
 *
 * Sin RefreshDatabase — registrar la organización ejecuta CREATE DATABASE.
 */

const NEUTRAL = 'Si el correo existe, recibirás un enlace';
const LINK_EXPIRED = 'El enlace de restablecimiento de contraseña ha expirado o ya ha sido utilizado.';
const HOST = 'http://veeduria-smr.govtrace.localhost';

beforeEach(function () {
    $this->artisan('migrate');
    Notification::fake();

    $this->tenant = (new RegisterOrganization)->handle('900123456-8', 'Veeduría Ciudadana Santa Marta', 'veeduria-smr');
    $this->veedor = reportingMember($this->tenant, 'carlos@correo.co');
});

afterEach(function () {
    if (tenant()) {
        tenancy()->end();
    }

    Tenant::query()->get()->each->delete();
    SuperAdmin::query()->where('email', 'root@govtrace.app')->delete();
});

/** Pide el enlace para $email y devuelve el token que llegó al veedor por correo (null si no le llegó ninguno). */
function requestResetLink(string $email): ?string
{
    test()->post(HOST.'/forgot-password', ['email' => $email])
        ->assertRedirect()
        ->assertSessionHas('status', NEUTRAL);

    // El token en claro solo viaja en el correo.
    return $email === test()->veedor->email ? Notification::sent(test()->veedor, ResetPasswordLink::class)->last()?->token : null;
}

function resetPassword(string $token, string $password, string $email = 'carlos@correo.co')
{
    return test()->post(HOST.'/reset-password', [
        'token' => $token,
        'email' => $email,
        'password' => $password,
        'password_confirmation' => $password,
    ]);
}

it('Restablecimiento exitoso: with the link opened 10 minutes later, the new password logs in', function () {
    $token = requestResetLink('carlos@correo.co');
    Notification::assertSentTo($this->veedor, ResetPasswordLink::class, fn (ResetPasswordLink $link) => str_starts_with(
        $link->url,
        HOST."/reset-password/{$token}?email=carlos%40correo.co",
    ));

    $this->travel(10)->minutes();

    $this->withoutVite()->get(HOST."/reset-password/{$token}?email=carlos%40correo.co")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Auth/ResetPassword')->where('valid', true)->where('email', 'carlos@correo.co')->where('token', $token));

    resetPassword($token, 'Nueva#2026x')
        ->assertRedirect(HOST.'/login')
        ->assertSessionHas('status', 'Su contraseña fue cambiada. Ya puede iniciar sesión.');

    $this->post(HOST.'/login', ['email' => 'carlos@correo.co', 'password' => 'Nueva#2026x'])->assertRedirect(HOST.'/reports/new');
    $this->assertAuthenticatedAs(test()->tenant->run(fn () => OrganizationUser::query()->findOrFail($this->veedor->id)), 'tenant');
});

it('La respuesta es neutra ante un correo no registrado, and nothing is sent', function () {
    expect(requestResetLink('nadie@correo.co'))->toBeNull();

    Notification::assertNothingSent();
});

it('Enlace vencido o ya usado: the password cannot be changed', function (Closure $spoil) {
    $token = requestResetLink('carlos@correo.co');
    $spoil($token);

    $this->withoutVite()->get(HOST."/reset-password/{$token}?email=carlos%40correo.co")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Auth/ResetPassword')->where('valid', false)->where('message', LINK_EXPIRED)->missing('token'));

    resetPassword($token, 'Otra#2026xx')->assertSessionHasErrors(['token' => LINK_EXPIRED]);

    $this->post(HOST.'/login', ['email' => 'carlos@correo.co', 'password' => 'Otra#2026xx'])->assertSessionHasErrors('email');
})->with([
    // El parámetro es Closure: Pest entrega la función tal cual, sin evaluarla.
    'se emitió hace 61 minutos' => [fn (string $token) => test()->travel(61)->minutes()],
    'ya fue utilizado' => [fn (string $token) => resetPassword($token, 'Nueva#2026x')->assertRedirect(HOST.'/login')],
]);

it('La nueva contraseña cumple las reglas mínimas', function () {
    $token = requestResetLink('carlos@correo.co');

    resetPassword($token, 'corta1#')
        ->assertSessionHasErrors(['password' => 'La contraseña debe tener al menos 8 caracteres, incluir una mayúscula, una minúscula, un número y un símbolo especial.']);

    $this->post(HOST.'/login', ['email' => 'carlos@correo.co', 'password' => 'Veeduria#2026'])->assertRedirect(HOST.'/reports/new');
});

// Reglas derivadas ------------------------------------------------------

it('sends no link to a deactivated account, nor to a pending invitation, and still answers the same', function () {
    $this->tenant->run(function () {
        OrganizationUser::query()->whereKey($this->veedor->id)->update(['is_active' => false]);
        (new InviteObserver)->handle('laura@correo.co');
    });
    Notification::fake(); // la invitación de laura no cuenta

    requestResetLink('carlos@correo.co');
    requestResetLink('laura@correo.co');

    Notification::assertNothingSent();
});

it('serves the screen to ask for the link, from the login', function () {
    $this->withoutVite()->get(HOST.'/forgot-password')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Auth/ForgotPassword')->where('context', 'Veeduría Ciudadana Santa Marta'));
});

it('lets the Super Administrador reset his password from the global panel too', function () {
    $root = SuperAdmin::factory()->create(['email' => 'root@govtrace.app', 'password' => 'Veeduria#2026']);

    $this->post('http://govtrace.localhost/forgot-password', ['email' => 'root@govtrace.app'])->assertSessionHas('status', NEUTRAL);
    $token = null;
    Notification::assertSentTo($root, ResetPasswordLink::class, function (ResetPasswordLink $link) use (&$token) {
        $token = $link->token;

        return str_starts_with($link->url, "http://govtrace.localhost/reset-password/{$token}");
    });

    $this->post('http://govtrace.localhost/reset-password', ['token' => $token, 'email' => 'root@govtrace.app', 'password' => 'Nueva#2026x', 'password_confirmation' => 'Nueva#2026x'])
        ->assertRedirect('http://govtrace.localhost/login');
    $this->post('http://govtrace.localhost/login', ['email' => 'root@govtrace.app', 'password' => 'Nueva#2026x'])->assertRedirect();
    $this->assertAuthenticatedAs($root->fresh(), 'web');
});
