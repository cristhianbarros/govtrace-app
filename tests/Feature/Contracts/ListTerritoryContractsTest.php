<?php

use App\Application\Contracts\ListTerritoryContracts;
use App\Application\Organization\ConfigureTerritory;
use App\Application\Organization\RegisterOrganization;
use App\Domain\Contracts\Contract;
use App\Infrastructure\Tenancy\Tenant;
use Database\Seeders\DivipolaSeeder;
use Illuminate\Support\Facades\DB;

/*
 * Iteración 8 — Contratos del territorio: listado, búsqueda y tarjeta
 * pública (specs/PLAN.md). Traduce features/US-015.feature (5 casos).
 *
 * Backend únicamente (Done-when de la iteración): el truncado del objeto
 * a 50 caracteres con tooltip es presentación (it. 18, panel del
 * Administrador) — aquí se prueba que el backend entrega el texto
 * completo, sin truncar, para que la pantalla decida cómo mostrarlo.
 *
 * Sin RefreshDatabase — registrar la organización de las Antecedentes
 * ejecuta CREATE DATABASE.
 */

beforeEach(function () {
    $this->artisan('migrate');
    (new DivipolaSeeder)->run();
    $this->tenant = (new RegisterOrganization)->handle('900123456-8', 'Veeduría Ciudadana Santa Marta', 'veeduria-smr');
    (new ConfigureTerritory)->handle($this->tenant, ['47']); // Magdalena
});

afterEach(function () {
    if (tenant()) {
        tenancy()->end();
    }

    Tenant::query()->get()->each->delete();
    DB::table('contracts')->delete();
});

/**
 * @param  array<string, mixed>  $overrides
 */
function magdalenaContract(array $overrides = []): Contract
{
    static $sequence = 0;
    $sequence++;

    return Contract::fromSecop(fn () => Contract::create(array_merge([
        'secop_contract_id' => "CO1.PCCNTR.LIST{$sequence}",
        'process_number' => "261-{$sequence}",
        'entity_name' => 'Alcaldía de Santa Marta',
        'contractor_name' => 'Constructora Caribe S.A.S.',
        'object' => 'Pavimentación de vías urbanas',
        'contract_type' => 'Obra',
        'value' => 1_000_000 * $sequence,
        'signed_at' => now()->subDays($sequence),
        'end_date' => now()->addMonths(6),
        'status' => 'En ejecución',
        'department_code' => '47',
        'municipality_code' => '47001', // Santa Marta
        'secop_url' => 'https://community.secop.gov.co/x',
    ], $overrides)));
}

it('shows a paginated table of 20 contracts per page, ordered by fecha de firma descending', function () {
    for ($i = 0; $i < 45; $i++) {
        magdalenaContract();
    }

    $page = (new ListTerritoryContracts)->handle($this->tenant);

    expect($page->perPage())->toBe(20)
        ->and($page->total())->toBe(45)
        ->and($page->count())->toBe(20);

    $signedDates = $page->getCollection()->pluck('signed_at');
    expect($signedDates->values()->all())->toBe($signedDates->sortDesc()->values()->all());
});

it('inverts the order when sorting by value twice', function () {
    magdalenaContract(['secop_contract_id' => 'CO1.PCCNTR.V1', 'value' => 100]);
    magdalenaContract(['secop_contract_id' => 'CO1.PCCNTR.V2', 'value' => 300]);
    magdalenaContract(['secop_contract_id' => 'CO1.PCCNTR.V3', 'value' => 200]);

    // El clic en el encabezado alterna la dirección; el backend solo
    // necesita respetarla cuando se le pide, lo demás (recordar el
    // estado del último clic) es de la pantalla (it. 18).
    $ascending = (new ListTerritoryContracts)->handle($this->tenant, sortBy: 'value', direction: 'asc');
    $descending = (new ListTerritoryContracts)->handle($this->tenant, sortBy: 'value', direction: 'desc');

    expect($ascending->getCollection()->pluck('value')->map(fn ($v) => (float) $v)->all())->toBe([100.0, 200.0, 300.0])
        ->and($descending->getCollection()->pluck('value')->map(fn ($v) => (float) $v)->all())->toBe([300.0, 200.0, 100.0]);
});

it('returns the full object text untruncated, leaving the tooltip truncation to the screen', function () {
    $longObject = str_repeat('Mejoramiento de la vía terciaria del corregimiento ', 3); // > 50 caracteres
    magdalenaContract(['object' => $longObject]);

    $page = (new ListTerritoryContracts)->handle($this->tenant);

    expect($page->getCollection()->first()->object)->toBe($longObject);
});

it('a watched department includes its Gobernación and every one of its municipalities, and nothing outside it', function () {
    // La Gobernación: SECOP la publica con departamento conocido y
    // ciudad "No Definido" — no tiene municipio (it. 7 lo descartaba;
    // esta historia obliga a guardarlo como un contrato departamental).
    Contract::fromSecop(fn () => Contract::create([
        'secop_contract_id' => 'CO1.PCCNTR.GOB47',
        'entity_name' => 'Gobernación del Magdalena',
        'contract_type' => 'Obra',
        'status' => 'En ejecución',
        'value' => 1,
        'department_code' => '47',
        'municipality_code' => null,
    ]));

    magdalenaContract(['secop_contract_id' => 'CO1.PCCNTR.SM', 'municipality_code' => '47001']); // Santa Marta
    magdalenaContract(['secop_contract_id' => 'CO1.PCCNTR.CI', 'municipality_code' => '47189']); // Ciénaga
    magdalenaContract(['secop_contract_id' => 'CO1.PCCNTR.MED', 'department_code' => '05', 'municipality_code' => '05001']); // Medellín, fuera del territorio

    $ids = (new ListTerritoryContracts)->handle($this->tenant)->getCollection()->pluck('secop_contract_id');

    expect($ids)->toContain('CO1.PCCNTR.GOB47', 'CO1.PCCNTR.SM', 'CO1.PCCNTR.CI')
        ->and($ids)->not->toContain('CO1.PCCNTR.MED');
});

it('an empty territory returns no contracts, for the screen to show its own message', function () {
    $withoutContracts = (new RegisterOrganization)->handle('890000062-6', 'Veeduría Paisa', 'veeduria-paisa');
    (new ConfigureTerritory)->handle($withoutContracts, ['05001']); // Medellín, sin contratos sincronizados

    $page = (new ListTerritoryContracts)->handle($withoutContracts);

    expect($page->total())->toBe(0);

    $withoutContracts->delete();
});
