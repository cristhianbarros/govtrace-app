<?php

use App\Application\Organization\ConfigureTerritory;
use App\Application\Organization\RegisterOrganization;
use App\Application\Sealing\SealingNetwork;
use App\Domain\Organization\Roles;
use App\Domain\Reports\Evidence;
use App\Infrastructure\Tenancy\Tenant;
use Database\Seeders\DivipolaSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\Support\FakeSealingNetwork;

/*
 * Iteración 46e — R-PRIV-05 reescrita (features/US-009.feature): los rostros
 * se difuminan en el celular, antes de calcular la huella. El servidor no
 * puede comprobarlo ni lo intenta: guarda la foto como llegó y lo que el
 * celular dice que difuminó — cuántos rostros, cuántas zonas a mano y cuántos
 * difuminados propuestos se quitaron —, y la Bandeja lo muestra.
 *
 * Sin RefreshDatabase — registrar la organización ejecuta CREATE DATABASE.
 */

const BLURRING_INVALID = 'Los datos del difuminado de las fotos no son válidos.';

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

function blurredPhoto(int $number): UploadedFile
{
    return UploadedFile::fake()->createWithContent("difuminada-{$number}.jpg", file_get_contents(base_path('tests/fixtures/evidence/foto.jpg'))."\x00difuminada-{$number}");
}

/** @return list<array<string, mixed>> the files of the report in the Bandeja */
function inboxFilesOf(array $overrides): array
{
    sealedReport(test()->tenant, test()->veedor, $overrides);

    return inbox(test()->administrator)->assertOk()->json('data.0.files');
}

it('La Bandeja dice cuántas zonas se difuminaron: photo by photo, as the phone said', function () {
    $files = inboxFilesOf([
        'files' => [blurredPhoto(1), blurredPhoto(2)],
        'blurs' => json_encode([['faces' => 2, 'dismissed' => 0, 'manual' => 1], ['faces' => 0, 'dismissed' => 0, 'manual' => 0]]),
    ]);

    expect(array_column($files, 'blurring'))->toBe([
        ['faces' => 2, 'dismissed' => 0, 'manual' => 1],
        ['faces' => 0, 'dismissed' => 0, 'manual' => 0],
    ]);
});

it('Quito un recuadro que no es un rostro, y la Bandeja lo marca: keeps how many proposed blurs were removed', function () {
    $files = inboxFilesOf(['files' => [blurredPhoto(1)], 'blurs' => json_encode([['faces' => 0, 'dismissed' => 1, 'manual' => 0]])]);

    expect($files[0]['blurring'])->toBe(['faces' => 0, 'dismissed' => 1, 'manual' => 0])
        ->and($this->tenant->run(fn () => Evidence::query()->sole()->only(['blurred_faces', 'dismissed_faces', 'blurred_by_hand'])))
        ->toBe(['blurred_faces' => 0, 'dismissed_faces' => 1, 'blurred_by_hand' => 0]);
});

it('receives a report from an app that does not say what it blurred, and says nothing about it', function () {
    expect(inboxFilesOf(['files' => [blurredPhoto(1)]])[0]['blurring'])->toBeNull();
});

it('keeps nothing of the blurring of a PDF', function () {
    expect(inboxFilesOf(['files' => [evidencePdf()], 'blurs' => json_encode([null])])[0]['blurring'])->toBeNull();
});

it('refuses what the phone says it blurred when it does not fit the files', function (Closure $blurs) {
    sendReport($this->veedor, ['files' => [blurredPhoto(1)], 'blurs' => $blurs()])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['blurs' => BLURRING_INVALID]);
})->with([
    'no es JSON' => [fn () => 'tres rostros'],
    'otra cantidad que la de los archivos' => [fn () => json_encode([['faces' => 1, 'dismissed' => 0, 'manual' => 0], null])],
    'un número negativo' => [fn () => json_encode([['faces' => -1, 'dismissed' => 0, 'manual' => 0]])],
    'más de 50 zonas' => [fn () => json_encode([['faces' => 51, 'dismissed' => 0, 'manual' => 0]])],
    'le falta un dato' => [fn () => json_encode([['faces' => 1, 'manual' => 0]])],
]);
