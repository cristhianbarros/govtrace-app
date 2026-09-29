<?php

use App\Application\Organization\ConfigureTerritory;
use App\Application\Organization\RegisterOrganization;
use App\Application\Sealing\SealingNetwork;
use App\Domain\Organization\Roles;
use App\Domain\Worksites\Worksite;
use App\Infrastructure\Tenancy\Tenant;
use Database\Seeders\DivipolaSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\Support\FakeSealingNetwork;

/*
 * Iteración 31 — Filtros del mapa público (specs/PLAN.md). Traduce
 * features/US-028.feature (los 2 casos del servidor; el botón "Aplicar"
 * deshabilitado es de la pantalla, Vitest) contra GET /public/worksites con
 * filtros: estado (verde, amarillo, rojo), fechas de las evidencias
 * publicadas, presupuesto (la suma de los contratos de la ficha) y municipio.
 * La respuesta sigue siendo liviana (R-MAP-02).
 *
 * Sin RefreshDatabase — registrar la organización ejecuta CREATE DATABASE.
 */

beforeEach(function () {
    $this->artisan('migrate');
    (new DivipolaSeeder)->run();
    Storage::fake('evidencias');
    Notification::fake();
    app()->instance(SealingNetwork::class, new FakeSealingNetwork);

    $this->tenant = (new RegisterOrganization)->handle('900123456-8', 'Veeduría Ciudadana Santa Marta', 'veeduria-smr');
    (new ConfigureTerritory)->handle($this->tenant, ['47']);
    $this->administrator = reportingMember($this->tenant, 'ana.perez@veeduria-smr.org', Roles::Administrator);
    $this->veedor = reportingMember($this->tenant, 'carlos@correo.co');
});

afterEach(function () {
    if (tenant()) {
        tenancy()->end();
    }

    Tenant::query()->get()->each->delete();
    DB::table('contracts')->delete();
    DB::table('audit_logs')->delete();
});

/** An anchored worksite with its contract; a published report of $classification captured on $capturedAt, if given. */
function filteredWorksite(string $secopContractId, array $contract, ?string $classification = null, ?string $capturedAt = null): Worksite
{
    reportableContract($secopContractId, $contract);
    [$latitude, $longitude] = [11.2408, -74.1990];
    $worksite = worksiteWithContracts(test()->tenant, [$secopContractId], [$latitude, $longitude]);

    if ($classification) {
        test()->travelTo($capturedAt);
        publishedReport(test()->tenant, test()->veedor, test()->administrator, [
            'secop_contract_id' => $secopContractId,
            'classification' => $classification,
            'captured_at' => now()->subMinutes(5)->toIso8601String(),
        ]);
        test()->travelBack();
    }

    return $worksite;
}

function filteredPins(array $filters): array
{
    return array_column(publicGet('/public/worksites?'.http_build_query($filters))->assertOk()->json('data'), 'id');
}

it('Filtrar por estado, fechas, presupuesto y municipio: only the worksites that meet the 4 filters', function () {
    $match = filteredWorksite('CO1.PCCNTR.1000001', ['value' => 1_500_000_000, 'municipality_code' => '47001'], 'Abandono', '2026-09-15 15:00:00');
    // Cada una falla un solo filtro.
    filteredWorksite('CO1.PCCNTR.1000002', ['value' => 1_500_000_000, 'municipality_code' => '47001'], 'Retraso', '2026-09-15 15:00:00');
    filteredWorksite('CO1.PCCNTR.1000003', ['value' => 1_500_000_000, 'municipality_code' => '47001'], 'Abandono', '2026-08-15 15:00:00');
    filteredWorksite('CO1.PCCNTR.1000004', ['value' => 800_000_000, 'municipality_code' => '47001'], 'Abandono', '2026-09-15 15:00:00');
    filteredWorksite('CO1.PCCNTR.1000005', ['value' => 1_500_000_000, 'municipality_code' => '47189'], 'Abandono', '2026-09-15 15:00:00');

    $pins = filteredPins(['status' => 'red', 'from' => '2026-09-01', 'to' => '2026-09-30', 'min_value' => 1_000_000_000, 'municipality' => '47001']);

    expect($pins)->toBe([$match->id]);
});

it('Ninguna obra coincide: no pin', function () {
    filteredWorksite('CO1.PCCNTR.1000001', ['value' => 1_500_000_000, 'municipality_code' => '47001']);

    expect(filteredPins(['municipality' => '47001', 'min_value' => 900_000_000_000]))->toBe([]);
});

// Reglas derivadas ------------------------------------------------------

it('applies each filter on its own, and counts the budget of every contract of a grouped worksite', function () {
    reportableContract('CO1.PCCNTR.2000001', ['value' => 600_000_000]);
    reportableContract('CO1.PCCNTR.2000002', ['value' => 600_000_000]);
    $grouped = worksiteWithContracts($this->tenant, ['CO1.PCCNTR.2000001', 'CO1.PCCNTR.2000002'], [11.2408, -74.1990]);
    $green = filteredWorksite('CO1.PCCNTR.1000001', ['value' => 100_000_000, 'municipality_code' => '47189']);

    expect(filteredPins(['min_value' => 1_000_000_000]))->toBe([$grouped->id])
        ->and(filteredPins(['status' => 'green']))->toBe([$grouped->id, $green->id])
        ->and(filteredPins(['municipality' => '47189']))->toBe([$green->id])
        ->and(filteredPins([]))->toBe([$grouped->id, $green->id]);
});

it('counts the evidence dates in the time of Colombia', function () {
    // El 1 de octubre a las 03:00 UTC todavía es 30 de septiembre en Colombia.
    $late = filteredWorksite('CO1.PCCNTR.1000001', [], 'Avance', '2026-10-01 03:05:00');

    expect(filteredPins(['from' => '2026-09-01', 'to' => '2026-09-30']))->toBe([$late->id]);
});

it('refuses a filter it does not know', function () {
    publicGet('/public/worksites?status=morado')->assertUnprocessable();
    publicGet('/public/worksites?from=ayer')->assertUnprocessable();
});

it('offers the municipalities of the worksites on the map, for the filter', function () {
    filteredWorksite('CO1.PCCNTR.1000001', ['municipality_code' => '47001']);
    filteredWorksite('CO1.PCCNTR.1000002', ['municipality_code' => '47189']);

    expect(publicGet('/public/worksites/filters')->assertOk()->json('data.municipalities'))->toBe([
        ['code' => '47189', 'name' => 'Ciénaga'],
        ['code' => '47001', 'name' => 'Santa Marta'],
    ]);
});
