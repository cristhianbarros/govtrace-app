<?php

use App\Application\Organization\RegisterOrganization;
use App\Domain\Audit\AuditLog;
use App\Domain\Configuration\ParameterValue;
use App\Domain\Contracts\Contract;
use App\Domain\Contracts\SecopSyncRun;
use App\Domain\Geography\Department;
use App\Domain\Geography\Municipality;
use App\Domain\Organization\OrganizationTerritory;
use App\Infrastructure\Tenancy\Tenant;
use Database\Seeders\DivipolaSeeder;

/*
 * Iteración 10 — verificación técnica de la plomería multi-tenant. Dentro
 * del contexto de un tenant, Stancl apunta la conexión por defecto a la
 * base de ESA organización; un modelo de una tabla central que no declare
 * su conexión la buscaría ahí y fallaría. La it. 9 lo encontró con
 * Contract; crear un reporte (it. 10) lee territorio y parámetros desde
 * el contexto del veedor. Todo modelo central declara CentralConnection.
 */

it('keeps every central model on the central connection', function (string $model) {
    expect((new $model)->getConnectionName())->toBe(config('tenancy.database.central_connection'));
})->with([
    Contract::class,
    SecopSyncRun::class,
    Department::class,
    Municipality::class,
    OrganizationTerritory::class,
    ParameterValue::class,
    AuditLog::class,
]);

it('reads central tables from inside a tenant context', function () {
    $this->artisan('migrate');
    (new DivipolaSeeder)->run();
    $tenant = (new RegisterOrganization)->handle('900123456-8', 'Veeduría Ciudadana Santa Marta', 'veeduria-smr');

    [$departments, $radius] = $tenant->run(fn () => [
        Department::query()->count(),
        ParameterValue::query()->where('key', 'geofence_radius_meters')->value('value'),
    ]);

    expect($departments)->toBe(33)
        ->and($radius)->toBe('500');

    $tenant->delete();
});
