<?php

use App\Application\Organization\RegisterOrganization;
use App\Domain\Audit\AuditLog;
use App\Domain\Organization\User;
use App\Infrastructure\Tenancy\Tenant;
use App\Models\User as SuperAdmin;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * Iteración 19 — Panel global del Super Administrador (specs/PLAN.md). Las
 * reglas de US-001, US-002 y US-011 ya se prueban en sus Actions (it. 3 y
 * 6); aquí, lo que las pantallas del panel necesitan del servidor: las
 * páginas y el JSON de cada una. Las pantallas se prueban con Vitest.
 */

beforeEach(function () {
    $this->artisan('migrate');
    Notification::fake();
    $this->superAdmin = SuperAdmin::factory()->create();
});

afterEach(function () {
    Tenant::query()->get()->each->delete();
    DB::table('audit_logs')->delete();
});

function asSuperAdmin(string $method, string $path, array $data = []): TestResponse
{
    return test()->actingAs(test()->superAdmin, 'web')->json($method, "http://govtrace.localhost{$path}", $data);
}

// Las pantallas -----------------------------------------------------------

it('serves each screen of the global panel to the Super Administrador', function (string $path, string $component) {
    test()->withoutVite()->actingAs(test()->superAdmin, 'web')
        ->get("http://govtrace.localhost{$path}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component($component));
})->with([
    'organizaciones' => ['/admin/organizations', 'SuperAdmin/Organizations'],
    'alta de organización' => ['/admin/organizations/new', 'SuperAdmin/NewOrganization'],
]);

it('sends a visitor without a session who opens a screen of the global panel to its login', function (string $path) {
    $this->get("http://govtrace.localhost{$path}")->assertRedirect('http://govtrace.localhost/login');
})->with([
    'organizaciones' => ['/admin/organizations'],
    'alta de organización' => ['/admin/organizations/new'],
]);

it('opens the panel of the Super Administrador on the list of organizations', function () {
    $this->actingAs($this->superAdmin, 'web')
        ->get('http://govtrace.localhost/dashboard')
        ->assertRedirect('http://govtrace.localhost/admin/organizations');
});

// Listado (US-001, US-011) -------------------------------------------------

it('lists the organizations with their NIT, subdomain and status', function () {
    $smr = (new RegisterOrganization)->handle('900123456-8', 'Veeduría Ciudadana Santa Marta', 'veeduria-smr');
    $cienaga = (new RegisterOrganization)->handle('890000062-6', 'Veeduría Ciénaga', 'veeduria-cienaga');

    expect(asSuperAdmin('GET', '/admin/organizations/data')->assertOk()->json('data'))->toBe([
        // it. 44d: su identificación en una línea — su NIT, su inscripción o los dos.
        ['id' => $smr->id, 'nit' => '900123456-8', 'identification' => 'NIT 900123456-8', 'name' => 'Veeduría Ciudadana Santa Marta', 'subdomain' => 'veeduria-smr.govtrace.localhost', 'status' => 'Activa', 'administrators' => []],
        ['id' => $cienaga->id, 'nit' => '890000062-6', 'identification' => 'NIT 890000062-6', 'name' => 'Veeduría Ciénaga', 'subdomain' => 'veeduria-cienaga.govtrace.localhost', 'status' => 'Activa', 'administrators' => []], // it. 43a: quién la administra
    ]);
});

// Alta (US-001) -------------------------------------------------------------

it('registers an organization from the screen', function () {
    asSuperAdmin('POST', '/admin/organizations', [
        'name' => 'Veeduría Ciudadana Santa Marta',
        'nit' => '900123456-8',
        'subdomain' => 'veeduria-smr',
    ])
        ->assertCreated()
        ->assertJson(['message' => 'Organización registrada. El subdominio veeduria-smr.govtrace.localhost ya está activo.']);

    expect(Tenant::query()->where('nit', '900123456-8')->exists())->toBeTrue();
});

it('does not register a duplicate NIT or an already-used subdomain', function () {
    (new RegisterOrganization)->handle('900123456-8', 'Veeduría Ciudadana Santa Marta', 'veeduria-smr');

    asSuperAdmin('POST', '/admin/organizations', ['name' => 'Otra Veeduría', 'nit' => '900123456-8', 'subdomain' => 'otra-veeduria'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['nit' => 'Ya existe una organización registrada con el NIT ingresado.']);

    asSuperAdmin('POST', '/admin/organizations', ['name' => 'Otra Veeduría', 'nit' => '901234567-7', 'subdomain' => 'veeduria-smr'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['subdomain' => 'El subdominio especificado ya no está disponible. Por favor elija otro.']);
});

it('does not register an invalid NIT, subdomain or name', function (array $data, string $field, string $message) {
    asSuperAdmin('POST', '/admin/organizations', array_merge(['name' => 'Veeduría Ciudadana Santa Marta', 'nit' => '900123456-8', 'subdomain' => 'veeduria-smr'], $data))
        ->assertUnprocessable()
        ->assertJsonValidationErrors([$field => $message]);
})->with([
    'NIT sin dígito' => [['nit' => '900123456'], 'nit', 'El NIT ingresado no es válido o el dígito de verificación no coincide con el algoritmo de la DIAN.'],
    'subdominio con mayúsculas' => [['subdomain' => 'Veeduria-SMR'], 'subdomain', 'El subdominio solo puede contener letras minúsculas, números y guiones, sin espacios ni caracteres especiales.'],
    'subdominio reservado' => [['subdomain' => 'admin'], 'subdomain', 'El subdominio utiliza una palabra reservada del sistema y no puede ser utilizado.'],
    'nombre muy corto' => [['name' => 'Ve'], 'name', 'El nombre de la organización debe tener entre 3 y 150 caracteres.'],
]);

// Administrador inicial (US-002) --------------------------------------------

it('assigns the initial Administrador right after registering, in the same screen', function () {
    asSuperAdmin('POST', '/admin/organizations', [
        'name' => 'Veeduría Ciudadana Santa Marta',
        'nit' => '900123456-8',
        'subdomain' => 'veeduria-smr',
        'administrator_name' => 'Ana Pérez',
        'administrator_email' => 'ana.perez@veeduria-smr.org',
    ])->assertCreated();

    $tenant = Tenant::query()->where('nit', '900123456-8')->sole();
    $administratorExists = $tenant->run(fn () => User::query()->where('email', 'ana.perez@veeduria-smr.org')->exists());

    expect($administratorExists)->toBeTrue();
});

it('does not register the organization when the initial Administrador is invalid', function () {
    asSuperAdmin('POST', '/admin/organizations', [
        'name' => 'Veeduría Ciudadana Santa Marta',
        'nit' => '900123456-8',
        'subdomain' => 'veeduria-smr',
        'administrator_name' => 'Ana Pérez',
        'administrator_email' => 'ana.perez@',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['administrator_email' => 'El correo electrónico no tiene un formato válido.']);

    expect(Tenant::query()->where('nit', '900123456-8')->exists())->toBeFalse();
});

// Datos legales (US-011) ------------------------------------------------------

it('shows the organization detail with its legal data and lets the Super Administrador change its NIT', function () {
    (new RegisterOrganization)->handle('900123456-8', 'Veeduría Ciudadana Santa Marta', 'veeduria-smr');
    $tenant = Tenant::query()->sole();

    expect(asSuperAdmin('GET', "/admin/organizations/{$tenant->id}")->assertOk()->json('data'))
        ->toBe(['id' => $tenant->id, 'name' => 'Veeduría Ciudadana Santa Marta', 'nit' => '900123456-8', 'registration_number' => null, 'registration_authority' => null, 'subdomain' => 'veeduria-smr.govtrace.localhost', 'status' => 'Activa']);

    // It. 44d: la misma pantalla cambia el NIT y la inscripción, los datos legales.
    asSuperAdmin('PUT', "/admin/organizations/{$tenant->id}/nit", ['nit' => '901234567-7'])
        ->assertOk()
        ->assertJson(['message' => 'Los datos legales han sido actualizados.']);

    expect($tenant->refresh()->nit)->toBe('901234567-7')
        ->and(AuditLog::query()->where('action', 'organization.legal_data_updated')->exists())->toBeTrue();
});

it('does not change the NIT to one already used, or to an invalid one', function () {
    (new RegisterOrganization)->handle('900123456-8', 'Veeduría Ciudadana Santa Marta', 'veeduria-smr');
    (new RegisterOrganization)->handle('901234567-7', 'Veeduría Ciénaga', 'veeduria-cienaga');
    $tenant = Tenant::query()->where('nit', '900123456-8')->sole();

    asSuperAdmin('PUT', "/admin/organizations/{$tenant->id}/nit", ['nit' => '901234567-7'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['nit' => 'Ya existe una organización registrada con el NIT ingresado.']);

    asSuperAdmin('PUT', "/admin/organizations/{$tenant->id}/nit", ['nit' => '901234567-2'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['nit' => 'El NIT ingresado no es válido o el dígito de verificación no coincide con el algoritmo de la DIAN.']);

    expect($tenant->refresh()->nit)->toBe('900123456-8');
});

it('only lets the Super Administrador use the panel data', function () {
    (new RegisterOrganization)->handle('900123456-8', 'Veeduría Ciudadana Santa Marta', 'veeduria-smr');
    $tenant = Tenant::query()->sole();

    $this->getJson('http://govtrace.localhost/admin/organizations/data')->assertUnauthorized();
    $this->postJson('http://govtrace.localhost/admin/organizations', [])->assertUnauthorized();
    $this->getJson("http://govtrace.localhost/admin/organizations/{$tenant->id}")->assertUnauthorized();
    $this->putJson("http://govtrace.localhost/admin/organizations/{$tenant->id}/nit", [])->assertUnauthorized();
});
