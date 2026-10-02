<?php

use App\Domain\Audit\AuditLog;
use App\Domain\Organization\OrganizationRequest;
use App\Infrastructure\Tenancy\Tenant;
use App\Jobs\PurgeOrganizationRequests;
use App\Models\User as SuperAdmin;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;

/*
 * Iteración 46b — la validación asistida de una veeduría (enmiendas de
 * features/US-062-ALT.feature y features/US-001.feature). La solicitud lleva
 * el PDF de su resolución o de su certificado de inscripción, y el Super
 * Administrador ve lo que dicen los datos abiertos del RUES (Confecámaras,
 * datos.gov.co c82u-588k) antes de decidir. La decisión sigue siendo suya, y
 * el log de auditoría la guarda con lo que vio. El RUES se simula con
 * Http::fake: ningún test sale a la red (Http::preventStrayRequests).
 *
 * Sin RefreshDatabase — aprobar registra una organización, que ejecuta CREATE DATABASE.
 */

const RUES_HOST = 'http://govtrace.localhost';
const RUES_URL = 'www.datos.gov.co/resource/c82u-588k.json*';
// The metadata of the dataset: when its monthly extract was published (2026-09-04 14:15:35, Bogotá).
const RUES_EXTRACT_URL = 'www.datos.gov.co/api/views/c82u-588k.json*';
const RUES_EXTRACT = ['rowsUpdatedAt' => 1788549335];
const NEEDS_THE_PDF = 'Adjunte la resolución o el certificado de inscripción en PDF, de hasta 10 MB.';
const REGISTERED_IN_A_PERSONERIA = 'Inscrita en una personería: los datos abiertos del RUES no la traen. Revise el PDF de la resolución.';
const RUES_UNAVAILABLE = 'No se pudo consultar el RUES ahora. Puede decidir con el PDF, o volver a intentarlo más tarde.';

beforeEach(function () {
    $this->artisan('migrate');
    Notification::fake();
    Storage::fake('evidencias');
    $this->superAdmin = SuperAdmin::factory()->create(['name' => 'Equipo GovTrace']);
});

afterEach(function () {
    Tenant::query()->get()->each->delete();
    OrganizationRequest::query()->delete();
    DB::table('audit_logs')->delete();
    $this->superAdmin->delete();
});

function resolutionPdf(string $name = 'resolucion.pdf', ?string $content = null): UploadedFile
{
    return UploadedFile::fake()->createWithContent($name, $content ?? "%PDF-1.7\n1 0 obj << /Type /Catalog >> endobj\n%%EOF\n");
}

/** @param  array<string, mixed>  $changes */
function askForTheAlta(array $changes = []): TestResponse
{
    test()->flushSession();

    return test()->postJson(RUES_HOST.'/organization-requests', [
        'name' => 'Veeduría Ciudadana de La Pradera',
        'contact_email' => 'contacto@lapradera.org',
        'registration_number' => 'Resolución 045 de 2026',
        'registration_authority' => 'Personería de Medellín',
        'document' => resolutionPdf(),
        'data_authorization' => true,
        ...$changes,
    ]);
}

function inThePanel(string $method, string $path, array $data = []): TestResponse
{
    test()->flushSession();

    return test()->actingAs(test()->superAdmin, 'web')->json($method, RUES_HOST.$path, $data);
}

/** The row of the RUES open data for a veeduría registered in a chamber of commerce. */
function ruesRow(array $changes = []): array
{
    return [
        'razon_social' => 'VEEDURIA CIUDADANA PAISAJE URBANO',
        'numero_identificacion' => '0000000000000',
        'organizacion_juridica' => 'VEEDURIA',
        'estado_matricula' => 'ACTIVA',
        'camara_comercio' => 'MEDELLIN PARA ANTIOQUIA',
        'matricula' => '2183031',
        'fecha_matricula' => '20240724',
        'fecha_actualizacion' => '2026/09/15 08:30:00.000000000',
        ...$changes,
    ];
}

function paisajeUrbano(): OrganizationRequest
{
    askForTheAlta([
        'name' => 'Veeduría Ciudadana Paisaje Urbano',
        'registration_number' => 'Matrícula 2183031',
        'registration_authority' => 'Cámara de Comercio de Medellín para Antioquia',
    ])->assertCreated();

    return OrganizationRequest::query()->where('name', 'Veeduría Ciudadana Paisaje Urbano')->sole();
}

it('Una veeduría pide su alta desde el Inicio: its PDF is kept private with the request', function () {
    askForTheAlta()->assertCreated();

    $request = OrganizationRequest::query()->sole();
    expect($request->document_path)->toBe("central/organization-requests/{$request->id}.pdf");
    Storage::disk('evidencias')->assertExists($request->document_path);
});

it('La solicitud necesita el PDF de la resolución o del certificado de inscripción', function (array $changes) {
    askForTheAlta($changes)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['document' => NEEDS_THE_PDF]);

    expect(OrganizationRequest::query()->exists())->toBeFalse();
})->with([
    'sin el PDF' => [fn () => ['document' => null]],
    'un archivo que no es un PDF, aunque se llame .pdf' => [fn () => ['document' => resolutionPdf('resolucion.pdf', 'Hola, no soy un PDF')]],
    'un PDF de más de 10 MB' => [fn () => ['document' => UploadedFile::fake()->create('resolucion.pdf', 10_241, 'application/pdf')]],
]);

