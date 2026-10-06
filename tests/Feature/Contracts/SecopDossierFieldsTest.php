<?php

use App\Application\Contracts\ProcessSecopContractRow;
use App\Domain\Contracts\Contract;
use App\Domain\Contracts\ContractArchive;
use Database\Seeders\DivipolaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

/*
 * Iteración 46j — de cada fila de SECOP II, GovTrace guarda además lo que el
 * expediente le dice a la Contraloría: el nombre del supervisor (nunca su
 * documento), el orden de la entidad y el origen de los recursos. Enmienda de
 * la it. 45c: ahora tienen una finalidad (Ley 1581, principio de finalidad).
 */

uses(RefreshDatabase::class);

beforeEach(function () {
    (new DivipolaSeeder)->run();
});

/** @return array<string, mixed> A row as the SODA API gives it, with the fields of the 46j. */
function secopRowWithFunding(array $overrides = []): array
{
    return array_merge([
        'id_contrato' => 'CO1.PCCNTR.7654321',
        'referencia_del_contrato' => '262-2025',
        'nombre_entidad' => 'Gobernación del Magdalena',
        'proveedor_adjudicado' => 'Constructora Caribe S.A.S.',
        'descripcion_del_proceso' => 'Pavimentación Calle 30',
        'tipo_de_contrato' => 'Obra',
        'estado_contrato' => 'En ejecución',
        'valor_del_contrato' => '1000000000.000000',
        'fecha_de_firma' => '2026-01-15T00:00:00.000',
        'fecha_de_fin_del_contrato' => '2026-09-15T00:00:00.000',
        'ciudad' => 'Santa Marta',
        'departamento' => 'Magdalena',
        'orden' => 'Territorial',
        'nombre_supervisor' => 'Pedro Gómez',
        'tipo_de_documento_supervisor' => 'Cédula de Ciudadanía',
        'n_mero_de_documento_supervisor' => '79123456',
        'origen_de_los_recursos' => 'Distribuido',
        'presupuesto_general_de_la_nacion_pgn' => '0',
        'sistema_general_de_participaciones' => '150000000',
        'sistema_general_de_regal_as' => '0',
        'recursos_propios_alcald_as_gobernaciones_y_resguardos_ind_genas_' => '60000000',
        'recursos_de_credito' => '0',
        'recursos_propios' => '1600000',
    ], $overrides);
}

function storedContract(): Contract
{
    return Contract::query()->where('secop_contract_id', 'CO1.PCCNTR.7654321')->firstOrFail();
}

it('guarda el nombre del supervisor, el orden de la entidad y las seis fuentes de los recursos', function () {
    (new ProcessSecopContractRow)->handle(secopRowWithFunding());

    $contract = storedContract();

    expect($contract->supervisor_name)->toBe('Pedro Gómez')
        ->and($contract->entity_order)->toBe('Territorial')
        ->and($contract->funding_sources)->toBe(['pgn' => 0, 'sgp' => 150_000_000, 'sgr' => 0, 'territorial' => 60_000_000, 'credit' => 0, 'own' => 1_600_000]);
});

it('el documento del supervisor no se guarda, ni en sus columnas ni en el payload', function () {
    (new ProcessSecopContractRow)->handle(secopRowWithFunding());

    $stored = json_encode(storedContract()->getAttributes());

    expect($stored)->not->toContain('79123456')->not->toContain('Cédula de Ciudadanía');
});

it('"No definido" o vacío es sin dato: the supervisor stays null', function () {
    (new ProcessSecopContractRow)->handle(secopRowWithFunding(['nombre_supervisor' => 'No definido']));
    expect(storedContract()->supervisor_name)->toBeNull();

    (new ProcessSecopContractRow)->handle(secopRowWithFunding(['nombre_supervisor' => '  ']));
    expect(storedContract()->supervisor_name)->toBeNull();
});

it('una fila sin el desglose de los recursos deja esas columnas sin dato', function () {
    $row = secopRowWithFunding();
    unset($row['sistema_general_de_participaciones'], $row['recursos_propios'], $row['presupuesto_general_de_la_nacion_pgn'], $row['sistema_general_de_regal_as'], $row['recursos_propios_alcald_as_gobernaciones_y_resguardos_ind_genas_'], $row['recursos_de_credito'], $row['orden']);

    (new ProcessSecopContractRow)->handle($row);

    expect(storedContract()->funding_sources)->toBeNull()
        ->and(storedContract()->entity_order)->toBeNull();
});

it('el archivo de contratos conserva las columnas nuevas, al archivar y al volver', function () {
    (new ProcessSecopContractRow)->handle(secopRowWithFunding(['estado_contrato' => 'Terminado', 'fecha_de_fin_del_contrato' => '2015-01-01T00:00:00.000']));

    ContractArchive::archive(['CO1.PCCNTR.7654321']);
    $restored = ContractArchive::restore('CO1.PCCNTR.7654321');

    expect($restored->supervisor_name)->toBe('Pedro Gómez')
        ->and($restored->entity_order)->toBe('Territorial')
        ->and($restored->funding_sources['sgp'])->toBe(150_000_000);
});
