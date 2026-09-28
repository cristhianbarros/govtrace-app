<?php

use App\Application\Organization\ConfigureTerritory;
use App\Application\Organization\RegisterOrganization;
use App\Application\Sealing\SealingNetwork;
use App\Domain\Reports\Evidence;
use App\Domain\Reports\Report;
use App\Domain\Sealing\MerkleTree;
use App\Domain\Sealing\Notifications\SponsorOutOfFunds;
use App\Domain\Sealing\ReportSeal;
use App\Domain\Sealing\SealingPause;
use App\Infrastructure\Tenancy\Tenant;
use App\Jobs\ConfirmSeal;
use App\Jobs\SealReport;
use App\Models\User as SuperAdmin;
use Database\Seeders\DivipolaSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\Support\FakeSealingNetwork;

/*
 * Iteración 13 — Sellado por raíz de Merkle (specs/PLAN.md). Traduce
 * features/US-020b.feature (7 casos) y la mitad de servidor de un escenario
 * de US-020a ("el servidor descarta el duplicado"), con la red de Stellar
 * en memoria (FakeSealingNetwork). La misma cadena contra la red local
 * standalone de verdad está en StellarSealingNetworkTest (make test-stellar).
 *
 * Los trabajos de sellado se corren a mano (Queue::fake está activo en toda
 * la suite, tests/Pest.php): así cada estado se ve por separado.
 *
 * Sin RefreshDatabase — registrar la organización ejecuta CREATE DATABASE.
 */

beforeEach(function () {
    $this->artisan('migrate');
    (new DivipolaSeeder)->run();
    Storage::fake('evidencias');
    Notification::fake();

    $this->network = new FakeSealingNetwork;
    app()->instance(SealingNetwork::class, $this->network);

    // Antecedentes: un veedor de una organización que vigila Magdalena.
    $this->tenant = (new RegisterOrganization)->handle('900123456-8', 'Veeduría Ciudadana Santa Marta', 'veeduria-smr');
    (new ConfigureTerritory)->handle($this->tenant, ['47']);
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
    DB::table('sealing_pauses')->delete();
});

/** Una foto JPEG real con contenido propio, para que cada una tenga su hash. */
function distinctPhoto(int $number): UploadedFile
{
    return UploadedFile::fake()->createWithContent("foto{$number}.jpg", file_get_contents(base_path('tests/fixtures/evidence/foto.jpg'))."\x00foto-{$number}");
}

/** El reporte de las Antecedentes: 3 fotos que ya pasaron la verificación de hashes. */
function reportWithThreePhotos(array $overrides = []): int
{
    return sendReport(test()->veedor, array_merge(['files' => [distinctPhoto(1), distinctPhoto(2), distinctPhoto(3)]], $overrides))
        ->assertCreated()
        ->json('id');
}

function runSealReport(int $reportId): void
{
    app()->call([new SealReport(test()->tenant->id, $reportId), 'handle']);
}

function runConfirmSeal(int $reportId): void
{
    app()->call([new ConfirmSeal(test()->tenant->id, $reportId), 'handle']);
}

function sealOf(int $reportId): ReportSeal
{
    return test()->tenant->run(fn () => ReportSeal::query()->where('report_id', $reportId)->sole());
}

it('seals a single Merkle root per report: one leaf per file plus the metadata leaf', function () {
    $reportId = reportWithThreePhotos();

    runSealReport($reportId);

    $seal = sealOf($reportId);
    [$report, $evidences] = $this->tenant->run(fn () => [Report::query()->findOrFail($reportId), Evidence::query()->where('report_id', $reportId)->orderBy('id')->get()]);
    $metadata = json_decode($seal->metadata_json, true);

    // 4 hojas: las 3 fotos, en orden, y el hash del JSON de metadatos.
    $leaves = [...$evidences->pluck('sha256')->all(), hash('sha256', $seal->metadata_json)];
    expect($seal->merkle_root)->toBe(MerkleTree::fromLeaves($leaves)->root());

    // R-PRIV-03: latitud, longitud, comentario, hora de captura y el
    // seudónimo del veedor, nunca su ID ni su correo.
    expect(array_keys($metadata))->toBe(['captured_at', 'classification', 'comment', 'latitude', 'longitude', 'pseudonym'])
        ->and($metadata['comment'])->toBe('Obra detenida hace 2 meses')
        ->and($metadata['latitude'])->toBe(number_format((float) $report->latitude, 7, '.', ''))
        ->and($metadata['captured_at'])->toBe($report->captured_at->utc()->format('Y-m-d\TH:i:s\Z'))
        ->and($metadata['pseudonym'])->toMatch('/^[0-9a-f]{64}$/')
        ->and($seal->metadata_json)->not->toContain('carlos@correo.co');

    // La prueba de inclusión de cada archivo recompone la raíz.
    foreach ($evidences as $evidence) {
        expect(MerkleTree::verify($evidence->sha256, $evidence->merkle_proof, $seal->merkle_root))->toBeTrue();
    }

    // Una sola transacción, con la raíz y la referencia de la obra (R-BLK-02).
    expect($this->network->submissions)->toBe([[
        'worksite' => hash('sha256', "{$this->tenant->id}:{$this->worksite->id}"),
        'root' => $seal->merkle_root,
    ]]);
});

