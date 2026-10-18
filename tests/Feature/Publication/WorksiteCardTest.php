<?php

use App\Application\Organization\ConfigureTerritory;
use App\Application\Organization\RegisterOrganization;
use App\Application\Sealing\SealingNetwork;
use App\Domain\Organization\OrganizationStatus;
use App\Domain\Organization\Roles;
use App\Domain\Reports\Evidence;
use App\Domain\Reports\Report;
use App\Infrastructure\Tenancy\Tenant;
use App\Jobs\PurgeDecommissionedEvidence;
use Database\Seeders\DivipolaSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\Support\FakeSealingNetwork;

/*
 * Iteración 60 — La tarjeta de la obra en el mapa (features/US-029.feature y
 * US-027.feature). Al tocar un pin, el mapa pide la tarjeta de esa obra
 * (GET /public/worksites/{id}/card): su estado, su nombre, su municipio, su
 * valor, la fecha de la evidencia publicada más reciente y hasta 3
 * miniaturas. Las miniaturas se generan al publicar: copias de 320 px para
 * ver, que se van con la foto al retirarla o tras la baja.
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
    reportableContract('CO1.PCCNTR.1234567', ['value' => 1_250_000_000]);
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

/** The fixture photo itself (evidencePhoto() is always the 1×1 one): rostros-lejanos.jpg is 1920×1440. */
function fixturePhoto(string $name): UploadedFile
{
    return UploadedFile::fake()->createWithContent($name, file_get_contents(base_path("tests/fixtures/evidence/{$name}")));
}

/** A published report of the worksite with these fixture photos, captured $minutesAgo. */
function publishedWithPhotos(array $photos, int $minutesAgo, string $classification = 'Avance'): int
{
    return publishedReport(test()->tenant, test()->veedor, test()->administrator, [
        'classification' => $classification,
        'captured_at' => now()->subMinutes($minutesAgo)->toIso8601String(),
        'files' => array_map(fn (string $photo) => fixturePhoto($photo), $photos),
    ]);
}

/** @return list<Evidence> the photos of a report, in their order */
function photosOf(int $reportId): array
{
    return test()->tenant->run(fn () => Evidence::query()->where('report_id', $reportId)->orderBy('id')->get()->all());
}

function cardOf(string $worksitePublicId): array
{
    return publicGet("/public/worksites/{$worksitePublicId}/card")->assertOk()->json('data');
}

it('Al tocar un pin se abre la tarjeta de la obra: its state and why, name, municipality, value, the newest published date and up to 3 thumbnails', function () {
    $oldest = publishedWithPhotos(['foto.jpg'], 60);
    $older = publishedWithPhotos(['sin-rostro.jpg', 'rostro-pintura.jpg'], 40);
    $newest = publishedWithPhotos(['rostros-lejanos.jpg'], 20, 'Retraso');
    sealedReport($this->tenant, $this->veedor, ['captured_at' => now()->subMinutes(5)->toIso8601String()]); // oculta: no cuenta

    $card = cardOf($this->worksite->public_id);

    $thumbnail = fn (Evidence $photo) => "/public/evidences/{$photo->public_id}/thumbnail";
    $newestCapture = $this->tenant->run(fn () => Report::query()->findOrFail($newest)->captured_at->toIso8601String());
    expect($card)->toMatchArray([
        'id' => $this->worksite->public_id,
        'name' => 'Pavimentación Calle 30',
        'municipality' => 'Santa Marta',
        'last_evidence_at' => $newestCapture,
    ])
        ->and($card['value'])->toEqual(1_250_000_000)
        ->and($card['condition'])->toMatchArray(['color' => 'yellow', 'label' => 'Alerta'])
        ->and($card['photos'])->toBe([
            ['report_id' => publicIdOf(Report::class, $newest), 'thumbnail_url' => $thumbnail(photosOf($newest)[0])],
            ['report_id' => publicIdOf(Report::class, $older), 'thumbnail_url' => $thumbnail(photosOf($older)[0])],
            ['report_id' => publicIdOf(Report::class, $older), 'thumbnail_url' => $thumbnail(photosOf($older)[1])],
        ]);
    expect(photosOf($oldest))->toHaveCount(1);
});

