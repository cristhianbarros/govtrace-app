<?php

use App\Application\Organization\ConfigureTerritory;
use App\Application\Organization\InviteObserver;
use App\Application\Organization\RegisterOrganization;
use App\Application\Platform\SuperAdministrators;
use App\Application\Sealing\SealingNetwork;
use App\Domain\Organization\Notifications\WelcomeNotification;
use App\Domain\Organization\OrganizationRequest;
use App\Domain\Organization\Roles;
use App\Domain\Organization\User as OrganizationUser;
use App\Domain\Reports\Evidence;
use App\Domain\Reports\Report;
use App\Domain\Sealing\ReportSeal;
use App\Domain\Shared\PublicId;
use App\Domain\Worksites\Worksite;
use App\Infrastructure\Tenancy\Tenant;
use App\Models\User as SuperAdmin;
use Database\Seeders\DivipolaSeeder;
use Illuminate\Routing\Route as RouteDefinition;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Symfony\Component\Uid\Ulid;
use Tests\Support\FakeSealingNetwork;

/*
 * Iteración 46c — US-064-SEC: identificadores públicos que no se pueden
 * recorrer (features/US-064-SEC.feature, R-SEC-08). Las URL y el API llevan
 * un ULID en vez del número consecutivo de las obras, los reportes, las
 * evidencias, los informes ciudadanos, los usuarios y las solicitudes de
 * alta. Las llaves numéricas no cambian: la referencia de la obra sellada
 * en Stellar se calcula con ellas (R-BLK-02).
 *
 * Sin RefreshDatabase — registrar la organización ejecuta CREATE DATABASE.
 */

const PID_HOST = 'http://veeduria-smr.govtrace.localhost';
const PID_CENTRAL = 'http://govtrace.localhost';
const PID_ULID = '/^[0-9a-hjkmnp-tv-z]{26}$/';

/** The route parameters that name a record of those tables. */
const PID_RECORD_PARAMETERS = ['worksite', 'report', 'evidence', 'observer', 'user', 'organizationRequest'];

/** The keys of the API that carry the identifier of one of those records. */
const PID_RECORD_KEYS = ['id', 'report_id', 'worksite_id', 'evidence_id', 'user_id', 'request_id', 'reporte'];

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
    OrganizationRequest::query()->delete();
    SuperAdmin::query()->delete();
    DB::table('contracts')->delete();
    DB::table('audit_logs')->delete();
});

/** @return array{0: int, 1: string} the number and the public id of the first record of that kind */
function pidRecordOf(string $kind): array
{
    return test()->tenant->run(function () use ($kind) {
        $record = match ($kind) {
            'worksite' => Worksite::query()->orderBy('id')->firstOrFail(),
            'report' => Report::query()->orderBy('id')->firstOrFail(),
            'evidence' => Evidence::query()->orderBy('id')->firstOrFail(),
        };

        return [$record->id, $record->public_id];
    });
}

/**
 * Every value under a key that names a record, at any depth.
 *
 * @return list<mixed>
 */
function pidRecordIdsIn(array $json): array
{
    return collect(Arr::dot($json))
        ->filter(fn (mixed $value, string $key) => in_array(Str::afterLast($key, '.'), PID_RECORD_KEYS, true))
        ->values()
        ->all();
}

it('La página pública de una obra se abre con su identificador público: and so do the photo, download, proof and receipt of its evidence', function () {
    publishedReport($this->tenant, $this->veedor, $this->administrator);
    [, $worksite] = pidRecordOf('worksite');
    [, $report] = pidRecordOf('report');
    [, $evidence] = pidRecordOf('evidence');

    expect($worksite)->toMatch(PID_ULID)
        ->and(publicGet('/public/worksites')->assertOk()->json('data.0.id'))->toBe($worksite)
        ->and(publicGet('/public/worksites/list')->assertOk()->json('data.0.id'))->toBe($worksite);

    $this->withoutVite()->get(PID_HOST."/worksite/{$worksite}")->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->component('Public/Worksite')->where('worksiteId', $worksite));

    $card = publicGet("/public/worksites/{$worksite}")->assertOk()->json('data.timeline.0');
    expect($card['receipt_url'])->toBe("/public/reports/{$report}/receipt")
        ->and($card['files'][0]['photo_url'])->toBe("/public/evidences/{$evidence}/photo")
        ->and($card['files'][0]['download_url'])->toBe("/public/evidences/{$evidence}/download")
        ->and($card['files'][0]['proof_url'])->toBe("/public/evidences/{$evidence}/proof");

    foreach ([$card['receipt_url'], ...array_values(Arr::only($card['files'][0], ['photo_url', 'download_url', 'proof_url']))] as $url) {
        publicGet($url)->assertOk();
    }
});

