<?php

use App\Application\Contracts\ProcessSecopContractRow;
use App\Application\Organization\ConfigureTerritory;
use App\Application\Organization\RegisterOrganization;
use App\Domain\Contracts\Contract;
use App\Domain\Reports\Report;
use App\Infrastructure\Tenancy\Tenant;
use Database\Seeders\DivipolaSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

/*
 * Iteración 30 — Reportes sin conexión (specs/PLAN.md): lo que US-018 le
 * pide al servidor cuando por fin llega un reporte que esperó en el
 * teléfono. La bandeja de salida, sus límites y su vigencia son del
 * navegador (Vitest y la prueba de extremo a extremo, make e2e).
 * - La ubicación y la hora son las de la captura, no las del envío.
 * - Un contrato que SECOP anuló mientras el reporte esperaba lo acepta,
 *   si la captura fue antes de la anulación.
 * - Cerrar sesión (POST /logout): la app avisa antes si hay pendientes.
 *
 * Sin RefreshDatabase — registrar la organización ejecuta CREATE DATABASE.
 */

beforeEach(function () {
    $this->artisan('migrate');
    (new DivipolaSeeder)->run();
    Storage::fake('evidencias');
    Notification::fake();

    $this->tenant = (new RegisterOrganization)->handle('900123456-8', 'Veeduría Ciudadana Santa Marta', 'veeduria-smr');
    (new ConfigureTerritory)->handle($this->tenant, ['47']);
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

/** The SECOP row of CO1.PCCNTR.1234567 with the given status, as the nightly sync reads it. */
function annulledRow(string $status = 'Cancelado'): array
{
    return [
        'id_contrato' => 'CO1.PCCNTR.1234567',
        'referencia_del_contrato' => '261-2025',
        'nombre_entidad' => 'Alcaldía Distrital de Santa Marta',
        'proveedor_adjudicado' => 'Constructora Caribe S.A.S.',
        'descripcion_del_proceso' => 'Pavimentación Calle 30',
        'tipo_de_contrato' => 'Obra',
        'estado_contrato' => $status,
        'valor_del_contrato' => '1000000000.000000',
        'fecha_de_firma' => '2026-01-15T00:00:00.000',
        'fecha_de_fin_del_contrato' => '2026-12-31T00:00:00.000',
        'ciudad' => 'Santa Marta',
        'departamento' => 'Magdalena',
        'urlproceso' => ['url' => 'https://community.secop.gov.co/Public/Tendering/OpportunityDetail/Index?x=1'],
    ];
}

it('Ubicación y hora se congelan al capturar: the ones of the capture, and the geofence measured there', function () {
    // Capturado ayer a 100 m de la obra; enviado hoy, desde otra parte.
    [$latitude, $longitude] = pointMetersNorthOf(santaMartaWorksiteLocation(), 100);
    $capturedAt = now()->subHours(33)->startOfSecond();

    $this->flushSession();
    $reportId = sendReport($this->veedor, ['latitude' => $latitude, 'longitude' => $longitude, 'captured_at' => $capturedAt->toIso8601String()])->assertCreated()->json('id');
    tenancy()->end();

    // Dentro de la organización: Eloquent necesita su conexión para leer la fecha.
    [$storedAt, $storedLatitude, $suspicious] = $this->tenant->run(function () use ($reportId) {
        $report = Report::query()->findOrFail($reportId);

        return [$report->captured_at->toIso8601String(), (float) $report->latitude, $report->suspicious_capture_time];
    });
    expect($storedAt)->toBe($capturedAt->toIso8601String())
        ->and($storedLatitude)->toBe(round($latitude, 7))
        ->and($suspicious)->toBeFalse();
});

it('El contrato se anula mientras el reporte esperaba en la cola: accepted, queued for sealing and hidden', function () {
    // El lunes, con el contrato "En ejecución", el veedor toma la foto sin señal.
    $monday = now()->subDays(2)->startOfSecond();
    // El martes, SECOP lo anula.
    $this->travelTo(now()->subDay());
    (new ProcessSecopContractRow)->handle(annulledRow());
    $this->travelBack();

    // El miércoles llega el reporte.
    $this->flushSession();
    $reportId = sendReport($this->veedor, ['captured_at' => $monday->toIso8601String()])->assertCreated()->json('id');
    tenancy()->end();

    expect(editorialStatusOf($this->tenant, $reportId))->toBe('Oculto')
        ->and($this->tenant->run(fn () => Report::query()->findOrFail($reportId)->seal->status->label()))->toBe('En Cola');
});

// Reglas derivadas ------------------------------------------------------

it('refuses a report captured after the annulment', function () {
    $this->travelTo(now()->subDay());
    (new ProcessSecopContractRow)->handle(annulledRow());
    $this->travelBack();

    $this->flushSession();
    sendReport($this->veedor, ['captured_at' => now()->subMinutes(5)->toIso8601String()])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['secop_contract_id' => 'Este contrato ya no admite reportes: fue anulado, o terminó o se liquidó hace más tiempo del permitido.']);
});

it('keeps the moment of the annulment through the nightly syncs, and forgets it if SECOP reopens the contract', function () {
    $this->travelTo(now()->subDays(3));
    (new ProcessSecopContractRow)->handle(annulledRow());
    $this->travelBack();
    $annulledAt = Contract::query()->where('secop_contract_id', 'CO1.PCCNTR.1234567')->value('cancelled_at');

    (new ProcessSecopContractRow)->handle(annulledRow());
    expect(Contract::query()->where('secop_contract_id', 'CO1.PCCNTR.1234567')->value('cancelled_at'))->toEqual($annulledAt);

    (new ProcessSecopContractRow)->handle(annulledRow('En ejecución'));
    expect(Contract::query()->where('secop_contract_id', 'CO1.PCCNTR.1234567')->value('cancelled_at'))->toBeNull();
});

it('does not date an annulment it did not see happen: one annulled since always takes no late report', function () {
    Contract::fromSecop(fn () => Contract::query()->where('secop_contract_id', 'CO1.PCCNTR.1234567')->sole()->update(['status' => 'cancelled']));

    (new ProcessSecopContractRow)->handle(annulledRow());

    expect(Contract::query()->where('secop_contract_id', 'CO1.PCCNTR.1234567')->value('cancelled_at'))->toBeNull();
    $this->flushSession();
    sendReport($this->veedor, ['captured_at' => now()->subDays(2)->toIso8601String()])->assertUnprocessable();
});

it('lets the veedor log out: the session ends', function () {
    $this->flushSession();
    $this->actingAs($this->veedor, 'tenant')->getJson('http://veeduria-smr.govtrace.localhost/me/reports')->assertOk();

    $this->postJson('http://veeduria-smr.govtrace.localhost/logout')->assertNoContent();

    $this->assertGuest('tenant');
});
