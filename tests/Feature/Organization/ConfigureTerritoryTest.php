<?php

use App\Application\Organization\ConfigureTerritory;
use App\Application\Organization\RegisterOrganization;
use App\Domain\Organization\Exceptions\OrganizationValidationException;
use App\Domain\Organization\OrganizationTerritory;
use App\Infrastructure\Tenancy\Tenant;
use Database\Seeders\DivipolaSeeder;

/*
 * Iteración 6 — Datos legales, territorio, log de auditoría y parámetros
 * (specs/PLAN.md). Traduce features/US-012.feature. Sin RefreshDatabase
 * — registrar la organización de las Antecedentes ejecuta CREATE DATABASE.
 *
 * El escenario "quitar una ciudad no borra lo ya registrado" solo se
 * prueba aquí en su mecánica de reemplazo del territorio: la parte sobre
 * evidencias y mapa público le corresponde a las iteraciones que
 * construyen esas piezas (it. 8+, it. 24+).
 */

beforeEach(function () {
    $this->artisan('migrate');
    (new DivipolaSeeder)->run();
    $this->tenant = (new RegisterOrganization)->handle('900123456-8', 'Veeduría Ciudadana Santa Marta', 'veeduria-smr');
});

afterEach(function () {
    if (tenant()) {
        tenancy()->end();
    }

    Tenant::query()->get()->each->delete();
});

it('configures a mix of departments and municipalities', function () {
    (new ConfigureTerritory)->handle($this->tenant, ['47', '05001']);

    $territory = OrganizationTerritory::query()->where('tenant_id', $this->tenant->id)->get();

    expect($territory)->toHaveCount(2)
        ->and($territory->pluck('department_code')->filter()->values()->all())->toBe(['47'])
        ->and($territory->pluck('municipality_code')->filter()->values()->all())->toBe(['05001']);
});

it('rejects an empty territory', function () {
    (new ConfigureTerritory)->handle($this->tenant, []);
})->throws(OrganizationValidationException::class, 'Debe seleccionar al menos un departamento o municipio para delimitar el territorio de vigilancia.');

it('rejects a code that does not exist in DIVIPOLA', function () {
    (new ConfigureTerritory)->handle($this->tenant, ['99999']);
})->throws(OrganizationValidationException::class);

it('removing a municipality replaces the territory without touching anything else', function () {
    (new ConfigureTerritory)->handle($this->tenant, ['47001', '47189']); // Santa Marta + Ciénaga

    (new ConfigureTerritory)->handle($this->tenant, ['47001']); // se quita Ciénaga

    $codes = OrganizationTerritory::query()->where('tenant_id', $this->tenant->id)->pluck('municipality_code');

    expect($codes)->toHaveCount(1)->and($codes->first())->toBe('47001');
});
