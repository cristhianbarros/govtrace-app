<?php

use App\Application\Organization\ConfigureTerritory;
use App\Application\Organization\RegisterOrganization;
use App\Application\Publication\PublicTimeline;
use App\Application\Sealing\SealingNetwork;
use App\Domain\Audit\AuditLog;
use App\Domain\Organization\Roles;
use App\Domain\Reports\Evidence;
use App\Domain\Reports\Exceptions\EvidenceIsImmutable;
use App\Domain\Reports\Report;
use App\Domain\Sealing\ReportSeal;
use App\Infrastructure\Tenancy\Tenant;
use Database\Seeders\DivipolaSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Support\FakeSealingNetwork;

/*
 * Iteración 15 — Publicación editorial (specs/PLAN.md). Traduce
 * features/US-037.feature (6 casos) contra el endpoint real,
 * POST /reports/{id}/withdraw, y la línea de tiempo pública que la it. 24
 * sirve (PublicTimeline): la tarjeta retirada es una lápida.
 *
 * Sin RefreshDatabase — registrar la organización ejecuta CREATE DATABASE.
 */

const TOMBSTONE_NOTICE = '🚫 Evidencia retirada por la organización por incumplimiento de políticas.';

beforeEach(function () {
    $this->artisan('migrate');
    (new DivipolaSeeder)->run();
    Storage::fake('evidencias');
    $this->network = new FakeSealingNetwork;
    app()->instance(SealingNetwork::class, $this->network);

    // Antecedentes: el Administrador y una evidencia publicada de la obra
    // "Pavimentación Calle 30".
    $this->tenant = (new RegisterOrganization)->handle('900123456-8', 'Veeduría Ciudadana Santa Marta', 'veeduria-smr');
    (new ConfigureTerritory)->handle($this->tenant, ['47']);
    $this->administrator = reportingMember($this->tenant, 'admin@veeduria-smr.org', Roles::Administrator);
    $this->veedor = reportingMember($this->tenant, 'carlos@correo.co');
    reportableContract('CO1.PCCNTR.1234567'); // su objeto: "Pavimentación Calle 30"
    $this->worksite = worksiteWithContracts($this->tenant, ['CO1.PCCNTR.1234567'], santaMartaWorksiteLocation());

    $this->published = sealedReport($this->tenant, $this->veedor);
    editorialDecision($this->administrator, 'publish', $this->published)->assertOk();
});

afterEach(function () {
    if (tenant()) {
        tenancy()->end();
    }

    Tenant::query()->get()->each->delete();
    DB::table('contracts')->delete();
    DB::table('audit_logs')->where('action', 'like', 'evidence.%')->delete();
});

/** @return array{report: Report, seal: ReportSeal, files: array<string, string>} la evidencia tal como está guardada */
function storedEvidence(Tenant $tenant, int $reportId): array
{
    return $tenant->run(function () use ($reportId) {
        $report = Report::query()->with(['seal', 'evidences'])->findOrFail($reportId);

        return [
            'report' => $report,
            'seal' => $report->seal,
            'files' => $report->evidences->mapWithKeys(fn (Evidence $evidence) => [$evidence->storage_path => Storage::disk('evidencias')->get($evidence->storage_path)])->all(),
        ];
    });
}

it('withdraws with a reason: the public card becomes a tombstone, the seal stays available, and the log records reason, admin and time', function () {
    editorialDecision($this->administrator, 'withdraw', $this->published, ['reason' => 'Aparece un menor de edad identificable'])
        ->assertOk()
        ->assertJson(['status' => 'Retirado']);

    $timeline = $this->tenant->run(fn () => (new PublicTimeline)->handle($this->worksite->id));
    $seal = storedEvidence($this->tenant, $this->published)['seal'];

    // La lápida: sin fotos ni comentario, con el aviso.
    expect($timeline)->toHaveCount(1)
        ->and($timeline[0]['report_id'])->toBe($this->published)
        ->and($timeline[0])->not->toHaveKey('comment')
        ->and($timeline[0])->not->toHaveKey('files')
        ->and($timeline[0]['notice'])->toBe(TOMBSTONE_NOTICE);

    // El sello sigue disponible para auditoría externa, en la tarjeta y en la red.
    expect($timeline[0]['seal'])->toBe(['merkle_root' => $seal->merkle_root, 'tx_hash' => $seal->tx_hash, 'ledger' => $seal->ledger])
        ->and($this->network->findSeal($seal->merkle_root)->ledger)->toBe($seal->ledger);

    $entry = AuditLog::query()->where('action', 'evidence.withdrawn')->sole();

    expect($entry->organization_id)->toBe($this->tenant->id)
        ->and($entry->actor_type)->toBe('organization_admin')
        ->and($entry->actor_id)->toBe((string) $this->administrator->id)
        ->and($entry->created_at)->not->toBeNull()
        ->and($entry->before)->toBe(['report_id' => $this->published, 'editorial_status' => 'published'])
        ->and($entry->after)->toBe(['report_id' => $this->published, 'editorial_status' => 'withdrawn', 'reason' => 'Aparece un menor de edad identificable']);
});

