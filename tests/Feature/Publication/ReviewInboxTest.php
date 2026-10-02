<?php

use App\Application\Organization\ConfigureTerritory;
use App\Application\Organization\RegisterOrganization;
use App\Application\Publication\PublicTimeline;
use App\Application\Sealing\SealingNetwork;
use App\Domain\Audit\AuditLog;
use App\Domain\Organization\Roles;
use App\Domain\Organization\User as OrganizationUser;
use App\Domain\Reports\Report;
use App\Infrastructure\Tenancy\Tenant;
use Database\Seeders\DivipolaSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Stancl\Tenancy\Middleware\ScopeSessions;
use Tests\Support\FakeSealingNetwork;

/*
 * Iteración 15 — Publicación editorial (specs/PLAN.md). Traduce
 * features/US-036.feature (8 casos) contra los endpoints reales de la
 * bandeja de entrada: GET /inbox y POST /reports/{id}/publish | reject.
 * La pantalla es de la it. 18; el mapa y la línea de tiempo completos, de
 * la it. 24 — aquí se prueba qué evidencias les llegan.
 *
 * "Evidencia", en el lenguaje del negocio, es un reporte con sus archivos:
 * se publica, se rechaza o se retira entero, porque se selló entero (una
 * raíz de Merkle por reporte, US-020b).
 *
 * Sin RefreshDatabase — registrar la organización ejecuta CREATE DATABASE.
 */

beforeEach(function () {
    $this->artisan('migrate');
    (new DivipolaSeeder)->run();
    Storage::fake('evidencias');
    $this->network = new FakeSealingNetwork;
    app()->instance(SealingNetwork::class, $this->network);

    // Antecedentes: el Administrador de "Veeduría Ciudadana Santa Marta" y
    // 4 evidencias selladas en estado "Oculto".
    $this->tenant = (new RegisterOrganization)->handle('900123456-8', 'Veeduría Ciudadana Santa Marta', 'veeduria-smr');
    (new ConfigureTerritory)->handle($this->tenant, ['47']);
    $this->administrator = reportingMember($this->tenant, 'admin@veeduria-smr.org', Roles::Administrator);
    $this->veedor = reportingMember($this->tenant, 'carlos@correo.co');
    reportableContract('CO1.PCCNTR.1234567');
    $this->worksite = worksiteWithContracts($this->tenant, ['CO1.PCCNTR.1234567'], santaMartaWorksiteLocation());

    $this->hidden = array_map(fn () => sealedReport($this->tenant, $this->veedor), range(1, 4));
});

afterEach(function () {
    if (tenant()) {
        tenancy()->end();
    }

    Tenant::query()->get()->each->delete();
    DB::table('contracts')->delete();
    DB::table('audit_logs')->where('action', 'like', 'evidence.%')->delete();
});

/** @return array{0: list<int>, 1: list<array<string, mixed>>} lo publicado en el mapa y la línea de tiempo de la obra */
function publicView(Tenant $tenant, int $worksiteId): array
{
    return $tenant->run(fn () => [
        Report::query()->onPublicMap()->orderBy('id')->pluck('id')->all(),
        (new PublicTimeline)->handle($worksiteId),
    ]);
}

it('Toda evidencia sellada nace oculta: is born hidden when sealed, and does not show on the public map', function () {
    $reportId = sealedReport($this->tenant, $this->veedor);

    $report = $this->tenant->run(fn () => Report::query()->with('seal')->findOrFail($reportId));
    [$onMap, $timeline] = publicView($this->tenant, $this->worksite->id);

    expect($report->seal->status->label())->toBe('Sellada')
        ->and($report->editorial_status->label())->toBe('Oculto')
        ->and($onMap)->toBe([])
        ->and($timeline)->toBe([]);
});

it('publishes an evidence: "Evidencia publicada. Ya es visible en el mapa.", and it shows on the map and the timeline', function () {
    $reportId = $this->hidden[0];
    inbox($this->administrator)->assertOk()->assertJsonCount(4, 'data');

    editorialDecision($this->administrator, 'publish', $reportId)
        ->assertOk()
        ->assertJson(['message' => 'Evidencia publicada. Ya es visible en el mapa.', 'status' => 'Publicado']);

    [$onMap, $timeline] = publicView($this->tenant, $this->worksite->id);

    expect(editorialStatusOf($this->tenant, $reportId))->toBe('Publicado')
        ->and($onMap)->toBe([$reportId])
        ->and(array_column($timeline, 'report_id'))->toBe([$reportId])
        ->and($timeline[0]['comment'])->toBe('Obra detenida hace 2 meses')
        ->and($timeline[0]['files'])->toHaveCount(1);

    // Sale de la bandeja; quedan las otras 3.
    inbox($this->administrator)->assertJsonCount(3, 'data');
});

