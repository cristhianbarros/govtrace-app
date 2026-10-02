<?php

use App\Application\Organization\ConfigureTerritory;
use App\Application\Organization\RegisterOrganization;
use App\Application\Sealing\SealingNetwork;
use App\Domain\Audit\AuditLog;
use App\Domain\Organization\Roles;
use App\Domain\Reports\Report;
use App\Domain\Sealing\ReportSeal;
use App\Domain\Sealing\SealStatus;
use App\Infrastructure\Tenancy\Tenant;
use App\Jobs\CheckSealingQueue;
use App\Jobs\ConfirmSeal;
use App\Jobs\SealReport;
use App\Models\User as SuperAdmin;
use Database\Seeders\DivipolaSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\Support\FakeSealingNetwork;

/*
 * Iteración 32 — US-047-MNT (features/US-047-MNT.feature, 2 casos): el
 * Super Administrador vuelve a encolar desde su panel las evidencias en
 * "Falla de Sellado" (US-021: tras el quinto intento no hay reintentos
 * automáticos). Vuelven "En Cola" con 5 intentos nuevos.
 *
 * Sin RefreshDatabase — registrar la organización ejecuta CREATE DATABASE.
 */

const LAST_ERROR = 'La red de Stellar no respondió (sendTransaction): cURL error 28: Operation timed out';

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
    DB::table('audit_logs')->delete();
    $this->superAdmin->delete();
});

/** A report in "Falla de Sellado": its fifth attempt failed (US-021). */
function failedSeal(): int
{
    test()->flushSession();
    $reportId = createdReportId(sendReport(test()->veedor));
    tenancy()->end();

    test()->tenant->run(fn () => ReportSeal::query()->where('report_id', $reportId)->update([
        'status' => SealStatus::Failed,
        'attempts' => 5,
        'last_error' => LAST_ERROR,
        'failed_at' => now(),
    ]));

    // Recibirlo despachó su primer SealReport: aquí solo cuentan los de re-encolar.
    Queue::fake();

    return $reportId;
}

function sealStatus(int $reportId): string
{
    return test()->tenant->run(fn () => ReportSeal::query()->where('report_id', $reportId)->sole()->status->label());
}

/** @param  list<int>  $reportIds */
function requeue(array $reportIds, ?string $organizationId = null): TestResponse
{
    return test()->actingAs(test()->superAdmin, 'web')->postJson('http://govtrace.localhost/admin/sealing/requeue', [
        'seals' => array_map(fn (int $id) => ['organization_id' => $organizationId ?? test()->tenant->id, 'report_id' => publicIdOf(Report::class, $id)], $reportIds), // it. 46c
    ]);
}

// US-047-MNT -----------------------------------------------------------------

it('Re-encolar varias evidencias a la vez: 3 of the 4 go back "En Cola", the fourth stays in "Falla de Sellado"', function () {
    [$first, $second, $third, $fourth] = [failedSeal(), failedSeal(), failedSeal(), failedSeal()];

    requeue([$first, $second, $third])->assertOk()->assertJson(['requeued' => 3, 'message' => '3 evidencias vuelven a la cola de sellado.']);

    expect(array_map(sealStatus(...), [$first, $second, $third, $fourth]))
        ->toBe(['En Cola', 'En Cola', 'En Cola', 'Falla de Sellado']);
    Queue::assertPushed(SealReport::class, 3);
    foreach ([$first, $second, $third] as $reportId) {
        Queue::assertPushed(SealReport::class, fn (SealReport $job) => $job->tenantId === $this->tenant->id && $job->reportId === $reportId);
    }
});

it('Un Administrador de Organización no puede re-encolar', function () {
    $reportId = failedSeal();
    $body = ['seals' => [['organization_id' => $this->tenant->id, 'report_id' => publicIdOf(Report::class, $reportId)]]];

    $this->actingAs($this->administrator, 'tenant')->postJson('http://govtrace.localhost/admin/sealing/requeue', $body)->assertUnauthorized();
    $this->actingAs($this->administrator, 'tenant')->postJson('http://veeduria-smr.govtrace.localhost/admin/sealing/requeue', $body)->assertNotFound();

    expect(sealStatus($reportId))->toBe('Falla de Sellado');
    Queue::assertNotPushed(SealReport::class);
});

