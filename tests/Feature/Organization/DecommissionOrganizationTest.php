<?php

use App\Application\Organization\ConfigureTerritory;
use App\Application\Organization\InviteObserver;
use App\Application\Organization\RegisterOrganization;
use App\Application\Organization\SuperAdminAuthorizations;
use App\Application\Sealing\SealingNetwork;
use App\Domain\Audit\AuditLog;
use App\Domain\Organization\Notifications\WelcomeNotification;
use App\Domain\Organization\Roles;
use App\Domain\Organization\SuperAdminAuthorization;
use App\Domain\Organization\User as OrganizationUser;
use App\Domain\Reports\Evidence;
use App\Domain\Reports\Report;
use App\Domain\Sealing\MerkleTree;
use App\Domain\Sealing\ReportSeal;
use App\Infrastructure\Tenancy\Tenant;
use App\Jobs\PurgeDecommissionedEvidence;
use App\Models\User as SuperAdmin;
use Carbon\CarbonImmutable;
use Database\Seeders\DivipolaSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\FakeSealingNetwork;

/*
 * Iteración 33 — US-003b (features/US-003b.feature, 6 casos, ajustada a
 * Stellar): la baja definitiva de una organización, con doble
 * confirmación — la primera muestra lo que implica, la segunda pide
 * escribir su subdominio —. Es lógica: nada se borra, se revocan los
 * accesos, el mapa sale de línea y las evidencias siguen verificables. Los
 * archivos se borran a los 5 años; los sellos y las pruebas, nunca.
 *
 * Sin RefreshDatabase — registrar la organización ejecuta CREATE DATABASE.
 */

const ORGANIZATION_DECOMMISSIONED = 'La organización veedora fue dada de baja. Sus usuarios ya no tienen acceso.';
const DECOMMISSIONED_NOTICE = '⚠️ Esta organización fue dada de baja. Su mapa público ya no está disponible; sus evidencias selladas siguen verificables en el validador.';
const MAP_OFFLINE = 'Esta organización fue dada de baja: su mapa público ya no está disponible. Sus evidencias selladas siguen verificables en el validador (/verify).';
const FILE_PURGED = 'Este archivo se borró al cumplirse 5 años de la baja de la organización. Su sello en Stellar y su prueba de inclusión se conservan.';

beforeEach(function () {
    $this->artisan('migrate');
    (new DivipolaSeeder)->run();
    Storage::fake('evidencias');
    Notification::fake();
    $this->network = new FakeSealingNetwork;
    app()->instance(SealingNetwork::class, $this->network);
    Carbon::setTestNow('2026-09-29 12:00:00');

    $this->tenant = (new RegisterOrganization)->handle('900123456-8', 'Veeduría Ciudadana Santa Marta', 'veeduria-smr');
    (new ConfigureTerritory)->handle($this->tenant, ['47']);
    $this->administrator = reportingMember($this->tenant, 'ana.perez@veeduria-smr.org', Roles::Administrator);
    $this->veedor = reportingMember($this->tenant, 'carlos@veeduria-smr.org');
    reportableContract('CO1.PCCNTR.1234567');
    $this->worksite = worksiteWithContracts($this->tenant, ['CO1.PCCNTR.1234567'], santaMartaWorksiteLocation());
    $this->superAdmin = SuperAdmin::factory()->create();

    // Antecedentes: 5 evidencias selladas en Stellar (y publicadas en su mapa).
    $this->reportIds = array_map(
        fn (int $n) => publishedReport($this->tenant, $this->veedor, $this->administrator, ['files' => [decommissionPhoto("obra-{$n}")]]),
        range(1, 5),
    );
});

afterEach(function () {
    if (tenant()) {
        tenancy()->end();
    }

    Tenant::query()->get()->each->delete();
    DB::table('contracts')->delete();
    DB::table('audit_logs')->delete();
    $this->superAdmin->delete();
    Carbon::setTestNow();
});

/** A photo with bytes, and so a hash, of its own. */
function decommissionPhoto(string $name): UploadedFile
{
    return UploadedFile::fake()->createWithContent("{$name}.jpg", file_get_contents(base_path('tests/fixtures/evidence/foto.jpg'))."\x00{$name}");
}

/** POST as the Super Administrador, in the global panel. */
function inGlobalPanel(string $path, array $data = []): TestResponse
{
    if (tenant()) {
        tenancy()->end();
    }

    return test()->actingAs(test()->superAdmin, 'web')->postJson('http://govtrace.localhost/admin/organizations/'.test()->tenant->id.$path, $data);
}

/** The first confirmation: what the decommission implies, and the token for the second. */
function firstConfirmation(): TestResponse
{
    return inGlobalPanel('/decommission/start');
}