it('Una obra sin evidencias publicadas lo dice en su tarjeta: no date and no thumbnails', function () {
    sealedReport($this->tenant, $this->veedor); // oculta

    $card = cardOf($this->worksite->public_id);

    expect($card['last_evidence_at'])->toBeNull()
        ->and($card['photos'])->toBe([])
        ->and($card['condition']['color'])->toBe('green');
});

it('La tarjeta de una obra se pide al tocar su pin: the pins stay light, and the card brings only what it shows', function () {
    publishedWithPhotos(['sin-rostro.jpg'], 20);

    expect(array_keys(publicGet('/public/worksites')->assertOk()->json('data.0')))->toBe(['id', 'lat', 'lng', 'color_pin'])
        ->and(array_keys(cardOf($this->worksite->public_id)))->toBe(['id', 'name', 'municipality', 'value', 'condition', 'last_evidence_at', 'citizens', 'photos']); // it. 66b: y cuántos ciudadanos informaron
    publicGet('/public/worksites/01j9xq3m7v8k2d4f6g8h0jkmnp/card')->assertNotFound();
});

it('Las miniaturas son livianas y solo de lo publicado: 320 px at most, the sealed file untouched, and none for a hidden or rejected evidence', function () {
    $published = publishedWithPhotos(['rostros-lejanos.jpg'], 20);
    $hidden = sealedReport($this->tenant, $this->veedor, ['files' => [fixturePhoto('sin-rostro.jpg')]]);
    $rejected = sealedReport($this->tenant, $this->veedor, ['files' => [fixturePhoto('rostro-pintura.jpg')]]);
    editorialDecision($this->administrator, 'reject', $rejected, ['reason' => 'No se ve la obra.'])->assertOk();

    [$photo] = photosOf($published);
    $thumbnail = Storage::disk('evidencias')->get($photo->thumbnail_path);
    expect(getimagesizefromstring($thumbnail))->toMatchArray([0 => 320, 1 => 240, 'mime' => 'image/jpeg'])
        ->and(hash('sha256', Storage::disk('evidencias')->get($photo->storage_path)))->toBe($photo->sha256);

    $served = publicGet("/public/evidences/{$photo->public_id}/thumbnail")->assertOk();
    expect($served->headers->get('Content-Type'))->toBe('image/jpeg')
        ->and($served->headers->get('Cache-Control'))->toContain('no-store')
        ->and($served->streamedContent())->toBe($thumbnail);
    // La línea de tiempo de la obra también la usa.
    expect(publicGet("/public/worksites/{$this->worksite->public_id}")->json('data.timeline.0.files.0'))
        ->toMatchArray(['thumbnail_url' => "/public/evidences/{$photo->public_id}/thumbnail", 'photo_url' => "/public/evidences/{$photo->public_id}/photo"]);

    foreach ([$hidden, $rejected] as $unpublished) {
        [$unpublishedPhoto] = photosOf($unpublished);
        expect($unpublishedPhoto->thumbnail_path)->toBeNull();
        publicGet("/public/evidences/{$unpublishedPhoto->public_id}/thumbnail")->assertNotFound();
    }
});

it('La miniatura se va con la foto: withdrawing the evidence deletes it', function () {
    $published = publishedWithPhotos(['rostros-lejanos.jpg'], 20);
    [$photo] = photosOf($published);
    expect($photo->thumbnail_path)->not->toBeNull();
    Storage::disk('evidencias')->assertExists($photo->thumbnail_path);

    editorialDecision($this->administrator, 'withdraw', $published, ['reason' => 'Muestra la cara de un menor.'])->assertOk();

    Storage::disk('evidencias')->assertMissing($photo->thumbnail_path);
    expect(photosOf($published)[0]->thumbnail_path)->toBeNull();
    publicGet("/public/evidences/{$photo->public_id}/thumbnail")->assertNotFound();
});

it('deletes the thumbnails with the files, 5 years after the organization was decommissioned', function () {
    $published = publishedWithPhotos(['rostros-lejanos.jpg'], 20);
    [$photo] = photosOf($published);
    expect($photo->thumbnail_path)->not->toBeNull();
    Storage::disk('evidencias')->assertExists($photo->thumbnail_path);
    Tenant::query()->whereKey($this->tenant->id)->update(['status' => OrganizationStatus::Decommissioned->value, 'decommissioned_at' => now()->subYears(6)]);

    app()->call([new PurgeDecommissionedEvidence, 'handle']);

    Storage::disk('evidencias')->assertMissing($photo->storage_path);
    Storage::disk('evidencias')->assertMissing($photo->thumbnail_path);
});

