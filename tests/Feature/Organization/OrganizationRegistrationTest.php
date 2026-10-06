<?php

use App\Application\Dossier\DossierDocuments;
use App\Application\Dossier\WorksiteDossier;
use App\Application\Organization\RegisterOrganization;
use App\Application\Organization\UpdateOrganizationLegalData;
use App\Domain\Audit\AuditLog;
use App\Domain\Organization\Exceptions\OrganizationValidationException;
use App\Domain\Worksites\Worksite;
use App\Infrastructure\Tenancy\Domain;
use App\Infrastructure\Tenancy\Tenant;
use App\Models\User as SuperAdmin;
use Illuminate\Database\QueryException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;

/*
 * Iteración 44d — Una veeduría sin NIT (enmiendas a features/US-001.feature
 * y features/US-011.feature; docs/proceso-actual.md A5). Una veeduría que
 * forman unos ciudadanos se inscribe en la personería o en la cámara de
 * comercio (Ley 850 de 2003, art. 3), y puede no tener NIT. Una organización
 * se identifica con su NIT, con su inscripción — el número de la resolución
 * o el acta y la entidad que la registró — o con los dos (R-LEG-06).
 *
 * Sin RefreshDatabase — registrar la organización ejecuta CREATE DATABASE.
 */

const IDENTIFICATION_REQUIRED = 'Ingrese el NIT de la organización, o el número de la resolución o el acta de su inscripción y la entidad que la registró.';
const INCOMPLETE_REGISTRATION = 'Para identificar la inscripción hacen falta los dos datos: el número de la resolución o el acta, y la entidad que la registró.';
const INVALID_AUTHORITY = 'La entidad de registro debe ser una personería o una cámara de comercio. Por ejemplo: Personería de Santa Marta.';
const DUPLICATE_REGISTRATION = 'Ya existe una organización registrada con esa inscripción.';

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
function registerAsSuperAdmin(array $data): TestResponse
{
    return test()->actingAs(test()->superAdmin, 'web')->postJson('http://govtrace.localhost/admin/organizations', $data);
}

it('Alta de una veeduría sin NIT, con su inscripción: it is registered, its subdomain answers, and the log keeps its registration', function () {
    registerAsSuperAdmin([
        'name' => 'Veeduría del Parque Los Trupillos',
        'registration_number' => 'Resolución 012 de 2026',
        'registration_authority' => 'Personería de Santa Marta',
        'subdomain' => 'trupillos',
    ])->assertCreated();

    // Solo la que este test registró: la base de desarrollo puede tener otras (la demo, las de e2e).
    $tenant = Tenant::query()->whereHas('domains', fn ($domains) => $domains->where('domain', 'trupillos.govtrace.localhost'))->sole();
    expect($tenant->nit)->toBeNull()
        ->and($tenant->registration_number)->toBe('Resolución 012 de 2026')
        ->and($tenant->registration_authority)->toBe('Personería de Santa Marta')
        ->and(Domain::query()->where('domain', 'trupillos.govtrace.localhost')->exists())->toBeTrue()
        ->and(Arr::except(AuditLog::query()->where('action', 'organization.registered')->where('after->name', 'Veeduría del Parque Los Trupillos')->sole()->after, 'rues'))->toBe([
            'nit' => null,
            'name' => 'Veeduría del Parque Los Trupillos',
            'subdomain' => 'trupillos.govtrace.localhost',
            'registration' => 'Resolución 012 de 2026 · Personería de Santa Marta',
        ])
        // it. 46b: inscrita en una personería, que los datos abiertos del RUES no traen
        ->and(AuditLog::query()->where('action', 'organization.registered')->where('after->name', 'Veeduría del Parque Los Trupillos')->sole()->after['rues']['status'])->toBe('personeria');
});