it('Un enlace público viejo, con número, redirige al nuevo', function (string $path, string $kind) {
    publishedReport($this->tenant, $this->veedor, $this->administrator);
    [$number, $publicId] = pidRecordOf($kind);

    $this->get(PID_HOST.sprintf($path, $number))
        ->assertStatus(301)
        ->assertRedirect(PID_HOST.sprintf($path, $publicId));
})->with([
    'la página de la obra' => ['/worksite/%s', 'worksite'],
    'los datos de la obra' => ['/public/worksites/%s', 'worksite'],
    'el recibo' => ['/public/reports/%s/receipt', 'report'],
    'la foto' => ['/public/evidences/%s/photo', 'evidence'],
    'la descarga' => ['/public/evidences/%s/download', 'evidence'],
    'la prueba' => ['/public/evidences/%s/proof', 'evidence'],
]);

it('redirects an old link only to what is already public: a number of a hidden evidence is not found', function () {
    $reportId = sealedReport($this->tenant, $this->veedor); // sellada, y oculta hasta que la publiquen
    $evidenceId = $this->tenant->run(fn () => Report::query()->findOrFail($reportId)->evidences()->value('id'));

    $this->get(PID_HOST."/public/reports/{$reportId}/receipt")->assertNotFound();
    $this->get(PID_HOST."/public/evidences/{$evidenceId}/download")->assertNotFound();
});

it('redirects an old invitation only with its token: without it, the screen of an invalid link, which tells nothing', function () {
    $veedor = $this->tenant->run(fn () => (new InviteObserver)->handle('nuevo@correo.co'));

    foreach (["/set-password/{$veedor->id}?token=otro", "/set-password/{$veedor->id}", '/set-password/987654?token=otro'] as $path) {
        $this->withoutVite()->get(PID_HOST.$path)
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->component('Auth/SetPassword')->where('valid', false)->missing('email'));
    }
});

it('answers 404 to a public id or a number that does not exist', function () {
    publicGet('/public/worksites/'.strtolower((string) new Ulid))->assertNotFound();
    $this->get(PID_HOST.'/worksite/987654')->assertNotFound();
});

it('Ninguna ruta de la app lleva el número de un registro', function () {
    $routes = collect(Route::getRoutes()->getRoutes());
    $isLegacy = fn (RouteDefinition $route) => str_starts_with((string) $route->getName(), 'legacy.');

    $offenders = $routes->reject($isLegacy)->flatMap(fn (RouteDefinition $route) => collect($route->parameterNames())
        ->intersect(PID_RECORD_PARAMETERS)
        ->filter(fn (string $parameter) => ($route->wheres[$parameter] ?? null) !== PublicId::PATTERN)
        ->map(fn (string $parameter) => "{$route->uri()} ({$parameter})"));

    expect($offenders->values()->all())->toBe([])
        // Solo los enlaces que pudieron compartirse aceptan todavía el número, y solo para redirigir.
        ->and($routes->filter($isLegacy)->map(fn (RouteDefinition $route) => $route->getName())->unique()->sort()->values()->all())->toBe([
            'legacy.public.evidences.download',
            'legacy.public.evidences.photo',
            'legacy.public.evidences.proof',
            'legacy.public.reports.receipt',
            'legacy.public.worksite',
            'legacy.public.worksites.show',
            'legacy.set-password.show',
            'legacy.tenant.set-password.show',
        ])
        ->and($routes->filter($isLegacy)->every(fn (RouteDefinition $route) => $route->methods() === ['GET', 'HEAD']))->toBeTrue();
});

it('Ninguna ruta de la app lleva el número de un registro: an internal route answers 404 to a number', function () {
    $reportId = sealedReport($this->tenant, $this->veedor);
    [, $report] = pidRecordOf('report');

    $this->actingAs($this->administrator, 'tenant')->postJson(PID_HOST."/reports/{$reportId}/publish")->assertNotFound();
    $this->actingAs($this->administrator, 'tenant')->postJson(PID_HOST."/reports/{$report}/publish")->assertOk();
});

