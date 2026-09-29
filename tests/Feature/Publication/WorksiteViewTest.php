<?php

use App\Application\Organization\ConfigureTerritory;
use App\Application\Organization\RegisterOrganization;
use App\Application\Publication\PublicTimeline;
use App\Application\Sealing\SealingNetwork;
use App\Domain\Contracts\Contract;
use App\Domain\Organization\Roles;
use App\Domain\Reports\Report;
use App\Infrastructure\Tenancy\Tenant;
use Database\Seeders\DivipolaSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\Support\FakeSealingNetwork;

/*
 * Iteración 24 — Línea de tiempo de evidencias publicadas de una obra
 * (specs/PLAN.md). Traduce features/US-029.feature (5 casos) contra
 * GET /public/worksites/{id}: lo que el mapa pide al hacer clic en un pin
 * (R-MAP-02). Trae los contratos de la ficha, con la tarjeta de US-017, y
 * la línea de tiempo, con las coordenadas de cada evidencia a unos 100 m
 * (R-PRIV-02). La pantalla, con el visor, es de la it. 26.
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
    reportableContract('CO1.PCCNTR.1234567', [
        'entity_name' => 'Alcaldía de Santa Marta',
        'contractor_name' => 'Constructora Caribe S.A.S.',
        'value' => 1_250_000_000,
        'signed_at' => '2026-01-15',
        'end_date' => '2026-09-15', // 8 meses después de firmado
        'secop_url' => 'https://community.secop.gov.co/Public/Tendering/OpportunityDetail/Index?noticeUID=CO1.NTC.1',
    ]);
    $this->worksite = worksiteWithContracts($this->tenant, ['CO1.PCCNTR.1234567'], santaMartaWorksiteLocation());
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
 * "Pavimentación Calle 30": 3 evidencias publicadas y 2 ocultas, capturadas
 * con minutos de diferencia. Returns [published ids, oldest first; hidden ids].
 */
function calle30Evidences(): array
{
    $published = [];
    foreach ([50 => 'Avance', 40 => 'Retraso', 30 => 'Abandono'] as $minutesAgo => $classification) {
        $published[] = publishedReport(test()->tenant, test()->veedor, test()->administrator, [
            'classification' => $classification,
            'captured_at' => now()->subMinutes($minutesAgo)->toIso8601String(),
        ]);
    }

    $hidden = [];
    foreach ([45, 35] as $minutesAgo) {
        $hidden[] = sealedReport(test()->tenant, test()->veedor, ['captured_at' => now()->subMinutes($minutesAgo)->toIso8601String()]);
    }

    return [$published, $hidden];
}

function calle30View(): array
{
    return publicGet('/public/worksites/'.test()->worksite->id)->assertOk()->json('data');
}

it('Línea de tiempo cargada al hacer clic en el pin: the contract and the 3 published evidences, by date', function () {
    [$published] = calle30Evidences();

    $view = calle30View();

    expect($view['name'])->toBe('Pavimentación Calle 30')
        ->and($view['contracts'])->toHaveCount(1)
        ->and($view['contracts'][0])->toMatchArray([
            'secop_contract_id' => 'CO1.PCCNTR.1234567',
            'object' => 'Pavimentación Calle 30',
            'entity_name' => 'Alcaldía de Santa Marta',
            'contractor_name' => 'Constructora Caribe S.A.S.',
            'value' => '1250000000.00',
            'term_months' => 8,
            'cancelled' => false,
        ])
        // La más reciente primero.
        ->and(array_column($view['timeline'], 'report_id'))->toBe(array_reverse($published));

    // Fecha y hora, clasificación, comentario, miniaturas y lo que usa "Verificar Sello Blockchain".
    $card = $view['timeline'][0];
    expect($card)->toMatchArray(['classification' => 'Abandono', 'comment' => 'Obra detenida hace 2 meses'])
        ->and($card['captured_at'])->not->toBeEmpty()
        ->and($card['files'][0]['photo_url'])->toBe("/public/evidences/{$card['files'][0]['id']}/photo")
        ->and($card['seal']['merkle_root'])->not->toBeNull()
        ->and($card['receipt_url'])->toBe("/public/reports/{$card['report_id']}/receipt");
});

