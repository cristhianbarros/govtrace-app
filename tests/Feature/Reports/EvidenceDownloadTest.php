<?php

use App\Application\Organization\ConfigureTerritory;
use App\Application\Organization\RegisterOrganization;
use App\Application\Publication\PublicTimeline;
use App\Application\Sealing\SealingNetwork;
use App\Domain\Organization\Roles;
use App\Domain\Reports\Report;
use App\Domain\Sealing\MerkleTree;
use App\Domain\Sealing\ReportSeal;
use App\Infrastructure\Tenancy\Tenant;
use Database\Seeders\DivipolaSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\Support\FakeSealingNetwork;

/*
 * Iteración 23 — Descargar el archivo sellado con su prueba de inclusión
 * (specs/PLAN.md). Traduce features/US-026.feature (3 casos) contra los
 * endpoints públicos: GET /public/evidences/{id}/download y
 * /public/evidences/{id}/proof, a los que llevan las tarjetas publicadas
 * de la línea de tiempo.
 *
 * La prueba es la que usa el script independiente (tools/verify): las hojas,
 * el camino de Merkle, la raíz y la transacción. Sin el JSON de metadatos:
 * lleva las coordenadas exactas, y el mapa público solo da aproximadas
 * (R-PRIV-02).
 *
 * Sin RefreshDatabase — registrar la organización ejecuta CREATE DATABASE.
 */

const DOWNLOAD_HOST = 'http://veeduria-smr.govtrace.localhost';

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

/** The fixture photo with bytes of its own, so each one has its hash. */
function gairaPhoto(int $number): UploadedFile
{
    return UploadedFile::fake()->createWithContent("obra-gaira-{$number}.jpg", file_get_contents(base_path('tests/fixtures/evidence/foto.jpg'))."\x00obra-gaira-{$number}");
}

/** The fixture photo with an APP1 segment carrying GPS coordinates after its JFIF header, maybe behind fill bytes. */
function photoWithMetadata(string $payload, string $fill = ''): UploadedFile
{
    $jpeg = file_get_contents(base_path('tests/fixtures/evidence/foto.jpg'));
    $afterJfif = 2 + 2 + unpack('n', substr($jpeg, 4, 2))[1];
    $app1 = $fill."\xFF\xE1".pack('n', strlen($payload) + 2).$payload;

    return UploadedFile::fake()->createWithContent('con-gps.jpg', substr($jpeg, 0, $afterJfif).$app1.substr($jpeg, $afterJfif));
}

/** @return array<string, mixed> the public timeline card of the report */
function timelineCard(int $reportId): ?array
{
    return collect(test()->tenant->run(fn () => (new PublicTimeline)->handle(test()->worksite->id)))->firstWhere('report_id', $reportId);
}

function evidenceIdsOf(int $reportId): array
{
    return test()->tenant->run(fn () => Report::query()->findOrFail($reportId)->evidences()->orderBy('id')->pluck('id')->all());
}

it('Descarga del archivo exacto y su prueba: the same binary that was sealed, without EXIF, and its inclusion proof', function () {
    $photos = [gairaPhoto(1), gairaPhoto(2)];
    $originals = array_map(fn (UploadedFile $photo) => $photo->getContent(), $photos);
    $reportId = sealedReport($this->tenant, $this->veedor, ['files' => $photos]);
    editorialDecision($this->administrator, 'publish', $reportId)->assertOk();
    auth('tenant')->logout();
    $seal = $this->tenant->run(fn () => ReportSeal::query()->where('report_id', $reportId)->sole());

    $file = timelineCard($reportId)['files'][0];

    $download = $this->get(DOWNLOAD_HOST.$file['download_url'])->assertOk();
    $bytes = $download->streamedContent();
    expect($bytes)->toBe($originals[0])
        ->and(hash('sha256', $bytes))->toBe($file['sha256'])
        ->and(str_contains($bytes, 'Exif'))->toBeFalse()
        ->and($download->headers->get('Content-Type'))->toBe('image/jpeg')
        ->and($download->headers->get('Content-Disposition'))->toBe('attachment; filename=evidencia-'.substr($file['sha256'], 0, 12).'.jpg');

    $response = $this->get(DOWNLOAD_HOST.$file['proof_url'])->assertOk();
    $proof = $response->json();
    expect($response->headers->get('Content-Disposition'))->toBe('attachment; filename=evidencia-'.substr($file['sha256'], 0, 12).'.prueba.json')
        ->and($proof['format'])->toBe('govtrace-proof/1')
        ->and($proof['file'])->toBe(['name' => 'evidencia-'.substr($file['sha256'], 0, 12).'.jpg', 'sha256' => $file['sha256']])
        // Las hojas: los 2 archivos y los metadatos, en orden.
        ->and($proof['leaves'])->toHaveCount(3)
        ->and($proof['leaves'][$proof['leaf_index']])->toBe($file['sha256'])
        ->and(MerkleTree::fromLeaves($proof['leaves'])->root())->toBe($proof['merkle_root'])
        // El camino de Merkle recompone la raíz desde el archivo.
        ->and(MerkleTree::verify($file['sha256'], $proof['proof'], $proof['merkle_root']))->toBeTrue()
        ->and($proof['merkle_root'])->toBe($seal->merkle_root)
        ->and($proof['worksite_reference'])->toBe($seal->worksite_reference)
        ->and($proof['stellar'])->toMatchArray([
            'network_passphrase' => config('stellar.network_passphrase'),
            'contract_id' => FakeSealingNetwork::CONTRACT_ID,
            'tx_hash' => $seal->tx_hash,
            'ledger' => $seal->ledger,
        ]);

    // R-PRIV-02: ni el comentario ni las coordenadas exactas.
    $raw = $response->getContent();
    expect($raw)->not->toContain('Obra detenida')
        ->and($raw)->not->toContain('metadata_json');
});