it('walks a report through Recibida, En Cola, Transmitiendo and Sellada', function () {
    $this->network->closesLedgerRightAway = false;

    $reportId = reportWithThreePhotos();
    $queued = sealOf($reportId);

    expect($queued->status->label())->toBe('En Cola')
        ->and($queued->received_at)->not->toBeNull()
        ->and($queued->queued_at)->not->toBeNull();
    Queue::assertPushed(SealReport::class, fn (SealReport $job) => $job->reportId === $reportId);

    runSealReport($reportId);
    $transmitting = sealOf($reportId);

    expect($transmitting->status->label())->toBe('Transmitiendo')
        ->and($transmitting->tx_hash)->not->toBeNull()
        ->and($transmitting->transmitted_at)->not->toBeNull();
    Queue::assertPushed(ConfirmSeal::class, fn (ConfirmSeal $job) => $job->reportId === $reportId);

    // R-BLK-06: la red cierra el ledger con la transacción, y eso es definitivo.
    $this->network->closeLedgerWith($transmitting->tx_hash);
    runConfirmSeal($reportId);
    $sealed = sealOf($reportId);

    expect($sealed->status->label())->toBe('Sellada')
        ->and($sealed->ledger)->toBe($this->network->findSeal($sealed->merkle_root)->ledger)
        ->and($sealed->sealed_at)->not->toBeNull();
});

it('stays Transmitiendo while the network has not closed a ledger with the transaction', function () {
    $this->network->closesLedgerRightAway = false;
    $reportId = reportWithThreePhotos();
    runSealReport($reportId);

    runConfirmSeal($reportId);

    expect(sealOf($reportId)->status->label())->toBe('Transmitiendo');
});

it('seals the root the server computed, ignoring the one the phone sent', function () {
    $phoneRoot = str_repeat('ab', 32);

    $reportId = reportWithThreePhotos(['merkle_root' => $phoneRoot]);
    runSealReport($reportId);

    $seal = sealOf($reportId);

    // R-SEC-06: la raíz sale de los hashes que el servidor verificó.
    expect($seal->merkle_root)->not->toBe($phoneRoot)
        ->and($this->network->submissions[0]['root'])->toBe($seal->merkle_root);
});

it('never asks the veedor for a Stellar account, a wallet, a signature or XLM', function () {
    // R-BLK-01: el reporte se crea sin nada criptográfico, y la respuesta
    // no le devuelve al veedor nada que firmar ni pagar.
    $response = sendReport($this->veedor, ['files' => [distinctPhoto(1)]])->assertCreated();

    expect(array_keys($response->json()))->toBe(['id']);

    runSealReport($response->json('id'));
    runConfirmSeal($response->json('id'));

    expect(sealOf($response->json('id'))->status->label())->toBe('Sellada');
});

it('the sealer account signs and the sponsor account pays; no secret key in the repository or .env.example', function () {
    // Que la selladora firme y la patrocinadora pague con fee bump se
    // comprueba en la red real (StellarSealingNetworkTest, make
    // test-stellar). Aquí, que ninguna llave secreta se versiona (R-BLK-04).
    $tracked = array_filter(explode("\0", (string) shell_exec('git -C '.escapeshellarg(base_path()).' ls-files -z')));
    $leaks = [];
    foreach ($tracked as $file) {
        $path = base_path($file);
        if (is_file($path) && filesize($path) < 2_000_000 && preg_match('/\bS[A-Z2-7]{55}\b/', (string) file_get_contents($path), $match)) {
            $leaks[] = "{$file}: {$match[0]}";
        }
    }

    expect($tracked)->not->toBeEmpty()
        ->and($leaks)->toBe([])
        ->and(file_get_contents(base_path('.env.example')))->toMatch('/^STELLAR_SEALER_SECRET=$/m')
        ->toMatch('/^STELLAR_SPONSOR_SECRET=$/m');
});

it('pauses sealing and alerts the Super Administrador when the sponsor account runs out of XLM', function () {
    $superAdmin = SuperAdmin::factory()->create();
    $this->network->sponsorFunded = false;

    $first = reportWithThreePhotos();
    $second = reportWithThreePhotos();
    runSealReport($first);
    runSealReport($second);

    expect(SealingPause::isActive())->toBeTrue()
        ->and(sealOf($first)->status->label())->toBe('En Cola')
        ->and(sealOf($second)->status->label())->toBe('En Cola')
        ->and($this->network->submissions)->toBe([]);

    // Una sola alerta crítica por pausa, no una por reporte.
    Notification::assertSentToTimes($superAdmin, SponsorOutOfFunds::class, 1);

    // Con saldo otra vez, el sellado se reanuda solo.
    $this->network->sponsorFunded = true;
    runSealReport($first);
    runConfirmSeal($first);

    expect(SealingPause::isActive())->toBeFalse()
        ->and(sealOf($first)->status->label())->toBe('Sellada');
});

// US-020a, del lado del servidor -----------------------------------------

it('discards a duplicate that the contract rejects with "Hash ya registrado"', function () {
    $reportId = reportWithThreePhotos();
    runSealReport($reportId);
    $onChain = $this->network->findSeal(sealOf($reportId)->merkle_root);

    // Un reintento del mismo reporte (p. ej. tras una caída) vuelve a
    // intentar la misma raíz: la red la rechaza y el servidor no la reenvía,
    // toma el sello que ya existe.
    $this->tenant->run(fn () => ReportSeal::query()->where('report_id', $reportId)->update(['status' => 'queued']));
    runSealReport($reportId);

    $seal = sealOf($reportId);

    expect($this->network->submissions)->toHaveCount(1)
        ->and($seal->status->label())->toBe('Sellada')
        ->and($seal->ledger)->toBe($onChain->ledger);
});
