<?php

use App\Application\Organization\ConfigureTerritory;
use App\Application\Organization\InviteObserver;
use App\Application\Organization\RegisterOrganization;
use App\Application\Sealing\SealingNetwork;
use App\Domain\Audit\AuditLog;
use App\Domain\Geography\PlaceName;
use App\Domain\Organization\OrganizationTerritory;
use App\Domain\Organization\Roles;
use App\Domain\Organization\User as OrganizationUser;
use App\Domain\Reports\Evidence;
use App\Domain\Reports\Report;
use App\Infrastructure\Tenancy\Tenant;
use Database\Seeders\DivipolaSeeder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\FakeSealingNetwork;

/*
 * Iteración 18 — Panel del Administrador de Organización (specs/PLAN.md).
 * Las reglas de US-005, US-012, US-015, US-035, US-036 y US-037 ya se
 * prueban en sus Actions y endpoints (it. 5, 6, 8, 11, 15); aquí, lo que las
 * pantallas del panel necesitan del servidor: las páginas y el JSON de cada
 * una. Las pantallas se prueban con Vitest.
 *
 * Sin RefreshDatabase — registrar la organización ejecuta CREATE DATABASE.
 */

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
});

afterEach(function () {
    if (tenant()) {
        tenancy()->end();
    }

    Tenant::query()->get()->each->delete();
    DB::table('contracts')->delete();
    DB::table('audit_logs')->delete();
});

function asAdministrator(string $method, string $path, array $data = []): TestResponse
{
    return test()->actingAs(test()->administrator, 'tenant')->json($method, "http://veeduria-smr.govtrace.localhost{$path}", $data);
}

// Las pantallas -----------------------------------------------------------