it('Una evidencia retirada no se puede descargar: its card has no download button', function () {
    $reportId = sealedReport($this->tenant, $this->veedor);
    editorialDecision($this->administrator, 'publish', $reportId)->assertOk();
    editorialDecision($this->administrator, 'withdraw', $reportId, ['reason' => 'Aparece un menor de edad identificable'])->assertOk();
    auth('tenant')->logout();

    expect(timelineCard($reportId))->not->toHaveKey('files');

    [$evidenceId] = evidenceIdsOf($reportId);
    $this->get(DOWNLOAD_HOST."/public/evidences/{$evidenceId}/download")->assertNotFound();
    $this->get(DOWNLOAD_HOST."/public/evidences/{$evidenceId}/proof")->assertNotFound();
});

it('Una evidencia no publicada no se puede descargar, ni por su dirección', function (string $state) {
    $reportId = sealedReport($this->tenant, $this->veedor);
    if ($state === 'rechazada') {
        editorialDecision($this->administrator, 'reject', $reportId, ['reason' => 'No corresponde a la obra'])->assertOk();
    }
    auth('tenant')->logout();

    [$evidenceId] = evidenceIdsOf($reportId);
    $this->get(DOWNLOAD_HOST."/public/evidences/{$evidenceId}/download")->assertNotFound();
    $this->get(DOWNLOAD_HOST."/public/evidences/{$evidenceId}/proof")->assertNotFound();
})->with(['oculta', 'rechazada']);

// Reglas derivadas ------------------------------------------------------

it('refuses a photo that still carries EXIF or XMP: nothing sealed and published can carry the phone location', function (string $payload, string $fill) {
    $this->flushSession();

    sendReport($this->veedor, ['files' => [photoWithMetadata($payload, $fill)]])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['files' => 'La foto conserva metadatos EXIF, como la ubicación del teléfono. Envíela desde la app GovTrace, que los quita.']);

    expect($this->tenant->run(fn () => Report::query()->count()))->toBe(0);
})->with([
    'EXIF' => ["Exif\0\0MM\0*GPSLatitude=11.2408;GPSLongitude=-74.1990", ''],
    'XMP' => ["http://ns.adobe.com/xap/1.0/\0<x:xmpmeta><exif:GPSLatitude>11,14.448N</exif:GPSLatitude></x:xmpmeta>", ''],
    'XMP extendido' => ["http://ns.adobe.com/xmp/extension/\0".str_repeat('0', 40).'<exif:GPSLongitude>74,11.94W</exif:GPSLongitude>', ''],
    'EXIF tras bytes de relleno' => ["Exif\0\0MM\0*GPSLatitude=11.2408", "\xFF\xFF\xFF"],
]);

it('accepts a photo with other APP1 data, like a JPEG straight from the app', function () {
    $this->flushSession();

    sendReport($this->veedor, ['files' => [photoWithMetadata('ICC_PROFILE-no-es-exif', '')]])->assertCreated();
});

it('links each published card to its receipt, and each file to its download and its proof', function () {
    $reportId = sealedReport($this->tenant, $this->veedor);
    editorialDecision($this->administrator, 'publish', $reportId)->assertOk();
    [$evidenceId] = evidenceIdsOf($reportId);

    $card = timelineCard($reportId);

    expect($card['receipt_url'])->toBe("/public/reports/{$reportId}/receipt")
        ->and($card['files'][0])->toMatchArray([
            'download_url' => "/public/evidences/{$evidenceId}/download",
            'proof_url' => "/public/evidences/{$evidenceId}/proof",
        ]);
});
