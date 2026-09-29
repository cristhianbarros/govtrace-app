<?php

use App\Application\Organization\ConfigureTerritory;
use App\Application\Organization\RegisterOrganization;
use App\Application\Sealing\SealingNetwork;
use App\Domain\Organization\Roles;
use App\Domain\Reports\Evidence;
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
 * Iteración 34 — US-050-RPT (features/US-050-RPT.feature, 3 casos): el
 * Administrador exporta en CSV las obras y evidencias de su organización —
 * una fila por archivo de evidencia, con el seudónimo del veedor (R-PRIV-03),
 * nunca su nombre ni su correo. Solo su organización: vive en su base.
 *
 * Sin RefreshDatabase — registrar la organización ejecuta CREATE DATABASE.
 */

const EXPORT_COLUMNS = ['obra', 'contrato', 'municipio', 'fecha', 'clasificación', 'estado editorial', 'hash de la evidencia', 'comentario', 'seudónimo del veedor'];

beforeEach(function () {
    $this->artisan('migrate');
    (new DivipolaSeeder)->run();
    Storage::fake('evidencias');
    Notification::fake();
    app()->instance(SealingNetwork::class, new FakeSealingNetwork);
    Carbon::setTestNow('2026-09-15 15:00:00'); // 10:00 en Colombia

    // Antecedentes: el Administrador de "Veeduría Ciudadana Santa Marta".
    $this->tenant = (new RegisterOrganization)->handle('900123456-8', 'Veeduría Ciudadana Santa Marta', 'veeduria-smr');
    (new ConfigureTerritory)->handle($this->tenant, ['47']);
    $this->administrator = reportingMember($this->tenant, 'ana.perez@veeduria-smr.org', Roles::Administrator);
    $this->veedor = reportingMember($this->tenant, 'carlos.gomez@correo.co');
    $this->tenant->run(fn () => $this->veedor->update(['name' => 'Carlos Gómez']));
    reportableContract('CO1.PCCNTR.1234567', ['object' => 'Pavimentación Calle 30']);
    $worksite = worksiteWithContracts($this->tenant, ['CO1.PCCNTR.1234567'], santaMartaWorksiteLocation());
    $this->tenant->run(fn () => $worksite->update(['name' => 'Pavimentación Calle 30']));
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

function exportPhoto(string $name): UploadedFile
{
    return UploadedFile::fake()->createWithContent("{$name}.jpg", file_get_contents(base_path('tests/fixtures/evidence/foto.jpg'))."\x00{$name}");
}

function exportCsv(): TestResponse
{
    // Su propia sesión, en su subdominio, como en un navegador.
    test()->flushSession();

    return test()->actingAs(test()->administrator, 'tenant')->get('http://veeduria-smr.govtrace.localhost/export.csv');
}

/** @return list<array<string, string>> the rows, by column */
function exportedRows(TestResponse $response): array
{
    $lines = array_map(fn (string $line) => str_getcsv($line, ',', '"', ''), preg_split('/\r?\n/', trim(preg_replace('/^\xEF\xBB\xBF/', '', $response->streamedContent()))));
    $header = array_shift($lines);

    return array_map(fn (array $line) => array_combine($header, $line), $lines);
}

// US-050-RPT ---------------------------------------------------------------------

it('Exportación con las columnas definidas: one row per evidence file', function () {
    publishedReport($this->tenant, $this->veedor, $this->administrator, [
        'files' => [exportPhoto('frente'), exportPhoto('costado')],
        'classification' => 'Retraso',
        'comment' => 'Obra detenida hace 2 meses',
    ]);
    sealedReport($this->tenant, $this->veedor, ['files' => [exportPhoto('fondo')], 'classification' => 'Avance', 'comment' => '']);

    $response = exportCsv()->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    expect($response->headers->get('Content-Disposition'))->toContain('attachment; filename=veeduria-smr-evidencias-2026-09-15.csv');

    $rows = exportedRows($response);
    $pseudonym = $this->tenant->run(fn () => VeedorPseudonym::query()->where('user_id', $this->veedor->id)->value('pseudonym'));
    $hashes = $this->tenant->run(fn () => Evidence::query()->orderBy('id')->pluck('sha256', 'id')->all());

    expect(array_keys($rows[0]))->toBe(EXPORT_COLUMNS)
        ->and($rows)->toHaveCount(3)
        ->and($rows[0])->toBe([
            'obra' => 'Pavimentación Calle 30',
            'contrato' => 'CO1.PCCNTR.1234567',
            'municipio' => 'Santa Marta',
            'fecha' => '2026-09-15 09:58',
            'clasificación' => 'Retraso',
            'estado editorial' => 'Publicado',
            'hash de la evidencia' => array_values($hashes)[0],
            'comentario' => 'Obra detenida hace 2 meses',
            'seudónimo del veedor' => $pseudonym,
        ])
        ->and(array_column($rows, 'hash de la evidencia'))->toBe(array_values($hashes))
        ->and(array_column($rows, 'estado editorial'))->toBe(['Publicado', 'Publicado', 'Oculto']);
});

it('La exportación solo incluye la propia organización', function () {
    publishedReport($this->tenant, $this->veedor, $this->administrator, ['files' => [exportPhoto('propia')]]);

    // "Veeduría Ciénaga" tiene 12 evidencias.
    $cienaga = (new RegisterOrganization)->handle('890000062-6', 'Veeduría Ciénaga', 'veeduria-cienaga');
    (new ConfigureTerritory)->handle($cienaga, ['47']);
    $otherVeedor = reportingMember($cienaga, 'lucia@correo.co');
    worksiteWithContracts($cienaga, ['CO1.PCCNTR.1234567'], santaMartaWorksiteLocation());
    $theirHashes = [];
    foreach (range(1, 4) as $n) {
        $files = [exportPhoto("cienaga-{$n}-a"), exportPhoto("cienaga-{$n}-b"), exportPhoto("cienaga-{$n}-c")];
        $theirHashes = [...$theirHashes, ...array_map(sha256Of(...), $files)];
        sealedReport($cienaga, $otherVeedor, ['files' => $files]);
    }
    expect($cienaga->run(fn () => Evidence::query()->count()))->toBe(12);

    tenancy()->end();
    $rows = exportedRows(exportCsv()->assertOk());

    expect($rows)->toHaveCount(1)
        ->and(array_intersect(array_column($rows, 'hash de la evidencia'), $theirHashes))->toBe([]);
});

it('El veedor aparece con seudónimo: no row carries his name or email', function () {
    publishedReport($this->tenant, $this->veedor, $this->administrator, ['files' => [exportPhoto('uno')]]);
    sealedReport($this->tenant, $this->veedor, ['files' => [exportPhoto('dos')]]);

    $csv = exportCsv()->assertOk()->streamedContent();

    expect($csv)->not->toContain('Carlos')
        ->not->toContain('carlos.gomez@correo.co')
        ->not->toContain('correo.co')
        ->and(exportedRows(exportCsv())[0]['seudónimo del veedor'])->toMatch('/^[0-9a-f]{64}$/');
});

// Reglas de US-050-RPT -------------------------------------------------------------

it('opens right in a spreadsheet: UTF-8 with its mark, so the accents read well', function () {
    publishedReport($this->tenant, $this->veedor, $this->administrator, ['files' => [exportPhoto('uno')]]);

    expect(exportCsv()->streamedContent())->toStartWith("\xEF\xBB\xBFobra,contrato,municipio,fecha,clasificación");
});

it('exports only a header when nothing was received yet', function () {
    expect(exportedRows(exportCsv()->assertOk()))->toBe([]);
});

it('lets only the Administrador export', function () {
    $this->actingAs($this->veedor, 'tenant')->get('http://veeduria-smr.govtrace.localhost/export.csv')->assertForbidden();
    publicGet('/export.csv')->assertUnauthorized();
});
