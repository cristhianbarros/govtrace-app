<?php

use App\Application\Organization\InviteObserver;
use App\Application\Organization\RegisterOrganization;
use App\Infrastructure\Tenancy\Tenant;
use Illuminate\Support\Str;

/*
 * Iteración 5 — Invitaciones de veedores (specs/PLAN.md). Traduce
 * features/US-030.feature (aceptar la invitación vía HTTP, ya que
 * SetPasswordController es el consumidor real del enlace de US-005).
 */

beforeEach(function () {
    $this->artisan('migrate');
    $this->tenant = (new RegisterOrganization)->handle('900123456-8', 'Veeduría Ciudadana Santa Marta', 'veeduria-smr');
    $this->domain = 'veeduria-smr.govtrace.localhost';

    [$this->veedor, $this->plainToken] = $this->tenant->run(function () {
        $veedor = (new InviteObserver)->handle('carlos@correo.co');

        // El "plain" token nunca se persiste (solo su hash); lo recreamos
        // aquí regenerando uno nuevo y guardando su hash, igual que hace
        // InviteObserver, para tener el valor en claro que un test HTTP
        // necesita enviar en el formulario.
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

it('activates the account and logs the user in immediately', function () {
    $response = $this->post("http://{$this->domain}/set-password/{$this->veedor->public_id}", [
        'token' => $this->plainToken,
        'password' => 'Veeduria#2026',
        'password_confirmation' => 'Veeduria#2026',
        'declaration' => true, // US-057-LEG: un veedor declara que no tiene impedimentos para serlo
        'data_authorization' => true, // US-058-LEG: y autoriza el tratamiento de sus datos
    ]);

    // Hasta "Mis Reportes" (it. 28), el veedor entra a su pantalla central.
    $response->assertRedirect("http://{$this->domain}/reports/new");
    $this->assertAuthenticatedAs($this->veedor->fresh(), 'tenant');

    $this->tenant->run(function () {
        $fresh = $this->veedor->fresh();

        expect($fresh->invitation_token_hash)->toBeNull()
            ->and($fresh->is_active)->toBeTrue();
    });
});

it('rejects an expired invitation link', function () {
    $this->tenant->run(fn () => $this->veedor->forceFill(['invitation_expires_at' => now()->subMinute()])->save());

    $response = $this->post("http://{$this->domain}/set-password/{$this->veedor->public_id}", [
        'token' => $this->plainToken,
        'password' => 'Veeduria#2026',
        'password_confirmation' => 'Veeduria#2026',
    ]);

    $response->assertSessionHasErrors(['token' => 'El enlace de invitación ha expirado o no es válido. Solicite una nueva invitación al administrador.']);
    $this->assertGuest('tenant');
});

it('rejects a password that does not meet the minimum rules', function (string $password) {
    $response = $this->post("http://{$this->domain}/set-password/{$this->veedor->public_id}", [
        'token' => $this->plainToken,
        'password' => $password,
        'password_confirmation' => $password,
    ]);

    $response->assertSessionHasErrors(['password' => 'La contraseña debe tener al menos 8 caracteres, incluir una mayúscula, una minúscula, un número y un símbolo especial.']);
    $this->assertGuest('tenant');
})->with([
    'menos de 8 caracteres' => ['Ve#2026'],
    'sin mayúscula' => ['veeduria#2026'],
    'sin minúscula' => ['VEEDURIA#2026'],
    'sin número' => ['Veeduria#abc'],
    'sin símbolo' => ['Veeduria2026'],
]);
