<?php

use App\Application\Contracts\SearchSelectableContracts;
use App\Application\Organization\ConfigureTerritory;
use App\Application\Organization\RegisterOrganization;
use App\Domain\Contracts\Contract;
use App\Infrastructure\Tenancy\Tenant;
use Database\Seeders\DivipolaSeeder;
use Illuminate\Support\Facades\DB;

/*
 * Iteración 8 — Contratos del territorio: listado, búsqueda y tarjeta
 * pública (specs/PLAN.md). Traduce features/US-016.feature (17 casos:
 * 2 escenarios + el Esquema de 13 filas + 2 escenarios más). El Esquema
 * usa los estados reales de SECOP II desde la corrección de R-SEC-07.
 *
 * Backend únicamente: el debounce de 300 ms es de la pantalla de la PWA
 * (it. 16); aquí se prueba la regla de mínimo 3 caracteres y de qué
 * contratos son seleccionables.
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
function searchableContract(array $overrides = []): Contract
{
    static $sequence = 0;
    $sequence++;

    return Contract::fromSecop(fn () => Contract::create(array_merge([
        'secop_contract_id' => "CO1.PCCNTR.SEARCH{$sequence}",
        'process_number' => "261-{$sequence}",
        'entity_name' => 'Alcaldía Distrital de Santa Marta',
        'contractor_name' => 'Constructora Caribe S.A.S.',
        'object' => 'Pavimentación Calle 30',
        'contract_type' => 'Obra',
        'value' => 1_000_000,
        'signed_at' => now()->subMonths(2),
        'end_date' => now()->addMonths(6),
        'status' => 'En ejecución',
        'department_code' => '47',
        'municipality_code' => '47001', // Santa Marta
        'secop_url' => 'https://community.secop.gov.co/x',
    ], $overrides)));
}

it('finds a contract by a keyword of its object, contractor or process number', function () {
    searchableContract();

    $results = (new SearchSelectableContracts)->handle($this->tenant, 'Pavi');

    expect($results->pluck('object'))->toContain('Pavimentación Calle 30');
});

it('does not search below the 3-character minimum', function () {
    // "Pavimentación Calle 30" sí contiene "Pa" — si la regla de mínimo
    // no se aplicara, esta búsqueda encontraría el contrato igual.
    searchableContract();

    $results = (new SearchSelectableContracts)->handle($this->tenant, 'Pa');

    expect($results)->toBeEmpty();
});

it('Qué contratos se pueden seleccionar según su estado: only shows the contracts whose status makes them selectable', function (string $estado, ?int $mesesDesdeElCierre, bool $aparece) {
    searchableContract([
        'secop_contract_id' => 'CO1.PCCNTR.PARQUE',
        'object' => 'Parque Bastidas',
        'status' => $estado,
        'end_date' => $mesesDesdeElCierre !== null ? now()->subMonths($mesesDesdeElCierre) : now()->addMonths(6),
    ]);

    $results = (new SearchSelectableContracts)->handle($this->tenant, 'Parque Bastidas');

    expect($results->isNotEmpty())->toBe($aparece);
})->with([
    // Estados tal como los publica SECOP II (R-SEC-07), comparados sin
    // distinguir mayúsculas: "terminado" llega en minúscula.
    'En ejecución' => ['En ejecución', null, true],
    'Modificado' => ['Modificado', null, true],
    'Aprobado' => ['Aprobado', null, true],
    'cedido' => ['cedido', null, true],
    'Suspendido (obra paralizada)' => ['Suspendido', null, true],
    'terminado hace 11 meses' => ['terminado', 11, true],
    'Cerrado hace 12 meses' => ['Cerrado', 12, true],
    'terminado hace 13 meses' => ['terminado', 13, false],
    // "Cancelado" en SECOP ya llega guardado como 'cancelled' (it. 7, US-033).
    'Cancelado' => ['cancelled', null, false],
    'Borrador' => ['Borrador', null, false],
    'enviado Proveedor' => ['enviado Proveedor', null, false],
    'En aprobación' => ['En aprobación', null, false],
    'MODIFICADO en mayúsculas' => ['MODIFICADO', null, true],
]);

it('Solo aparecen contratos del territorio de la organización: only shows contracts within the organization watching them', function () {
    searchableContract([
        'secop_contract_id' => 'CO1.PCCNTR.MED',
        'department_code' => '05',
        'municipality_code' => '05001', // Medellín, fuera del territorio de Santa Marta
    ]);
    searchableContract(['secop_contract_id' => 'CO1.PCCNTR.SM']);

    $results = (new SearchSelectableContracts)->handle($this->tenant, 'Pavimentación Calle 30');

    expect($results)->toHaveCount(1)
        ->and($results->first()->municipality_code)->toBe('47001');
});

it('returns nothing for a search with no matches', function () {
    searchableContract();

    $results = (new SearchSelectableContracts)->handle($this->tenant, 'Estadio Olímpico');

    expect($results)->toBeEmpty();
});
