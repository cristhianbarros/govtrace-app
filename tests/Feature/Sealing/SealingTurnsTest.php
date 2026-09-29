<?php

use App\Application\Organization\ConfigureTerritory;
use App\Application\Organization\RegisterOrganization;
use App\Application\Sealing\SealingNetwork;
use App\Domain\Sealing\ReportSeal;
use App\Domain\Sealing\SealingRetryPolicy;
use App\Domain\Sealing\SealStatus;
use App\Infrastructure\Tenancy\Tenant;
use App\Jobs\ConfirmSeal;
use App\Jobs\SealReport;
use Database\Seeders\DivipolaSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\Support\FakeSealingNetwork;

/*
 * Iteración 39 — varias evidencias a la vez (US-021). La red en memoria se
 * porta como Stellar: una sola transacción pendiente de la selladora a la
 * vez. Mientras tanto, otro sello encuentra la selladora ocupada y vuelve en
 * unos segundos, sin gastar un intento. Antes, ese rechazo contaba como una
 * falla: con 7 evidencias a la vez, los reintentos volvían a chocar entre sí
 * y, tras el quinto, las últimas quedaban en "Falla de Sellado".
 *
 * La misma prueba contra la red local de verdad está en SealingBurstTest
 * (make test-stellar).
 *
 * Sin RefreshDatabase — registrar la organización ejecuta CREATE DATABASE.
 */

beforeEach(function () {
    $this->artisan('migrate');
    (new DivipolaSeeder)->run();
    Storage::fake('evidencias');
    Notification::fake();
    $this->network = new FakeSealingNetwork;
    // Un ledger cierra cuando el test lo dice: así se ve quién espera su turno.
    $this->network->closesLedgerRightAway = false;
    app()->instance(SealingNetwork::class, $this->network);

    reportableContract('CO1.PCCNTR.1234567');
    $this->organizations = [];
    foreach ([['900123456-8', 'Veeduría Ciudadana Santa Marta', 'veeduria-smr'], ['890000062-6', 'Veeduría Ciénaga', 'veeduria-cienaga']] as [$nit, $name, $subdomain]) {
        $tenant = (new RegisterOrganization)->handle($nit, $name, $subdomain);
        (new ConfigureTerritory)->handle($tenant, ['47']);
        worksiteWithContracts($tenant, ['CO1.PCCNTR.1234567'], santaMartaWorksiteLocation());
        $this->organizations[$subdomain] = [$tenant, reportingMember($tenant, "veedor@{$subdomain}.org")];
    }
});

afterEach(function () {
    if (tenant()) {
        tenancy()->end();
    }

    Tenant::query()->get()->each->delete();
    DB::table('contracts')->delete();
    DB::table('audit_logs')->delete();
});

/**
 * $count reports of the organization at $subdomain, each "En Cola", as its veedor sends them.
 *
 * @return list<array{0: Tenant, 1: int}>
 */
function reportsOf(string $subdomain, int $count): array
{
    [$tenant, $veedor] = test()->organizations[$subdomain];

    return array_map(function (int $number) use ($tenant, $veedor, $subdomain) {
        test()->flushSession();
        $reportId = sendReport($veedor, ['comment' => "Reporte {$number} de {$subdomain}"], "{$subdomain}.govtrace.localhost")->assertCreated()->json('id');
        tenancy()->end();

        return [$tenant, $reportId];
    }, range(1, $count));
}

/** Runs one attempt of SealReport, as the worker would, and returns the job: was it put back in the queue, and when? */
function sealTurn(Tenant $tenant, int $reportId): SealReport
{
    $job = (new SealReport($tenant->id, $reportId))->withFakeQueueInteractions();
    app()->call([$job, 'handle']);

    return $job;
}

function sealRecordOf(Tenant $tenant, int $reportId): ReportSeal
{
    return $tenant->run(fn () => ReportSeal::query()->where('report_id', $reportId)->sole());
}

