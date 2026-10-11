<?php

use App\Domain\Audit\AuditLog;
use App\Infrastructure\Tenancy\Tenant;
use App\Models\User as SuperAdmin;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;

/*
 * Iteración 51 — el subdominio sale del nombre de la veeduría (enmienda de
 * features/US-001.feature). El Super Administrador ya no lo inventa: se propone
 * con Str::slug() del nombre, y lo puede ajustar antes de registrar (decisión del
 * usuario, 2026-10-10). Sin uno escrito, el servidor lo calcula del nombre; con uno
 * escrito, se respeta y se valida como siempre.
 *
 * Sin RefreshDatabase — registrar la organización ejecuta CREATE DATABASE.
 */

const NIT_A = '900123456-8';
const NIT_B = '890000062-6';
const NIT_C = '901234567-7';

beforeEach(function () {
    $this->artisan('migrate');
    $this->superAdmin = SuperAdmin::factory()->create(['name' => 'Equipo GovTrace']);
});

afterEach(function () {
    if (tenant()) {
        tenancy()->end();
    }

    Tenant::query()->get()->each->delete();
    DB::table('audit_logs')->delete();
    $this->superAdmin->delete();
});

/** @param  array<string, mixed>  $data */
function registerNamed(array $data): TestResponse
{
    return test()->actingAs(test()->superAdmin, 'web')->postJson('http://govtrace.localhost/admin/organizations', $data);
}

function subdomainsOfAll(): array
{
    return Tenant::query()->with('domains')->orderBy('id')->get()->map(fn (Tenant $tenant) => $tenant->domains->first()->domain)->all();
}

function suggestionFor(string $name): TestResponse
{
    return test()->actingAs(test()->superAdmin, 'web')->getJson('http://govtrace.localhost/admin/organizations/subdomain?'.http_build_query(['name' => $name]));
}

it('El subdominio sale del nombre de la veeduría: Veeduría Ciudadana Santa Marta queda veeduria-ciudadana-santa-marta', function () {
    registerNamed(['name' => 'Veeduría Ciudadana Santa Marta', 'nit' => NIT_A])->assertCreated();

    expect(subdomainsOfAll())->toBe(['veeduria-ciudadana-santa-marta.govtrace.localhost']);
});

it('Quita tildes, la eñe y los símbolos, y junta los espacios: "Veeduría  del Parque — Los Trupillos (Ciénaga)"', function () {
    registerNamed(['name' => 'Veeduría  del Parque — Los Trupillos (Ciénaga)', 'nit' => NIT_A])->assertCreated();

    expect(subdomainsOfAll())->toBe(['veeduria-del-parque-los-trupillos-cienaga.govtrace.localhost']);
});

it('Si el Super Administrador escribe un subdominio, se respeta: and a taken one is refused, never given a number', function () {
    registerNamed(['name' => 'Veeduría Ciudadana Santa Marta', 'nit' => NIT_A, 'subdomain' => 'smr'])->assertCreated();
    expect(subdomainsOfAll())->toBe(['smr.govtrace.localhost']);

    registerNamed(['name' => 'Otra veeduría distinta', 'nit' => NIT_B, 'subdomain' => 'smr'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('subdomain');
    expect(subdomainsOfAll())->toBe(['smr.govtrace.localhost']);
});

it('Dos veedurías con el mismo nombre: la segunda toma -2, y la tercera -3', function () {
    foreach ([NIT_A, NIT_B, NIT_C] as $nit) {
        registerNamed(['name' => 'Veeduría Ciudadana de Ciénaga', 'nit' => $nit])->assertCreated();
    }

    // Los ids de las organizaciones no son secuenciales: se comparan como un conjunto.
    expect(subdomainsOfAll())->toEqualCanonicalizing([
        'veeduria-ciudadana-de-cienaga.govtrace.localhost',
        'veeduria-ciudadana-de-cienaga-2.govtrace.localhost',
        'veeduria-ciudadana-de-cienaga-3.govtrace.localhost',
    ]);
});

it('Un nombre largo se corta a 63 caracteres, el límite del DNS, sin guion al final', function () {
    $name = 'Veeduría ciudadana para el seguimiento de las obras públicas del distrito turístico de Santa Marta';
    registerNamed(['name' => $name, 'nit' => NIT_A])->assertCreated();

    $label = explode('.', subdomainsOfAll()[0])[0];
    expect(strlen($label))->toBeLessThanOrEqual(63)
        ->and($label)->toStartWith('veeduria-ciudadana-para-el-seguimiento')
        ->and($label)->not->toEndWith('-');
});

it('Un nombre cuyo subdominio sería una palabra reservada o muy corto se rechaza, con el mensaje de siempre', function (string $name, string $message) {
    registerNamed(['name' => $name, 'nit' => NIT_A])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['subdomain' => $message]);
    expect(Tenant::query()->count())->toBe(0);
})->with([
    'reservada' => ['API', 'El subdominio utiliza una palabra reservada del sistema y no puede ser utilizado.'],
    'sin letras' => ['¿¿¿ ???', 'El subdominio solo puede contener letras minúsculas, números y guiones, sin espacios ni caracteres especiales.'],
]);

it('propone el subdominio de un nombre para el formulario: with its address, and the next free one when it is taken', function () {
    expect(suggestionFor('Veeduría Ciudadana Santa Marta')->assertOk()->json())->toBe([
        'subdomain' => 'veeduria-ciudadana-santa-marta',
        'address' => 'veeduria-ciudadana-santa-marta.govtrace.localhost',
    ]);

    registerNamed(['name' => 'Veeduría Ciudadana Santa Marta', 'nit' => NIT_A])->assertCreated();

    expect(suggestionFor('Veeduría Ciudadana Santa Marta')->json('subdomain'))->toBe('veeduria-ciudadana-santa-marta-2');
});

it('no propone nada si el nombre no da un subdominio válido: an empty answer, so the field stays free to write', function () {
    expect(suggestionFor('¿¿¿')->assertOk()->json())->toBe(['subdomain' => null, 'address' => null])
        ->and(suggestionFor('API')->json('subdomain'))->toBeNull();
    test()->actingAs(test()->superAdmin, 'web')->getJson('http://govtrace.localhost/admin/organizations/subdomain')->assertUnprocessable()->assertJsonValidationErrors('name');
});

it('Solo el Super Administrador pide la propuesta', function () {
    test()->getJson('http://govtrace.localhost/admin/organizations/subdomain?name=Algo')->assertUnauthorized();
});

it('registers the audit entry with the subdomain it got, computed or written', function () {
    registerNamed(['name' => 'Veeduría Ciudadana Santa Marta', 'nit' => NIT_A])->assertCreated();

    expect(AuditLog::query()->where('action', 'organization.registered')->sole()->after['subdomain'])->toBe('veeduria-ciudadana-santa-marta.govtrace.localhost');
});