it('Visor de fotos: the thumbnail opens the photo itself, as it was sealed', function () {
    calle30Evidences();
    $photo = calle30View()['timeline'][0]['files'][0];

    $response = publicGet($photo['photo_url'])->assertOk();

    expect(hash('sha256', $response->streamedContent()))->toBe($photo['sha256'])
        ->and($response->headers->get('Content-Type'))->toBe('image/jpeg')
        ->and($response->headers->get('Content-Disposition'))->toStartWith('inline');
});

it('Las evidencias ocultas no aparecen', function () {
    [$published, $hidden] = calle30Evidences();

    $shown = array_column(calle30View()['timeline'], 'report_id');

    expect($shown)->toHaveCount(3)
        ->and(array_intersect($shown, $hidden))->toBe([]);
});

it('Las coordenadas de cada evidencia se muestran aproximadas: to about 100 m, never the exact ones', function () {
    publishedReport($this->tenant, $this->veedor, $this->administrator, ['latitude' => 11.240812, 'longitude' => -74.199034]);

    $response = publicGet('/public/worksites/'.$this->worksite->id)->assertOk();

    expect($response->json('data.timeline.0.approximate_location'))->toBe(['lat' => 11.241, 'lng' => -74.199])
        ->and($response->getContent())->not->toContain('11.240812')
        ->and($response->getContent())->not->toContain('74.199034');
});

it('Una evidencia retirada queda como lápida: without its photos, comment or place, with its seal', function () {
    [$published] = calle30Evidences();
    editorialDecision($this->administrator, 'withdraw', $published[1], ['reason' => 'Aparece un menor de edad identificable'])->assertOk();

    $timeline = calle30View()['timeline'];
    $tombstone = collect($timeline)->firstWhere('report_id', $published[1]);

    expect($timeline)->toHaveCount(3)
        ->and($tombstone)->not->toHaveKeys(['files', 'comment', 'approximate_location'])
        ->and($tombstone['notice'])->toBe(PublicTimeline::TOMBSTONE_NOTICE)
        ->and($tombstone['seal']['merkle_root'])->not->toBeNull()
        ->and($tombstone['receipt_url'])->toBe("/public/reports/{$published[1]}/receipt");
});

// Reglas derivadas ------------------------------------------------------

it('Contrato anulado en SECOP que ya tenía evidencias (US-017): the view keeps them and warns', function () {
    publishedReport($this->tenant, $this->veedor, $this->administrator);
    publishedReport($this->tenant, $this->veedor, $this->administrator);
    Contract::fromSecop(fn () => Contract::query()->where('secop_contract_id', 'CO1.PCCNTR.1234567')->sole()->update(['status' => 'cancelled']));

    $view = calle30View();

    expect($view['timeline'])->toHaveCount(2)
        ->and($view['contracts'][0])->toMatchArray(['cancelled' => true, 'cancelled_notice' => '⚠️ Contrato Anulado/Retirado en SECOP']);
});

it('serves no photo of a PDF, nor of a hidden or withdrawn evidence', function () {
    $pdf = publishedReport($this->tenant, $this->veedor, $this->administrator, ['files' => [evidencePdf()]]);
    $hidden = sealedReport($this->tenant, $this->veedor);
    $withdrawn = publishedReport($this->tenant, $this->veedor, $this->administrator);
    editorialDecision($this->administrator, 'withdraw', $withdrawn, ['reason' => 'Solicitud del afectado'])->assertOk();

    $evidenceOf = fn (int $reportId) => $this->tenant->run(fn () => Report::query()->findOrFail($reportId)->evidences()->value('id'));

    expect(collect(calle30View()['timeline'])->firstWhere('report_id', $pdf)['files'][0])->not->toHaveKey('photo_url');
    foreach ([$pdf, $hidden, $withdrawn] as $reportId) {
        publicGet("/public/evidences/{$evidenceOf($reportId)}/photo")->assertNotFound();
    }
});

it('has no view of a worksite the organization does not have', function () {
    publicGet('/public/worksites/'.($this->worksite->id + 1))->assertNotFound();
});
