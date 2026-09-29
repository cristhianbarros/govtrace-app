<?php

use App\Application\Organization\ConfigureTerritory;
use App\Application\Organization\RegisterOrganization;
use App\Domain\Reports\Evidence;
use App\Domain\Reports\Report;
use App\Domain\Sealing\ReportSeal;
use App\Infrastructure\Tenancy\Tenant;
use App\Jobs\SealReport;
use Database\Seeders\DivipolaSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

/*
 * Iteración 11 — Archivos de evidencia (specs/PLAN.md). Traduce los casos
 * de SERVIDOR de features/US-009.feature (Done-when): cantidad y
 * combinación, peso, video, hash que coincide o no, y "las fotos no se
 * difuminan". Los escenarios que ocurren en el teléfono — optimizar la
 * foto y purgar el EXIF, limpiar los metadatos del PDF, no dejar mezclar
 * fotos y PDF — son de la PWA (it. 16).
 *
 * El almacenamiento de objetos es falso aquí (Storage::fake); el disco
 * real "evidencias" (S3/LocalStack) ya lo prueba ObjectStorageTest.
 *
 * Sin RefreshDatabase — registrar la organización ejecuta CREATE DATABASE.
 */

beforeEach(function () {
    $this->artisan('migrate');
    (new DivipolaSeeder)->run();
    Storage::fake('evidencias');

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
    DB::table('audit_logs')->delete(); // los reenvíos del sellado (it. 23), en la base central
});

function storedEvidences(Tenant $tenant): Collection
{
    return $tenant->run(fn () => Evidence::query()->orderBy('id')->get());
}

it('El servidor verifica que el hash coincide antes de encolar el sellado: queues the evidence for sealing when the hash the server recomputes matches the phone\'s', function () {
    $photo = evidencePhoto();
    $phoneHash = sha256Of($photo);

    sendReport($this->veedor, ['files' => [$photo], 'hashes' => [$phoneHash]])->assertCreated();

    $evidence = storedEvidences($this->tenant)->sole();
    $seal = $this->tenant->run(fn () => ReportSeal::query()->where('report_id', $evidence->report_id)->sole());

    // "Encolada para el sellado": el reporte queda "En Cola" y su trabajo
    // de sellado (it. 13, US-020b) queda despachado.
    expect($evidence->sha256)->toBe($phoneHash)
        ->and($seal->status->label())->toBe('En Cola')
        ->and($evidence->kind)->toBe('photo')
        ->and(Storage::disk('evidencias')->exists($evidence->storage_path))->toBeTrue();
    Queue::assertPushed(SealReport::class);
});

it('accepts 1 to 5 photos or a single PDF, never mixed and never none', function (Closure $archivos, bool $aceptado) {
    $response = sendReport($this->veedor, ['files' => $archivos()]);

    if ($aceptado) {
        $response->assertCreated();
    } else {
        $response->assertUnprocessable()->assertJsonValidationErrors('files');
        expect(storedEvidences($this->tenant))->toBeEmpty();
    }
})->with([
    '1 foto' => [fn () => [evidencePhoto()], true],
    '5 fotos' => [fn () => array_map(fn ($i) => evidencePhoto("foto{$i}.jpg"), range(1, 5)), true],
    '6 fotos' => [fn () => array_map(fn ($i) => evidencePhoto("foto{$i}.jpg"), range(1, 6)), false],
    '1 PDF' => [fn () => [evidencePdf()], true],
    '2 PDF' => [fn () => [evidencePdf('a.pdf'), evidencePdf('b.pdf')], false],
    '2 fotos y 1 PDF' => [fn () => [evidencePhoto('a.jpg'), evidencePhoto('b.jpg'), evidencePdf()], false],
    'ningún archivo' => [fn () => [], false],
]);

it('accepts files of up to 10 MB each', function (int $kilobytes, bool $aceptado) {
    $pdf = UploadedFile::fake()->create('grande.pdf', $kilobytes, 'application/pdf');

    $response = sendReport($this->veedor, ['files' => [$pdf]]);

    $aceptado
        ? $response->assertCreated()
        : $response->assertUnprocessable()->assertJsonValidationErrors('files');
})->with([
    '10 MB' => [10 * 1024, true],
    '10.1 MB' => [10 * 1024 + 103, false],
]);

it('rejects a video', function () {
    $video = UploadedFile::fake()->create('obra.mp4', 2048, 'video/mp4');

    sendReport($this->veedor, ['files' => [$video]])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('files');

    expect(storedEvidences($this->tenant))->toBeEmpty();
});

it('Las fotos no se difuminan: stores the photo byte for byte, without blurring faces or plates', function () {
    // R-PRIV-05: nada se difumina ni se reprocesa en el servidor; lo que se
    // guarda (y se sella) es exactamente lo que el teléfono envió. Decide
    // el Administrador al revisar (US-036, it. 15).
    $photo = evidencePhoto('rostros-y-placa.jpg');
    $original = file_get_contents($photo->getRealPath());

    sendReport($this->veedor, ['files' => [$photo]])->assertCreated();

    $evidence = storedEvidences($this->tenant)->sole();

    expect(Storage::disk('evidencias')->get($evidence->storage_path))->toBe($original);
});

it('does not queue the evidence when the file arrives altered, and tells the veedor', function () {
    $original = file_get_contents(base_path('tests/fixtures/evidence/foto.jpg'));
    $phoneHash = hash('sha256', $original);

    // Un solo byte cambiado en el camino.
    $altered = $original;
    $altered[40] = chr(ord($altered[40]) ^ 0x01);
    $photo = UploadedFile::fake()->createWithContent('foto.jpg', $altered);

    sendReport($this->veedor, ['files' => [$photo], 'hashes' => [$phoneHash]])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['files' => 'Alerta de seguridad: El archivo fue alterado o corrompido durante la transmisión (el hash del servidor no coincide con el de su celular). Por favor, intente de nuevo.']);

    expect(storedEvidences($this->tenant))->toBeEmpty()
        ->and($this->tenant->run(fn () => Report::query()->count()))->toBe(0)
        ->and(Storage::disk('evidencias')->allFiles())->toBeEmpty();
});
