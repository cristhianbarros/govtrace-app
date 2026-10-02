<?php

/*
 * make e2e y make ux-check — lo que las pruebas en el navegador necesitan en
 * la base de desarrollo (la que usa la app de make up). Se rehace en cada
 * corrida:
 * - un Super Administrador con contraseña conocida;
 * - la organización "veeduria-e2e" (Magdalena): su Administradora, su veedor,
 *   un contrato de Santa Marta y su obra anclada;
 * - cinco reportes del veedor, por el mismo camino que los de su teléfono
 *   (CreateReport, la huella de la foto, el sello): dos publicados, dos por
 *   revisar en la Bandeja y uno rechazado con su motivo. Se sellan aquí
 *   mismo, en la red de sellado en memoria de los tests: sin Stellar y sin
 *   la cola;
 * - y ninguna de las organizaciones que crea el flujo de alta (it. 40a).
 *
 * Corre dentro del contenedor app: docker compose exec app php tests/e2e/fixture.php
 */

use App\Application\Organization\ConfigureTerritory;
use App\Application\Organization\RegisterOrganization;
use App\Application\Publication\EditorialDecisions;
use App\Application\Reports\CreateReport;
use App\Application\Reports\NewReport;
use App\Application\Sealing\SealingNetwork;
use App\Domain\Contracts\Contract;
use App\Domain\Organization\Roles;
use App\Domain\Organization\User;
use App\Domain\Reports\EvidenceUpload;
use App\Domain\Worksites\Worksite;
use App\Infrastructure\Tenancy\Tenant;
use App\Jobs\ConfirmSeal;
use App\Jobs\SealReport;
use App\Jobs\SendReviewDigests;
use App\Models\User as SuperAdministrator;
use Database\Seeders\DivipolaSeeder;
use Illuminate\Contracts\Console\Kernel;
use Tests\Support\FakeSealingNetwork;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

const E2E_SUBDOMAIN = 'veeduria-e2e';
const E2E_NIT = '901555777-4';
const E2E_CONTRACT = 'CO1.PCCNTR.9990001';
const E2E_PASSWORD = 'Veeduria#2026';
const E2E_WORKSITE = [11.2408, -74.1990];
// Las que crea el flujo de alta (tests/e2e/flujos): su subdominio y los dos NIT que usa.
const E2E_CREATED_SUBDOMAINS = ['veeduria-e2e-alta'];
const E2E_CREATED_NITS = ['901555888-3', '901555999-2'];
// [clasificación, días atrás, metros al norte de la obra, comentario, foto de database/seeders/demo, decisión]
const E2E_REPORTS = [
    ['Avance', 5, 30, 'Arrancaron las obras del parque.', 1, 'publish'],
    ['Retraso', 4, 35, 'La foto no deja ver la obra.', 5, 'reject'],
    ['Retraso', 3, 45, 'No hay personal en la obra desde el lunes.', 2, 'publish'],
    ['Avance', 2, 25, 'Instalan las luminarias de la cancha.', 3, null],
    ['Abandono', 1, 60, 'La obra quedó sin cerramiento ni vigilancia.', 4, null],
];

// Nada va a la cola del worker: ni la sincronización con SECOP que dispara el
// territorio, ni el sellado. El sello se hace aquí, en la red en memoria.
config(['queue.connections.e2e' => ['driver' => 'null'], 'queue.default' => 'e2e']);
app()->instance(SealingNetwork::class, new FakeSealingNetwork);

// DIVIPOLA, por si la base de desarrollo nunca lo cargó (el territorio lo necesita).
(new DivipolaSeeder)->run();

// Las de la corrida anterior, también una que quedó a medias, sin dominio.
$apex = config('tenancy.apex_domain');
Tenant::query()
    ->whereIn('nit', [E2E_NIT, ...E2E_CREATED_NITS])
    ->orWhereHas('domains', fn ($domains) => $domains->whereIn(
        'domain',
        array_map(fn (string $subdomain) => "{$subdomain}.{$apex}", [E2E_SUBDOMAIN, ...E2E_CREATED_SUBDOMAINS]),
    ))
    ->get()
    ->each->delete();