it('has no bulk publishing: evidences are published one by one', function () {
    $rows = inbox($this->administrator)->assertOk()->json('data');

    // Cada fila trae sus propias acciones; ninguna actúa sobre varias.
    expect(array_unique(array_map(fn (array $row) => implode(',', $row['actions']), $rows)))->toBe(['publish,reject']);

    // No existe un endpoint para publicar varias...
    test()->actingAs($this->administrator, 'tenant')
        ->postJson('http://veeduria-smr.govtrace.localhost/reports/publish', ['ids' => $this->hidden])
        ->assertNotFound();

    // ...y publicar una no arrastra a otras, aunque se le pidan.
    editorialDecision($this->administrator, 'publish', $this->hidden[0], ['ids' => $this->hidden])->assertOk();

    expect(array_map(fn (int $id) => editorialStatusOf($this->tenant, $id), $this->hidden))
        ->toBe(['Publicado', 'Oculto', 'Oculto', 'Oculto']);
});

it('rejects an evidence with a reason: never public nor a tombstone, and its veedor sees "Rechazada" with the reason', function () {
    $reportId = $this->hidden[0];

    editorialDecision($this->administrator, 'reject', $reportId, ['reason' => 'La foto no corresponde a la obra'])
        ->assertOk()
        ->assertJson(['status' => 'Rechazado']);

    $report = $this->tenant->run(fn () => Report::query()->findOrFail($reportId));
    [$onMap, $timeline] = publicView($this->tenant, $this->worksite->id);

    expect($report->editorial_status->label())->toBe('Rechazado')
        ->and($onMap)->toBe([])
        ->and($timeline)->toBe([]);

    // Nunca se hace pública: ni después.
    editorialDecision($this->administrator, 'publish', $reportId)->assertConflict();
    expect(editorialStatusOf($this->tenant, $reportId))->toBe('Rechazado');

    // R-USR-02: lo que ve su veedor en "Mis Reportes" (la pantalla, it. 28).
    expect($report->editorialStatusForVeedor())->toBe(['status' => 'Rechazada', 'reason' => 'La foto no corresponde a la obra']);
});

it('does not reject without a reason', function (array $payload) {
    editorialDecision($this->administrator, 'reject', $this->hidden[0], $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['reason' => 'Rechazar exige un motivo.']);

    expect(editorialStatusOf($this->tenant, $this->hidden[0]))->toBe('Oculto');
})->with([
    'sin motivo' => [[]],
    'motivo vacío' => [['reason' => '']],
    'solo espacios' => [['reason' => '   ']],
]);

it('Solo el Administrador de la organización publica: only lets the Administrador de la organización publish', function () {
    editorialDecision($this->veedor, 'publish', $this->hidden[0])->assertForbidden();
    inbox($this->veedor)->assertForbidden();

    expect(editorialStatusOf($this->tenant, $this->hidden[0]))->toBe('Oculto');
});

it('Un Administrador no publica evidencias de otra organización: does not let an Administrador publish evidences of another organization', function () {
    // "Veeduría Ciénaga", con su propio Administrador: el primer usuario de
    // su base, con el mismo id que el de Santa Marta en la suya.
    $cienaga = (new RegisterOrganization)->handle('890000062-6', 'Veeduría Ciénaga', 'veeduria-cienaga');
    (new ConfigureTerritory)->handle($cienaga, ['47']);
    $theirAdministrator = reportingMember($cienaga, 'admin@veeduria-cienaga.org', Roles::Administrator);
    $theirVeedor = reportingMember($cienaga, 'laura@correo.co');
    worksiteWithContracts($cienaga, ['CO1.PCCNTR.1234567'], santaMartaWorksiteLocation());
    $theirReport = sealedReport($cienaga, $theirVeedor);

    expect($theirAdministrator->id)->toBe($this->administrator->id);

    // La sesión del Administrador de Santa Marta presentada en el subdominio
    // de Ciénaga (p. ej. copiando la cookie) no vale ahí.
    test()->actingAs($this->administrator, 'tenant')
        ->withSession([ScopeSessions::$tenantIdKey => $this->tenant->id])
        ->postJson("http://veeduria-cienaga.govtrace.localhost/reports/{$theirReport}/publish")
        ->assertForbidden();

    expect(editorialStatusOf($cienaga, $theirReport))->toBe('Oculto');
});

