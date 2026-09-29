<?php

use App\Application\Organization\RegisterOrganization;
use App\Domain\Contracts\Contract;
use App\Domain\Reports\EditorialStatus;
use App\Domain\Reports\Report;
use App\Domain\Worksites\Worksite;
use App\Infrastructure\Tenancy\Tenant;
use Database\Seeders\DivipolaSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * Iteración 34 — US-051-RPT (features/US-051-RPT.feature, 2 casos): las
 * estadísticas públicas del territorio, sin sesión (R-VER-02) — obras en
 * riesgo (los pines rojos del mapa), evidencias publicadas por mes (de
 * captura, en la hora de Colombia) y contratos anulados que tienen
 * evidencias publicadas. Solo cuenta lo publicado.
 *
 * Las evidencias se crean directo en la base: aquí importa contarlas, y
 * el camino de un reporte hasta publicarse ya lo prueban sus tests.
 *
 * Sin RefreshDatabase — registrar la organización ejecuta CREATE DATABASE.
 */

beforeEach(function () {
    $this->artisan('migrate');
    (new DivipolaSeeder)->run();
    Notification::fake();
    Carbon::setTestNow('2026-09-29 12:00:00');

    // Antecedentes: un visitante sin sesión en el mapa de "veeduria-smr".
    $this->tenant = (new RegisterOrganization)->handle('900123456-8', 'Veeduría Ciudadana Santa Marta', 'veeduria-smr');
    $this->veedor = reportingMember($this->tenant, 'carlos@correo.co');
});

afterEach(function () {
    if (tenant()) {
        tenancy()->end();
    }

    Tenant::query()->get()->each->delete();
    DB::table('contracts')->delete();
    Carbon::setTestNow();
});

/** A worksite of its own contract, anchored $n km north of Santa Marta. */
function statsWorksite(string $secopContractId, int $n): Worksite
{
    reportableContract($secopContractId);

    return worksiteWithContracts(test()->tenant, [$secopContractId], pointMetersNorthOf(santaMartaWorksiteLocation(), $n * 1000));
}

/** $count evidences of that worksite, captured at $capturedAt (UTC). */
function statsEvidence(Worksite $worksite, string $classification, string $capturedAt, EditorialStatus $status = EditorialStatus::Published, int $count = 1): void
{
    test()->tenant->run(function () use ($worksite, $classification, $capturedAt, $status, $count) {
        foreach (range(1, $count) as $n) {
            Report::create([
                'user_id' => test()->veedor->id,
                'worksite_id' => $worksite->id,
                'classification' => $classification,
                'latitude' => $worksite->latitude,
                'longitude' => $worksite->longitude,
                'accuracy_meters' => 15,
                'geofence_radius_meters' => 500,
                'captured_at' => $capturedAt,
                'received_at' => $capturedAt,
                'editorial_status' => $status,
            ]);
        }
    });
}

/** SECOP annulled it (US-017): the contract stays, with its evidence. */
function annulled(string $secopContractId): void
{
    Contract::fromSecop(fn () => Contract::query()->where('secop_contract_id', $secopContractId)->update(['status' => 'cancelled', 'cancelled_at' => '2026-09-01 00:00:00']));
}

function publicStats(): array
{
    return publicGet('/public/stats')->assertOk()->json();
}

// US-051-RPT ----------------------------------------------------------------------

it('Estadísticas del mapa público: 3 worksites at risk, the evidences by month and 2 annulled contracts with evidence', function () {
    [$a, $b, $c, $d] = [statsWorksite('CO1.A', 1), statsWorksite('CO1.B', 2), statsWorksite('CO1.C', 3), statsWorksite('CO1.D', 4)];
    // 3 obras en riesgo: su última evidencia publicada es un abandono (pin rojo).
    statsEvidence($a, 'Abandono', '2026-07-10 15:00:00');
    statsEvidence($b, 'Abandono', '2026-08-10 15:00:00');
    statsEvidence($c, 'Abandono', '2026-09-10 15:00:00');
    statsEvidence($d, 'Retraso', '2026-09-12 15:00:00'); // amarillo: en alerta, no en riesgo
    // 2 contratos anulados con evidencias; uno sin evidencias y otro con una oculta no cuentan.
    annulled('CO1.A');
    annulled('CO1.B');
    statsWorksite('CO1.E', 5);
    annulled('CO1.E');
    statsEvidence(statsWorksite('CO1.F', 6), 'Retraso', '2026-09-05 15:00:00', EditorialStatus::Hidden);
    annulled('CO1.F');

    expect(publicStats())->toBe([
        'worksites_at_risk' => 3,
        'published_by_month' => [
            ['month' => '2026-07', 'total' => 1],
            ['month' => '2026-08', 'total' => 1],
            ['month' => '2026-09', 'total' => 2],
        ],
        'cancelled_contracts_with_evidence' => 2,
    ]);

    publicGet('/stats')->assertOk()->assertInertia(fn (Assert $page) => $page->component('Public/Stats'));
});

it('Solo cuentan las evidencias publicadas: September shows 10 of 14', function () {
    $worksite = statsWorksite('CO1.A', 1);
    statsEvidence($worksite, 'Avance', '2026-09-10 15:00:00', EditorialStatus::Published, 10);
    statsEvidence($worksite, 'Avance', '2026-09-11 15:00:00', EditorialStatus::Hidden, 4);

    expect(publicStats()['published_by_month'])->toBe([['month' => '2026-09', 'total' => 10]]);
});

// Reglas de US-051-RPT --------------------------------------------------------------

it('counts neither a rejected nor a withdrawn evidence', function () {
    $worksite = statsWorksite('CO1.A', 1);
    statsEvidence($worksite, 'Avance', '2026-09-10 15:00:00');
    statsEvidence($worksite, 'Avance', '2026-09-10 15:00:00', EditorialStatus::Rejected);
    statsEvidence($worksite, 'Avance', '2026-09-10 15:00:00', EditorialStatus::Withdrawn);

    expect(publicStats()['published_by_month'])->toBe([['month' => '2026-09', 'total' => 1]]);
});

it('puts an evidence in its month of Colombia: 1 October at 03:00 UTC is still 30 September', function () {
    statsEvidence(statsWorksite('CO1.A', 1), 'Avance', '2026-10-01 03:00:00');

    expect(publicStats()['published_by_month'])->toBe([['month' => '2026-09', 'total' => 1]]);
});

it('counts a worksite at risk because its contract is overdue, as its pin on the map', function () {
    statsWorksite('CO1.A', 1);
    Contract::fromSecop(fn () => Contract::query()->where('secop_contract_id', 'CO1.A')->update(['end_date' => '2026-06-30']));

    expect(publicStats()['worksites_at_risk'])->toBe(1);
});

it('says nothing is known yet for a territory without evidence', function () {
    expect(publicStats())->toBe(['worksites_at_risk' => 0, 'published_by_month' => [], 'cancelled_contracts_with_evidence' => 0]);
});

it('goes offline with the map when the organization is decommissioned (US-003b)', function () {
    $this->tenant->update(['status' => 'decommissioned', 'decommissioned_at' => now()]);

    publicGet('/public/stats')->assertStatus(410);
    publicGet('/stats')->assertInertia(fn (Assert $page) => $page->component('Public/Offline'));
});
