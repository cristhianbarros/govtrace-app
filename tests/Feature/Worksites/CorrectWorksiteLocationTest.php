<?php

use App\Application\Organization\ConfigureTerritory;
use App\Application\Organization\RegisterOrganization;
use App\Domain\Audit\AuditLog;
use App\Domain\Organization\Roles;
use App\Domain\Organization\User as OrganizationUser;
use App\Domain\Shared\PublicId;
use App\Domain\Worksites\Worksite;
use App\Infrastructure\Tenancy\Domain;
use App\Infrastructure\Tenancy\Tenant;
use Database\Seeders\DivipolaSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;

/*
 * Iteración 11 — Corrección de ubicación (specs/PLAN.md). Traduce
 * features/US-035.feature (4 casos) contra el endpoint real,
 * PATCH /worksites/{id}/location.
 *
 * El mapa con el pin arrastrable y los dos campos son la pantalla del
 * Administrador (it. 18): de cualquiera de las dos formas, al backend le
 * llegan una latitud y una longitud — por eso las dos filas del Esquema
 * ejercitan la misma petición.
 *
 * Sin RefreshDatabase — registrar la organización ejecuta CREATE DATABASE.
 */

beforeEach(function () {
    $this->artisan('migrate');
    (new DivipolaSeeder)->run();
    Storage::fake('evidencias');

    // Antecedentes: la obra "Acueducto Gaira" con ubicación 11.2000, -74.2300.
    $this->tenant = (new RegisterOrganization)->handle('900123456-8', 'Veeduría Ciudadana Santa Marta', 'veeduria-smr');
    (new ConfigureTerritory)->handle($this->tenant, ['47']);
    $this->administrator = reportingMember($this->tenant, 'admin@veeduria-smr.org', Roles::Administrator);

    reportableContract('CO1.PCCNTR.1234567');
    $this->gaira = worksiteWithContracts($this->tenant, ['CO1.PCCNTR.1234567'], [11.2000, -74.2300]);
});

afterEach(function () {
    if (tenant()) {
        tenancy()->end();
    }

    Tenant::query()->get()->each->delete();
    DB::table('contracts')->delete();
    DB::table('audit_logs')->where('action', 'worksite.location_corrected')->delete();
});

function correctLocation(OrganizationUser $member, string $subdomain, int $worksiteId, float $latitude, float $longitude): TestResponse
{
    // It. 46c: la ruta nombra la obra por su identificador público; una que no es de esa organización no se encuentra.
    $worksite = Domain::query()->where('domain', "{$subdomain}.govtrace.localhost")->firstOrFail()->tenant
        ->run(fn () => Worksite::query()->whereKey($worksiteId)->value('public_id')) ?? PublicId::generate();

    return test()->actingAs($member, 'tenant')->patchJson("http://{$subdomain}.govtrace.localhost/worksites/{$worksite}/location", [
        'latitude' => $latitude,
        'longitude' => $longitude,
    ]);
}

it('corrects the official location, and the geofence of the veedores follows it', function (string $forma) {
    correctLocation($this->administrator, 'veeduria-smr', $this->gaira->id, 11.2408, -74.1990)
        ->assertOk()
        ->assertJson(['message' => 'La ubicación oficial de la obra ha sido ajustada. La nueva geocerca de 500m ya está activa para los veedores.']);

    $location = $this->tenant->run(fn () => $this->gaira->fresh()->location());
    expect($location->latitude)->toBe(11.2408)
        ->and($location->longitude)->toBe(-74.199);

    // Un veedor a 120 m de la nueva ubicación ya puede reportar
    // (sendReport lo ubica ahí por defecto); desde la vieja, a más de 5 km, no.
    $veedor = reportingMember($this->tenant, 'carlos@correo.co');
    sendReport($veedor)->assertCreated();
    sendReport($veedor, ['latitude' => 11.2000, 'longitude' => -74.2300])->assertUnprocessable();
})->with([
    'arrastrando el pin' => ['pin'],
    'escribiendo latitud y longitud' => ['campos'],
]);

it('Cada corrección queda en el log de auditoría: records every correction in the audit log: who, when, the previous and the new coordinates', function () {
    correctLocation($this->administrator, 'veeduria-smr', $this->gaira->id, 11.2408, -74.1990)->assertOk();

    $entry = AuditLog::query()->where('action', 'worksite.location_corrected')->sole();

    expect($entry->organization_id)->toBe($this->tenant->id)
        ->and($entry->actor_type)->toBe('organization_admin')
        ->and($entry->actor_id)->toBe((string) $this->administrator->id)
        ->and($entry->created_at)->not->toBeNull()
        ->and($entry->before)->toBe(['worksite_id' => $this->gaira->id, 'latitude' => 11.2, 'longitude' => -74.23])
        ->and($entry->after)->toBe(['worksite_id' => $this->gaira->id, 'latitude' => 11.2408, 'longitude' => -74.199]);
});

it('La corrección no afecta a otra organización: does not touch the same worksite in another organization', function () {
    // "Veeduría Ciénaga" también vigila la obra, con su propia ficha.
    $cienaga = (new RegisterOrganization)->handle('890000062-6', 'Veeduría Ciénaga', 'veeduria-cienaga');
    (new ConfigureTerritory)->handle($cienaga, ['47']);
    $theirGaira = worksiteWithContracts($cienaga, ['CO1.PCCNTR.1234567'], [11.2000, -74.2300]);

    correctLocation($this->administrator, 'veeduria-smr', $this->gaira->id, 11.2408, -74.1990)->assertOk();

    $theirLocation = $cienaga->run(fn () => Worksite::query()->findOrFail($theirGaira->id)->location());

    expect($theirLocation->latitude)->toBe(11.2)
        ->and($theirLocation->longitude)->toBe(-74.23);
});

// Técnicos --------------------------------------------------------------

it('only lets the Administrador de Organización correct a location', function () {
    $veedor = reportingMember($this->tenant, 'carlos@correo.co');

    correctLocation($veedor, 'veeduria-smr', $this->gaira->id, 11.2408, -74.1990)->assertForbidden();

    expect($this->tenant->run(fn () => (float) $this->gaira->fresh()->latitude))->toBe(11.2);
});

it('rejects coordinates that do not exist', function () {
    correctLocation($this->administrator, 'veeduria-smr', $this->gaira->id, 95.0, -74.1990)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('location');
});