it('Las evidencias ya publicadas reciben su miniatura: the command gives each published photo its own, and running it again does nothing', function () {
    $published = publishedWithPhotos(['rostros-lejanos.jpg', 'sin-rostro.jpg'], 20);
    $hidden = sealedReport($this->tenant, $this->veedor, ['files' => [fixturePhoto('rostro-pintura.jpg')]]);
    // Como antes de la it. 60: publicadas, sin miniatura.
    $this->tenant->run(function () use ($published) {
        foreach (Evidence::query()->where('report_id', $published)->get() as $photo) {
            Storage::disk('evidencias')->delete($photo->thumbnail_path);
            $photo->update(['thumbnail_path' => null]);
        }
    });

    $this->artisan('evidence:thumbnails')->expectsOutputToContain('Miniaturas nuevas: 2.')->assertSuccessful();

    foreach (photosOf($published) as $photo) {
        Storage::disk('evidencias')->assertExists($photo->thumbnail_path);
    }
    expect(photosOf($hidden)[0]->thumbnail_path)->toBeNull();
    $this->artisan('evidence:thumbnails')->expectsOutputToContain('Miniaturas nuevas: 0.')->assertSuccessful();
});

it('Sin miniatura, la tarjeta muestra la foto completa: a photo that cannot be shrunk is published anyway', function () {
    $hidden = sealedReport($this->tenant, $this->veedor, ['files' => [fixturePhoto('sin-rostro.jpg')]]);
    [$photo] = photosOf($hidden);
    // Lo que hay en el almacenamiento no es una imagen que se pueda leer.
    Storage::disk('evidencias')->put($photo->storage_path, 'no es una imagen');

    editorialDecision($this->administrator, 'publish', $hidden)->assertOk();

    expect(photosOf($hidden)[0]->thumbnail_path)->toBeNull()
        ->and(cardOf($this->worksite->public_id)['photos'])->toBe([
            ['report_id' => publicIdOf(Report::class, $hidden), 'thumbnail_url' => "/public/evidences/{$photo->public_id}/photo"],
        ]);
    expect(publicGet("/public/worksites/{$this->worksite->public_id}")->json('data.timeline.0.files.0'))->not->toHaveKey('thumbnail_url');
});

// It. 66c (US-069): la obra se comparte con su vista previa (Open Graph), en el HTML de su página:
// WhatsApp y las redes no ejecutan JavaScript.
it('La obra se comparte con una vista previa: the HTML names the work and its state, the value and entity of its contract, and its newest published photo', function () {
    publishedWithPhotos(['sin-rostro.jpg'], 20, 'Retraso');
    $thumbnail = cardOf($this->worksite->public_id)['photos'][0]['thumbnail_url'];
    $host = 'http://veeduria-smr.govtrace.localhost';

    $html = publicGet("/worksite/{$this->worksite->public_id}")->assertOk()->getContent();

    expect($html)->toContain('<meta property="og:title" content="Pavimentación Calle 30 · Alerta">')
        ->toContain('<meta property="og:description" content="Santa Marta · Contrato por $1.250.000.000 · Alcaldía Distrital de Santa Marta. Evidencia ciudadana sellada: nadie la puede cambiar.">')
        ->toContain('<meta property="og:site_name" content="Veeduría Ciudadana Santa Marta · GovTrace">')
        ->toContain("<meta property=\"og:url\" content=\"{$host}/worksite/{$this->worksite->public_id}\">")
        ->toContain("<meta property=\"og:image\" content=\"{$host}{$thumbnail}\">")
        ->toContain('<meta name="twitter:card" content="summary_large_image">');
});

it('Una obra sin evidencias publicadas se comparte sin foto: its name and state, and no image', function () {
    $html = publicGet("/worksite/{$this->worksite->public_id}")->assertOk()->getContent();

    expect($html)->toContain('<meta property="og:title" content="Pavimentación Calle 30 · Normal">')
        ->not->toContain('og:image')
        ->toContain('<meta name="twitter:card" content="summary">');
});