it('El mapa, la línea de tiempo y los datos abiertos exponen el identificador público', function () {
    publishedReport($this->tenant, $this->veedor, $this->administrator);
    [$worksiteNumber, $worksite] = pidRecordOf('worksite');
    [$reportNumber, $report] = pidRecordOf('report');

    $ids = [
        ...pidRecordIdsIn(publicGet('/public/worksites')->json()),
        ...pidRecordIdsIn(publicGet('/public/worksites/list')->json()),
        ...pidRecordIdsIn(publicGet("/public/worksites/{$worksite}")->json()),
        ...pidRecordIdsIn(publicGet("/public/reports/{$report}/receipt")->json()),
        ...pidRecordIdsIn(publicGet('/open-data.json')->json()),
    ];
    // El validador busca la prueba por la huella; en su modo contextual, dentro de un reporte, por su identificador público.
    $sha256 = $this->tenant->run(fn () => Evidence::query()->sole()->sha256);
    $ids = [...$ids, ...pidRecordIdsIn(publicGet("/public/proofs/{$sha256}?report={$report}")->assertOk()->json())];
    publicGet("/public/proofs/{$sha256}?report={$reportNumber}")->assertNotFound();
    expect($ids)->not->toBeEmpty()->each->toMatch(PID_ULID);

    $csv = $this->get(PID_HOST.'/open-data.csv')->assertOk()->streamedContent();
    expect($csv)->toContain($report)
        ->and(str_getcsv(explode("\n", trim($csv))[1])[0])->toBe($report)
        ->and([$worksiteNumber, $reportNumber])->each->toBeInt();
});

it('identifies every record by its public id in the panels of the Administrador and the veedor', function () {
    $reportId = sealedReport($this->tenant, $this->veedor);
    $this->tenant->run(fn () => (new InviteObserver)->handle('nuevo@correo.co'));

    $ids = [
        ...pidRecordIdsIn(inbox($this->administrator)->assertOk()->json()),
        ...pidRecordIdsIn($this->actingAs($this->administrator, 'tenant')->getJson(PID_HOST.'/observers')->assertOk()->json()),
        ...pidRecordIdsIn($this->actingAs($this->administrator, 'tenant')->getJson(PID_HOST.'/administrators')->assertOk()->json()),
        ...pidRecordIdsIn($this->actingAs($this->administrator, 'tenant')->getJson(PID_HOST.'/worksites')->assertOk()->json()),
        ...pidRecordIdsIn($this->actingAs($this->veedor, 'tenant')->getJson(PID_HOST.'/me/reports')->assertOk()->json()),
    ];

    expect($ids)->not->toBeEmpty()->each->toMatch(PID_ULID)
        ->and($ids)->not->toContain($reportId);
});

it('identifies every record by its public id in the global panel', function () {
    $superAdmin = SuperAdmin::factory()->create(['name' => 'Ana Directora']);
    OrganizationRequest::create([
        'name' => 'Veeduría de La Pradera', 'contact_email' => 'contacto@lapradera.org', 'registration_number' => 'Resolución 045 de 2026',
        'registration_authority' => 'Personería de Medellín', 'data_authorized_at' => now(), 'data_policy_version' => 'v1',
    ]);
    tenancy()->end();

    $as = fn (string $path) => $this->actingAs($superAdmin, 'web')->getJson(PID_CENTRAL.$path)->assertOk()->json();
    $ids = [
        ...pidRecordIdsIn($as('/admin/super-administrators/data')),
        ...pidRecordIdsIn($as('/admin/organization-requests/data')),
        ...pidRecordIdsIn(Arr::except($as('/admin/organizations/data'), 'data.0.id')), // la organización ya tiene su UUID
    ];

    expect($ids)->not->toBeEmpty()->each->toMatch(PID_ULID);
});

it('approves a request for an alta from the new organization, by its public id', function () {
    $superAdmin = SuperAdmin::factory()->create(['name' => 'Ana Directora']);
    $request = OrganizationRequest::create([
        'name' => 'Veeduría de La Pradera', 'contact_email' => 'contacto@lapradera.org', 'registration_number' => 'Resolución 045 de 2026',
        'registration_authority' => 'Personería de Medellín', 'data_authorized_at' => now(), 'data_policy_version' => 'v1',
    ]);
    tenancy()->end();

    $this->actingAs($superAdmin, 'web')->withoutVite()->get(PID_CENTRAL."/admin/organizations/new?request={$request->public_id}")
        ->assertInertia(fn (AssertableInertia $page) => $page->where('request.id', $request->public_id));
    // Una ruta interna no acepta el número.
    $this->actingAs($superAdmin, 'web')->get(PID_CENTRAL."/admin/organizations/new?request={$request->id}")->assertNotFound();

    $this->actingAs($superAdmin, 'web')->postJson(PID_CENTRAL.'/admin/organizations', [
        'name' => 'Veeduría de La Pradera', 'registration_number' => 'Resolución 045 de 2026', 'registration_authority' => 'Personería de Medellín',
        'subdomain' => 'la-pradera', 'request_id' => $request->public_id,
    ])->assertCreated();

    expect($request->fresh()->status)->toBe('approved');
});

