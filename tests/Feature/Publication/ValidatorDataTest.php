<?php

use App\Application\Organization\ConfigureTerritory;
use App\Application\Organization\RegisterOrganization;
use App\Application\Sealing\SealingNetwork;
use App\Domain\Organization\Roles;
use App\Domain\Reports\Report;
use App\Domain\Shared\PublicId;
use App\Infrastructure\Tenancy\Tenant;
use Database\Seeders\DivipolaSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\FakeSealingNetwork;

/*
 * Iteración 27 — Lo que el validador público pide a GovTrace (US-024): la
 * prueba de inclusión de un hash, GET /public/proofs/{sha256} — el archivo
 * nunca sale del navegador (R-VER-01) —, y la página /verify, sin sesión
 * (R-VER-02), con lo que el navegador necesita para leer el sello en la
 * red por su cuenta: el RPC público, la red y los contratos (R-MNT-01).
 * El veredicto lo da el navegador (Vitest: lib/validator.test.js).
 *
 * Sin RefreshDatabase — registrar la organización ejecuta CREATE DATABASE.
 */

beforeEach(function () {
    $this->artisan('migrate');
    (new DivipolaSeeder)->run();
    Storage::fake('evidencias');
    Notification::fake();
    app()->instance(SealingNetwork::class, new FakeSealingNetwork);
    config(['stellar.explorer_url' => 'https://stellar.expert/explorer/testnet', 'stellar.public_rpc_url' => 'https://soroban-testnet.stellar.org']);

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

/** A photo with bytes of its own, and so a hash of its own. */
function validatorPhoto(string $name): UploadedFile
{
    return UploadedFile::fake()->createWithContent("{$name}.jpg", file_get_contents(base_path('tests/fixtures/evidence/foto.jpg'))."\x00{$name}");
}

function proofOf(string $sha256, ?int $reportId = null)
{
    // It. 46c: el modo contextual nombra el reporte por su identificador público.
    return publicGet("/public/proofs/{$sha256}".($reportId ? '?report='.publicIdOf(Report::class, $reportId) : ''));
}

it('gives the proof of a published file by its hash, and where it stands', function () {
    $photo = validatorPhoto('obra-gaira');
    $reportId = publishedReport($this->tenant, $this->veedor, $this->administrator, ['files' => [$photo]]);
    $sha256 = sha256Of($photo);

    $answer = proofOf($sha256)->assertOk()->json('data');

    expect($answer['report_id'])->toBe(publicIdOf(Report::class, $reportId))
        ->and($answer['visibility'])->toBe('published')
        ->and($answer['proof'])->toMatchArray(['format' => 'govtrace-proof/1', 'file' => ['name' => 'evidencia-'.substr($sha256, 0, 12).'.jpg', 'sha256' => $sha256]])
        ->and($answer['proof']['stellar']['contract_id'])->toBe(FakeSealingNetwork::CONTRACT_ID);
});

it('tells a hidden, rejected or withdrawn evidence apart: its seal stays verifiable', function (string $decision, string $visibility) {
    $photo = validatorPhoto('reporte');
    $reportId = sealedReport($this->tenant, $this->veedor, ['files' => [$photo]]);
    if ($decision === 'withdraw') {
        editorialDecision($this->administrator, 'publish', $reportId)->assertOk();
    }
    if ($decision !== 'none') {
        editorialDecision($this->administrator, $decision, $reportId, ['reason' => 'Motivo de la decisión'])->assertOk();
    }

    expect(proofOf(sha256Of($photo))->assertOk()->json('data.visibility'))->toBe($visibility);
})->with([
    'oculta' => ['none', 'unpublished'],
    'rechazada' => ['reject', 'unpublished'],
    'retirada' => ['withdraw', 'withdrawn'],
]);

it('knows no proof of a file that was never sealed, nor of one still being sealed', function () {
    $pending = validatorPhoto('en-cola');
    $this->flushSession();
    sendReport($this->veedor, ['files' => [$pending]])->assertCreated();
    tenancy()->end();

    proofOf(sha256Of($pending))->assertNotFound();
    proofOf(hash('sha256', 'un archivo que nunca llegó'))->assertNotFound();
    proofOf('no-es-un-hash')->assertNotFound();
});

it('looks only in the given report, for the contextual mode', function () {
    $photo = validatorPhoto('obra-gaira');
    $reportId = publishedReport($this->tenant, $this->veedor, $this->administrator, ['files' => [$photo]]);
    $other = publishedReport($this->tenant, $this->veedor, $this->administrator, ['files' => [validatorPhoto('otra')]]);

    proofOf(sha256Of($photo), $reportId)->assertOk()->assertJsonPath('data.report_id', publicIdOf(Report::class, $reportId));
    proofOf(sha256Of($photo), $other)->assertNotFound();
});

it('El validador no exige registro: serves /verify without a session, with what the browser needs to read the network', function () {
    config(['stellar.sealing_contract_id' => 'CAF2JUMJPPMHLXMO3HV4SOT3PSHXSYMAPRPM67FCT4F5UU3NCGKAT3VE', 'stellar.network_passphrase' => 'Test SDF Network ; September 2015']);

    $this->withoutVite()->get('http://veeduria-smr.govtrace.localhost/verify')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Public/Validator')
            ->where('stellar.rpc_url', 'https://soroban-testnet.stellar.org')
            ->where('stellar.network_passphrase', 'Test SDF Network ; September 2015')
            ->where('stellar.explorer_url', 'https://stellar.expert/explorer/testnet')
            // Los oficiales de esa red (tools/verify/contracts.json) y el configurado.
            ->where('stellar.contracts', ['CABXHM74HFSAZD4FDFDONSIDJOVJBU7ZJXYBCHY3JJDCQUDFTHBT2WUI', 'CAKUYPROMNYKZCMCNI2N5RTWZE3JZ7RR4Q2W5FNVQNPANNMQPLJ4PLDY', 'CAF2JUMJPPMHLXMO3HV4SOT3PSHXSYMAPRPM67FCT4F5UU3NCGKAT3VE']));
});

it('gives the view of a worksite the same, for the contextual mode of each card', function () {
    $this->withoutVite()->get('http://veeduria-smr.govtrace.localhost/worksite/'.PublicId::generate())
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Public/Worksite')->has('stellar.contracts')->where('stellar.rpc_url', 'https://soroban-testnet.stellar.org'));
});