it('does not withdraw without a reason', function (array $payload) {
    editorialDecision($this->administrator, 'withdraw', $this->published, $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['reason' => 'Retirar exige un motivo.']);

    expect(editorialStatusOf($this->tenant, $this->published))->toBe('Publicado');
})->with([
    'sin motivo' => [[]],
    'motivo vacío' => [['reason' => '']],
    'solo espacios' => [['reason' => '   ']],
]);

it('cannot undo a withdrawal', function () {
    editorialDecision($this->administrator, 'withdraw', $this->published, ['reason' => 'Solicitud del afectado'])->assertOk();

    editorialDecision($this->administrator, 'publish', $this->published)
        ->assertConflict()
        ->assertJson(['message' => 'El retiro es definitivo: una evidencia retirada no se vuelve a publicar.']);

    expect(editorialStatusOf($this->tenant, $this->published))->toBe('Retirado');
});

it('does not delete the evidence when withdrawing it', function () {
    $before = storedEvidence($this->tenant, $this->published);

    editorialDecision($this->administrator, 'withdraw', $this->published, ['reason' => 'Solicitud del afectado'])->assertOk();

    $after = storedEvidence($this->tenant, $this->published);

    // Revocación lógica: el reporte, sus archivos y su sello siguen ahí.
    expect($after['report']->editorial_status->label())->toBe('Retirado')
        ->and($after['report']->editorial_reason)->toBe('Solicitud del afectado')
        ->and($after['files'])->toBe($before['files'])
        ->and($after['seal']->merkle_root)->toBe($before['seal']->merkle_root);
});

it('does not withdraw an evidence that was never published: it is rejected from the inbox', function () {
    $hidden = sealedReport($this->tenant, $this->veedor);

    editorialDecision($this->administrator, 'withdraw', $hidden, ['reason' => 'Solicitud del afectado'])
        ->assertConflict()
        ->assertJson(['message' => 'Una evidencia nunca publicada no se retira: se rechaza desde la bandeja de entrada.']);

    $row = collect(inbox($this->administrator)->json('data'))->firstWhere('id', $hidden);

    expect($row['actions'])->toBe(['publish', 'reject'])
        ->and(editorialStatusOf($this->tenant, $hidden))->toBe('Oculto');
});

it('does not let the Administrador delete or alter a sealed evidence', function () {
    $before = storedEvidence($this->tenant, $this->published);
    $evidence = $before['report']->evidences->first();
    $admin = test()->actingAs($this->administrator, 'tenant');

    // No hay ninguna ruta para borrar el reporte o su archivo, ni para reemplazarlo.
    foreach ([
        $admin->deleteJson("http://veeduria-smr.govtrace.localhost/reports/{$this->published}"),
        $admin->deleteJson("http://veeduria-smr.govtrace.localhost/reports/{$this->published}/evidences/{$evidence->id}"),
        $admin->postJson("http://veeduria-smr.govtrace.localhost/reports/{$this->published}/evidences/{$evidence->id}", ['file' => evidencePhoto('otra.jpg')]),
        $admin->putJson("http://veeduria-smr.govtrace.localhost/reports/{$this->published}/evidences/{$evidence->id}", ['file' => evidencePhoto('otra.jpg')]),
    ] as $response) {
        expect($response->status())->toBeIn([404, 405]);
    }

    // R-TA-02, también en el dominio: ni por código se borra ni se altera.
    $this->tenant->run(function () use ($evidence) {
        expect(fn () => Evidence::query()->findOrFail($evidence->id)->delete())->toThrow(EvidenceIsImmutable::class)
            ->and(fn () => Evidence::query()->findOrFail($evidence->id)->update(['sha256' => str_repeat('0', 64)]))->toThrow(EvidenceIsImmutable::class)
            ->and(fn () => Report::query()->findOrFail($this->published)->delete())->toThrow(EvidenceIsImmutable::class)
            ->and(fn () => Report::query()->findOrFail($this->published)->update(['comment' => 'Otro comentario']))->toThrow(EvidenceIsImmutable::class)
            ->and(fn () => ReportSeal::query()->where('report_id', $this->published)->sole()->update(['merkle_root' => str_repeat('0', 64)]))->toThrow(EvidenceIsImmutable::class);
    });

    $after = storedEvidence($this->tenant, $this->published);

    expect($after['files'])->toBe($before['files'])
        ->and($after['report']->comment)->toBe($before['report']->comment)
        ->and($after['seal']->only(['merkle_root', 'tx_hash', 'ledger']))->toBe($before['seal']->only(['merkle_root', 'tx_hash', 'ledger']));
});