it('El Super Administrador ve lo que dice el RUES de una veeduría inscrita en una cámara de comercio: only the one of that chamber', function () {
    Http::fake([
        RUES_URL => Http::response([ruesRow(), ruesRow(['razon_social' => 'OTRA ORGANIZACION', 'camara_comercio' => 'BOGOTA'])]),
        RUES_EXTRACT_URL => Http::response(RUES_EXTRACT),
    ]);
    $request = paisajeUrbano();

    $answer = inThePanel('GET', "/admin/organization-requests/{$request->id}/rues")->assertOk()->json();

    // The date of the data is the one of the monthly extract; each record says when its own data were last updated.
    expect($answer)->toMatchArray(['status' => 'found', 'message' => null, 'data_date' => '2026-09-04'])
        ->and($answer['records'])->toBe([[
            'name' => 'VEEDURIA CIUDADANA PAISAJE URBANO',
            'nit' => null,
            'legal_form' => 'VEEDURIA',
            'status' => 'ACTIVA',
            'chamber' => 'MEDELLIN PARA ANTIOQUIA',
            'registration' => '2183031',
            'registered_on' => '2024-07-24',
            'updated_on' => '2026-09-15',
        ]]);
    Http::assertSent(fn (HttpRequest $sent) => str_contains(urldecode($sent->url()), "matricula='2183031'"));
});

it('shows every match when none is of the chamber the veeduría wrote', function () {
    Http::fake([RUES_URL => Http::response([ruesRow(['camara_comercio' => 'BOGOTA']), ruesRow(['camara_comercio' => 'CALI'])])]);
    $request = paisajeUrbano();

    expect(array_column(inThePanel('GET', "/admin/organization-requests/{$request->id}/rues")->json('records'), 'chamber'))->toBe(['BOGOTA', 'CALI']);
});

it('asks the date of the monthly extract once a day, not on every lookup', function () {
    Http::fake([RUES_URL => Http::response([ruesRow()]), RUES_EXTRACT_URL => Http::response(RUES_EXTRACT)]);

    inThePanel('GET', '/admin/rues?nit=900123456-8')->assertOk()->assertJsonPath('data_date', '2026-09-04');
    inThePanel('GET', '/admin/rues?nit=901234567-7')->assertOk()->assertJsonPath('data_date', '2026-09-04');

    expect(Http::recorded(fn (HttpRequest $sent) => str_contains($sent->url(), '/api/views/'))->count())->toBe(1);
});

it('still shows what the RUES found when the date of its extract does not come', function () {
    Http::fake([RUES_URL => Http::response([ruesRow()]), RUES_EXTRACT_URL => Http::response('', 503)]);

    inThePanel('GET', '/admin/rues?nit=900123456-8')->assertOk()
        ->assertJsonPath('status', 'found')
        ->assertJsonPath('data_date', null)
        ->assertJsonPath('records.0.name', 'VEEDURIA CIUDADANA PAISAJE URBANO');
});

it('Una veeduría inscrita en una personería no está en los datos abiertos del RUES: and its PDF can be downloaded', function () {
    Http::fake();
    askForTheAlta()->assertCreated();
    $request = OrganizationRequest::query()->sole();

    inThePanel('GET', "/admin/organization-requests/{$request->id}/rues")
        ->assertOk()
        ->assertJson(['status' => 'personeria', 'message' => REGISTERED_IN_A_PERSONERIA, 'records' => []]);
    Http::assertNothingSent();

    $download = inThePanel('GET', "/admin/organization-requests/{$request->id}/document")->assertOk();
    expect($download->headers->get('Content-Type'))->toBe('application/pdf')
        ->and($download->headers->get('Content-Disposition'))->toStartWith('attachment;')
        ->and($download->headers->get('X-Content-Type-Options'))->toBe('nosniff')
        ->and($download->headers->get('Content-Security-Policy'))->toBe('sandbox')
        ->and($download->streamedContent())->toStartWith('%PDF-');
});

it('Si el RUES no responde, la revisión sigue: the request can still be decided', function () {
    Http::fake([RUES_URL => fn () => throw new ConnectionException('timeout')]);
    $request = paisajeUrbano();

    inThePanel('GET', "/admin/organization-requests/{$request->id}/rues")
        ->assertOk()
        ->assertJson(['status' => 'unavailable', 'message' => RUES_UNAVAILABLE, 'records' => []]);

    inThePanel('POST', "/admin/organization-requests/{$request->id}/reject", ['reason' => 'No se pudo comprobar la inscripción.'])->assertOk();
});

