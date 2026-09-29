<?php

use App\Application\Organization\RegisterOrganization;
use App\Domain\Contracts\Contract;
use App\Domain\Worksites\Worksite;
use App\Infrastructure\Tenancy\Tenant;
use App\Jobs\CalculateWorksitesAtRisk;
use Database\Seeders\DivipolaSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Iteración 9 — Ficha de obra por organización y cálculo de riesgo
 * (specs/PLAN.md). Traduce features/US-034.feature: el Esquema de 5
 * filas + el escenario "el estado vive en la ficha, no en el contrato"
 * (6 casos), más 2 tests técnicos (el job programado, y que solo
 * recalcula organizaciones activas — es el primer job que entra al
 * contexto de cada tenant, así que probar el aislamiento importa).
 *
 * Sin RefreshDatabase — registrar la organización de las Antecedentes
 * ejecuta CREATE DATABASE.
 */

beforeEach(function () {
    $this->artisan('migrate');
    (new DivipolaSeeder)->run();
});

afterEach(function () {
    if (tenant()) {
        tenancy()->end();
    }

    Tenant::query()->get()->each->delete();
    DB::table('contracts')->delete();
});

/**
 * @param  array<string, mixed>  $overrides
 */
function riskyContract(array $overrides = []): Contract
{
    return Contract::fromSecop(fn () => Contract::create(array_merge([
        'secop_contract_id' => 'CO1.PCCNTR.RIESGO',
        'entity_name' => 'Alcaldía de Santa Marta',
        'contract_type' => 'Obra',
        'status' => 'En ejecución',
        'department_code' => '47',
        'municipality_code' => '47001',
        'end_date' => '2026-09-26',
    ], $overrides)));
}

it('marks the worksite "en riesgo" only when its contract expired and SECOP still shows it "En ejecución"', function (string $fechaFin, string $estado, bool $enRiesgo) {
    $this->travelTo('2026-09-27');

    $tenant = (new RegisterOrganization)->handle('900123456-8', 'Veeduría Ciudadana Santa Marta', 'veeduria-smr');
    riskyContract(['end_date' => $fechaFin, 'status' => $estado]);
    $worksiteId = worksiteWithContracts($tenant, ['CO1.PCCNTR.RIESGO'], null)->id;

    (new CalculateWorksitesAtRisk)->handle();

    $atRisk = $tenant->run(fn () => Worksite::find($worksiteId)->at_risk);

    expect($atRisk)->toBe($enRiesgo);

    $tenant->delete();
    $this->travelBack();
})->with([
    'vencido y aún En ejecución' => ['2026-09-26', 'En ejecución', true],
    'todavía no vence' => ['2026-09-28', 'En ejecución', false],
    'vencido pero ya terminado' => ['2026-09-26', 'terminado', false],
    // R-SEC-07: "Modificado" (una adición o prórroga) sigue en ejecución.
    'vencido y Modificado' => ['2026-09-26', 'Modificado', true],
    'vencido pero Suspendido' => ['2026-09-26', 'Suspendido', false],
]);

it('saves the calculated risk in the ficha de obra, never touching the contract itself', function () {
    $this->travelTo('2026-09-27');

    $tenant = (new RegisterOrganization)->handle('900123456-8', 'Veeduría Ciudadana Santa Marta', 'veeduria-smr');
    $contract = riskyContract();
    $originalStatus = $contract->status;
    $originalUpdatedAt = $contract->updated_at;
    $worksiteId = worksiteWithContracts($tenant, ['CO1.PCCNTR.RIESGO'], null)->id;

    (new CalculateWorksitesAtRisk)->handle();

    $atRisk = $tenant->run(fn () => Worksite::find($worksiteId)->at_risk);
    $contract->refresh();

    // R-SEC-01: el contrato de SECOP no cambia; "en riesgo" solo existe
    // en la ficha de obra de la organización.
    expect($atRisk)->toBeTrue()
        ->and($contract->status)->toBe($originalStatus)
        ->and($contract->updated_at)->toEqual($originalUpdatedAt)
        ->and(Schema::hasColumn('contracts', 'at_risk'))->toBeFalse();

    $tenant->delete();
    $this->travelBack();
});

it('only recalculates the worksites of active organizations, leaving suspended ones untouched', function () {
    $this->travelTo('2026-09-27');

    $active = (new RegisterOrganization)->handle('900123456-8', 'Veeduría Ciudadana Santa Marta', 'veeduria-smr');
    $suspended = (new RegisterOrganization)->handle('890000062-6', 'Veeduría Paisa', 'veeduria-paisa');
    $suspended->update(['status' => 'suspended']);

    riskyContract(['secop_contract_id' => 'CO1.PCCNTR.ACTIVA']);
    riskyContract(['secop_contract_id' => 'CO1.PCCNTR.SUSPENDIDA']);

    $activeWorksiteId = worksiteWithContracts($active, ['CO1.PCCNTR.ACTIVA'], null)->id;
    $suspendedWorksiteId = worksiteWithContracts($suspended, ['CO1.PCCNTR.SUSPENDIDA'], null)->id;

    (new CalculateWorksitesAtRisk)->handle();

    expect($active->run(fn () => Worksite::find($activeWorksiteId)->at_risk))->toBeTrue()
        ->and($suspended->run(fn () => Worksite::find($suspendedWorksiteId)->at_risk))->toBeFalse();

    $active->delete();
    $suspended->delete();
    $this->travelBack();
});

it('a worksite grouping several contracts is at risk when any of them expired while still "En ejecución"', function () {
    // US-045-INT (it. 29) agrupa contratos en una ficha — p. ej. una obra
    // con varias fases. Basta con que una fase siga "En ejecución" con la
    // fecha vencida para que la obra entera quede en riesgo (it. 10).
    $this->travelTo('2026-09-27');

    $tenant = (new RegisterOrganization)->handle('900123456-8', 'Veeduría Ciudadana Santa Marta', 'veeduria-smr');
    riskyContract(['secop_contract_id' => 'CO1.PCCNTR.FASE1', 'status' => 'terminado']);
    riskyContract(['secop_contract_id' => 'CO1.PCCNTR.FASE2', 'status' => 'En ejecución']);
    $worksiteId = worksiteWithContracts($tenant, ['CO1.PCCNTR.FASE1', 'CO1.PCCNTR.FASE2'], null)->id;

    (new CalculateWorksitesAtRisk)->handle();

    expect($tenant->run(fn () => Worksite::find($worksiteId)->at_risk))->toBeTrue();

    $tenant->delete();
    $this->travelBack();
});

it('is scheduled to run once a day, after the SECOP sync it depends on', function () {
    Artisan::call('schedule:list');

    // 03:00, una hora después de la sincronización de las 02:00
    // (routes/console.php) — calcular el riesgo con contratos viejos no
    // tendría sentido.
    expect(Artisan::output())->toMatch('/0\s+3\s+\*\s+\*\s+\*\s+calculate-worksites-at-risk/');
});
