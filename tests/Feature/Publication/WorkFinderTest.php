<?php

use App\Application\Organization\ChangeOrganizationStatus;
use App\Application\Organization\ConfigureTerritory;
use App\Application\Organization\RegisterOrganization;
use App\Application\Sealing\SealingNetwork;
use App\Domain\Contracts\ContractFollower;
use App\Domain\Organization\OrganizationStatus;
use App\Domain\Organization\Roles;
use App\Infrastructure\Tenancy\Tenant;
use Database\Seeders\DivipolaSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\Support\FakeSealingNetwork;

/*
 * Iteración 59 — GovTrace sugiere la veeduría (features/US-066.feature). En
 * el Inicio, el ciudadano busca la obra entre los contratos de SECOP, y
 * GovTrace le dice qué veedurías pueden atenderla, con reglas públicas: la
 * que ya sigue la obra, la de territorio más específico, la que publicó en
 * los últimos 90 días; en un empate, iguales y en orden alfabético. Las
 * fichas viven en la base de cada veeduría: un resumen en la base central
 * (contract_followers) dice quién sigue qué contrato.
 *
 * Sin RefreshDatabase — registrar la organización ejecuta CREATE DATABASE.
 */

const FINDER_MINCA = 'CO1.PCCNTR.7654321';

beforeEach(function () {
    $this->artisan('migrate');
    (new DivipolaSeeder)->run();
    Storage::fake('evidencias');
    Notification::fake();
    app()->instance(SealingNetwork::class, new FakeSealingNetwork);

    $this->santaMarta = finderOrganization('Veeduría Santa Marta', 'veeduria-smr', ['47001'], '900123456-8');
    $this->magdalena = finderOrganization('Veeduría del Magdalena', 'veeduria-magdalena', ['47'], '890000062-6');
    reportableContract(FINDER_MINCA, ['object' => 'Construcción de la vía a Minca', 'entity_name' => 'Alcaldía Distrital de Santa Marta']);
});

afterEach(function () {
    if (tenant()) {
        tenancy()->end();
    }

    Tenant::query()->get()->each->delete();
    DB::table('contracts')->delete();
    DB::table('audit_logs')->delete();
});

/** @param  list<string>  $territory  DIVIPOLA codes */
function finderOrganization(string $name, string $subdomain, array $territory, ?string $nit = null): Tenant
{
    $tenant = (new RegisterOrganization)->handle($nit, $name, $subdomain, $nit === null ? "Resolución {$subdomain} de 2026" : null, $nit === null ? 'Personería de Santa Marta' : null);
    (new ConfigureTerritory)->handle($tenant, $territory);

    return $tenant;
}

/** A published evidence of $tenant on that contract, through its own sheet at the Santa Marta worksite. */
function finderPublished(Tenant $tenant, string $secopContractId): int
{
    $slug = $tenant->domains()->value('domain');
    $administrator = reportingMember($tenant, "admin@{$slug}", Roles::Administrator);
    $veedor = reportingMember($tenant, "veedor@{$slug}");
    worksiteWithContracts($tenant, [$secopContractId], santaMartaWorksiteLocation());

    return publishedReport($tenant, $veedor, $administrator, ['secop_contract_id' => $secopContractId]);
}

function findWorks(array $query = []): TestResponse
{
    return test()->getJson('http://govtrace.localhost/public/obras'.($query === [] ? '' : '?'.http_build_query($query)));
}

function whoCanHelp(string $secopContractId): TestResponse
{
    return test()->getJson('http://govtrace.localhost/public/obras/quien?'.http_build_query(['contrato' => $secopContractId]));
}

/** @return list<array{name: string, reasons: list<string>, suggested: bool}> */
function suggestions(string $secopContractId = FINDER_MINCA): array
{
    return array_map(
        fn (array $organization) => ['name' => $organization['name'], 'reasons' => $organization['reasons'], 'suggested' => $organization['suggested']],
        whoCanHelp($secopContractId)->assertOk()->json('data.organizations'),
    );
}

it('El ciudadano busca una obra en el Inicio: by name without accents, by municipality, 20 at a time, only among the works a veeduría covers', function () {
    reportableContract('CO1.PCCNTR.0800001', ['object' => 'Pavimentación del barrio Rebolo', 'department_code' => '08', 'municipality_code' => '08001']);
    foreach (range(1, 21) as $number) {
        reportableContract(sprintf('CO1.PCCNTR.20%05d', $number), ['object' => "Andén del barrio {$number}", 'municipality_code' => '47189']);
    }

    expect(findWorks(['q' => 'via a minca'])->assertOk()->json('data'))->toBe([[
        'contract_id' => FINDER_MINCA,
        'name' => 'Construcción de la vía a Minca',
        'municipality' => 'Santa Marta',
        'entity_name' => 'Alcaldía Distrital de Santa Marta',
    ]]);
    expect(findWorks(['municipality' => '47001'])->json('data'))->toHaveCount(1)
        ->and(findWorks(['q' => 'rebolo'])->json('data'))->toBe([]);

    $first = findWorks(['municipality' => '47189']);
    expect($first->json('data'))->toHaveCount(20)
        ->and($first->json('has_more'))->toBeTrue()
        ->and(findWorks(['municipality' => '47189', 'page' => 2])->json('data'))->toHaveCount(1);
});

