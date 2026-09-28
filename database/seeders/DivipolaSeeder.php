<?php

namespace Database\Seeders;

use App\Domain\Geography\Department;
use App\Domain\Geography\Municipality;
use Illuminate\Database\Seeder;

/**
 * Loads the official DIVIPOLA reference data (DANE, dataset gdxc-w37w on
 * datos.gov.co — see database/data/divipola.json) into the central
 * departments/municipalities tables. Central data, run once per
 * environment: php artisan db:seed --class=DivipolaSeeder.
 */
class DivipolaSeeder extends Seeder
{
    public function run(): void
    {
        $data = json_decode(
            file_get_contents(database_path('data/divipola.json')),
            associative: true,
        );

        foreach ($data['departamentos'] as $department) {
            Department::query()->updateOrCreate(
                ['code' => $department['codigo']],
                ['name' => $department['nombre']],
            );
        }

        foreach ($data['municipios'] as $municipality) {
            Municipality::query()->updateOrCreate(
                ['code' => $municipality['codigo']],
                [
                    'name' => $municipality['nombre'],
                    'department_code' => $municipality['departamento_codigo'],
                    'latitude' => $municipality['latitud'],
                    'longitude' => $municipality['longitud'],
                ],
            );
        }
    }
}
