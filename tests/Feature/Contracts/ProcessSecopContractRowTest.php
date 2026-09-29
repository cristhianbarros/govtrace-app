<?php

use App\Application\Contracts\ProcessSecopContractRow;
use App\Application\Contracts\SecopRowOutcome;
use App\Domain\Contracts\Contract;
use App\Domain\Organization\WatchedTerritories;
use Database\Seeders\DivipolaSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;

/*
 * Iteración 7 — Sincronización SECOP II (specs/PLAN.md). Traduce
 * features/US-032.feature (5 casos), features/US-033.feature (5 casos) y
 * dos escenarios de features/US-013.feature que no necesitan HTTP
 * (emparejamiento con DIVIPOLA, contrato no editable) — la fila SECOP ya
 * llegó, esto es solo la lógica de filtro, emparejamiento y upsert.
 */

uses(RefreshDatabase::class);

beforeEach(function () {
    (new DivipolaSeeder)->run();
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function secopRow(array $overrides = []): array
{
    return array_merge([
        'id_contrato' => 'CO1.PCCNTR.1234567',
        'referencia_del_contrato' => '261-2025',
        'nombre_entidad' => 'Alcaldía de Santa Marta',
        'proveedor_adjudicado' => 'Constructora Caribe S.A.S.',
        'descripcion_del_proceso' => 'Pavimentación Calle 30',
        'tipo_de_contrato' => 'Obra',
        'estado_contrato' => 'En ejecución',
        'valor_del_contrato' => '1000000000.000000',
        'fecha_de_firma' => '2026-01-15T00:00:00.000',
        'fecha_de_fin_del_contrato' => '2026-09-15T00:00:00.000',
        'ciudad' => 'Santa Marta',
        'departamento' => 'Magdalena',
        'urlproceso' => ['url' => 'https://community.secop.gov.co/Public/Tendering/OpportunityDetail/Index?x=1'],
    ], $overrides);
}

// US-032: solo contratos de tipo "Obra" ------------------------------------

it('Filtro por el tipo de contrato oficial de SECOP II: keeps or discards a contract by its official SECOP contract type', function (string $tipo, SecopRowOutcome $expected) {
    $outcome = (new ProcessSecopContractRow)->handle(secopRow(['tipo_de_contrato' => $tipo]));

    expect($outcome)->toBe($expected)
        ->and(Contract::query()->where('secop_contract_id', 'CO1.PCCNTR.1234567')->exists())->toBe($outcome->isSaved());
})->with([
    'Obra' => ['Obra', SecopRowOutcome::Inserted],
    'Consultoría' => ['Consultoría', SecopRowOutcome::NotWorks],
    'Prestación de servicios' => ['Prestación de servicios', SecopRowOutcome::NotWorks],
    'Suministros' => ['Suministros', SecopRowOutcome::NotWorks],
    'Compraventa' => ['Compraventa', SecopRowOutcome::NotWorks],
]);

// US-033: sincronización incremental sin duplicados ------------------------

it('Un contrato nuevo se inserta: inserts a new contract', function () {
    $outcome = (new ProcessSecopContractRow)->handle(secopRow());

    expect($outcome)->toBe(SecopRowOutcome::Inserted)
        ->and(Contract::query()->where('secop_contract_id', 'CO1.PCCNTR.1234567')->count())->toBe(1);
});

it('Un contrato modificado se actualiza sin duplicarse: updates an existing contract without duplicating it', function () {
    (new ProcessSecopContractRow)->handle(secopRow(['valor_del_contrato' => '1000000000.000000']));

    $outcome = (new ProcessSecopContractRow)->handle(secopRow(['valor_del_contrato' => '1200000000.000000']));

    $contracts = Contract::query()->where('secop_contract_id', 'CO1.PCCNTR.1234567')->get();

    expect($outcome)->toBe(SecopRowOutcome::Updated)
        ->and($contracts)->toHaveCount(1)
        ->and((float) $contracts->first()->value)->toBe(1200000000.00);
});

it('La base de datos impide duplicar el identificador: the unique constraint on secop_contract_id blocks a duplicate row', function () {
    (new ProcessSecopContractRow)->handle(secopRow());

    expect(fn () => Contract::fromSecop(fn () => Contract::query()->create([
        'secop_contract_id' => 'CO1.PCCNTR.1234567',
        'entity_name' => 'Otra entidad',
        'contract_type' => 'Obra',
        'status' => 'En ejecución',
        'department_code' => '47',
        'municipality_code' => '47001',
    ])))->toThrow(QueryException::class);
});

it('Un contrato anulado en SECOP nunca se borra: a contract SECOP reports as Anulado is marked cancelled, never deleted', function (bool $hasEvidence) {
    (new ProcessSecopContractRow)->handle(secopRow());

    // "con evidencias selladas" no se puede simular todavía (it. 10+); lo
    // que sí es cierto para ambos casos es que el contrato jamás se borra.
    (new ProcessSecopContractRow)->handle(secopRow(['estado_contrato' => 'Anulado']));

    $contract = Contract::query()->where('secop_contract_id', 'CO1.PCCNTR.1234567')->first();

    expect($contract)->not->toBeNull()
        ->and($contract->status)->toBe('cancelled');
})->with([
    'con evidencias selladas' => [true],
    'sin evidencias ni sellos' => [false],
]);

it('treats the real SECOP II status "Cancelado" as cancelled too (recorded fixture)', function () {
    // La API real no usa "Anulado": el estado que publica es "Cancelado"
    // (1.809 contratos de obra al 2026-09-27). Fila grabada de Santa Marta.
    $rows = secopFixture('magdalena_obras');
    $cancelled = collect($rows)->firstWhere('estado_contrato', 'Cancelado');

    (new ProcessSecopContractRow)->handle($cancelled);

    expect(Contract::query()->where('secop_contract_id', $cancelled['id_contrato'])->value('status'))->toBe('cancelled');
});

// R-SEC-01: nadie edita ni borra un contrato a mano ------------------------

it('Los datos de un contrato no se pueden editar manualmente: rejects a manual edit to a synced contract', function () {
    (new ProcessSecopContractRow)->handle(secopRow());

    $contract = Contract::query()->where('secop_contract_id', 'CO1.PCCNTR.1234567')->first();
    $contract->value = 999999999;

    expect(fn () => $contract->save())->toThrow(RuntimeException::class);
    expect(Contract::query()->where('secop_contract_id', 'CO1.PCCNTR.1234567')->first()->value)->not->toEqual(999999999);
});

it('rejects deleting a synced contract, even through the SECOP door', function () {
    (new ProcessSecopContractRow)->handle(secopRow());

    $contract = Contract::query()->where('secop_contract_id', 'CO1.PCCNTR.1234567')->first();

    expect(fn () => $contract->delete())->toThrow(RuntimeException::class)
        ->and(fn () => Contract::fromSecop(fn () => $contract->delete()))->toThrow(RuntimeException::class);
    expect(Contract::query()->where('secop_contract_id', 'CO1.PCCNTR.1234567')->exists())->toBeTrue();
});

// US-013: emparejamiento normalizado del municipio con DIVIPOLA -----------

it('matches the municipality name SECOP sends, discarding what does not match', function (string $ciudad, ?string $codigoEsperado) {
    $outcome = (new ProcessSecopContractRow)->handle(secopRow(['ciudad' => $ciudad]));

    if ($codigoEsperado === null) {
        expect($outcome)->toBe(SecopRowOutcome::Unmatched)
            ->and(Contract::query()->exists())->toBeFalse();

        return;
    }

    $contract = Contract::query()->where('secop_contract_id', 'CO1.PCCNTR.1234567')->first();
    expect($outcome)->toBe(SecopRowOutcome::Inserted)->and($contract->municipality_code)->toBe($codigoEsperado);
})->with([
    'SANTA MARTA' => ['SANTA MARTA', '47001'],
    'Ciénaga' => ['Ciénaga', '47189'],
    'Cienaga sin tilde' => ['Cienaga', '47189'],
    'Villa Inexistente' => ['Villa Inexistente', null],
]);

// R-SEC-02 / R-AUD-06: lo que cae fuera del territorio vigilado no se toca -

it('ignores a row outside the watched territory without storing or updating it', function () {
    $onlyMedellin = new WatchedTerritories(departmentCodes: [], municipalityCodes: ['05001']);

    $outcome = (new ProcessSecopContractRow)->handle(secopRow(), $onlyMedellin);

    expect($outcome)->toBe(SecopRowOutcome::OutOfTerritory)
        ->and(Contract::query()->exists())->toBeFalse();
});

// US-015/US-016 (it. 8): un contrato de la Gobernación (departamento
// conocido, ciudad "No Definido") se guarda sin municipio propio.

it('stores a Gobernación contract at the department level when the department is watched whole', function () {
    $wholeMagdalena = new WatchedTerritories(departmentCodes: ['47'], municipalityCodes: []);

    $outcome = (new ProcessSecopContractRow)->handle(secopRow(['ciudad' => 'No Definido']), $wholeMagdalena);

    $contract = Contract::query()->where('secop_contract_id', 'CO1.PCCNTR.1234567')->first();

    expect($outcome)->toBe(SecopRowOutcome::Inserted)
        ->and($contract->department_code)->toBe('47')
        ->and($contract->municipality_code)->toBeNull();
});

it('ignores a Gobernación contract when only one of its municipalities is watched, not the whole department', function () {
    $onlySantaMarta = new WatchedTerritories(departmentCodes: [], municipalityCodes: ['47001']);

    $outcome = (new ProcessSecopContractRow)->handle(secopRow(['ciudad' => 'No Definido']), $onlySantaMarta);

    expect($outcome)->toBe(SecopRowOutcome::OutOfTerritory)
        ->and(Contract::query()->exists())->toBeFalse();
});