it('shows the suspicious capture time mark in the inbox, and that evidence is decided like the others', function (string $decision, array $data, string $status) {
    // R-SEC-05: capturada 10 minutos "en el futuro" — se recibe y se marca.
    $suspicious = sealedReport($this->tenant, $this->veedor, ['captured_at' => now()->addMinutes(10)->toIso8601String()]);

    $rows = collect(inbox($this->administrator)->assertOk()->json('data'))->keyBy('id');

    expect($rows[$suspicious]['suspicious_capture_time'])->toBeTrue()
        ->and($rows[$suspicious]['actions'])->toBe(['publish', 'reject'])
        ->and($rows->except($suspicious)->pluck('suspicious_capture_time')->unique()->all())->toBe([false]);

    // R-MON-02: la marca no bloquea ni rechaza; decide el Administrador.
    editorialDecision($this->administrator, $decision, $suspicious, $data)->assertOk();

    expect(editorialStatusOf($this->tenant, $suspicious))->toBe($status);
})->with([
    'publicarla' => ['publish', [], 'Publicado'],
    'rechazarla' => ['reject', ['reason' => 'Hora de captura inconsistente'], 'Rechazado'],
]);

// Reglas derivadas ------------------------------------------------------

it('lists only sealed evidences in the inbox, and does not publish one before it is sealed', function () {
    // Sin sellar no hay nada que el validador público pueda verificar
    // (US-024): la evidencia llega a la bandeja cuando llega a "Sellada".
    $this->network->closesLedgerRightAway = false;
    test()->flushSession();
    $inFlight = sendReport($this->veedor)->assertCreated()->json('id');

    $rows = inbox($this->administrator)->assertOk()->json('data');

    expect(array_column($rows, 'id'))->toBe($this->hidden)
        ->and($rows[0])->toHaveKeys(['id', 'worksite_id', 'classification', 'comment', 'captured_at', 'received_at', 'suspicious_capture_time', 'files', 'seal', 'actions'])
        ->and($rows[0]['seal'])->toHaveKeys(['merkle_root', 'tx_hash', 'ledger']);

    editorialDecision($this->administrator, 'publish', $inFlight)
        ->assertConflict()
        ->assertJson(['message' => 'La evidencia todavía no está sellada: se publica cuando llegue a «Sellada».']);

    expect(editorialStatusOf($this->tenant, $inFlight))->toBe('Oculto');
});

it('records every editorial decision in the audit log: who, when, before and after, and the reason', function () {
    editorialDecision($this->administrator, 'publish', $this->hidden[0])->assertOk();
    editorialDecision($this->administrator, 'reject', $this->hidden[1], ['reason' => 'La foto no corresponde a la obra'])->assertOk();

    $published = AuditLog::query()->where('action', 'evidence.published')->sole();
    $rejected = AuditLog::query()->where('action', 'evidence.rejected')->sole();

    expect($published->organization_id)->toBe($this->tenant->id)
        ->and($published->actor_type)->toBe('organization_admin')
        ->and($published->actor_id)->toBe((string) $this->administrator->id)
        ->and($published->created_at)->not->toBeNull()
        ->and($published->before)->toBe(['report_id' => $this->hidden[0], 'editorial_status' => 'hidden'])
        ->and($published->after)->toBe(['report_id' => $this->hidden[0], 'editorial_status' => 'published'])
        ->and($rejected->after)->toBe(['report_id' => $this->hidden[1], 'editorial_status' => 'rejected', 'reason' => 'La foto no corresponde a la obra']);
});

/*
 * It. 40b — V4 de docs/mapa-funcional.md: el Administrador decide sabiendo
 * de qué obra es cada evidencia y qué veedor la envió (decidido por el
 * usuario el 2026-09-29: "sí, que vea el veedor").
 */