it('serves each screen of the panel to the Administrador, and none to the veedor', function (string $path, string $component) {
    $this->withoutVite()->actingAs($this->administrator, 'tenant')
        ->get("http://veeduria-smr.govtrace.localhost{$path}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component($component)->where('organization', 'Veeduría Ciudadana Santa Marta'));

    $this->withoutVite()->actingAs($this->veedor, 'tenant')
        ->get("http://veeduria-smr.govtrace.localhost{$path}")
        ->assertForbidden();
})->with([
    'bandeja de entrada' => ['/admin/inbox', 'Admin/Inbox'],
    'veedores' => ['/admin/observers', 'Admin/Observers'],
    'territorio' => ['/admin/territory', 'Admin/Territory'],
    'contratos' => ['/admin/contracts', 'Admin/Contracts'],
    'obras' => ['/admin/worksites', 'Admin/Worksites'],
    // It. 29: US-049-RPT y US-042-SEC.
    'resumen del territorio' => ['/admin/summary', 'Admin/Summary'],
    'autorización al Super Administrador' => ['/admin/authorization', 'Admin/SuperAdminAuthorization'],
]);

it('opens the panel of the Administrador on the inbox', function () {
    $this->actingAs($this->administrator, 'tenant')
        ->get('http://veeduria-smr.govtrace.localhost/organization/dashboard')
        ->assertRedirect('http://veeduria-smr.govtrace.localhost/admin/inbox');
});

// Bandeja de entrada (US-036, US-037) --------------------------------------

it('lists the published evidences, the ones that can be withdrawn', function () {
    reportableContract('CO1.PCCNTR.1234567');
    worksiteWithContracts($this->tenant, ['CO1.PCCNTR.1234567'], santaMartaWorksiteLocation());
    $published = sealedReport($this->tenant, $this->veedor);
    $hidden = sealedReport($this->tenant, $this->veedor);
    editorialDecision($this->administrator, 'publish', $published)->assertOk();

    $rows = asAdministrator('GET', '/inbox?status=published')->assertOk()->json('data');

    expect(array_column($rows, 'id'))->toBe([publicIdOf(Report::class, $published)])
        ->and($rows[0]['actions'])->toBe(['withdraw'])
        ->and(array_column(asAdministrator('GET', '/inbox')->json('data'), 'id'))->toBe([publicIdOf(Report::class, $hidden)]);
});

it('lets the Administrador see the file of an evidence to review it, and nobody else', function () {
    reportableContract('CO1.PCCNTR.1234567');
    worksiteWithContracts($this->tenant, ['CO1.PCCNTR.1234567'], santaMartaWorksiteLocation());
    $reportId = sealedReport($this->tenant, $this->veedor);
    $evidence = $this->tenant->run(fn () => Evidence::query()->where('report_id', $reportId)->sole());

    $response = $this->actingAs($this->administrator, 'tenant')->get("http://veeduria-smr.govtrace.localhost/evidences/{$evidence->public_id}/file");

    $response->assertOk()->assertHeader('Content-Type', 'image/jpeg');
    expect(hash('sha256', $response->streamedContent()))->toBe($evidence->sha256);

    $this->actingAs($this->veedor, 'tenant')->get("http://veeduria-smr.govtrace.localhost/evidences/{$evidence->public_id}/file")->assertForbidden();
});

// Veedores (US-005) -------------------------------------------------------

it('lists the veedores of the organization with the status of their invitation', function () {
    $this->tenant->run(function () {
        (new InviteObserver)->handle('laura@correo.co');
        (new InviteObserver)->handle('pedro@correo.co');
        OrganizationUser::query()->where('email', 'pedro@correo.co')->update(['invitation_expires_at' => now()->subHour()]);
    });

    // Cada fila trae además su id, para desactivar o reactivar (it. 20).
    expect(collect(asAdministrator('GET', '/observers')->assertOk()->json('data'))->map(fn (array $row) => Arr::only($row, ['email', 'status']))->all())->toBe([
        ['email' => 'carlos@correo.co', 'status' => 'Activo'],
        ['email' => 'laura@correo.co', 'status' => 'Invitación pendiente'],
        ['email' => 'pedro@correo.co', 'status' => 'Invitación vencida'],
    ]);
});

it('invites a veedor from the screen, or says why not', function () {
    asAdministrator('POST', '/observers/invite', ['email' => 'laura@correo.co'])
        ->assertCreated()
        ->assertJson(['message' => 'Invitación enviada a laura@correo.co. El enlace vence en 48 horas.']);

    asAdministrator('POST', '/observers/invite', ['email' => 'carlos@correo.co'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email' => 'Ya existe un usuario registrado o una invitación pendiente con este correo electrónico en la organización.']);
});

// Territorio (US-012) -----------------------------------------------------

it('shows the current territory and finds departments and municipalities by name', function () {
    expect(asAdministrator('GET', '/territory')->assertOk()->json('data'))->toBe([
        ['code' => '47', 'name' => 'Magdalena', 'kind' => 'Departamento'],
    ]);

    $found = asAdministrator('GET', '/territory/search?q=Magd')->assertOk()->json('data');
    expect($found[0])->toBe(['code' => '47', 'name' => 'Magdalena', 'kind' => 'Departamento']);

    $medellin = collect(asAdministrator('GET', '/territory/search?q=Medell')->json('data'))->firstWhere('code', '05001');
    expect($medellin)->toBe(['code' => '05001', 'name' => 'Medellín (Antioquia)', 'kind' => 'Municipio']);

    expect(asAdministrator('GET', '/territory/search?q=Ma')->json('data'))->toBe([]);
});

it('writes the DIVIPOLA names as a person would', function (string $official, string $display) {
    expect(PlaceName::forDisplay($official))->toBe($display);
})->with([
    ['MAGDALENA', 'Magdalena'],
    ['CIÉNAGA', 'Ciénaga'],
    ['BOGOTÁ, D.C.', 'Bogotá, D.C.'],
    ['EL CARMEN DE BOLÍVAR', 'El Carmen de Bolívar'],
    ['ARCHIPIÉLAGO DE SAN ANDRÉS, PROVIDENCIA Y SANTA CATALINA', 'Archipiélago de San Andrés, Providencia y Santa Catalina'],
]);

it('saves the territory, recording who changed it', function () {
    asAdministrator('PUT', '/territory', ['codes' => ['47', '05001']])
        ->assertOk()
        ->assertJson(['message' => 'Territorio guardado. Los contratos de SECOP II se están actualizando.']);

    $territory = OrganizationTerritory::query()->where('tenant_id', $this->tenant->id)->get();
    $entry = AuditLog::query()->where('action', 'organization.territory_configured')->latest('id')->first();

    expect($territory->pluck('department_code')->filter()->values()->all())->toBe(['47'])
        ->and($territory->pluck('municipality_code')->filter()->values()->all())->toBe(['05001'])
        ->and($entry->actor_type)->toBe('organization_admin')
        ->and($entry->actor_id)->toBe((string) $this->administrator->id);
});

it('does not save an empty territory or an unknown code', function (array $codes, string $message) {
    asAdministrator('PUT', '/territory', ['codes' => $codes])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['codes' => $message]);
})->with([
    'vacío' => [[], 'Debe seleccionar al menos un departamento o municipio para delimitar el territorio de vigilancia.'],
    'código inexistente' => [['99999'], 'El código geográfico no pertenece a la tabla oficial de departamentos y municipios.'],
]);

// Contratos (US-015) ------------------------------------------------------

it('pages the contracts of the territory, 20 at a time, sorted by signing date or by value', function () {
    foreach (range(1, 25) as $n) {
        reportableContract(sprintf('CO1.PCCNTR.%07d', $n), [
            'process_number' => "SMR-{$n}",
            'contractor_name' => "Contratista {$n}",
            'value' => $n * 1_000_000,
            'signed_at' => now()->subDays($n)->toDateString(),
        ]);
    }

    $first = asAdministrator('GET', '/contracts')->assertOk();
    expect($first->json('data'))->toHaveCount(20)
        ->and($first->json('data.0'))->toMatchArray(['process_number' => 'SMR-1', 'object' => 'Pavimentación Calle 30', 'contractor_name' => 'Contratista 1', 'value' => 1000000, 'status' => 'En ejecución'])
        ->and($first->json('meta'))->toBe(['current_page' => 1, 'last_page' => 2, 'total' => 25]);

    expect(asAdministrator('GET', '/contracts?page=2')->json('data'))->toHaveCount(5)
        ->and(asAdministrator('GET', '/contracts?sort=value&direction=desc')->json('data.0.process_number'))->toBe('SMR-25')
        ->and(asAdministrator('GET', '/contracts?sort=value&direction=asc')->json('data.0.process_number'))->toBe('SMR-1')
        // Solo se ordena por fecha de firma o por valor.
        ->and(asAdministrator('GET', '/contracts?sort=contractor_name&direction=sideways')->json('data.0.process_number'))->toBe('SMR-1');
});

it('Buscar un contrato del territorio: by words of its object, its contractor, its process number or its SECOP id', function () {
    reportableContract('CO1.PCCNTR.1111111', ['object' => 'Construcción del parque Los Trupillos', 'contractor_name' => 'Consorcio Parques', 'process_number' => 'SMR-LP-001']);
    reportableContract('CO1.PCCNTR.2222222', ['object' => 'Pavimentación Calle 30', 'contractor_name' => 'Vías del Caribe', 'process_number' => 'SMR-LP-002']);

    $found = fn (string $words) => collect(asAdministrator('GET', '/contracts?q='.urlencode($words))->assertOk()->json('data'))->pluck('secop_contract_id')->all();

    expect($found('trupillos'))->toBe(['CO1.PCCNTR.1111111'])
        ->and($found('Vías del'))->toBe(['CO1.PCCNTR.2222222'])
        ->and($found('LP-002'))->toBe(['CO1.PCCNTR.2222222'])
        ->and($found('2222222'))->toBe(['CO1.PCCNTR.2222222'])
        ->and($found('no existe'))->toBe([])
        ->and(asAdministrator('GET', '/contracts?q=trupillos')->json('meta.total'))->toBe(1);
});

// Obras (US-035) ----------------------------------------------------------

it('lists the worksites of the organization with their official location and their contracts', function () {
    reportableContract('CO1.PCCNTR.1111111', ['object' => 'Acueducto Gaira']);
    reportableContract('CO1.PCCNTR.3333333', ['object' => 'Acueducto Gaira, segunda etapa']);
    $gaira = worksiteWithContracts($this->tenant, ['CO1.PCCNTR.1111111', 'CO1.PCCNTR.3333333'], [11.2, -74.23]);

    expect(asAdministrator('GET', '/worksites')->assertOk()->json('data'))->toBe([[
        'id' => $gaira->public_id,
        'name' => null, // sin nombre hasta que el Administrador la agrupe (US-045-INT)
        'latitude' => 11.2,
        'longitude' => -74.23,
        'contracts' => [
            ['secop_contract_id' => 'CO1.PCCNTR.1111111', 'object' => 'Acueducto Gaira'],
            ['secop_contract_id' => 'CO1.PCCNTR.3333333', 'object' => 'Acueducto Gaira, segunda etapa'],
        ],
    ]]);
});

it('only lets the Administrador use the data of the panel', function (string $method, string $path) {
    $this->actingAs($this->veedor, 'tenant')->json($method, "http://veeduria-smr.govtrace.localhost{$path}")->assertForbidden();
})->with([
    ['GET', '/observers'],
    ['GET', '/territory'],
    ['GET', '/territory/search?q=Magd'],
    ['PUT', '/territory'],
    ['GET', '/contracts'],
    ['GET', '/worksites'],
]);
