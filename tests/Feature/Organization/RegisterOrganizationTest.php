<?php

use App\Application\Organization\RegisterOrganization;
use App\Domain\Audit\AuditLog;
use App\Domain\Organization\Exceptions\OrganizationValidationException;
use App\Infrastructure\Tenancy\Domain;
use App\Infrastructure\Tenancy\Tenant;
use App\Models\User as SuperAdmin;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Stancl\Tenancy\Events\CreatingDatabase;
use Stancl\Tenancy\Events\TenantCreated;

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
    if (tenant()) {
        tenancy()->end();
    }

    Tenant::query()->get()->each->delete();
    DB::table('audit_logs')->delete();
});

/** The databases of organizations that exist in the PostgreSQL server. */
function tenantDatabases(): int
{
    return DB::scalar("select count(*) from pg_database where datname like 'tenant%'");
}

it('registers an organization and its subdomain responds', function () {
    $tenant = (new RegisterOrganization)->handle('900123456-8', 'Veeduría Ciudadana Santa Marta', 'veeduria-smr');

    expect($tenant->status)->toBe('active')
        ->and(Domain::query()->where('domain', 'veeduria-smr.govtrace.localhost')->exists())->toBeTrue();

    $this->withoutVite()->get('http://veeduria-smr.govtrace.localhost/')->assertOk();
});

it('No se puede registrar un NIT ya existente: rejects a NIT that is already registered', function () {
    (new RegisterOrganization)->handle('900123456-8', 'Veeduría Ciudadana Santa Marta', 'veeduria-smr');

    (new RegisterOrganization)->handle('900123456-8', 'Otra Veeduría', 'otra-veeduria');
})->throws(OrganizationValidationException::class, 'Ya existe una organización registrada con el NIT ingresado.');

it('El NIT debe traer un dígito de verificación válido según la DIAN: rejects a NIT with an invalid or missing check digit', function (string $nit) {
    (new RegisterOrganization)->handle($nit, 'Veeduría Ciudadana Santa Marta', 'veeduria-smr');
})->with([
    'sin dígito de verificación' => ['900123456'],
    'dígito de verificación incorrecto' => ['900123456-3'],
    'base no numérica' => ['90012A456-8'],
])->throws(OrganizationValidationException::class, 'El NIT ingresado no es válido o el dígito de verificación no coincide con el algoritmo de la DIAN.');

it('No se puede usar un subdominio ya asignado: rejects a subdomain that is already assigned', function () {
    (new RegisterOrganization)->handle('890000062-6', 'Veeduría Ciénaga', 'veeduria-smr');

    (new RegisterOrganization)->handle('900123456-8', 'Veeduría Ciudadana Santa Marta', 'veeduria-smr');
})->throws(OrganizationValidationException::class, 'El subdominio especificado ya no está disponible. Por favor elija otro.');

it('El subdominio solo admite minúsculas, números y guiones, con mínimo 3 caracteres: rejects a malformed subdomain', function (string $subdomain) {
    (new RegisterOrganization)->handle('900123456-8', 'Veeduría Ciudadana Santa Marta', $subdomain);
})->with([
    'mayúsculas' => ['Veeduria-SMR'],
    'espacio' => ['veeduria smr'],
    'tilde' => ['veeduría'],
    'menos de 3 caracteres' => ['ve'],
])->throws(OrganizationValidationException::class, 'El subdominio solo puede contener letras minúsculas, números y guiones, sin espacios ni caracteres especiales.');

it('El subdominio no puede ser una palabra reservada: rejects a reserved subdomain', function (string $reserved) {
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

it('Solo el Super Administrador asigna el subdominio de una organización: never allows changing the subdomain once an organization is registered', function () {
    (new RegisterOrganization)->handle('900123456-8', 'Veeduría Ciudadana Santa Marta', 'veeduria-smr');

    $domain = Domain::query()->where('domain', 'veeduria-smr.govtrace.localhost')->firstOrFail();
    $domain->domain = 'smr-veeduria.govtrace.localhost';

    expect(fn () => $domain->save())->toThrow(RuntimeException::class);
    expect(Domain::query()->where('domain', 'veeduria-smr.govtrace.localhost')->exists())->toBeTrue();
});

// Iteración 36 — lo que encontró /audit (specs/AUDIT.md) ------------------------

it('records the registration in the audit log, with the Super Administrador who did it (R-AUD-04)', function () {
    $superAdmin = SuperAdmin::factory()->create();

    $this->actingAs($superAdmin, 'web')->postJson('http://govtrace.localhost/admin/organizations', [
        'name' => 'Veeduría Ciudadana Santa Marta',
        'nit' => '900123456-8',
        'subdomain' => 'veeduria-smr',
    ])->assertCreated();

    $entry = AuditLog::query()->where('action', 'organization.registered')->sole();
    expect($entry->organization_id)->toBe(Tenant::query()->sole()->id)
        ->and($entry->actor_type)->toBe('super_admin')
        ->and($entry->actor_id)->toBe((string) $superAdmin->id)
        ->and($entry->before)->toBeNull()
        // it. 46b: nadie consultó el RUES antes de registrarla
        ->and($entry->after)->toBe(['nit' => '900123456-8', 'name' => 'Veeduría Ciudadana Santa Marta', 'subdomain' => 'veeduria-smr.govtrace.localhost', 'rues' => null]);

    $superAdmin->delete();
});

it('does not record a registration that was rejected', function () {
    (new RegisterOrganization)->handle('900123456-8', 'Veeduría Ciudadana Santa Marta', 'veeduria-smr');

    expect(fn () => (new RegisterOrganization)->handle('900123456-8', 'Otra Veeduría', 'otra-veeduria'))->toThrow(OrganizationValidationException::class);

    expect(AuditLog::query()->where('action', 'organization.registered')->count())->toBe(1);
});

it('leaves nothing behind when the registration fails half way: the NIT and the subdomain stay free', function (Closure $failAt) {
    $databases = tenantDatabases();
    $this->failing = true;
    $failAt(fn () => test()->failing ? throw new RuntimeException('Falla simulada.') : null);

    expect(fn () => (new RegisterOrganization)->handle('900123456-8', 'Veeduría Ciudadana Santa Marta', 'veeduria-smr'))
        ->toThrow(RuntimeException::class, 'Falla simulada');

    expect(Tenant::query()->count())->toBe(0)
        ->and(Domain::query()->count())->toBe(0)
        ->and(tenantDatabases())->toBe($databases)
        ->and(AuditLog::query()->where('action', 'organization.registered')->exists())->toBeFalse();

    // Resuelta la falla, el mismo NIT y el mismo subdominio se registran.
    $this->failing = false;
    (new RegisterOrganization)->handle('900123456-8', 'Veeduría Ciudadana Santa Marta', 'veeduria-smr');
    expect(Tenant::query()->count())->toBe(1);
})->with([
    'antes de crear su base' => [fn (Closure $fail) => Event::listen(CreatingDatabase::class, $fail)],
    // Después de crear y migrar su base (los listeners de stancl corren antes que este).
    'al preparar su base' => [fn (Closure $fail) => Event::listen(TenantCreated::class, $fail)],
    'al crear su dominio' => [fn (Closure $fail) => Domain::creating($fail)],
]);
