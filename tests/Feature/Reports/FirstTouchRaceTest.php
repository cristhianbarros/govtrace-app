<?php

use App\Application\Organization\ConfigureTerritory;
use App\Application\Organization\RegisterOrganization;
use App\Application\Reports\CreateReport;
use App\Application\Reports\NewReport;
use App\Domain\Organization\User as OrganizationUser;
use App\Domain\Reports\Report;
use App\Domain\Worksites\Worksite;
use App\Domain\Worksites\WorksiteContract;
use App\Infrastructure\Tenancy\Tenant;
use Database\Seeders\DivipolaSeeder;
use Illuminate\Support\Facades\DB;

/*
 * Iteración 10 — escenario "Dos veedores envían a la vez el primer
 * reporte de una obra sin ubicación" de features/US-008.feature, con dos
 * transacciones reales (Done-when de la iteración).
 *
 * La transacción del veedor A queda abierta en este proceso, ya con la
 * ubicación fijada. La del veedor B corre en otro proceso
 * (tests/support/create_report_in_parallel.php) y TIENE que quedarse
 * esperando el bloqueo de A: solo cuando Postgres muestra a B esperando,
 * A confirma. Así la carrera ocurre de verdad, sin depender de la suerte.
 *
 * Sin RefreshDatabase — registrar la organización ejecuta CREATE DATABASE.
 */

beforeEach(function () {
    $this->artisan('migrate');
    (new DivipolaSeeder)->run();
});

afterEach(function () {
    if (tenant()) {
        DB::rollBack(0);
        tenancy()->end();
    }

    Tenant::query()->get()->each->delete();
    DB::table('contracts')->delete();
});

/** @return array{0: resource, 1: array<int, resource>} */
function startParallelReport(Tenant $tenant, OrganizationUser $veedor, string $secopContractId, array $position): array
{
    $process = proc_open(
        [PHP_BINARY, base_path('tests/support/create_report_in_parallel.php'), $tenant->id, (string) $veedor->id, $secopContractId, (string) $position[0], (string) $position[1]],
        [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes,
        base_path(),
    );

    stream_set_blocking($pipes[1], false);
    stream_set_blocking($pipes[2], false);

    return [$process, $pipes];
}

/** @return array<string, mixed> */
function finishParallelReport(array $child, int $seconds = 30): array
{
    [$process, $pipes] = $child;
    $stdout = $stderr = '';
    $deadline = microtime(true) + $seconds;

    do {
        $stdout .= stream_get_contents($pipes[1]);
        $stderr .= stream_get_contents($pipes[2]);
        $running = proc_get_status($process)['running'];

        if ($running) {
            usleep(50_000);
        }
    } while ($running && microtime(true) < $deadline);

    if ($running) {
        proc_terminate($process);
    }

    $stdout .= stream_get_contents($pipes[1]);
    $stderr .= stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    proc_close($process);

    return json_decode($stdout, true) ?? ['status' => 'crashed', 'output' => $stdout.$stderr];
}

/** Other backends of the tenant database blocked waiting for a lock right now. */
function backendsWaitingOnLocks(Tenant $tenant): int
{
    // Por la conexión central, fuera de la transacción abierta: dentro de
    // una transacción Postgres congela su foto de pg_stat_activity.
    return (int) DB::connection(config('tenancy.database.central_connection'))
        ->selectOne("select count(*) as waiting from pg_stat_activity where datname = ? and wait_event_type = 'Lock'", [$tenant->database()->getName()])
        ->waiting;
}

function waitUntil(Closure $condition, int $seconds): bool
{
    $deadline = microtime(true) + $seconds;

    while (microtime(true) < $deadline) {
        if ($condition()) {
            return true;
        }

        usleep(100_000);
    }

    return false;
}

it('lets only the first of two simultaneous first reports anchor the worksite, and validates the second one against it', function (bool $worksiteAlreadyExists) {
    $tenant = (new RegisterOrganization)->handle('900123456-8', 'Veeduría Ciudadana Santa Marta', 'veeduria-smr');
    (new ConfigureTerritory)->handle($tenant, ['47']);
    $veedorA = reportingMember($tenant, 'ana@correo.co');
    $veedorB = reportingMember($tenant, 'beto@correo.co');

    reportableContract('CO1.PCCNTR.2222222');
    if ($worksiteAlreadyExists) {
        worksiteWithContracts($tenant, ['CO1.PCCNTR.2222222'], null);
    }

    $positionA = santaMartaWorksiteLocation();
    $positionB = pointMetersNorthOf($positionA, 80); // el otro veedor, a 80 m

    [$bWasBlocked, $resultB] = $tenant->run(function () use ($tenant, $veedorA, $veedorB, $positionA, $positionB) {
        DB::beginTransaction();

        (new CreateReport)->handle($veedorA, new NewReport(
            secopContractId: 'CO1.PCCNTR.2222222',
            classification: 'Avance',
            comment: null,
            latitude: $positionA[0],
            longitude: $positionA[1],
            accuracyMeters: 10.0,
            capturedAt: now(),
        ));

        $child = startParallelReport($tenant, $veedorB, 'CO1.PCCNTR.2222222', $positionB);
        $bWasBlocked = waitUntil(fn () => backendsWaitingOnLocks($tenant) > 0, seconds: 15);

        DB::commit();

        return [$bWasBlocked, finishParallelReport($child)];
    });

    [$worksites, $links, $reports] = $tenant->run(fn () => [
        Worksite::query()->get(),
        WorksiteContract::query()->where('secop_contract_id', 'CO1.PCCNTR.2222222')->count(),
        Report::query()->count(),
    ]);

    expect($bWasBlocked)->toBeTrue('El veedor B nunca quedó esperando el bloqueo de A: la carrera no ocurrió.')
        ->and($resultB['status'])->toBe('accepted', json_encode($resultB))
        ->and($reports)->toBe(2)
        ->and($links)->toBe(1)
        ->and($worksites)->toHaveCount(1)
        // Solo la primera transacción fijó la ubicación oficial.
        ->and((float) $worksites->first()->latitude)->toBe($positionA[0])
        ->and((float) $worksites->first()->longitude)->toBe($positionA[1]);
})->with([
    'la ficha todavía no existe' => [false],
    'la ficha existe sin ubicación' => [true],
]);
