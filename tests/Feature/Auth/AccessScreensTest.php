<?php

use App\Application\Organization\InviteObserver;
use App\Application\Organization\RegisterOrganization;
use App\Domain\Organization\Roles;
use App\Domain\Organization\User as OrganizationUser;
use App\Domain\Shared\PublicId;
use App\Infrastructure\Tenancy\Tenant;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * Iteración 17 — Acceso: las pantallas de iniciar sesión y de activar la
 * cuenta (specs/PLAN.md). Las reglas de US-030 y US-031 ya se prueban
 * contra los POST (LoginTest, AcceptInvitationTest, it. 4 y 5); aquí, lo
 * que el servidor le da a cada pantalla y cómo navega el navegador.
 *
 * Sin RefreshDatabase — registrar la organización ejecuta CREATE DATABASE.
 */

beforeEach(function () {
    $this->artisan('migrate');
    $this->tenant = (new RegisterOrganization)->handle('900123456-8', 'Veeduría Ciudadana Santa Marta', 'veeduria-smr');

    [$this->veedor, $this->plainToken] = $this->tenant->run(function () {
        $veedor = (new InviteObserver)->handle('carlos@correo.co');
        // El token en claro solo viaja en el correo: se regenera uno para el test.
        $plain = Str::random(64);
        $veedor->forceFill(['invitation_token_hash' => hash('sha256', $plain)])->save();

        return [$veedor, $plain];
    });
});

afterEach(function () {
    if (tenant()) {
        tenancy()->end();
    }

    Tenant::query()->get()->each->delete();
});

it('serves the login screen of the organization, on its subdomain', function () {
    $this->withoutVite()
        ->get('http://veeduria-smr.govtrace.localhost/login')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Auth/Login')->where('context', 'Veeduría Ciudadana Santa Marta'));
});

it('serves the login screen of the global panel, for the Super Administrador', function () {
    $this->withoutVite()
        ->get('http://govtrace.localhost/login')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Auth/Login')->where('context', 'Panel global'));
});

it('sends a visitor without a session who opens a screen of the organization to its login', function () {
    $this->get('http://veeduria-smr.govtrace.localhost/reports/new')
        ->assertRedirect('http://veeduria-smr.govtrace.localhost/login');
});

it('after logging in from the screen, loads the dashboard of the role as a full page', function () {
    $this->tenant->run(function () {
        $administrator = OrganizationUser::create(['name' => 'Ana Pérez', 'email' => 'ana.perez@veeduria-smr.org', 'password' => 'Veeduria#2026']);
        $administrator->assignRole(Roles::Administrator->value);
    });

    // Inertia manda X-Inertia; la respuesta le pide al navegador una visita completa,
    // porque la sesión y su token CSRF cambiaron.
    $this->withHeaders(['X-Inertia' => 'true'])
        ->post('http://veeduria-smr.govtrace.localhost/login', ['email' => 'ana.perez@veeduria-smr.org', 'password' => 'Veeduria#2026'])
        ->assertStatus(409)
        ->assertHeader('X-Inertia-Location', 'http://veeduria-smr.govtrace.localhost/organization/dashboard');
});

it('serves the activation screen of a valid invitation link', function () {
    $this->withoutVite()
        ->get("http://veeduria-smr.govtrace.localhost/set-password/{$this->veedor->public_id}?token={$this->plainToken}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Auth/SetPassword')
            ->where('valid', true)
            ->where('email', 'carlos@correo.co')
            ->where('token', $this->plainToken)
            ->where('action', "/set-password/{$this->veedor->public_id}"));
});

it('Enlace de invitación vencido: the activation screen says so and offers no form', function () {
    $this->tenant->run(fn () => $this->veedor->forceFill(['invitation_expires_at' => now()->subHour()])->save());

    $this->withoutVite()
        ->get("http://veeduria-smr.govtrace.localhost/set-password/{$this->veedor->public_id}?token={$this->plainToken}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Auth/SetPassword')
            ->where('valid', false)
            ->where('message', 'El enlace de invitación ha expirado o no es válido. Solicite una nueva invitación al administrador.')
            ->missing('email')
            ->missing('token'));
});

it('does not tell whether an account exists: a wrong token or an unknown user look like an expired link', function (string $path) {
    $this->withoutVite()
        ->get("http://veeduria-smr.govtrace.localhost{$path}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Auth/SetPassword')->where('valid', false)->missing('email'));
})->with([
    // It. 46c: el enlace nombra al usuario por su identificador público.
    'token equivocado' => [fn () => '/set-password/'.test()->veedor->public_id.'?token=otro'],
    'sin token' => [fn () => '/set-password/'.test()->veedor->public_id],
    'usuario inexistente' => [fn () => '/set-password/'.PublicId::generate().'?token=otro'],
]);

it('takes the veedor from the old dashboard address to "Nuevo Reporte"', function () {
    $this->tenant->run(fn () => $this->veedor->forceFill(['is_active' => true])->save());

    $this->actingAs($this->veedor, 'tenant')
        ->get('http://veeduria-smr.govtrace.localhost/veedor/dashboard')
        ->assertRedirect('http://veeduria-smr.govtrace.localhost/reports/new');
});
