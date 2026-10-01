<?php

use App\Application\Organization\ConfigureTerritory;
use App\Application\Organization\RegisterOrganization;
use App\Application\Sealing\SealingNetwork;
use App\Domain\Organization\Roles;
use App\Domain\Sealing\Notifications\SealingQueueStalled;
use App\Domain\Sealing\ReportSeal;
use App\Domain\Sealing\SealStatus;
use App\Infrastructure\Tenancy\Tenant;
use App\Jobs\CheckSealingQueue;
use App\Jobs\ConfirmSeal;
use App\Jobs\SealReport;
use App\Models\User as SuperAdmin;
use Database\Seeders\DivipolaSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\FakeSealingNetwork;

/*
 * Iteración 22 — Reintentos del sellado con retraso creciente (specs/PLAN.md).
 * Traduce features/US-021.feature (5 casos, ya ajustada a Stellar) con la red
 * en memoria (FakeSealingNetwork), que aquí falla como la real: el RPC no
 * responde, o una transacción queda sin incluir o es rechazada.
 *
 * Los intentos los cuenta cada sello (report_seals.attempts), no la cola:
 * una pausa por falta de saldo no es una falla y no los gasta.
 *
 * Sin RefreshDatabase — registrar la organización ejecuta CREATE DATABASE.
 */

const STALLED_ALERT = '⚠️ Aviso: hay evidencias que llevan más de 2 horas en fila para ser certificadas de forma segura. GovTrace sigue intentándolo solo; ninguna se pierde.';

beforeEach(function () {
    $this->artisan('migrate');
    (new DivipolaSeeder)->run();
    Storage::fake('evidencias');
    Notification::fake();
    $this->network = new FakeSealingNetwork;
    app()->instance(SealingNetwork::class, $this->network);

    $this->tenant = (new RegisterOrganization)->handle('900123456-8', 'Veeduría Ciudadana Santa Marta', 'veeduria-smr');
    (new ConfigureTerritory)->handle($this->tenant, ['47']);
    $this->administrator = reportingMember($this->tenant, 'ana.perez@veeduria-smr.org', Roles::Administrator);
    $this->veedor = reportingMember($this->tenant, 'carlos@correo.co');
    reportableContract('CO1.PCCNTR.1234567');
    worksiteWithContracts($this->tenant, ['CO1.PCCNTR.1234567'], santaMartaWorksiteLocation());
    $this->superAdmin = SuperAdmin::factory()->create();
});

afterEach(function () {
    if (tenant()) {
        tenancy()->end();
    }

    Tenant::query()->get()->each->delete();
    DB::table('contracts')->delete();
    DB::table('sealing_pauses')->delete();
    DB::table('audit_logs')->delete(); // los reenvíos del sellado (it. 23), en la base central
    $this->superAdmin->delete();
});

/** A report "En Cola", as a veedor sends it. */
function queuedReport(): int
{
    test()->flushSession();
    $reportId = sendReport(test()->veedor)->assertCreated()->json('id');
    tenancy()->end();

    return $reportId;
}

/** Runs one attempt of SealReport and returns the job, to see if and when it was put back in the queue. */
function attemptSeal(int $reportId): SealReport
{
    $job = (new SealReport(test()->tenant->id, $reportId))->withFakeQueueInteractions();
    app()->call([$job, 'handle']);

    return $job;
}

function attemptConfirm(int $reportId): ConfirmSeal
{
    $job = (new ConfirmSeal(test()->tenant->id, $reportId))->withFakeQueueInteractions();
    app()->call([$job, 'handle']);

    return $job;
}

function sealOfReport(int $reportId): ReportSeal
{
    return test()->tenant->run(fn () => ReportSeal::query()->where('report_id', $reportId)->sole());
}

