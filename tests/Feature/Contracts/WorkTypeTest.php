<?php

use App\Application\Contracts\ProcessSecopContractRow;
use App\Domain\Contracts\Contract;
use App\Domain\Contracts\WorkType;
use Database\Seeders\DivipolaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

/*
 * Iteración 47a — Encontrar la obra en campo (specs/PLAN.md). El tipo de obra
 * de US-016: por las palabras del objeto del contrato y, si ninguna encaja,
 * por el código UNSPSC de SECOP II (codigo_de_categoria_principal). Medido en
 * Antioquia: el código solo no sirve (un acueducto y una vivienda caen en
 * "mantenimiento", una cancha en "oficios especializados"). Lo calcula la
 * sincronización y queda con el contrato.
 */

uses(RefreshDatabase::class);

beforeEach(function () {
    (new DivipolaSeeder)->run();
});

/** @return array<string, mixed> */
function secopWorksRow(string $object, ?string $unspsc): array
{
    return array_filter([
        'id_contrato' => 'CO1.PCCNTR.4700001',
        'referencia_del_contrato' => '261-2026',
        'nombre_entidad' => 'Alcaldía de Santa Marta',
        'proveedor_adjudicado' => 'Constructora Caribe S.A.S.',
        'descripcion_del_proceso' => $object,
        'codigo_de_categoria_principal' => $unspsc,
        'tipo_de_contrato' => 'Obra',
        'estado_contrato' => 'En ejecución',
        'valor_del_contrato' => '1000000000.000000',
        'fecha_de_firma' => '2026-01-15T00:00:00.000',
        'fecha_de_fin_del_contrato' => '2026-12-15T00:00:00.000',
        'ciudad' => 'Santa Marta',
        'departamento' => 'Magdalena',
        'urlproceso' => ['url' => 'https://community.secop.gov.co/Public/Tendering/OpportunityDetail/Index?x=1'],
    ], fn ($value) => $value !== null);
}

it('El tipo de obra sale de las palabras del objeto y, si no, del código UNSPSC', function (string $object, string $unspsc, string $label) {
    (new ProcessSecopContractRow)->handle(secopWorksRow($object, $unspsc));

    $contract = Contract::query()->where('secop_contract_id', 'CO1.PCCNTR.4700001')->sole();

    expect(WorkType::from($contract->work_type)->label())->toBe($label);
})->with([
    ['Construcción de redes de acueducto y alcantarillado', 'V1.72101500', 'Agua y saneamiento'],
    ['Mejoramientos de vivienda en la zona rural', 'V1.72101500', 'Vivienda'],
    ['Mantenimiento de la cancha de fútbol Pan de Azúcar', 'V1.72151500', 'Deporte y recreación'],
    ['Adecuación de la sede de la institución educativa', 'V1.72121400', 'Educación'],
    ['Ampliación del centro de salud del corregimiento', 'V1.72121400', 'Salud'],
    ['Construcción del parque lineal de la quebrada', 'V1.72141100', 'Espacio público'],
    ['Pavimentación de la vía al colegio', 'V1.72141100', 'Vías y puentes'],
    ['Obras de estabilización del talud', 'V1.72141100', 'Vías y puentes'],
    ['Obras complementarias del proyecto habitacional', 'V1.72111000', 'Vivienda'],
    ['Suministro e instalación de elementos de señalización', 'V1.81101500', 'Otras'],
]);

it('decides by the keyword that comes first in the object, whole words only', function () {
    expect(WorkType::classify('Pavimentación de la vía al colegio', null))->toBe(WorkType::Roads)
        ->and(WorkType::classify('Restaurante escolar junto a la vía principal', null))->toBe(WorkType::Education)
        // "via" no está dentro de "vivienda" ni de "aviación"
        ->and(WorkType::classify('Mejoramiento de vivienda', null))->toBe(WorkType::Housing)
        ->and(WorkType::classify('Hangar de aviación', null))->toBe(WorkType::Other)
        ->and(WorkType::classify(null, null))->toBe(WorkType::Other);
});

it('keeps the UNSPSC code SECOP II publishes, and a contract saved outside the sync also gets its type', function () {
    (new ProcessSecopContractRow)->handle(secopWorksRow('Obras varias', 'V1.72141100'));

    $contract = Contract::query()->where('secop_contract_id', 'CO1.PCCNTR.4700001')->sole();

    expect($contract->raw_payload['codigo_de_categoria_principal'])->toBe('V1.72141100')
        ->and($contract->work_type)->toBe(WorkType::Roads->value)
        ->and(reportableContract('CO1.PCCNTR.4700002', ['object' => 'Construcción del coliseo'])->work_type)->toBe(WorkType::Sports->value);
});
