<?php

use App\Application\Organization\ConfigureTerritory;
use App\Application\Organization\RegisterOrganization;
use App\Application\Sealing\SealingNetwork;
use App\Domain\Audit\AuditLog;
use App\Domain\Organization\Roles;
use App\Domain\Organization\SuperAdminAuthorization;
use App\Domain\Organization\User as OrganizationUser;
use App\Domain\Reports\Report;
use App\Infrastructure\Tenancy\Tenant;
use App\Models\User as SuperAdmin;
use Database\Seeders\DivipolaSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\Support\FakeSealingNetwork;

/*
 * Iteración 25 — Autorización explícita al Super Administrador para
 * reportar en nombre de una organización (specs/PLAN.md). Traduce
 * features/US-042-SEC.feature (5 casos), R-SA-02:
 * - el Administrador la otorga o la revoca en POST/DELETE
 *   /authorizations/super-admin de su organización: una por vez, 30 días;
 * - el Super Administrador reporta desde el panel global, en
 *   POST /admin/organizations/{id}/reports, con el mismo reporte que manda
 *   la PWA y sus mismas reglas.
 *
 * Sin RefreshDatabase — registrar la organización ejecuta CREATE DATABASE.
 */

const NO_AUTHORIZATION = 'No cuenta con una autorización activa de la organización para realizar esta acción.';

beforeEach(function () {
    $this->artisan('migrate');
    (new DivipolaSeeder)->run();
    Storage::fake('evidencias');
    Notification::fake();
    app()->instance(SealingNetwork::class, new FakeSealingNetwork);

    $this->superAdmin = SuperAdmin::factory()->create();
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
    $this->superAdmin->delete();
});

function authorizeSuperAdmin(OrganizationUser $member): TestResponse
{
    test()->flushSession();

    return test()->actingAs($member, 'tenant')->postJson('http://veeduria-smr.govtrace.localhost/authorizations/super-admin');
}

function revokeSuperAdmin(OrganizationUser $member): TestResponse
{
    test()->flushSession();

    return test()->actingAs($member, 'tenant')->deleteJson('http://veeduria-smr.govtrace.localhost/authorizations/super-admin');
}

/** @param  array<string, mixed>  $overrides */
function superAdminReport(array $overrides = []): TestResponse
{
    test()->flushSession();
    tenancy()->end();

    return test()->actingAs(test()->superAdmin, 'web')
        ->postJson('http://govtrace.localhost/admin/organizations/'.test()->tenant->id.'/reports', reportPayload($overrides));
}

function reportsOfTheOrganization(): int
{
    return test()->tenant->run(fn () => Report::query()->count());
}

it('Con autorización vigente el Super Administrador puede crear un reporte: accepted, and both in the audit log', function () {
    $this->travelTo(now()->subDays(10));
    authorizeSuperAdmin($this->administrator)->assertCreated();
    $this->travelBack();

    $reportId = superAdminReport()->assertCreated()->json('id'); // it. 46c: su identificador público

    $author = $this->tenant->run(fn () => Report::byPublicId($reportId)->user);
    $entries = AuditLog::query()->where('organization_id', $this->tenant->id)->orderBy('id')->get();
    $authorized = $entries->firstWhere('action', 'super_admin.authorized');
    $reported = $entries->firstWhere('action', 'report.created_by_super_admin');

    expect($author->name)->toBe('Super Administrador de GovTrace')
        ->and($authorized->only(['actor_type', 'actor_id']))->toBe(['actor_type' => 'organization_admin', 'actor_id' => (string) $this->administrator->id])
        ->and($reported->only(['actor_type', 'actor_id']))->toBe(['actor_type' => 'super_admin', 'actor_id' => (string) $this->superAdmin->id])
        ->and($reported->after)->toMatchArray(['report_id' => $this->tenant->run(fn () => Report::idOf($reportId)), 'secop_contract_id' => 'CO1.PCCNTR.1234567']);
});

it('Sin autorización vigente el Super Administrador no puede reportar', function (Closure $situation) {
    $situation();

    superAdminReport()->assertForbidden()->assertJson(['message' => NO_AUTHORIZATION]);

    expect(reportsOfTheOrganization())->toBe(0);
})->with([
    'nunca fue otorgada' => [fn () => null],
    'se otorgó hace 31 días' => [function () {
        test()->travelTo(now()->subDays(31));
        authorizeSuperAdmin(test()->administrator)->assertCreated();
        test()->travelBack();
    }],
    'fue revocada por el Administrador' => [function () {
        authorizeSuperAdmin(test()->administrator)->assertCreated();
        revokeSuperAdmin(test()->administrator)->assertOk();
    }],
]);