it('Reintentos con retraso creciente: 1 min, 5 min, 15 min y 1 h when the Stellar RPC fails', function () {
    $reportId = queuedReport();
    $this->network->unavailableCalls = 4;

    foreach ([60, 300, 900, 3600] as $attempt => $delay) {
        attemptSeal($reportId)->assertReleased(delay: $delay);

        $seal = sealOfReport($reportId);
        expect($seal->status->label())->toBe('En Cola')
            ->and($seal->attempts)->toBe($attempt + 1)
            ->and($seal->last_error)->toContain('La red de Stellar no respondió');
    }

    // El quinto intento, con la red de vuelta, sella.
    attemptSeal($reportId)->assertNotReleased();
    attemptConfirm($reportId);

    expect(sealOfReport($reportId)->status->label())->toBe('Sellada');
});

it('Falla definitiva tras el quinto intento: "Falla de Sellado", no sixth attempt, and the veedor sees no error', function () {
    $reportId = queuedReport();
    $this->network->unavailableCalls = 5;

    foreach (range(1, 4) as $failed) {
        attemptSeal($reportId);
    }
    attemptSeal($reportId)->assertNotReleased();

    $seal = sealOfReport($reportId);
    expect($seal->status->label())->toBe('Falla de Sellado')
        ->and($seal->status)->toBe(SealStatus::Failed)
        ->and($seal->attempts)->toBe(5)
        // Las fechas se leen dentro de la organización: Eloquent necesita su conexión para interpretarlas.
        ->and($this->tenant->run(fn () => ReportSeal::query()->where('report_id', $reportId)->value('failed_at')))->not->toBeNull()
        // US-010: el veedor ve "En Cola", nunca el error.
        ->and($seal->status->veedorLabel())->toBe('En Cola');

    // Ni un sexto intento automático, aunque el trabajo vuelva a correr.
    $this->network->unavailableCalls = 0;
    attemptSeal($reportId)->assertNotReleased();

    expect($this->network->submissions)->toBe([])
        ->and(sealOfReport($reportId)->status)->toBe(SealStatus::Failed);
});

it('Banner para el Administrador de Organización: he learns how many evidences could not be sealed; the veedor does not', function () {
    $failed = array_map(fn () => queuedReport(), range(1, 3));
    queuedReport(); // una más, que sigue en cola
    $this->tenant->run(fn () => ReportSeal::query()->whereIn('report_id', $failed)->update(['status' => SealStatus::Failed, 'attempts' => 5, 'failed_at' => now()]));

    $this->withoutVite()->actingAs($this->administrator, 'tenant')->get('http://veeduria-smr.govtrace.localhost/admin/inbox')
        ->assertInertia(fn (Assert $page) => $page->where('sealingFailures', 3));

    $this->withoutVite()->actingAs($this->veedor, 'tenant')->get('http://veeduria-smr.govtrace.localhost/reports/new')
        ->assertInertia(fn (Assert $page) => $page->where('sealingFailures', null));
});

it('La red de Stellar no confirma la transacción: after 5 minutes without a ledger, back to the queue with the same retry policy', function () {
    $reportId = queuedReport();
    $this->network->closesLedgerRightAway = false;
    attemptSeal($reportId);
    expect(sealOfReport($reportId)->status->label())->toBe('Transmitiendo');

    // Antes de 5 minutos, sigue esperando.
    $this->travel(4)->minutes();
    attemptConfirm($reportId)->assertReleased(delay: ConfirmSeal::RETRY_SECONDS);
    expect(sealOfReport($reportId)->status->label())->toBe('Transmitiendo');

    // A los 5 minutos sin ledger: vuelve a la cola, con el primer retraso.
    Queue::fake();
    $this->travel(2)->minutes();
    attemptConfirm($reportId)->assertNotReleased();

    $seal = sealOfReport($reportId);
    expect($seal->status->label())->toBe('En Cola')
        ->and($seal->attempts)->toBe(1)
        ->and($seal->last_error)->toContain('5 minutos');
    Queue::assertPushed(SealReport::class, fn (SealReport $job) => $job->reportId === $reportId && $job->delay->greaterThanOrEqualTo(now()->addSeconds(59)));
});

