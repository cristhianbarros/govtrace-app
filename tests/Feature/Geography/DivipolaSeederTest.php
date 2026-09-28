<?php

use App\Domain\Geography\Department;
use App\Domain\Geography\Municipality;
use Database\Seeders\DivipolaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

/*
 * Iteración 2 — Datos de referencia: DIVIPOLA y emparejamiento (specs/PLAN.md).
 * Done-when: "test de seeder: el conteo de departamentos y municipios
 * coincide con el archivo oficial, y se verifican los códigos 47, 47001,
 * 47189, 05 y 05001".
 */

uses(RefreshDatabase::class);

it('seeds exactly the departments and municipalities from the official DIVIPOLA file', function () {
    $source = json_decode(
        file_get_contents(database_path('data/divipola.json')),
        associative: true,
    );

    (new DivipolaSeeder)->run();

    expect(Department::query()->count())->toBe(count($source['departamentos']))
        ->and(Municipality::query()->count())->toBe(count($source['municipios']));
});

it('seeds the department and municipality codes the case relies on', function () {
    (new DivipolaSeeder)->run();

    expect(Department::query()->whereKey('47')->exists())->toBeTrue()
        ->and(Department::query()->whereKey('05')->exists())->toBeTrue()
        ->and(Municipality::query()->whereKey('47001')->value('name'))->toBe('SANTA MARTA')
        ->and(Municipality::query()->whereKey('47189')->value('name'))->toBe('CIÉNAGA')
        ->and(Municipality::query()->whereKey('05001')->value('name'))->toBe('MEDELLÍN')
        ->and(Municipality::query()->whereKey('47001')->value('department_code'))->toBe('47')
        ->and(Municipality::query()->whereKey('05001')->value('department_code'))->toBe('05');
});

it('running the seeder twice does not duplicate rows', function () {
    (new DivipolaSeeder)->run();
    (new DivipolaSeeder)->run();

    $source = json_decode(file_get_contents(database_path('data/divipola.json')), associative: true);

    expect(Municipality::query()->count())->toBe(count($source['municipios']));
});