it('Cada evidencia de la bandeja dice de qué obra es y quién la envió: the worksite, its municipality and the veedor', function () {
    $evidence = inbox($this->administrator)->assertOk()->json('data.0');

    expect($evidence['worksite'])->toBe(['id' => $this->worksite->id, 'name' => 'Pavimentación Calle 30', 'municipality' => 'Santa Marta'])
        ->and($evidence['observer'])->toBe('Miembro de prueba');
});

/*
 * It. 40c — la pestaña de la Bandeja cuenta cuántas evidencias esperan (solo
 * para el Administrador: al veedor no le toca revisar).
 */
it('shares how many evidences wait in the inbox, for the tab of the Bandeja', function () {
    $this->withoutVite()->actingAs($this->administrator, 'tenant')
        ->get('http://veeduria-smr.govtrace.localhost/admin/inbox')
        ->assertInertia(fn (Assert $page) => $page->where('inboxPending', 4));

    tenancy()->end();
    $this->flushSession();
    $this->withoutVite()->actingAs($this->veedor, 'tenant')
        ->get('http://veeduria-smr.govtrace.localhost/my-reports')
        ->assertInertia(fn (Assert $page) => $page->where('inboxPending', null));
});

/*
 * It. 45f — la ubicación en la Bandeja. El reporte que fijó la ubicación de
 * su obra (First-Touch, R-GEO-01) llega marcado, con ese punto, que es el de
 * la obra; los demás dicen a qué distancia de la obra se tomaron, sin las
 * coordenadas del veedor.
 */

/** A worksite that had no location, and the sealed report that fixed it. */
function anchoringReport(object $test): int
{
    reportableContract('CO1.PCCNTR.7654321');
    $test->unlocated = worksiteWithContracts($test->tenant, ['CO1.PCCNTR.7654321'], null);

    return sealedReport($test->tenant, $test->veedor, ['secop_contract_id' => 'CO1.PCCNTR.7654321']);
}

function inboxEvidence(OrganizationUser $administrator, int $reportId): array
{
    return collect(inbox($administrator)->assertOk()->json('data'))->firstWhere('id', $reportId);
}

it('La evidencia que fijó la ubicación de la obra llega marcada: with that point, which is the worksite\'s', function () {
    $reportId = anchoringReport($this);
    [$latitude, $longitude] = pointMetersNorthOf(santaMartaWorksiteLocation(), 120);

    expect(inboxEvidence($this->administrator, $reportId)['location'])->toEqual([
        'anchored_worksite' => true,
        'distance_meters' => 0,
        'point' => ['latitude' => round($latitude, 7), 'longitude' => round($longitude, 7)],
        'corrected' => false,
    ]);
});

it('tells when the location that report fixed was corrected afterwards', function () {
    $reportId = anchoringReport($this);

    $this->flushSession();
    $this->actingAs($this->administrator, 'tenant')
        ->patchJson("http://veeduria-smr.govtrace.localhost/worksites/{$this->unlocated->id}/location", ['latitude' => 11.2411, 'longitude' => -74.1995])
        ->assertOk();
    tenancy()->end();
    $this->flushSession();

    expect(inboxEvidence($this->administrator, $reportId)['location']['corrected'])->toBeTrue();
});

it('Cada evidencia de la bandeja dice a qué distancia de la obra se tomó: and never the coordinates of the veedor', function () {
    $evidence = inboxEvidence($this->administrator, $this->hidden[0]);
    [$latitude] = pointMetersNorthOf(santaMartaWorksiteLocation(), 120);

    expect($evidence['location'])->toBe(['anchored_worksite' => false, 'distance_meters' => 120, 'point' => null, 'corrected' => false])
        ->and(json_encode($evidence))->not->toContain((string) round($latitude, 4));
});

it('Rechazar la evidencia que fijó la ubicación no la cambia: the worksite keeps the location it fixed', function () {
    $reportId = anchoringReport($this);
    $fixed = $this->tenant->run(fn () => $this->unlocated->fresh()->location());

    $this->flushSession();
    editorialDecision($this->administrator, 'reject', $reportId, ['reason' => 'La foto no deja ver la obra.'])->assertOk();
    tenancy()->end();

    expect(editorialStatusOf($this->tenant, $reportId))->toBe('Rechazado')
        ->and($this->tenant->run(fn () => $this->unlocated->fresh()->location()))->toEqual($fixed);
});