it('Varias evidencias a la vez esperan su turno sin gastar intentos: 7 evidences of two organizations reach "Sellada", one after the other', function () {
    $reports = [...reportsOf('veeduria-smr', 4), ...reportsOf('veeduria-cienaga', 3)];

    // Llegan todas a la vez: la primera toma la selladora, las demás esperan su turno.
    foreach ($reports as $index => [$tenant, $reportId]) {
        $job = sealTurn($tenant, $reportId);
        $seal = sealRecordOf($tenant, $reportId);

        if ($index === 0) {
            expect($seal->status)->toBe(SealStatus::Transmitting);

            continue;
        }
        $job->assertReleased(delay: FakeSealingNetwork::BUSY_RETRY_SECONDS);
        expect($seal->status->label())->toBe('En Cola')
            ->and($seal->attempts)->toBe(0)
            ->and($seal->last_error)->toBeNull();
    }

    // Turno por turno: el ledger cierra, la confirmación la sella y la siguiente toma la selladora.
    $waiting = $reports;
    while ($waiting !== []) {
        [$tenant, $reportId] = array_shift($waiting);
        if (sealRecordOf($tenant, $reportId)->status !== SealStatus::Transmitting) {
            sealTurn($tenant, $reportId)->assertNotReleased();
        }
        $this->network->closeLedgerWith(sealRecordOf($tenant, $reportId)->tx_hash);
        app()->call([(new ConfirmSeal($tenant->id, $reportId))->withFakeQueueInteractions(), 'handle']);

        // Las que siguen en la fila, otra vez a la vez: la selladora está libre para una sola.
        foreach ($waiting as $index => [$other, $otherId]) {
            $index === 0 ? sealTurn($other, $otherId)->assertNotReleased() : sealTurn($other, $otherId)->assertReleased(delay: FakeSealingNetwork::BUSY_RETRY_SECONDS);
        }
    }

    $seals = collect($reports)->map(fn (array $report) => sealRecordOf(...$report));
    expect($seals->pluck('status')->unique()->all())->toBe([SealStatus::Sealed])
        ->and($seals->pluck('attempts')->unique()->all())->toBe([0])
        ->and($seals->pluck('last_error')->filter()->all())->toBe([])
        // Una transacción por evidencia: ninguna se envió de más.
        ->and($this->network->submissions)->toHaveCount(7)
        ->and($this->network->busyRefusals)->toBeGreaterThan(0);
});

it('lets the next seal go when a pending transaction never enters: its turn ends with its 4 minutes', function () {
    [[$tenant, $first], [, $second]] = reportsOf('veeduria-smr', 2);
    sealTurn($tenant, $first);
    sealTurn($tenant, $second)->assertReleased(delay: FakeSealingNetwork::BUSY_RETRY_SECONDS);

    // La primera nunca entra en un ledger: a los 4 minutos ya no puede, y la selladora queda libre.
    $this->travel(SealingRetryPolicy::TRANSACTION_VALIDITY_SECONDS + 1)->seconds();
    sealTurn($tenant, $second)->assertNotReleased();

    expect(sealRecordOf($tenant, $second)->status)->toBe(SealStatus::Transmitting)
        ->and(sealRecordOf($tenant, $second)->attempts)->toBe(0);
});

it('still counts a real failure of the network as an attempt, while others wait their turn', function () {
    [[$tenant, $first], [, $second]] = reportsOf('veeduria-smr', 2);
    $this->network->unavailableCalls = 1;

    sealTurn($tenant, $first)->assertReleased(delay: 60);
    sealTurn($tenant, $second);

    expect(sealRecordOf($tenant, $first)->attempts)->toBe(1)
        ->and(sealRecordOf($tenant, $first)->last_error)->toContain('La red de Stellar no respondió')
        ->and(sealRecordOf($tenant, $second)->status)->toBe(SealStatus::Transmitting);
});