it('Solo puede existir una autorización vigente a la vez: a second one is refused', function () {
    $this->travelTo(now()->subDays(5));
    authorizeSuperAdmin($this->administrator)->assertCreated();
    $this->travelBack();
    $until = now()->addDays(25)->timezone('America/Bogota')->format('d/m/Y');

    authorizeSuperAdmin($this->administrator)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['authorization' => "Ya hay una autorización vigente, hasta el {$until}. Revóquela antes de otorgar otra."]);

    expect($this->tenant->run(fn () => SuperAdminAuthorization::query()->count()))->toBe(1);
});

// Reglas derivadas ------------------------------------------------------

it('lets only the Administrador grant or revoke it', function () {
    authorizeSuperAdmin($this->veedor)->assertForbidden();
    revokeSuperAdmin($this->veedor)->assertForbidden();
});

it('logs the revocation, and a new authorization can follow it', function () {
    authorizeSuperAdmin($this->administrator)->assertCreated();
    revokeSuperAdmin($this->administrator)->assertOk()->assertJson(['message' => 'Autorización revocada. El Super Administrador ya no puede crear reportes en nombre de la organización.']);

    $revoked = AuditLog::query()->where('organization_id', $this->tenant->id)->where('action', 'super_admin.authorization_revoked')->sole();
    expect($revoked->actor_id)->toBe((string) $this->administrator->id);

    authorizeSuperAdmin($this->administrator)->assertCreated();
    revokeSuperAdmin($this->administrator)->assertOk();
    revokeSuperAdmin($this->administrator)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['authorization' => 'No hay una autorización vigente que revocar.']);
});

it('shows the Administrador the authorization in force, for the panel', function () {
    // Con el reloj quieto: la vigencia se calcula al autorizar y aquí se vuelve a calcular.
    $this->freezeSecond();
    test()->flushSession();
    $status = fn () => $this->actingAs($this->administrator, 'tenant')->getJson('http://veeduria-smr.govtrace.localhost/authorizations/super-admin')->assertOk()->json('data');

    expect($status())->toBe(['active' => false, 'granted_at' => null, 'expires_at' => null, 'granted_by' => null]);

    authorizeSuperAdmin($this->administrator)->assertCreated();

    expect($status())->toMatchArray(['active' => true, 'granted_by' => 'Miembro de prueba'])
        ->and($status()['expires_at'])->toBe(now()->addDays(30)->utc()->startOfSecond()->toIso8601String());
});

it('keeps the rules of any report: a contract outside the territory is refused all the same', function () {
    authorizeSuperAdmin($this->administrator)->assertCreated();
    reportableContract('CO1.PCCNTR.5555555', ['department_code' => '05', 'municipality_code' => '05001']);

    superAdminReport(['secop_contract_id' => 'CO1.PCCNTR.5555555'])->assertUnprocessable()->assertJsonValidationErrors(['secop_contract_id']);
});

it('reports through a member of the organization that nobody can log in as, and that is no veedor', function () {
    authorizeSuperAdmin($this->administrator)->assertCreated();
    superAdminReport()->assertCreated();
    superAdminReport()->assertCreated();

    $delegates = $this->tenant->run(fn () => OrganizationUser::query()->where('name', 'Super Administrador de GovTrace')->get());
    expect($delegates)->toHaveCount(1)
        ->and($delegates->first()->password)->toBeNull();

    test()->flushSession();
    $this->postJson('http://veeduria-smr.govtrace.localhost/login', ['email' => $delegates->first()->email, 'password' => ''])->assertUnprocessable();
    $this->postJson('http://veeduria-smr.govtrace.localhost/forgot-password', ['email' => $delegates->first()->email]);
    Notification::assertNothingSentTo($delegates->first());
    $this->actingAs($this->administrator, 'tenant')->getJson('http://veeduria-smr.govtrace.localhost/observers')
        ->assertOk()
        ->assertJsonMissing(['email' => $delegates->first()->email]);
});
