<?php

use App\Application\Organization\ConfigureTerritory;
use App\Application\Organization\RegisterOrganization;
use App\Application\Sealing\SealingNetwork;
use App\Domain\Organization\Roles;
use App\Domain\Organization\User as OrganizationUser;
use App\Domain\Sealing\ReportSeal;
use App\Domain\Sealing\SealStatus;
use App\Infrastructure\Tenancy\Tenant;
use Database\Seeders\DivipolaSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\FakeSealingNetwork;

/*
 * Iteración 28 — Seguimiento de mis reportes (specs/PLAN.md). Traduce
 * features/US-010.feature (10 casos) contra GET /me/reports: los reportes
 * del veedor, solo los suyos (R-VC-02), con el estado técnico y el
 * editorial por separado (R-USR-02) — y nunca un error de sellado (US-021).
 * La pantalla es Veedor/MyReports (Vitest).
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
    reportableContract('CO1.PCCNTR.1234567');
    worksiteWithContracts($this->tenant, ['CO1.PCCNTR.1234567'], santaMartaWorksiteLocation());
});

afterEach(function () {
    if (tenant()) {
        tenancy()->end();
    }

    Tenant::query()->get()->each->delete();
    DB::table('contracts')->delete();
    DB::table('audit_logs')->delete();
});

function myReports(?OrganizationUser $veedor = null): TestResponse
{
    test()->flushSession();

    return test()->actingAs($veedor ?? test()->veedor, 'tenant')->getJson('http://veeduria-smr.govtrace.localhost/me/reports');
}

/** A report of the veedor, received and not sealed yet. */
function receivedReport(array $overrides = []): int
{
    test()->flushSession();
    $reportId = sendReport(test()->veedor, $overrides)->assertCreated()->json('id');
    tenancy()->end();

    return $reportId;
}

function mine(int $reportId): array
{
    return collect(myReports()->assertOk()->json('data'))->firstWhere('id', $reportId);
}

it('Estado técnico y estado editorial por separado', function () {
    $reportId = publishedReport($this->tenant, $this->veedor, $this->administrator);

    expect(mine($reportId))->toMatchArray([
        'technical_status' => 'Sellado',
        'editorial_status' => 'Publicado',
        'rejection_reason' => null,
    ]);
});

it('Correspondencia del estado técnico visible', function (SealStatus $internal, string $visible) {
    $reportId = receivedReport();
    $this->tenant->run(fn () => ReportSeal::query()->where('report_id', $reportId)->update(['status' => $internal]));

    expect(mine($reportId)['technical_status'])->toBe($visible);
})->with([
    'Recibida' => [SealStatus::Received, 'En Cola'],
    'En Cola' => [SealStatus::Queued, 'En Cola'],
    'Transmitiendo' => [SealStatus::Transmitting, 'Sellando'],
    'Sellada' => [SealStatus::Sealed, 'Sellado'],
    'Falla de Sellado' => [SealStatus::Failed, 'En Cola'],
]);

it('Evidencia aún no revisada', function () {
    $reportId = sealedReport($this->tenant, $this->veedor);

    expect(mine($reportId)['editorial_status'])->toBe('En Revisión');
});

it('Evidencia rechazada con motivo', function () {
    $reportId = sealedReport($this->tenant, $this->veedor);
    editorialDecision($this->administrator, 'reject', $reportId, ['reason' => 'La foto no corresponde a la obra'])->assertOk();

    expect(mine($reportId))->toMatchArray(['editorial_status' => 'Rechazada', 'rejection_reason' => 'La foto no corresponde a la obra']);
});

it('El veedor nunca ve errores de sellado', function () {
    $reportId = receivedReport();
    $this->tenant->run(fn () => ReportSeal::query()->where('report_id', $reportId)->update([
        'status' => SealStatus::Failed, 'attempts' => 5, 'last_error' => 'La red de Stellar no respondió (sendTransaction): timeout',
    ]));

    $response = myReports()->assertOk();

    expect($response->json('data.0.technical_status'))->toBe('En Cola')
        ->and($response->getContent())->not->toContain('Stellar no respondió')
        ->and($response->getContent())->not->toContain('attempts')
        ->and($response->getContent())->not->toContain('Falla');
});

it('Solo veo mis propios reportes', function () {
    $laura = reportingMember($this->tenant, 'laura@correo.co');
    foreach (range(1, 3) as $ignored) {
        $this->flushSession();
        sendReport($laura)->assertCreated();
        tenancy()->end();
    }
    $own = receivedReport();

    expect(array_column(myReports()->assertOk()->json('data'), 'id'))->toBe([$own]);
});

// Reglas derivadas ------------------------------------------------------

it('lists the newest first, with what each one is about and the way to its receipt', function () {
    $older = receivedReport(['captured_at' => now()->subHour()->toIso8601String(), 'classification' => 'Avance']);
    $newer = receivedReport(['captured_at' => now()->subMinutes(5)->toIso8601String(), 'classification' => 'Abandono']);

    $reports = myReports()->assertOk()->json('data');

    expect(array_column($reports, 'id'))->toBe([$newer, $older])
        ->and($reports[0])->toMatchArray([
            'classification' => 'Abandono',
            // La ficha completa, no un contrato: el reporte es de la obra (R-INT-05).
            'worksite' => 'Pavimentación Calle 30',
            'receipt_url' => "/reports/{$newer}/receipt",
        ])
        ->and($reports[0]['captured_at'])->not->toBeEmpty();
});

it('shows a withdrawn one as "Retirado", without the reason of the Administrador', function () {
    $reportId = publishedReport($this->tenant, $this->veedor, $this->administrator);
    editorialDecision($this->administrator, 'withdraw', $reportId, ['reason' => 'Aparece un menor de edad identificable'])->assertOk();

    expect(mine($reportId))->toMatchArray(['editorial_status' => 'Retirado', 'rejection_reason' => null]);
});

it('is only for veedores, and serves the screen', function () {
    $this->flushSession();
    $this->actingAs($this->administrator, 'tenant')->getJson('http://veeduria-smr.govtrace.localhost/me/reports')->assertForbidden();

    $this->flushSession();
    $this->withoutVite()->actingAs($this->veedor, 'tenant')->get('http://veeduria-smr.govtrace.localhost/my-reports')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Veedor/MyReports'));
});