it('GovTrace sugiere la veeduría que ya sigue la obra: over the one with the more specific territory', function () {
    finderPublished($this->magdalena, FINDER_MINCA);

    expect(suggestions())->toBe([
        ['name' => 'Veeduría del Magdalena', 'reasons' => ['Ya sigue esta obra: 1 evidencia publicada', 'Vigila todo Magdalena', 'Publicó evidencias en los últimos 90 días'], 'suggested' => true],
        ['name' => 'Veeduría Santa Marta', 'reasons' => ['Vigila Santa Marta'], 'suggested' => false],
    ]);
});

it('Si ninguna la sigue, se sugiere la de territorio más específico: the municipality before the whole department', function () {
    expect(suggestions())->toBe([
        ['name' => 'Veeduría Santa Marta', 'reasons' => ['Vigila Santa Marta'], 'suggested' => true],
        ['name' => 'Veeduría del Magdalena', 'reasons' => ['Vigila todo Magdalena'], 'suggested' => false],
    ]);
});

it('Entre iguales, se sugiere la que publicó en los últimos 90 días: one that published 100 days ago no longer counts', function () {
    reportableContract('CO1.PCCNTR.1111111', ['object' => 'Acueducto de Gaira']);
    reportableContract('CO1.PCCNTR.2222222', ['object' => 'Parque de Bastidas']);
    $cienaga = finderOrganization('Veeduría Ciénaga y Santa Marta', 'veeduria-cienaga', ['47189', '47001']);

    $this->travelTo(now()->subDays(100));
    finderPublished($this->santaMarta, 'CO1.PCCNTR.1111111');
    $this->travelBack();
    $this->travelTo(now()->subDays(10));
    finderPublished($cienaga, 'CO1.PCCNTR.2222222');
    $this->travelBack();

    expect(array_slice(suggestions(), 0, 2))->toBe([
        ['name' => 'Veeduría Ciénaga y Santa Marta', 'reasons' => ['Vigila Santa Marta', 'Publicó evidencias en los últimos 90 días'], 'suggested' => true],
        ['name' => 'Veeduría Santa Marta', 'reasons' => ['Vigila Santa Marta'], 'suggested' => false],
    ]);
});

it('En un empate, las veedurías se muestran como iguales: in alphabetical order, none suggested', function () {
    finderOrganization('Veeduría Bonda', 'veeduria-bonda', ['47001']);

    expect(suggestions())->toBe([
        ['name' => 'Veeduría Bonda', 'reasons' => ['Vigila Santa Marta'], 'suggested' => false],
        ['name' => 'Veeduría Santa Marta', 'reasons' => ['Vigila Santa Marta'], 'suggested' => false],
        ['name' => 'Veeduría del Magdalena', 'reasons' => ['Vigila todo Magdalena'], 'suggested' => false],
    ]);
});

it('Una veeduría suspendida no se sugiere: it does not receive reports', function () {
    $suspended = finderOrganization('Veeduría Suspendida', 'veeduria-suspendida', ['47001']);
    (new ChangeOrganizationStatus)->handle($suspended, OrganizationStatus::Suspended, 'organization.suspended');

    expect(array_column(suggestions(), 'name'))->toBe(['Veeduría Santa Marta', 'Veeduría del Magdalena']);
});

it('covers a contract of the Gobernación only by the veedurías that watch the whole department', function () {
    reportableContract('CO1.PCCNTR.4700001', ['object' => 'Vía Ciénaga – Fundación', 'entity_name' => 'Gobernación del Magdalena', 'municipality_code' => null]);

    expect(suggestions('CO1.PCCNTR.4700001'))->toBe([
        ['name' => 'Veeduría del Magdalena', 'reasons' => ['Vigila todo Magdalena'], 'suggested' => true],
    ]);
});

