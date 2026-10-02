<?php

use App\Application\Organization\ConfigureTerritory;
use App\Application\Organization\RegisterOrganization;
use App\Application\Sealing\SealingNetwork;
use App\Domain\Organization\Roles;
use App\Domain\Reports\Report;
use App\Domain\Sealing\ReportSeal;
use App\Domain\Sealing\VeedorPseudonym;
use App\Infrastructure\Tenancy\Tenant;
use Database\Seeders\DivipolaSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\Support\FakeSealingNetwork;

/*
 * Iteración 34 — US-052-RPT (features/US-052-RPT.feature, 4 casos): los
 * datos abiertos de la organización, sin sesión (R-VER-02), en CSV o JSON:
 * un registro por evidencia publicada, con su sello en Stellar — para
 * auditarla y reutilizarla sin GovTrace —, las coordenadas aproximadas
 * (R-PRIV-02) y el seudónimo del veedor (R-PRIV-03), nunca quién es.
 *
 * Sin RefreshDatabase — registrar la organización ejecuta CREATE DATABASE.
 */

const OPEN_DATA_FIELDS = [
    'reporte', 'obra', 'contrato', 'municipio', 'fecha', 'clasificacion', 'latitud', 'longitud',
    'raiz_merkle', 'tx_id', 'ledger', 'contrato_de_sellado', 'comentario', 'seudonimo_veedor',
];

beforeEach(function () {
    $this->artisan('migrate');
    (new DivipolaSeeder)->run();
    Storage::fake('evidencias');
    Notification::fake();
    app()->instance(SealingNetwork::class, new FakeSealingNetwork);
    Carbon::setTestNow('2026-09-15 15:00:00');

    $this->tenant = (new RegisterOrganization)->handle('900123456-8', 'Veeduría Ciudadana Santa Marta', 'veeduria-smr');
    (new ConfigureTerritory)->handle($this->tenant, ['47']);
    $this->administrator = reportingMember($this->tenant, 'ana.perez@veeduria-smr.org', Roles::Administrator);
    $this->veedor = reportingMember($this->tenant, 'carlos.gomez@correo.co');
    $this->tenant->run(fn () => $this->veedor->update(['name' => 'Carlos Gómez']));
    reportableContract('CO1.PCCNTR.1234567');
    $worksite = worksiteWithContracts($this->tenant, ['CO1.PCCNTR.1234567'], santaMartaWorksiteLocation());
    $this->tenant->run(fn () => $worksite->update(['name' => 'Pavimentación Calle 30']));

    // Antecedentes: 10 evidencias publicadas, 3 ocultas y 1 retirada.
    $this->published = array_map(fn (int $n) => publishedReport($this->tenant, $this->veedor, $this->administrator, ['files' => [openDataPhoto("publicada-{$n}")]]), range(1, 10));
    $this->hidden = array_map(fn (int $n) => sealedReport($this->tenant, $this->veedor, ['files' => [openDataPhoto("oculta-{$n}")]]), range(1, 3));
    $this->withdrawn = publishedReport($this->tenant, $this->veedor, $this->administrator, ['files' => [openDataPhoto('retirada')]]);
    editorialDecision($this->administrator, 'withdraw', $this->withdrawn, ['reason' => 'La foto era de otra obra.'])->assertOk();
    tenancy()->end();
});

afterEach(function () {
    if (tenant()) {
        tenancy()->end();
    }

    Tenant::query()->get()->each->delete();
    DB::table('contracts')->delete();
    DB::table('audit_logs')->delete();
    Carbon::setTestNow();
});

function openDataPhoto(string $name): UploadedFile
{
    return UploadedFile::fake()->createWithContent("{$name}.jpg", file_get_contents(base_path('tests/fixtures/evidence/foto.jpg'))."\x00{$name}");
}

function openData(string $format): TestResponse
{
    return publicGet("/open-data.{$format}");
}

/** @return list<array<string, mixed>> */
function openDataRecords(string $format): array
{
    $response = openData($format)->assertOk();

    if ($format === 'json') {
        return $response->json('registros');
    }

    $lines = array_map(fn (string $line) => str_getcsv($line, ',', '"', ''), preg_split('/\r?\n/', trim($response->streamedContent())));
    $header = array_shift($lines);

    return array_map(fn (array $line) => array_combine($header, $line), $lines);
}

