<?php

use App\Application\Organization\ConfigureTerritory;
use App\Application\Organization\RegisterOrganization;
use App\Domain\Organization\Roles;
use App\Domain\Organization\SuperAdminAuthorization;
use App\Infrastructure\Tenancy\Tenant;
use App\Models\User as SuperAdmin;
use Database\Seeders\DivipolaSeeder;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * Iteración 43g — V7 de docs/mapa-funcional.md: la pantalla para que el Super
 * Administrador reporte en nombre de una organización que lo autorizó
 * (US-042-SEC, R-SA-02). La regla y el envío ya existían (it. 25); faltaban
 * la pantalla, saber desde el panel quién lo autorizó y hasta cuándo, y
 * buscar la obra en el territorio de esa organización.
 *
 * Sin RefreshDatabase — registrar la organización ejecuta CREATE DATABASE.
 */

const ON_BEHALF_REFUSED = 'No cuenta con una autorización activa de la organización para realizar esta acción.';
const CENTRAL_PANEL = 'http://govtrace.localhost';

beforeEach(function () {
    $this->artisan('migrate');
    (new DivipolaSeeder)->run();
    $this->superAdmin = SuperAdmin::factory()->create();
    $this->tenant = (new RegisterOrganization)->handle('900123456-8', 'Veeduría Ciudadana Santa Marta', 'veeduria-smr');
    (new ConfigureTerritory)->handle($this->tenant, ['47']);
    $this->administrator = reportingMember($this->tenant, 'ana.perez@veeduria-smr.org', Roles::Administrator);
    reportableContract('CO1.PCCNTR.1234567', ['object' => 'Pavimentación de la Calle 30']);
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

function authorizedBy(Tenant $tenant, $administrator): string
{
    return $tenant->run(fn () => SuperAdminAuthorization::grant($administrator)->expires_at->toIso8601String());
}

it('tells the Super Administrador, in the list of organizations, which one authorized it and until when', function () {
    $until = authorizedBy($this->tenant, $this->administrator);
    (new RegisterOrganization)->handle('890000062-6', 'Veeduría Ciénaga', 'veeduria-cienaga');

    $rows = collect($this->actingAs($this->superAdmin, 'web')->getJson(CENTRAL_PANEL.'/admin/organizations/data')->assertOk()->json('data'))->keyBy('name');

    expect($rows['Veeduría Ciudadana Santa Marta']['authorized_until'])->toBe($until)
        ->and($rows['Veeduría Ciénaga']['authorized_until'])->toBeNull();
});

it('opens the screen to report on behalf of an organization that authorized it, with until when', function () {
    $until = authorizedBy($this->tenant, $this->administrator);

    $this->actingAs($this->superAdmin, 'web')->get(CENTRAL_PANEL."/admin/organizations/{$this->tenant->id}/report")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('SuperAdmin/ReportOnBehalf')
            ->where('organization', ['id' => $this->tenant->id, 'name' => 'Veeduría Ciudadana Santa Marta'])
            ->where('authorizedUntil', $until));
});

it('opens it without an authorization in force too, and says the message of US-042-SEC', function () {
    $this->actingAs($this->superAdmin, 'web')->get(CENTRAL_PANEL."/admin/organizations/{$this->tenant->id}/report")
        ->assertInertia(fn (Assert $page) => $page->where('authorizedUntil', null)->where('refusal', ON_BEHALF_REFUSED));
});

it('searches the worksites of the territory of that organization, only with its authorization in force', function () {
    $search = fn () => $this->actingAs($this->superAdmin, 'web')->getJson(CENTRAL_PANEL."/admin/organizations/{$this->tenant->id}/contracts/search?q=Calle 30");

    $search()->assertForbidden()->assertJson(['message' => ON_BEHALF_REFUSED]);

    authorizedBy($this->tenant, $this->administrator);
    expect($search()->assertOk()->json('data'))->toHaveCount(1)
        ->and($search()->json('data.0'))->toMatchArray(['secop_contract_id' => 'CO1.PCCNTR.1234567', 'object' => 'Pavimentación de la Calle 30']);
});

it('serves the screen and the search only to the Super Administrador', function () {
    $this->get(CENTRAL_PANEL."/admin/organizations/{$this->tenant->id}/report")->assertRedirect(CENTRAL_PANEL.'/login');
    $this->getJson(CENTRAL_PANEL."/admin/organizations/{$this->tenant->id}/contracts/search?q=Calle")->assertUnauthorized();
});
