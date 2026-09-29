<?php

use App\Application\Organization\RegisterOrganization;
use App\Domain\Organization\Exceptions\OrganizationValidationException;
use App\Infrastructure\Tenancy\Domain;
use App\Infrastructure\Tenancy\Tenant;

/*
 * Iteración 3 — Organización: invariantes y alta (specs/PLAN.md).
 * Traduce features/US-001.feature: un test por Escenario/Esquema.
 *
 * NO usa RefreshDatabase a propósito: registrar una organización dispara
 * TenantCreated -> CreateDatabase (PostgreSQLDatabaseManager ejecuta
 * "CREATE DATABASE" sobre la misma conexión "pgsql" central), y Postgres
 * rechaza ese comando dentro de una transacción. RefreshDatabase envuelve
 * cada test en una transacción; por eso el afterEach limpia a mano
 * (borrar el Tenant dispara TenantDeleted -> DeleteDatabase).
 */

beforeEach(function () {
    $this->artisan('migrate'); // idempotente: no repite lo ya aplicado.
});

afterEach(function () {
    Tenant::query()->get()->each->delete();
});

it('registers an organization and its subdomain responds', function () {
    $tenant = (new RegisterOrganization)->handle('900123456-8', 'Veeduría Ciudadana Santa Marta', 'veeduria-smr');

    expect($tenant->status)->toBe('active')
        ->and(Domain::query()->where('domain', 'veeduria-smr.govtrace.localhost')->exists())->toBeTrue();

    $this->withoutVite()->get('http://veeduria-smr.govtrace.localhost/')->assertOk();
});

it('rejects a NIT that is already registered', function () {
    (new RegisterOrganization)->handle('900123456-8', 'Veeduría Ciudadana Santa Marta', 'veeduria-smr');

    (new RegisterOrganization)->handle('900123456-8', 'Otra Veeduría', 'otra-veeduria');
})->throws(OrganizationValidationException::class, 'Ya existe una organización registrada con el NIT ingresado.');

it('rejects a NIT with an invalid or missing check digit', function (string $nit) {
    (new RegisterOrganization)->handle($nit, 'Veeduría Ciudadana Santa Marta', 'veeduria-smr');
})->with([
    'sin dígito de verificación' => ['900123456'],
    'dígito de verificación incorrecto' => ['900123456-3'],
    'base no numérica' => ['90012A456-8'],
])->throws(OrganizationValidationException::class, 'El NIT ingresado no es válido o el dígito de verificación no coincide con el algoritmo de la DIAN.');

it('rejects a subdomain that is already assigned', function () {
    (new RegisterOrganization)->handle('890000062-6', 'Veeduría Ciénaga', 'veeduria-smr');

    (new RegisterOrganization)->handle('900123456-8', 'Veeduría Ciudadana Santa Marta', 'veeduria-smr');
})->throws(OrganizationValidationException::class, 'El subdominio especificado ya no está disponible. Por favor elija otro.');

it('rejects a malformed subdomain', function (string $subdomain) {
    (new RegisterOrganization)->handle('900123456-8', 'Veeduría Ciudadana Santa Marta', $subdomain);
})->with([
    'mayúsculas' => ['Veeduria-SMR'],
    'espacio' => ['veeduria smr'],
    'tilde' => ['veeduría'],
    'menos de 3 caracteres' => ['ve'],
])->throws(OrganizationValidationException::class, 'El subdominio solo puede contener letras minúsculas, números y guiones, sin espacios ni caracteres especiales.');

it('rejects a reserved subdomain', function (string $reserved) {
    (new RegisterOrganization)->handle('900123456-8', 'Veeduría Ciudadana Santa Marta', $reserved);
})->with(['api', 'admin', 'app', 'www', 'auth', 'assets'])
    ->throws(OrganizationValidationException::class, 'El subdominio utiliza una palabra reservada del sistema y no puede ser utilizado.');

it('validates the organization name is between 3 and 150 characters', function (int $length, string $expected) {
    $name = str_repeat('A', $length);
    $register = fn () => (new RegisterOrganization)->handle('900123456-8', $name, 'veeduria-smr');

    if ($expected === 'aceptada') {
        expect($register())->toBeInstanceOf(Tenant::class);
    } else {
        expect($register)->toThrow(OrganizationValidationException::class);
    }
})->with([
    [0, 'rechazada'],
    [2, 'rechazada'],
    [3, 'aceptada'],
    [150, 'aceptada'],
    [151, 'rechazada'],
]);

it('never allows changing the subdomain once an organization is registered', function () {
    (new RegisterOrganization)->handle('900123456-8', 'Veeduría Ciudadana Santa Marta', 'veeduria-smr');

    $domain = Domain::query()->where('domain', 'veeduria-smr.govtrace.localhost')->firstOrFail();
    $domain->domain = 'smr-veeduria.govtrace.localhost';

    expect(fn () => $domain->save())->toThrow(RuntimeException::class);
    expect(Domain::query()->where('domain', 'veeduria-smr.govtrace.localhost')->exists())->toBeTrue();
});
