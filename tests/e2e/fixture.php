<?php

/*
 * make e2e — lo que la prueba de extremo a extremo necesita en la base de
 * desarrollo (la que usa la app de make up): la organización
 * "veeduria-e2e", un veedor con contraseña, un contrato de Santa Marta y su
 * obra anclada. Se vuelve a crear en cada corrida. Corre dentro del
 * contenedor app: docker compose exec app php tests/e2e/fixture.php
 */

use App\Application\Organization\ConfigureTerritory;
use App\Application\Organization\RegisterOrganization;
use App\Domain\Contracts\Contract;
use App\Domain\Organization\Roles;
use App\Domain\Organization\User;
use App\Domain\Worksites\Worksite;
use App\Infrastructure\Tenancy\Tenant;
use Database\Seeders\DivipolaSeeder;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

const E2E_SUBDOMAIN = 'veeduria-e2e';
const E2E_CONTRACT = 'CO1.PCCNTR.9990001';

const E2E_NIT = '901555777-4';

// DIVIPOLA, por si la base de desarrollo nunca lo cargó (el territorio lo necesita).
(new DivipolaSeeder)->run();

// La de la corrida anterior — también una que quedó a medias, sin dominio.
Tenant::query()
    ->where('nit', E2E_NIT)
    ->orWhereHas('domains', fn ($domains) => $domains->where('domain', E2E_SUBDOMAIN.'.govtrace.localhost'))
    ->get()
    ->each->delete();

$tenant = (new RegisterOrganization)->handle(E2E_NIT, 'Veeduría de Pruebas E2E', E2E_SUBDOMAIN);
(new ConfigureTerritory)->handle($tenant, ['47']);

Contract::fromSecop(fn () => Contract::query()->updateOrCreate(['secop_contract_id' => E2E_CONTRACT], [
    'entity_name' => 'Alcaldía Distrital de Santa Marta',
    'contractor_name' => 'Constructora de Pruebas S.A.S.',
    'object' => 'Parque de pruebas sin conexión',
    'contract_type' => 'Obra',
    'status' => 'En ejecución',
    'signed_at' => now()->subMonths(2)->toDateString(),
    'end_date' => now()->addMonths(6)->toDateString(),
    'department_code' => '47',
    'municipality_code' => '47001',
    'process_number' => 'E2E-001',
]));

$tenant->run(function () {
    $veedor = User::query()->create(['name' => 'Veedor E2E', 'email' => 'e2e.veedor@correo.co', 'password' => 'Veeduria#2026']);
    $veedor->assignRole(Roles::Observer->value);

    $worksite = Worksite::query()->create(['latitude' => 11.2408, 'longitude' => -74.1990, 'located_at' => now()]);
    $worksite->contracts()->create(['secop_contract_id' => E2E_CONTRACT]);
});

echo 'http://'.E2E_SUBDOMAIN.".govtrace.localhost:8080 — e2e.veedor@correo.co\n";