it('Una organización necesita su NIT o su inscripción: without both, nothing is registered', function () {
    registerAsSuperAdmin(['name' => 'Veeduría del Parque Los Trupillos', 'subdomain' => 'trupillos'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['nit' => IDENTIFICATION_REQUIRED]);

    expect(Tenant::query()->exists())->toBeFalse();
});

it('La inscripción necesita el número y la entidad: one without the other is not enough', function (array $registration, string $field) {
    registerAsSuperAdmin(['name' => 'Veeduría del Parque Los Trupillos', 'subdomain' => 'trupillos', ...$registration])
        ->assertUnprocessable()
        ->assertJsonValidationErrors([$field => INCOMPLETE_REGISTRATION]);
})->with([
    'sin la entidad' => [['registration_number' => 'Resolución 012 de 2026'], 'registration_number'],
    'sin el número' => [['registration_authority' => 'Personería de Santa Marta'], 'registration_number'],
]);

it('La entidad de registro es una personería o una cámara de comercio: another authority is refused', function (string $authority, bool $valid) {
    $response = registerAsSuperAdmin(['name' => 'Veeduría del Parque Los Trupillos', 'subdomain' => 'trupillos', 'registration_number' => 'Acta 45 de 2025', 'registration_authority' => $authority]);

    $valid ? $response->assertCreated() : $response->assertUnprocessable()->assertJsonValidationErrors(['registration_authority' => INVALID_AUTHORITY]);
})->with([
    'personería' => ['Personería Distrital de Santa Marta', true],
    'sin tilde' => ['Personeria de Cienaga', true],
    'cámara de comercio' => ['Cámara de Comercio de Santa Marta para el Magdalena', true],
    'notaría' => ['Notaría Tercera de Santa Marta', false],
    'solo el municipio' => ['Santa Marta', false],
]);

it('No se puede registrar una inscripción que ya tiene otra organización: the same one, however it is written', function () {
    (new RegisterOrganization)->handle(null, 'Veeduría del Parque Los Trupillos', 'trupillos', 'Resolución 012 de 2026', 'Personería de Santa Marta');

    registerAsSuperAdmin(['name' => 'Otra veeduría', 'subdomain' => 'otra', 'registration_number' => 'resolución  012 de 2026', 'registration_authority' => 'personeria de santa marta'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['registration_number' => DUPLICATE_REGISTRATION]);

    // La misma resolución, de otra personería, es otra inscripción.
    registerAsSuperAdmin(['name' => 'Veeduría de Ciénaga', 'subdomain' => 'cienaga', 'registration_number' => 'Resolución 012 de 2026', 'registration_authority' => 'Personería de Ciénaga'])->assertCreated();
});

it('Una organización puede tener su NIT y su inscripción: both are kept, and the panel shows them', function () {
    registerAsSuperAdmin([
        'name' => 'Veeduría Ciudadana Santa Marta',
        'nit' => '900123456-8',
        'registration_number' => 'Acta 45 de 2025',
        'registration_authority' => 'Cámara de Comercio de Santa Marta',
        'subdomain' => 'veeduria-smr',
    ])->assertCreated();
    $tenant = Tenant::query()->whereHas('domains', fn ($domains) => $domains->where('domain', 'veeduria-smr.govtrace.localhost'))->sole();

    expect($this->actingAs($this->superAdmin, 'web')->getJson("http://govtrace.localhost/admin/organizations/{$tenant->id}")->json('data'))->toMatchArray([
        'nit' => '900123456-8',
        'registration_number' => 'Acta 45 de 2025',
        'registration_authority' => 'Cámara de Comercio de Santa Marta',
    ]);
    $listed = collect($this->actingAs($this->superAdmin, 'web')->getJson('http://govtrace.localhost/admin/organizations/data')->json('data'))->firstWhere('id', $tenant->id);
    expect($listed['identification'])->toBe('NIT 900123456-8 · Acta 45 de 2025, Cámara de Comercio de Santa Marta');
});

it('Actualización de la inscripción con registro de auditoría: a NIT-only organization gets its registration', function () {
    $tenant = (new RegisterOrganization)->handle('900123456-8', 'Veeduría Ciudadana Santa Marta', 'veeduria-smr');

    $this->actingAs($this->superAdmin, 'web')->putJson("http://govtrace.localhost/admin/organizations/{$tenant->id}/nit", [
        'nit' => '900123456-8',
        'registration_number' => 'Acta 45 de 2025',
        'registration_authority' => 'Cámara de Comercio de Santa Marta',
    ])->assertOk()->assertJson(['message' => 'Los datos legales han sido actualizados.']);

    $entry = AuditLog::query()->where('action', 'organization.legal_data_updated')->sole();
    expect($tenant->refresh()->registration_number)->toBe('Acta 45 de 2025')
        ->and($entry->before)->toBe(['nit' => '900123456-8'])
        ->and($entry->after)->toBe(['nit' => '900123456-8', 'registration' => 'Acta 45 de 2025 · Cámara de Comercio de Santa Marta']);
});

it('No se puede dejar una organización sin NIT ni inscripción', function () {
    $tenant = (new RegisterOrganization)->handle(null, 'Veeduría del Parque Los Trupillos', 'trupillos', 'Resolución 012 de 2026', 'Personería de Santa Marta');

    expect(fn () => (new UpdateOrganizationLegalData)->handle($tenant, null, null, null))
        ->toThrow(OrganizationValidationException::class, IDENTIFICATION_REQUIRED);
    expect($tenant->refresh()->registration_number)->toBe('Resolución 012 de 2026');
});

it('keeps an organization from being left without NIT nor registration in the database itself', function () {
    $tenant = (new RegisterOrganization)->handle('900123456-8', 'Veeduría Ciudadana Santa Marta', 'veeduria-smr');

    expect(fn () => DB::table('tenants')->where('id', $tenant->id)->update(['nit' => null]))->toThrow(QueryException::class);
});

it('Las plantillas identifican a la veeduría: by its registration when it has no NIT', function () {
    $tenant = (new RegisterOrganization)->handle(null, 'Veeduría del Parque Los Trupillos', 'trupillos', 'Resolución 012 de 2026', 'Personería de Santa Marta');
    reportableContract('CO1.PCCNTR.1234567');
    $worksite = worksiteWithContracts($tenant, ['CO1.PCCNTR.1234567'], santaMartaWorksiteLocation());

    $html = $tenant->run(fn () => (new DossierDocuments)->html('peticion', (new WorksiteDossier)->of(Worksite::query()->findOrFail($worksite->id))));
    DB::table('contracts')->delete();

    expect(html_entity_decode(strip_tags($html)))->toContain('«Veeduría del Parque Los Trupillos» (inscrita ante Personería de Santa Marta con Resolución 012 de 2026)');
});