// Reglas de US-047-MNT ----------------------------------------------------------

it('gives a requeued seal five fresh attempts, and it seals with the network back', function () {
    $reportId = failedSeal();

    requeue([$reportId])->assertOk();

    $seal = $this->tenant->run(fn () => ReportSeal::query()->where('report_id', $reportId)->sole());
    expect($seal->attempts)->toBe(0)
        ->and($seal->failed_at)->toBeNull();

    app()->call([new SealReport($this->tenant->id, $reportId), 'handle']);
    app()->call([new ConfirmSeal($this->tenant->id, $reportId), 'handle']);

    expect(sealStatus($reportId))->toBe('Sellada');
});

it('only requeues seals in "Falla de Sellado": a sealed one stays as it is', function () {
    $failed = failedSeal();
    $sealed = sealedReport($this->tenant, $this->veedor);
    Queue::fake(); // sealedReport despachó los suyos

    requeue([$failed, $sealed])->assertOk()->assertJson(['requeued' => 1, 'message' => '1 evidencia vuelve a la cola de sellado.']);

    expect(sealStatus($sealed))->toBe('Sellada');
    Queue::assertPushed(SealReport::class, 1);
});

it('records each requeue in the audit log, with the error it had', function () {
    $reportId = failedSeal();

    requeue([$reportId])->assertOk();

    $entry = AuditLog::query()->where('action', 'seal.requeued')->sole();
    expect($entry->organization_id)->toBe($this->tenant->id)
        ->and($entry->actor_type)->toBe('super_admin')
        ->and($entry->actor_id)->toBe((string) $this->superAdmin->id)
        ->and($entry->before)->toMatchArray(['report_id' => $reportId, 'status' => 'failed', 'attempts' => 5, 'last_error' => LAST_ERROR])
        ->and($entry->after)->toMatchArray(['report_id' => $reportId, 'status' => 'queued', 'attempts' => 0]);
});

it('does not raise a stalled-queue alert for a seal the Super Administrador just requeued (US-021)', function () {
    Carbon::setTestNow(now()->subHours(3));
    $reportId = failedSeal();
    Carbon::setTestNow();

    requeue([$reportId])->assertOk();
    app()->call([new CheckSealingQueue, 'handle']);

    Notification::assertNothingSent();
});

it('rejects a requeue without seals, or of an organization that does not exist', function (array $body) {
    $this->actingAs($this->superAdmin, 'web')->postJson('http://govtrace.localhost/admin/sealing/requeue', $body)->assertUnprocessable();
})->with([
    'sin evidencias' => [['seals' => []]],
    'organización inexistente' => [['seals' => [['organization_id' => 'no-existe', 'report_id' => 1]]]],
    'reporte sin su identificador público' => [['seals' => [['organization_id' => 'veeduria-smr', 'report_id' => 'uno']]]],
]);

it('lists the seals in "Falla de Sellado" of every organization, oldest failure first', function () {
    // La más reciente se recibió primero: el orden es el de la falla, no el de llegada.
    Carbon::setTestNow('2026-09-29 14:00:00');
    $newer = failedSeal();
    Carbon::setTestNow('2026-09-29 12:00:00');
    $older = failedSeal();
    sealedReport($this->tenant, $this->veedor); // sellada: no es una falla

    $failures = $this->actingAs($this->superAdmin, 'web')->getJson('http://govtrace.localhost/admin/sealing/data')->assertOk()->json('failures');

    expect($failures)->toBe([
        ['organization_id' => $this->tenant->id, 'organization' => 'Veeduría Ciudadana Santa Marta', 'report_id' => publicIdOf(Report::class, $older), 'failed_at' => '2026-09-29T07:00:00-05:00', 'attempts' => 5, 'last_error' => LAST_ERROR],
        ['organization_id' => $this->tenant->id, 'organization' => 'Veeduría Ciudadana Santa Marta', 'report_id' => publicIdOf(Report::class, $newer), 'failed_at' => '2026-09-29T09:00:00-05:00', 'attempts' => 5, 'last_error' => LAST_ERROR],
    ]);
});
