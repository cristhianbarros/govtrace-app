<?php

use App\Application\Privacy\DataPolicy;
use App\Domain\Audit\AuditLog;
use App\Domain\Organization\Notifications\OrganizationRequestRejected;
use App\Domain\Organization\OrganizationRequest;
use App\Domain\Shared\PublicId;
use App\Infrastructure\Tenancy\Tenant;
use App\Jobs\PurgeOrganizationRequests;
use App\Models\User as SuperAdmin;
use Illuminate\Http\UploadedFile;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * Iteración 43k — V10 de docs/mapa-funcional.md (features/US-062-ALT.feature):
 * una veeduría pide su alta desde el Inicio, sin cuenta; el Super
 * Administrador la ve en "Solicitudes de alta" y la aprueba (la Nueva
 * organización viene precargada) o la rechaza con un motivo, que le llega por
 * correo. No es autorregistro: el alta sigue siendo del Super Administrador.
 *
 * Sin RefreshDatabase — aprobar registra una organización, que ejecuta CREATE DATABASE.
 */

const CENTRAL_HOST = 'http://govtrace.localhost';
const NEEDS_AUTHORIZATION = 'Para enviar la solicitud, autorice el tratamiento de sus datos personales.';

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

/** @param  array<string, mixed>  $changes */
function requestAlta(array $changes = []): TestResponse
{
    test()->flushSession();

    return test()->postJson(CENTRAL_HOST.'/organization-requests', [
        'name' => 'Veeduría Ciudadana de La Pradera',
        'contact_email' => 'contacto@lapradera.org',
        'registration_number' => 'Resolución 045 de 2026',
        'registration_authority' => 'Personería de Medellín',
        // It. 46b: la resolución o el certificado de inscripción, en PDF.
        'document' => UploadedFile::fake()->createWithContent('resolucion.pdf', "%PDF-1.7\n%%EOF\n"),
        'data_authorization' => true,
        ...$changes,
    ]);
}

function asTheOperator(string $method, string $path, array $data = []): TestResponse
{
    test()->flushSession();

    return test()->actingAs(test()->superAdmin, 'web')->json($method, CENTRAL_HOST.$path, $data);
}

function pendingRequest(string $name = 'Veeduría Ciudadana de La Pradera'): OrganizationRequest
{
    requestAlta(['name' => $name])->assertCreated();

    return OrganizationRequest::query()->where('name', $name)->sole();
}

it('Una veeduría pide su alta desde el Inicio: it waits for the Super Administrador, with its authorization', function () {
    requestAlta()
        ->assertCreated()
        ->assertExactJson(['message' => 'Recibimos su solicitud. El equipo de GovTrace la revisará y le escribirá a contacto@lapradera.org.']);

    $request = OrganizationRequest::query()->sole();
    expect($request->only(['name', 'contact_email', 'registration_number', 'registration_authority', 'status']))->toBe([
        'name' => 'Veeduría Ciudadana de La Pradera',
        'contact_email' => 'contacto@lapradera.org',
        'registration_number' => 'Resolución 045 de 2026',
        'registration_authority' => 'Personería de Medellín',
        'status' => 'pending',
    ])->and($request->data_authorized_at)->not->toBeNull()
        ->and($request->data_policy_version)->toBe(DataPolicy::VERSION);
    Notification::assertNothingSent(); // nada al correo que alguien escribió: el formulario no envía correos a terceros
});

it('Sin autorizar el tratamiento de datos no se envía la solicitud', function () {
    requestAlta(['data_authorization' => false])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['data_authorization' => NEEDS_AUTHORIZATION]);

    expect(OrganizationRequest::query()->exists())->toBeFalse();
});

it('Una solicitud con datos inválidos se rechaza: it says which one to correct', function () {
    requestAlta(['contact_email' => 'contacto@', 'name' => 'VC'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'contact_email' => 'Escriba un correo de contacto válido, como contacto@veeduria.org.',
            'name' => 'El nombre de la organización debe tener entre 3 y 150 caracteres.',
        ]);

    requestAlta(['registration_authority' => ''])->assertUnprocessable()->assertJsonValidationErrors(['registration_authority']);
    expect(OrganizationRequest::query()->exists())->toBeFalse();
});

it('El Super Administrador ve las solicitudes pendientes, and the menu says how many', function () {
    pendingRequest('Veeduría Ciudadana de La Pradera');
    pendingRequest('Veeduría del Barrio El Salado');

    expect(collect(asTheOperator('GET', '/admin/organization-requests/data')->assertOk()->json('data'))->pluck('name')->all())
        ->toBe(['Veeduría Ciudadana de La Pradera', 'Veeduría del Barrio El Salado']);
    test()->flushSession();
    $this->actingAs($this->superAdmin, 'web')->get(CENTRAL_HOST.'/admin/organization-requests')
        ->assertInertia(fn (Assert $page) => $page->component('SuperAdmin/OrganizationRequests')->where('organizationRequestsPending', 2));
});