it('La invitación que llegó antes del cambio sigue sirviendo: the link of a veedor, with the same token', function () {
    $veedor = $this->tenant->run(fn () => (new InviteObserver)->handle('nuevo@correo.co'));
    $url = null;
    Notification::assertSentTo($veedor, WelcomeNotification::class, function (WelcomeNotification $mail) use (&$url) {
        $url = $mail->url;

        return true;
    });
    expect($url)->toStartWith(PID_HOST."/set-password/{$veedor->public_id}?token=");
    $token = Str::after($url, 'token=');

    $this->get(PID_HOST."/set-password/{$veedor->id}?token={$token}")
        ->assertStatus(301)
        ->assertRedirect($url);
    $this->withoutVite()->get($url)->assertInertia(fn (AssertableInertia $page) => $page->component('Auth/SetPassword')->where('valid', true));
});

it('La invitación que llegó antes del cambio sigue sirviendo: the link of a Super Administrador, with the same token', function () {
    $ana = SuperAdmin::factory()->create(['name' => 'Ana Directora']);
    (new SuperAdministrators)->invite($ana, 'Luis Gómez', 'luis@govtrace.org');
    $luis = SuperAdmin::query()->where('email', 'luis@govtrace.org')->sole();
    $url = null;
    Notification::assertSentTo($luis, WelcomeNotification::class, function (WelcomeNotification $mail) use (&$url) {
        $url = $mail->url;

        return true;
    });
    expect($url)->toStartWith(PID_CENTRAL."/set-password/{$luis->public_id}?token=");

    $this->get(PID_CENTRAL."/set-password/{$luis->id}?token=".Str::after($url, 'token='))
        ->assertStatus(301)
        ->assertRedirect($url);
});

it('Un reporte guardado sin conexión antes del cambio se envía igual: it names the worksite by its SECOP contract, not by a number', function () {
    // Lo que guarda el celular sin conexión (resources/js/lib/outbox.js): el contrato de SECOP, la hora de captura, los archivos.
    $response = sendReport($this->veedor, ['captured_at' => now()->subDays(2)->toIso8601String()])->assertCreated();

    expect($response->json('id'))->toMatch(PID_ULID)
        ->and($this->tenant->run(fn () => Report::query()->sole()->worksite_id))->toBe($this->worksite->id);
});

it('Lo ya sellado se sigue verificando', function () {
    sealedReport($this->tenant, $this->veedor);
    $second = sealedReport($this->tenant, $this->veedor);
    editorialDecision($this->administrator, 'publish', $second)->assertOk();
    [, $worksite] = pidRecordOf('worksite');

    // R-BLK-02: la referencia de la obra sigue saliendo de su número, como la de lo ya sellado.
    expect($this->tenant->run(fn () => ReportSeal::query()->orderBy('id')->pluck('worksite_reference')->all()))
        ->toBe(array_fill(0, 2, hash('sha256', $this->tenant->id.':'.$this->worksite->id)));

    $proofUrl = publicGet("/public/worksites/{$worksite}")->json('data.timeline.0.files.0.proof_url');
    expect(pidRecordIdsIn(publicGet($proofUrl)->assertOk()->json()))->toBe([]);
});

it('gives the records that already exist a public id with the date they were created', function () {
    $migration = include database_path('migrations/tenant/2026_10_02_000300_add_public_ids.php');
    $createdAt = Carbon::parse('2026-05-04 10:00:00');

    $publicId = $this->tenant->run(function () use ($migration, $createdAt) {
        $migration->down();
        DB::table('worksites')->update(['created_at' => $createdAt]);
        $migration->up();

        return DB::table('worksites')->value('public_id');
    });

    expect($publicId)->toMatch(PID_ULID)
        ->and(Ulid::fromString(strtoupper($publicId))->getDateTime()->getTimestamp())->toBe($createdAt->getTimestamp());
});

it('gives every new record its public id', function () {
    $reportId = sealedReport($this->tenant, $this->veedor);

    $this->tenant->run(function () use ($reportId) {
        $report = Report::query()->findOrFail($reportId);
        expect($report->public_id)->toMatch(PID_ULID)
            ->and($report->evidences()->pluck('public_id')->all())->each->toMatch(PID_ULID)
            ->and(OrganizationUser::query()->pluck('public_id')->all())->each->toMatch(PID_ULID);
    });
});