// US-052-RPT ---------------------------------------------------------------------

it('Descarga de datos abiertos: 10 records with every field', function (string $format) {
    $records = openDataRecords($format);

    expect($records)->toHaveCount(10);
    foreach ($records as $record) {
        expect(array_keys($record))->toBe(OPEN_DATA_FIELDS);
    }

    $first = collect($records)->firstWhere('reporte', publicIdOf(Report::class, $this->published[0])); // it. 46c
    [$seal, $report, $pseudonym] = $this->tenant->run(fn () => [
        ReportSeal::query()->where('report_id', $this->published[0])->sole(),
        Report::query()->findOrFail($this->published[0]),
        VeedorPseudonym::query()->where('user_id', $this->veedor->id)->value('pseudonym'),
    ]);
    expect($first)->toMatchArray([
        'obra' => 'Pavimentación Calle 30',
        'contrato' => 'CO1.PCCNTR.1234567',
        'municipio' => 'Santa Marta',
        'fecha' => '2026-09-15T09:58:00-05:00',
        'clasificacion' => 'Retraso',
        'raiz_merkle' => $seal->merkle_root,
        'tx_id' => $seal->tx_hash,
        'contrato_de_sellado' => FakeSealingNetwork::CONTRACT_ID,
        'comentario' => 'Obra detenida hace 2 meses',
        'seudonimo_veedor' => $pseudonym,
    ])->and((int) $first['ledger'])->toBe($seal->ledger)
        ->and((float) $first['latitud'])->toBe(round((float) $report->latitude, 3))
        ->and((float) $first['longitud'])->toBe(round((float) $report->longitude, 3));
})->with(['CSV' => 'csv', 'JSON' => 'json']);

it('Los datos abiertos protegen la privacidad del veedor', function () {
    $response = openData('json')->assertOk();

    foreach ($response->json('registros') as $record) {
        foreach (['latitud', 'longitud'] as $coordinate) {
            // A lo sumo 3 decimales: ~110 m.
            expect(strlen(explode('.', (string) $record[$coordinate])[1] ?? ''))->toBeLessThanOrEqual(3);
        }
        expect($record['seudonimo_veedor'])->toMatch('/^[0-9a-f]{64}$/')
            ->and($record['seudonimo_veedor'])->not->toBe((string) $this->veedor->id);
    }

    expect($response->getContent())->not->toContain('Carlos')
        ->not->toContain('carlos.gomez@correo.co')
        ->not->toContain('"user_id"')
        ->not->toContain('"veedor_id"');
});

it('Las evidencias no publicadas no se incluyen: none of the 3 hidden nor the withdrawn one', function () {
    $ids = array_column(openDataRecords('csv'), 'reporte');
    $publicIdsOf = fn (array $reports) => array_map(fn (int $id) => publicIdOf(Report::class, $id), $reports);

    expect($ids)->toEqualCanonicalizing($publicIdsOf($this->published))
        ->and(array_intersect($ids, $publicIdsOf([...$this->hidden, $this->withdrawn])))->toBe([]);
});

// Reglas de US-052-RPT ---------------------------------------------------------------

it('downloads as a file, with no session, as data reads best in each format', function () {
    openData('csv')->assertHeader('Content-Type', 'text/csv; charset=UTF-8')
        ->assertHeader('Content-Disposition', 'attachment; filename=veeduria-smr-datos-abiertos-2026-09-15.csv');

    $json = openData('json')->assertHeader('Content-Type', 'application/json')
        ->assertHeader('Content-Disposition', 'attachment; filename=veeduria-smr-datos-abiertos-2026-09-15.json');
    expect($json->json('organizacion'))->toBe('Veeduría Ciudadana Santa Marta')
        ->and($json->json('generado_en'))->toBe('2026-09-15T10:00:00-05:00')
        ->and($json->json('red'))->toBe(config('stellar.network_passphrase'));
});

it('keeps the open data available after a decommission: the evidence stays verifiable (US-003b)', function () {
    $this->tenant->update(['status' => 'decommissioned', 'decommissioned_at' => now()]);

    expect(openDataRecords('json'))->toHaveCount(10);
});

it('knows no other format', function () {
    publicGet('/open-data.xml')->assertNotFound();
});