/** Both confirmations, the second typing the subdomain. */
function decommission(): TestResponse
{
    $token = firstConfirmation()->assertOk()->json('token');

    return inGlobalPanel('/decommission', ['token' => $token, 'subdomain' => 'veeduria-smr']);
}

/** @return array<int, array{merkle_root: string, ledger: int, tx_hash: string, sealed_at: string}> */
function sealsOnRecord(): array
{
    return test()->tenant->run(fn () => ReportSeal::query()->orderBy('report_id')->get()
        ->mapWithKeys(fn (ReportSeal $seal) => [$seal->report_id => [
            'merkle_root' => $seal->merkle_root,
            'ledger' => $seal->ledger,
            'tx_hash' => $seal->tx_hash,
            'sealed_at' => $seal->sealed_at->toIso8601String(),
        ]])->all());
}

/** @return array<string, string> sha256 => where the file is stored */
function evidenceFiles(): array
{
    return test()->tenant->run(fn () => Evidence::query()->pluck('storage_path', 'sha256')->all());
}

/** What the validator of the browser concludes for a file: its proof leads to a root the network has (US-024). */
function verifiesAsAuthentic(string $sha256): bool
{
    $proof = publicGet("/public/proofs/{$sha256}")->assertOk()->json('data.proof');

    return MerkleTree::verify($sha256, $proof['proof'], $proof['merkle_root'])
        && test()->network->findSeal($proof['merkle_root']) !== null;
}

function runRetentionPolicy(): void
{
    app()->call([new PurgeDecommissionedEvidence, 'handle']);
}

// US-003b ----------------------------------------------------------------------

it('Baja lógica tras doble confirmación: "Dada de baja" with all its data, and every access revoked', function () {
    // Una invitación pendiente, antes de la baja.
    $invited = $this->tenant->run(fn () => (new InviteObserver)->handle('pedro@correo.co'));
    $invitationUrl = Notification::sent($invited, WelcomeNotification::class)->first()->url;
    tenancy()->end();
    $users = $this->tenant->run(fn () => OrganizationUser::query()->count());

    $first = firstConfirmation()->assertOk();
    expect($first->json('summary'))->toBe([
        'organization' => 'Veeduría Ciudadana Santa Marta',
        'subdomain' => 'veeduria-smr',
        'sealed_reports' => 5,
        'files_kept_until' => '2031-09-29',
    ])->and($first->json('message'))->toBe('Para confirmar la baja definitiva, escriba el subdominio de la organización: veeduria-smr.');

    inGlobalPanel('/decommission', ['token' => $first->json('token'), 'subdomain' => 'veeduria-smr'])
        ->assertOk()
        ->assertJson(['message' => 'Organización dada de baja. Sus usuarios ya no tienen acceso; su mapa salió de línea y sus evidencias siguen verificables.']);

    // Marcada, no borrada: la organización, su base, sus reportes, sus usuarios.
    $tenant = Tenant::query()->findOrFail($this->tenant->id);
    expect($tenant->statusLabel())->toBe('Dada de baja')
        ->and($tenant->decommissioned_at->toIso8601String())->toBe('2026-09-29T12:00:00+00:00')
        ->and($this->tenant->run(fn () => Report::query()->count()))->toBe(5)
        ->and($this->tenant->run(fn () => Evidence::query()->count()))->toBe(5)
        ->and($this->tenant->run(fn () => OrganizationUser::query()->count()))->toBe($users)
        ->and($this->tenant->run(fn () => OrganizationUser::query()->where('is_active', true)->count()))->toBe(0);

    // Se revocan todos los accesos: la sesión abierta…
    $this->actingAs($this->administrator, 'tenant')->getJson('http://veeduria-smr.govtrace.localhost/inbox')
        ->assertForbidden()
        ->assertJson(['message' => ORGANIZATION_DECOMMISSIONED]);
    $this->assertGuest('tenant');
    // …entrar de nuevo…
    $this->post('http://veeduria-smr.govtrace.localhost/login', ['email' => 'ana.perez@veeduria-smr.org', 'password' => 'Veeduria#2026'])
        ->assertSessionHasErrors(['email' => ORGANIZATION_DECOMMISSIONED]);
    // …reportar…
    sendReport($this->veedor)->assertForbidden();
    // …y la invitación pendiente.
    publicGet(parse_url($invitationUrl, PHP_URL_PATH).'?'.parse_url($invitationUrl, PHP_URL_QUERY))
        ->assertInertia(fn (Assert $page) => $page->component('Auth/SetPassword')->where('valid', false));

    $entry = AuditLog::query()->where('action', 'organization.decommissioned')->sole();
    expect($entry->organization_id)->toBe($this->tenant->id)
        ->and($entry->actor_type)->toBe('super_admin')
        ->and($entry->actor_id)->toBe((string) $this->superAdmin->id)
        ->and($entry->before)->toBe(['status' => 'active'])
        ->and($entry->after)->toBe(['status' => 'decommissioned', 'files_kept_until' => '2031-09-29']);
});