it('El Super Administrador aprueba una solicitud: the new organization comes pre-filled, and registering it approves the request', function () {
    $request = pendingRequest();

    test()->flushSession();
    $this->actingAs($this->superAdmin, 'web')->get(CENTRAL_HOST."/admin/organizations/new?request={$request->public_id}")
        ->assertInertia(fn (Assert $page) => $page->component('SuperAdmin/NewOrganization')->where('request', [
            'id' => $request->public_id,
            'name' => 'Veeduría Ciudadana de La Pradera',
            'contact_email' => 'contacto@lapradera.org',
            'registration_number' => 'Resolución 045 de 2026',
            'registration_authority' => 'Personería de Medellín',
        ]));

    asTheOperator('POST', '/admin/organizations', [
        'name' => 'Veeduría Ciudadana de La Pradera',
        'registration_number' => 'Resolución 045 de 2026',
        'registration_authority' => 'Personería de Medellín',
        'subdomain' => 'la-pradera',
        'administrator_name' => 'Contacto de La Pradera',
        'administrator_email' => 'contacto@lapradera.org',
        'request_id' => $request->public_id,
    ])->assertCreated();

    $tenant = Tenant::query()->where('name', 'Veeduría Ciudadana de La Pradera')->sole();
    expect($request->fresh()->status)->toBe('approved')
        ->and($request->fresh()->tenant_id)->toBe($tenant->id)
        ->and(AuditLog::query()->where('action', 'organization_request.approved')->sole()->organization_id)->toBe($tenant->id);
});

it('El Super Administrador rechaza una solicitud con un motivo: the contact gets it by email', function () {
    $request = pendingRequest();

    asTheOperator('POST', "/admin/organization-requests/{$request->public_id}/reject", ['reason' => 'La resolución no corresponde a una veeduría inscrita.'])
        ->assertOk()
        ->assertJson(['message' => 'Solicitud rechazada. Le escribimos a contacto@lapradera.org con el motivo.']);

    Notification::assertSentOnDemand(OrganizationRequestRejected::class, fn (OrganizationRequestRejected $mail, array $channels, AnonymousNotifiable $to) => $to->routes['mail'] === 'contacto@lapradera.org'
        && in_array('Motivo: La resolución no corresponde a una veeduría inscrita.', $mail->toMail($to)->introLines, true));
    expect($request->fresh()->status)->toBe('rejected')
        ->and(AuditLog::query()->where('action', 'organization_request.rejected')->exists())->toBeTrue();
});

it('asks for a reason to reject, and decides each request only once', function () {
    $request = pendingRequest();

    asTheOperator('POST', "/admin/organization-requests/{$request->public_id}/reject", ['reason' => ''])->assertUnprocessable();
    asTheOperator('POST', "/admin/organization-requests/{$request->public_id}/reject", ['reason' => 'No es una veeduría.'])->assertOk();
    asTheOperator('POST', "/admin/organization-requests/{$request->public_id}/reject", ['reason' => 'Otra vez.'])->assertStatus(409);
});

it('Un robot que llena el campo oculto no deja solicitud: it gets the same answer', function () {
    requestAlta(['website' => 'http://spam.example'])
        ->assertCreated()
        ->assertExactJson(['message' => 'Recibimos su solicitud. El equipo de GovTrace la revisará y le escribirá a contacto@lapradera.org.']);

    expect(OrganizationRequest::query()->exists())->toBeFalse();
});

it('limits the requests from the same connection', function () {
    config(['limits.organization_requests_per_hour' => 2]);

    requestAlta(['name' => 'Veeduría Uno'])->assertCreated();
    requestAlta(['name' => 'Veeduría Dos'])->assertCreated();
    requestAlta(['name' => 'Veeduría Tres'])->assertTooManyRequests();
});

it('Una solicitud decidida se borra a los 30 días', function () {
    $old = pendingRequest('Veeduría Vieja');
    $recent = pendingRequest('Veeduría Reciente');
    $waiting = pendingRequest('Veeduría que Espera');
    $old->forceFill(['status' => 'rejected', 'decided_at' => now()->subDays(31)])->save();
    $recent->forceFill(['status' => 'rejected', 'decided_at' => now()->subDays(29)])->save();
    $waiting->forceFill(['created_at' => now()->subDays(90)])->save();

    (new PurgeOrganizationRequests)->handle();

    expect(OrganizationRequest::query()->pluck('name')->sort()->values()->all())->toBe(['Veeduría Reciente', 'Veeduría que Espera']);
});

it('serves the requests only to the Super Administrador', function () {
    test()->flushSession();
    $this->getJson(CENTRAL_HOST.'/admin/organization-requests/data')->assertUnauthorized();
    $this->postJson(CENTRAL_HOST.'/admin/organization-requests/'.PublicId::generate().'/reject', ['reason' => 'x'])->assertUnauthorized();
});
