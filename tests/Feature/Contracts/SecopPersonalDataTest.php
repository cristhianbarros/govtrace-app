<?php

use App\Application\Contracts\ProcessSecopContractRow;
use App\Domain\Contracts\Contract;
use Database\Seeders\DivipolaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

/*
 * Iteración 45c — de cada fila de SECOP II, GovTrace guarda solo los campos
 * que usa (it. 7, decisión pendiente). La fila completa trae unos 90 campos,
 * con datos personales públicos que no necesita: del representante legal, su
 * documento, domicilio y género; del supervisor y del ordenador del gasto, su
 * nombre y documento; la cuenta bancaria del contratista (Ley 1581, principio
 * de finalidad). Y lo ya guardado se recorta.
 */

uses(RefreshDatabase::class);

beforeEach(function () {
    (new DivipolaSeeder)->run();
});

const SECOP_USED_FIELDS = [
    'id_contrato', 'referencia_del_contrato', 'nombre_entidad', 'proveedor_adjudicado', 'descripcion_del_proceso',
    'tipo_de_contrato', 'estado_contrato', 'valor_del_contrato', 'fecha_de_firma', 'fecha_de_fin_del_contrato',
    'ciudad', 'departamento', 'urlproceso',
];

/** @return array<string, mixed> A row as the SODA API gives it, with the personal data it carries. */
function fullSecopRow(): array
{
    return [
        'id_contrato' => 'CO1.PCCNTR.7654321',
        'referencia_del_contrato' => '262-2025',
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
        // Campos reales de la API (consultada el 2026-09-30), con valores inventados.
        'nombre_representante_legal' => 'Juana Pérez',
        'identificaci_n_representante_legal' => '1012345678',
        'domicilio_representante_legal' => 'Calle 1 # 2-3, Santa Marta',
        'g_nero_representante_legal' => 'Mujer',
        'nombre_supervisor' => 'Pedro Gómez',
        'n_mero_de_documento_supervisor' => '79123456',
        'nombre_ordenador_del_gasto' => 'Luis Díaz',
        'nombre_del_banco' => 'Banco de Prueba',
        'n_mero_de_cuenta' => '0123456789',
    ];
}

it('keeps from a SECOP row only the fields GovTrace uses, without the personal data of representatives and supervisors', function () {
    (new ProcessSecopContractRow)->handle(fullSecopRow());

    $raw = Contract::query()->where('secop_contract_id', 'CO1.PCCNTR.7654321')->firstOrFail()->raw_payload;

    expect(array_keys($raw))->toEqualCanonicalizing(SECOP_USED_FIELDS)
        ->and($raw['urlproceso'])->toBe(['url' => 'https://community.secop.gov.co/Public/Tendering/OpportunityDetail/Index?x=1']);
});

it('trims the SECOP rows already stored, active and archived', function () {
    $row = fullSecopRow();
    $columns = ['secop_contract_id' => $row['id_contrato'], 'entity_name' => 'Alcaldía de Santa Marta', 'contract_type' => 'Obra', 'status' => 'Terminado', 'department_code' => '47', 'municipality_code' => '47001', 'raw_payload' => json_encode($row)];
    DB::table('contracts')->insert($columns + ['created_at' => now(), 'updated_at' => now()]);
    DB::table('archived_contracts')->insert(['id' => 999, 'secop_contract_id' => 'CO1.PCCNTR.1111111'] + $columns + ['archived_at' => now()]);

    (require database_path('migrations/2026_10_01_000200_trim_secop_raw_payload.php'))->up();

    foreach (['contracts', 'archived_contracts'] as $table) {
        expect(array_keys(json_decode(DB::table($table)->value('raw_payload'), true)))->toEqualCanonicalizing(SECOP_USED_FIELDS);
    }
});