it('sends back to the queue a transaction the network rejected, and seals it on the next attempt', function () {
    $reportId = queuedReport();
    $this->network->closesLedgerRightAway = false;
    attemptSeal($reportId);
    $this->network->failTransaction(sealOfReport($reportId)->tx_hash);
    Queue::fake();

    attemptConfirm($reportId);

    expect(sealOfReport($reportId)->status->label())->toBe('En Cola')
        ->and(sealOfReport($reportId)->attempts)->toBe(1);

    $this->network->closesLedgerRightAway = true;
    attemptSeal($reportId);
    attemptConfirm($reportId);

    expect(sealOfReport($reportId)->status->label())->toBe('Sellada');
});

it('keeps waiting for the confirmation while the RPC does not answer, without spending attempts', function () {
    $reportId = queuedReport();
    $this->network->closesLedgerRightAway = false;
    attemptSeal($reportId);
    $this->network->unavailableCalls = 3;

    foreach (range(1, 3) as $check) {
        attemptConfirm($reportId)->assertReleased(delay: ConfirmSeal::RETRY_SECONDS);
    }

    expect(sealOfReport($reportId)->status->label())->toBe('Transmitiendo')
        ->and(sealOfReport($reportId)->attempts)->toBe(0);
});

it('Evidencia estancada más de 2 horas en cola: alerts the Super Administrador and the Administrador of that organization, once', function () {
    $cienaga = (new RegisterOrganization)->handle('890000062-6', 'Veeduría Ciénaga', 'veeduria-cienaga');
    // Notification::fake reconoce al destinatario por clase e id, y cada organización numera
    // sus usuarios desde 1: con dos usuarios antes, su Administrador no comparte id con los de Santa Marta.
    reportingMember($cienaga, 'uno@correo.co');
    reportingMember($cienaga, 'dos@correo.co');
    $theirAdministrator = reportingMember($cienaga, 'pedro@veeduria-cienaga.org', Roles::Administrator);

    $this->travel(-125)->minutes();
    $stalled = queuedReport();
    $this->travelBack();
    queuedReport(); // recién llegada: no cuenta

    app()->call([new CheckSealingQueue, 'handle']);

    $sentTo = fn ($notifiable) => Notification::assertSentTo($notifiable, SealingQueueStalled::class, fn (SealingQueueStalled $alert) => in_array(STALLED_ALERT, $alert->toMail($notifiable)->introLines, true));
    $sentTo($this->superAdmin);
    $sentTo($this->administrator);
    Notification::assertNotSentTo($this->veedor, SealingQueueStalled::class);
    Notification::assertNotSentTo($theirAdministrator, SealingQueueStalled::class);

    // Una sola alerta por evidencia estancada, no una cada 15 minutos.
    app()->call([new CheckSealingQueue, 'handle']);
    Notification::assertSentToTimes($this->administrator, SealingQueueStalled::class, 1);
    expect($this->tenant->run(fn () => ReportSeal::query()->where('report_id', $stalled)->value('stuck_alerted_at')))->not->toBeNull();
});

// Reglas derivadas ------------------------------------------------------

it('does not spend attempts while sealing is paused for lack of XLM in the sponsor account', function () {
    $reportId = queuedReport();
    $this->network->sponsorFunded = false;

    foreach (range(1, 7) as $check) {
        attemptSeal($reportId)->assertReleased(delay: SealReport::PAUSED_RETRY_SECONDS);
    }

    expect(sealOfReport($reportId)->attempts)->toBe(0)
        ->and(sealOfReport($reportId)->status->label())->toBe('En Cola');
});

it('checks the sealing queue every 15 minutes', function () {
    Artisan::call('schedule:list');

    expect(Artisan::output())->toMatch('/\*\/15\s+\*\s+\*\s+\*\s+\*\s+sealing-queue-check/');
});