it('Si ninguna veeduría cubre la obra: no veeduría, and the municipality for its Personería', function () {
    reportableContract('CO1.PCCNTR.0800001', ['object' => 'Pavimentación del barrio Rebolo', 'department_code' => '08', 'municipality_code' => '08001']);

    expect(whoCanHelp('CO1.PCCNTR.0800001')->assertOk()->json('data'))->toBe([
        'contract' => ['contract_id' => 'CO1.PCCNTR.0800001', 'name' => 'Pavimentación del barrio Rebolo', 'municipality' => 'Barranquilla', 'department' => 'Atlántico', 'entity_name' => 'Alcaldía Distrital de Santa Marta'],
        'organizations' => [],
    ]);
    whoCanHelp('CO1.PCCNTR.0000000')->assertNotFound();
});

it('Un toque lleva al formulario de esa veeduría: the page of the contract in its site, at the report form', function () {
    $organizations = whoCanHelp(FINDER_MINCA)->json('data.organizations');

    expect($organizations[0]['url'])->toBe('http://veeduria-smr.govtrace.localhost/contract/'.FINDER_MINCA.'#informar')
        ->and($organizations[1]['url'])->toBe('http://veeduria-magdalena.govtrace.localhost/contract/'.FINDER_MINCA.'#informar');
});

it('Usar mi ubicación propone el municipio, sin enviarla: the browser gets the seats of the covered municipalities, and no endpoint takes a location', function () {
    $municipalities = collect($this->getJson('http://govtrace.localhost/public/obras/municipios')->assertOk()->json('data'))->keyBy('code');

    expect($municipalities)->toHaveCount(30) // el Magdalena entero, por la veeduría del departamento
        ->and($municipalities['47001'])->toBe(['code' => '47001', 'name' => 'Santa Marta', 'department' => 'Magdalena', 'latitude' => 11.204679, 'longitude' => -74.199829]);
    // Las coordenadas que alguien mande no cambian nada: la búsqueda no las usa.
    expect(findWorks(['latitude' => 11.2, 'longitude' => -74.2])->json('data'))->toBe(findWorks()->json('data'));
});

it('El resumen central sigue a las fichas y a las evidencias: the sheet, publishing, withdrawing and grouping, and recomputing it gives the same', function () {
    $tenant = $this->magdalena;
    $administrator = reportingMember($tenant, 'ana@veeduria-magdalena.org', Roles::Administrator);
    $veedor = reportingMember($tenant, 'carlos@correo.co');
    $row = fn (string $contract) => ContractFollower::query()->where('tenant_id', $tenant->id)->where('secop_contract_id', $contract)->first();

    // El primer reporte crea la ficha: la veeduría ya sigue el contrato, sin evidencias publicadas.
    $reportId = sealedReport($tenant, $veedor, ['secop_contract_id' => FINDER_MINCA]);
    expect($row(FINDER_MINCA))->published_evidences->toBe(0)->last_published_at->toBeNull();

    editorialDecision($administrator, 'publish', $reportId, [], 'veeduria-magdalena.govtrace.localhost')->assertOk();
    expect($row(FINDER_MINCA))->published_evidences->toBe(1)->last_published_at->not->toBeNull();

    editorialDecision($administrator, 'withdraw', $reportId, ['reason' => 'Muestra la cara de un menor.'], 'veeduria-magdalena.govtrace.localhost')->assertOk();
    expect($row(FINDER_MINCA)->published_evidences)->toBe(0);

    // Agrupar: el otro contrato pasa a la ficha que ya tiene el reporte.
    reportableContract('CO1.PCCNTR.2222222', ['object' => 'Construcción de la vía a Minca, segunda fase']);
    $this->actingAs($administrator, 'tenant')->postJson('http://veeduria-magdalena.govtrace.localhost/worksites/group', [
        'name' => 'Vía a Minca',
        'secop_contract_ids' => [FINDER_MINCA, 'CO1.PCCNTR.2222222'],
    ])->assertSuccessful();
    tenancy()->end();
    expect($row('CO1.PCCNTR.2222222'))->not->toBeNull();

    $snapshot = fn () => ContractFollower::query()->orderBy('tenant_id')->orderBy('secop_contract_id')->get(['tenant_id', 'secop_contract_id', 'published_evidences', 'last_published_at'])->toArray();
    $before = $snapshot();
    ContractFollower::query()->delete();
    $this->artisan('contracts:followers')->assertSuccessful();
    expect($snapshot())->toEqual($before);
});

it('La búsqueda no abre una sesión ni guarda la obra en la dirección: no cookie on any answer', function () {
    foreach (['/public/obras?q=minca', '/public/obras/municipios', '/public/obras/quien?contrato='.FINDER_MINCA] as $path) {
        $response = $this->get("http://govtrace.localhost{$path}", ['Accept' => 'application/json'])->assertOk();
        expect($response->headers->getCookies())->toBe([]);
    }
});