it('La decisión queda en la auditoría con lo que dijo el RUES: what the Super Administrador saw', function () {
    Http::fake([RUES_URL => Http::response([ruesRow()])]);
    $request = paisajeUrbano();
    inThePanel('GET', "/admin/organization-requests/{$request->id}/rues")->assertOk();

    inThePanel('POST', "/admin/organization-requests/{$request->id}/reject", ['reason' => 'La matrícula está cancelada.'])->assertOk();

    $rues = AuditLog::query()->where('action', 'organization_request.rejected')->sole()->after['rues'];
    expect($rues['status'])->toBe('found')
        ->and($rues['records'][0])->toMatchArray(['name' => 'VEEDURIA CIUDADANA PAISAJE URBANO', 'status' => 'ACTIVA'])
        ->and($rues)->toHaveKey('queried_at');
});

it('El PDF de una solicitud aprobada queda con la organización: and the Super Administrador downloads it from Organizaciones', function () {
    askForTheAlta()->assertCreated();
    $request = OrganizationRequest::query()->sole();
    $requestPath = $request->document_path;

    inThePanel('POST', '/admin/organizations', [
        'name' => 'Veeduría Ciudadana de La Pradera',
        'registration_number' => 'Resolución 045 de 2026',
        'registration_authority' => 'Personería de Medellín',
        'subdomain' => 'la-pradera',
        'request_id' => $request->id,
    ])->assertCreated();

    $tenant = Tenant::query()->where('name', 'Veeduría Ciudadana de La Pradera')->sole();
    expect($tenant->registration_document_path)->toBe("{$tenant->id}/registro/inscripcion.pdf")
        ->and($request->fresh()->document_path)->toBeNull();
    Storage::disk('evidencias')->assertExists($tenant->registration_document_path);
    Storage::disk('evidencias')->assertMissing($requestPath);

    expect(collect(inThePanel('GET', '/admin/organizations/data')->json('data'))->firstWhere('id', $tenant->id)['has_registration_document'])->toBeTrue();
    expect(inThePanel('GET', "/admin/organizations/{$tenant->id}/registration-document")->assertOk()->streamedContent())->toStartWith('%PDF-');
});

it('El PDF de una solicitud rechazada se borra con ella', function () {
    askForTheAlta()->assertCreated();
    $request = OrganizationRequest::query()->sole();
    $path = $request->document_path;
    $request->forceFill(['status' => 'rejected', 'decided_at' => now()->subDays(31)])->save();

    (new PurgeOrganizationRequests)->handle();

    expect(OrganizationRequest::query()->exists())->toBeFalse();
    Storage::disk('evidencias')->assertMissing($path);
});

it('Solo el Super Administrador descarga el PDF de una solicitud: nor queries the RUES', function () {
    askForTheAlta()->assertCreated();
    $request = OrganizationRequest::query()->sole();

    test()->flushSession();
    $this->getJson(RUES_HOST."/admin/organization-requests/{$request->id}/document")->assertUnauthorized();
    $this->getJson(RUES_HOST."/admin/organization-requests/{$request->id}/rues")->assertUnauthorized();
    $this->getJson(RUES_HOST.'/admin/rues?nit=900123456-8')->assertUnauthorized();
});

it('Al dar de alta una organización se consulta el RUES por su NIT: and registering it keeps what it said in the audit log', function () {
    Http::fake([
        RUES_URL => Http::response([ruesRow(['razon_social' => 'VEEDURIA CIUDADANA SANTA MARTA', 'numero_identificacion' => '900123456', 'camara_comercio' => 'SANTA MARTA', 'organizacion_juridica' => 'LAS DEMÁS ORGANIZACIONES CIVILES,CORPORACIONES,FUNDACIONES'])]),
        RUES_EXTRACT_URL => Http::response(RUES_EXTRACT),
    ]);

    $answer = inThePanel('GET', '/admin/rues?nit=900123456-8')->assertOk()->json();

    expect($answer['status'])->toBe('found')
        ->and($answer['records'][0])->toMatchArray(['name' => 'VEEDURIA CIUDADANA SANTA MARTA', 'nit' => '900123456', 'chamber' => 'SANTA MARTA', 'status' => 'ACTIVA']);
    Http::assertSent(fn (HttpRequest $sent) => str_contains(urldecode($sent->url()), "numero_identificacion='900123456'"));

    inThePanel('POST', '/admin/organizations', ['name' => 'Veeduría Ciudadana Santa Marta', 'nit' => '900123456-8', 'subdomain' => 'veeduria-smr'])->assertCreated();

    expect(AuditLog::query()->where('action', 'organization.registered')->sole()->after['rues']['status'])->toBe('found');
    // registrarla usa lo que el Super Administrador ya vio: no vuelve a consultar
    expect(Http::recorded(fn (HttpRequest $sent) => str_contains($sent->url(), '/resource/'))->count())->toBe(1);
});

it('records that the RUES was not consulted when the Super Administrador did not ask', function () {
    Http::fake();

    inThePanel('POST', '/admin/organizations', ['name' => 'Veeduría Ciudadana Santa Marta', 'nit' => '900123456-8', 'subdomain' => 'veeduria-smr'])->assertCreated();

    expect(AuditLog::query()->where('action', 'organization.registered')->sole()->after['rues'])->toBeNull();
    Http::assertNothingSent();
});