SuperAdministrator::query()->updateOrCreate(
    ['email' => 'e2e.superadmin@govtrace.test'],
    ['name' => 'Super Administrador E2E', 'password' => E2E_PASSWORD],
)->forceFill(['is_active' => true, 'invitation_token_hash' => null, 'invitation_expires_at' => null])->save();
// It. 46a: los Super Administradores que invita el flujo (e2e.superadmin2.<hora>@govtrace.test).
SuperAdministrator::query()->where('email', 'like', 'e2e.superadmin2.%@govtrace.test')->delete();

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
    foreach ([
        ['Administradora E2E', 'e2e.admin@correo.co', Roles::Administrator, null],
        // US-057-LEG: el veedor ya declaró no tener impedimentos; la veedora nueva lo hace en su flujo (it. 44c).
        ['Veedor E2E', 'e2e.veedor@correo.co', Roles::Observer, now()],
        ['Veedora sin declarar E2E', 'e2e.sin-declarar@correo.co', Roles::Observer, null],
    ] as [$name, $email, $role, $declaredAt]) {
        $member = User::query()->create(['name' => $name, 'email' => $email, 'password' => E2E_PASSWORD]);
        $member->assignRole($role->value);
        $member->forceFill(['impediments_declared_at' => $declaredAt])->save();
    }

    $worksite = Worksite::query()->create(['latitude' => E2E_WORKSITE[0], 'longitude' => E2E_WORKSITE[1], 'located_at' => now()]);
    $worksite->contracts()->create(['secop_contract_id' => E2E_CONTRACT]);
});

foreach (E2E_REPORTS as $number => [$classification, $daysAgo, $metersNorth, $comment, $photo, $decision]) {
    $reportId = $tenant->run(function () use ($classification, $daysAgo, $metersNorth, $comment, $photo, $number) {
        $path = tempnam(sys_get_temp_dir(), 'govtrace-e2e-');
        file_put_contents($path, e2ePhoto($photo, 'GovTrace e2e · reporte '.($number + 1).' · '.microtime(true)));

        try {
            return (new CreateReport)->handle(User::query()->where('email', 'e2e.veedor@correo.co')->firstOrFail(), new NewReport(
                E2E_CONTRACT, $classification, $comment,
                E2E_WORKSITE[0] + rad2deg($metersNorth / 6_371_000), E2E_WORKSITE[1], 10,
                now()->subDays($daysAgo)->subHours($number + 1),
                [EvidenceUpload::fromPath($path)],
            ))->id;
        } finally {
            unlink($path);
        }
    });

    app()->call([new SealReport($tenant->id, $reportId), 'handle']);
    app()->call([new ConfirmSeal($tenant->id, $reportId), 'handle']);
    tenancy()->end();

    if ($decision !== null) {
        $tenant->run(function () use ($decision, $reportId) {
            $administrator = User::query()->where('email', 'e2e.admin@correo.co')->firstOrFail();
            $decision === 'publish'
                ? (new EditorialDecisions)->publish($administrator, $reportId)
                : (new EditorialDecisions)->reject($administrator, $reportId, 'La foto no deja ver la obra. Tómela de nuevo, de día.');
        });
    }
}

// It. 43i (US-060-MON): el resumen diario de lo que espera revisión, de esta organización;
// make e2e lo lee del correo de desarrollo.
app()->call([new SendReviewDigests($tenant->id), 'handle']);

/**
 * A demo photo with a JPEG comment of its own after the JFIF header, so every
 * run seals different bytes (a hash is the identity of an evidence).
 */
function e2ePhoto(int $photo, string $label): string
{
    $jpeg = (string) file_get_contents(database_path("seeders/demo/obra-{$photo}.jpg"));
    $afterJfif = 4 + unpack('n', substr($jpeg, 4, 2))[1];

    return substr($jpeg, 0, $afterJfif)."\xFF\xFE".pack('n', strlen($label) + 2).$label.substr($jpeg, $afterJfif);
}

echo 'http://'.E2E_SUBDOMAIN.".{$apex}:8080 — Super Administrador e2e.superadmin@govtrace.test, Administradora e2e.admin@correo.co, veedor e2e.veedor@correo.co\n";