it('La baja no se ejecuta sin la doble confirmación: after only the first one, it stays active', function () {
    firstConfirmation()->assertOk();
    // …y el Super Administrador cancela la segunda: nada más llega al servidor.

    expect($this->tenant->fresh()->statusLabel())->toBe('Activa');
    $this->post('http://veeduria-smr.govtrace.localhost/login', ['email' => 'ana.perez@veeduria-smr.org', 'password' => 'Veeduria#2026'])
        ->assertSessionHasNoErrors();

    // La segunda, sola, tampoco basta.
    inGlobalPanel('/decommission', ['subdomain' => 'veeduria-smr'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['token' => 'La primera confirmación venció o no es válida. Vuelva a solicitar la baja.']);
    expect($this->tenant->fresh()->statusLabel())->toBe('Activa')
        ->and(AuditLog::query()->where('action', 'organization.decommissioned')->exists())->toBeFalse();
});

it('Tras la baja, el mapa sale de línea pero las evidencias siguen verificables', function () {
    $sha256 = array_key_first(evidenceFiles());
    decommission()->assertOk();

    // El mapa no está disponible: ni la página, ni sus datos, ni la vista de una obra.
    publicGet('/')->assertOk()->assertInertia(fn (Assert $page) => $page->component('Public/Offline')->where('organizationNotice', DECOMMISSIONED_NOTICE));
    publicGet("/worksite/{$this->worksite->id}")->assertInertia(fn (Assert $page) => $page->component('Public/Offline'));
    foreach (['/public/worksites', '/public/worksites/filters', "/public/worksites/{$this->worksite->id}"] as $path) {
        publicGet($path)->assertStatus(410)->assertJson(['message' => MAP_OFFLINE]);
    }

    // Pero el validador libre sigue ahí, y un archivo sellado por ella es "Auténtico".
    publicGet('/verify')->assertOk()->assertInertia(fn (Assert $page) => $page->component('Public/Validator')->where('organizationNotice', DECOMMISSIONED_NOTICE));
    expect(verifiesAsAuthentic($sha256))->toBeTrue();
});

it('La baja no altera los registros en la blockchain: the 5 sealed roots stay as they are', function () {
    $seals = sealsOnRecord();
    $onChain = $this->network->onChain;
    $submissions = count($this->network->submissions);

    decommission()->assertOk();

    expect($seals)->toHaveCount(5)
        ->and(sealsOnRecord())->toBe($seals)
        ->and($this->network->onChain)->toEqual($onChain)
        ->and($this->network->submissions)->toHaveCount($submissions);
    foreach ($seals as $seal) {
        expect($this->network->findSeal($seal['merkle_root']))->not->toBeNull();
    }
});

it('Retención de 5 años de los archivos tras la baja', function (Closure $later, bool $kept) {
    decommission()->assertOk();
    $files = evidenceFiles();
    $seals = sealsOnRecord();

    Carbon::setTestNow($later(CarbonImmutable::parse('2026-09-29 12:00:00')));
    runRetentionPolicy();

    foreach ($files as $path) {
        expect(Storage::disk('evidencias')->exists($path))->toBe($kept);
    }
    // Los sellos en Stellar y las pruebas de inclusión se conservan.
    expect(sealsOnRecord())->toBe($seals);
    foreach (array_keys($files) as $sha256) {
        expect(verifiesAsAuthentic($sha256))->toBeTrue();
    }
})->with([
    '4 años y 11 meses: conservados' => [fn (CarbonImmutable $decommissioned) => $decommissioned->addYears(4)->addMonths(11), true],
    '5 años y 1 día: borrados' => [fn (CarbonImmutable $decommissioned) => $decommissioned->addYears(5)->addDay(), false],
]);

// Reglas de US-003b ---------------------------------------------------------------

it('asks to type the subdomain in the second confirmation', function () {
    $token = firstConfirmation()->json('token');

    inGlobalPanel('/decommission', ['token' => $token, 'subdomain' => 'veeduria'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['subdomain' => 'Escriba el subdominio de la organización (veeduria-smr) para confirmar la baja.']);

    expect($this->tenant->fresh()->statusLabel())->toBe('Activa');
});

it('lets the first confirmation expire after 10 minutes', function () {
    $token = firstConfirmation()->json('token');

    Carbon::setTestNow(now()->addMinutes(11));

    inGlobalPanel('/decommission', ['token' => $token, 'subdomain' => 'veeduria-smr'])->assertJsonValidationErrors(['token']);
    expect($this->tenant->fresh()->statusLabel())->toBe('Activa');
});

it('takes a token only for its own organization', function () {
    $other = (new RegisterOrganization)->handle('890000062-6', 'Veeduría Ciénaga', 'veeduria-cienaga');
    $tokenOfOther = $this->actingAs($this->superAdmin, 'web')->postJson("http://govtrace.localhost/admin/organizations/{$other->id}/decommission/start")->json('token');

    inGlobalPanel('/decommission', ['token' => $tokenOfOther, 'subdomain' => 'veeduria-smr'])->assertJsonValidationErrors(['token']);
    expect($this->tenant->fresh()->statusLabel())->toBe('Activa');
});

it('is definitive: no second decommission, no suspension, no reactivation', function () {
    decommission()->assertOk();

    firstConfirmation()->assertUnprocessable()->assertJsonValidationErrors(['status' => 'La organización ya fue dada de baja: es definitivo.']);
    inGlobalPanel('/suspend')->assertUnprocessable()->assertJsonValidationErrors(['status' => 'La organización ya fue dada de baja: es definitivo.']);
    inGlobalPanel('/reactivate')->assertUnprocessable()->assertJsonValidationErrors(['status' => 'La organización ya fue dada de baja: es definitivo.']);

    expect($this->tenant->fresh()->statusLabel())->toBe('Dada de baja');
});

it('also decommissions a suspended organization', function () {
    inGlobalPanel('/suspend')->assertOk();

    decommission()->assertOk();

    expect($this->tenant->fresh()->statusLabel())->toBe('Dada de baja')
        ->and(AuditLog::query()->where('action', 'organization.decommissioned')->sole()->before)->toBe(['status' => 'suspended']);
});

it('revokes an authorization in force of the Super Administrador to report on its behalf (US-042-SEC)', function () {
    $this->tenant->run(fn () => (new SuperAdminAuthorizations)->grant($this->administrator));
    tenancy()->end();

    decommission()->assertOk();

    expect($this->tenant->run(fn () => SuperAdminAuthorization::inForce()))->toBeNull();
});

it('lets only the Super Administrador of the global panel decommission', function () {
    $this->actingAs($this->administrator, 'tenant')
        ->postJson("http://govtrace.localhost/admin/organizations/{$this->tenant->id}/decommission/start")
        ->assertUnauthorized();
    $this->actingAs($this->administrator, 'tenant')
        ->postJson("http://veeduria-smr.govtrace.localhost/admin/organizations/{$this->tenant->id}/decommission/start")
        ->assertNotFound();

    expect($this->tenant->fresh()->statusLabel())->toBe('Activa');
});

it('lists the organization as "Dada de baja" in the global panel', function () {
    decommission()->assertOk();

    expect($this->actingAs($this->superAdmin, 'web')->getJson('http://govtrace.localhost/admin/organizations/data')->json('data.0.status'))
        ->toBe('Dada de baja');
});

it('keeps the files downloadable until the retention ends, and then says why they are gone', function () {
    $evidenceId = $this->tenant->run(fn () => Evidence::query()->min('id'));
    decommission()->assertOk();

    publicGet("/public/evidences/{$evidenceId}/download")->assertOk();

    Carbon::setTestNow('2031-09-30 12:00:00');
    runRetentionPolicy();

    publicGet("/public/evidences/{$evidenceId}/download")->assertStatus(410)->assertJson(['message' => FILE_PURGED]);
    publicGet("/public/evidences/{$evidenceId}/proof")->assertOk();
});

it('purges the files of each organization once, and records it in the audit log', function () {
    decommission()->assertOk();
    Carbon::setTestNow('2031-09-30 12:00:00');

    runRetentionPolicy();
    runRetentionPolicy();

    $entry = AuditLog::query()->where('action', 'organization.evidence_files_purged')->sole();
    expect($entry->organization_id)->toBe($this->tenant->id)
        ->and($entry->actor_type)->toBe('system')
        ->and($entry->after)->toBe(['files_deleted' => 5]);
});

it('never purges the files of an active or suspended organization', function () {
    inGlobalPanel('/suspend')->assertOk();
    $files = evidenceFiles();

    Carbon::setTestNow('2036-09-30 12:00:00');
    runRetentionPolicy();

    foreach ($files as $path) {
        expect(Storage::disk('evidencias')->exists($path))->toBeTrue();
    }
});

it('applies the retention policy every day', function () {
    Artisan::call('schedule:list', ['--timezone' => 'America/Bogota']); // it. 45a: la hora de Colombia

    expect(Artisan::output())->toMatch('/0\s+1\s+\*\s+\*\s+\*\s+decommissioned-evidence-purge/');
});
