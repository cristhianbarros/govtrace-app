<?php

use App\Application\Organization\ConfigureTerritory;
use App\Application\Organization\RegisterOrganization;
use App\Domain\Organization\Roles;
use App\Domain\Organization\User as OrganizationUser;
use App\Domain\Reports\Report;
use App\Domain\Worksites\Worksite;
use App\Infrastructure\Tenancy\Tenant;
use Database\Seeders\DivipolaSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;

/*
 * Iteración 25 — Resumen del territorio para el Administrador
 * (specs/PLAN.md). Traduce features/US-049-RPT.feature (2 casos) contra
 * GET /summary: obras por color (las del mapa, US-027), evidencias por
 * clasificación y por mes, y veedores activos. La pantalla es de la it. 29.
 *
 * Las fichas y los reportes se crean directo en la base de la organización:
 * el resumen solo los cuenta, y 36 reportes de verdad tardarían minutos.
 *
 * Sin RefreshDatabase — registrar la organización ejecuta CREATE DATABASE.
 */

beforeEach(function () {
    $this->artisan('migrate');
    (new DivipolaSeeder)->run();

    $this->tenant = (new RegisterOrganization)->handle('900123456-8', 'Veeduría Ciudadana Santa Marta', 'veeduria-smr');
    (new ConfigureTerritory)->handle($this->tenant, ['47']);
    $this->administrator = reportingMember($this->tenant, 'ana.perez@veeduria-smr.org', Roles::Administrator);
});

afterEach(function () {
    if (tenant()) {
        tenancy()->end();
    }

    Tenant::query()->get()->each->delete();
    DB::table('contracts')->delete();
});

function territorySummary(): TestResponse
{
    test()->flushSession();

    return test()->actingAs(test()->administrator, 'tenant')->getJson('http://veeduria-smr.govtrace.localhost/summary');
}

/** An anchored worksite of $tenant with its own contract, overdue in execution if $overdue. */
function summaryWorksite(Tenant $tenant, string $secopContractId, bool $overdue = false): Worksite
{
    reportableContract($secopContractId, ['end_date' => ($overdue ? now()->subMonth() : now()->addMonths(3))->toDateString()]);

    return worksiteWithContracts($tenant, [$secopContractId], santaMartaWorksiteLocation());
}

/** Reports recorded as they arrive, straight in the organization's database. */
function recordReports(Tenant $tenant, Worksite $worksite, OrganizationUser $veedor, string $classification, int $count, string $capturedAt, string $status = 'published'): void
{
    $tenant->run(function () use ($worksite, $veedor, $classification, $count, $capturedAt, $status) {
        foreach (range(1, $count) as $ignored) {
            Report::query()->create([
                'user_id' => $veedor->id,
                'worksite_id' => $worksite->id,
                'classification' => $classification,
                'comment' => null,
                'latitude' => 11.2408,
                'longitude' => -74.1990,
                'accuracy_meters' => 10,
                'geofence_radius_meters' => 500,
                'captured_at' => $capturedAt,
                'received_at' => $capturedAt,
                'suspicious_capture_time' => false,
                'editorial_status' => $status,
            ]);
        }
    });
}

it('Resumen de la organización: worksites by color, evidences by classification and by month, active veedores', function () {
    $veedor = reportingMember($this->tenant, 'carlos@correo.co');
    foreach (range(2, 6) as $n) {
        reportingMember($this->tenant, "veedor{$n}@correo.co");
    }
    // Ni uno desactivado ni una invitación pendiente son veedores activos.
    $inactive = reportingMember($this->tenant, 'inactivo@correo.co');
    $this->tenant->run(fn () => $inactive->update(['is_active' => false]));
    $invited = reportingMember($this->tenant, 'invitado@correo.co');
    $this->tenant->run(fn () => $invited->update(['invitation_token_hash' => hash('sha256', 'token'), 'invitation_expires_at' => now()->addDay()]));

    // 10 verdes: 25 "Avance" entre ellas, 5 aún ocultos.
    foreach (range(1, 10) as $n) {
        $green = summaryWorksite($this->tenant, "CO1.PCCNTR.10{$n}");
        recordReports($this->tenant, $green, $veedor, 'Avance', $n <= 5 ? 3 : 2, '2026-08-20 15:00:00', $n <= 5 ? 'published' : ($n === 6 ? 'hidden' : 'published'));
    }
    // 4 amarillas: 2 "Retraso" publicados en cada una.
    foreach (range(1, 4) as $n) {
        recordReports($this->tenant, summaryWorksite($this->tenant, "CO1.PCCNTR.20{$n}"), $veedor, 'Retraso', 2, '2026-09-10 15:00:00');
    }
    // 3 rojas: 2 con "Abandono" y una vencida que SECOP sigue mostrando en ejecución, con un tercer "Abandono" oculto.
    recordReports($this->tenant, summaryWorksite($this->tenant, 'CO1.PCCNTR.301'), $veedor, 'Abandono', 1, '2026-09-12 15:00:00');
    recordReports($this->tenant, summaryWorksite($this->tenant, 'CO1.PCCNTR.302'), $veedor, 'Abandono', 1, '2026-09-12 15:00:00');
    recordReports($this->tenant, summaryWorksite($this->tenant, 'CO1.PCCNTR.303', overdue: true), $veedor, 'Abandono', 1, '2026-09-01 03:00:00', 'hidden');

    $summary = territorySummary()->assertOk()->json('data');

    expect($summary['worksites_by_color'])->toBe(['green' => 10, 'yellow' => 4, 'red' => 3])
        ->and($summary['evidences_by_classification'])->toBe(['Avance' => 25, 'Retraso' => 8, 'Abandono' => 3])
        // Por mes en la hora de Colombia: el 1 de septiembre a las 03:00 UTC allá todavía es 31 de agosto.
        ->and($summary['evidences_by_month'])->toBe([
            ['month' => '2026-08', 'Avance' => 25, 'Retraso' => 0, 'Abandono' => 1],
            ['month' => '2026-09', 'Avance' => 0, 'Retraso' => 8, 'Abandono' => 2],
        ])
        ->and($summary['active_observers'])->toBe(6);
});

it('El resumen solo incluye datos de la propia organización', function () {
    $cienaga = (new RegisterOrganization)->handle('901234567-7', 'Veeduría Ciénaga', 'veeduria-cienaga');
    (new ConfigureTerritory)->handle($cienaga, ['47189']);
    $cienagaVeedor = reportingMember($cienaga, 'laura@correo.co');
    recordReports($cienaga, summaryWorksite($cienaga, 'CO1.PCCNTR.777'), $cienagaVeedor, 'Abandono', 12, '2026-09-15 15:00:00');

    $summary = territorySummary()->assertOk()->json('data');

    expect($summary)->toBe([
        'worksites_by_color' => ['green' => 0, 'yellow' => 0, 'red' => 0],
        'evidences_by_classification' => ['Avance' => 0, 'Retraso' => 0, 'Abandono' => 0],
        'evidences_by_month' => [],
        'active_observers' => 0,
    ]);
});

// Reglas derivadas ------------------------------------------------------

it('does not count rejected evidences: they did not belong to the worksite', function () {
    $veedor = reportingMember($this->tenant, 'carlos@correo.co');
    $worksite = summaryWorksite($this->tenant, 'CO1.PCCNTR.101');
    recordReports($this->tenant, $worksite, $veedor, 'Retraso', 2, '2026-09-10 15:00:00');
    recordReports($this->tenant, $worksite, $veedor, 'Abandono', 3, '2026-09-10 15:00:00', 'rejected');

    expect(territorySummary()->json('data.evidences_by_classification'))->toBe(['Avance' => 0, 'Retraso' => 2, 'Abandono' => 0]);
});

it('is only for the Administrador', function () {
    $veedor = reportingMember($this->tenant, 'carlos@correo.co');
    test()->flushSession();

    $this->actingAs($veedor, 'tenant')->getJson('http://veeduria-smr.govtrace.localhost/summary')->assertForbidden();
});
