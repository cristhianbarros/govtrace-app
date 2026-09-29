<?php

use App\Application\Organization\ConfigureTerritory;
use App\Application\Organization\RegisterOrganization;
use App\Infrastructure\Tenancy\Tenant;
use App\Jobs\SyncSecopContracts;
use Database\Seeders\DivipolaSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

/*
 * Iteración 30 — hallazgo de la prueba de extremo a extremo: con la cola en
 * la base de datos (QUEUE_CONNECTION=database, como en desarrollo y en
 * producción), un trabajo despachado dentro de una organización se buscaba
 * guardar en SU base, que no tiene tabla "jobs": crear un reporte respondía
 * 500. Los tests usan la cola "sync" y no lo veían. La cola vive en la base
 * central; el trabajo lleva consigo su organización (QueueTenancyBootstrapper).
 *
 * Sin RefreshDatabase — registrar la organización ejecuta CREATE DATABASE.
 */

beforeEach(function () {
    $this->artisan('migrate');
    (new DivipolaSeeder)->run();
    Storage::fake('evidencias');
    Notification::fake();
    config(['queue.default' => 'database']);
    // tests/Pest.php finge toda la cola; aquí solo la sincronización con SECOP (no sale a internet):
    // el sellado llega a la cola de verdad.
    Queue::fake([SyncSecopContracts::class]);

    $this->tenant = (new RegisterOrganization)->handle('900123456-8', 'Veeduría Ciudadana Santa Marta', 'veeduria-smr');
    (new ConfigureTerritory)->handle($this->tenant, ['47']);
    $this->veedor = reportingMember($this->tenant, 'carlos@correo.co');
    reportableContract('CO1.PCCNTR.1234567');
    worksiteWithContracts($this->tenant, ['CO1.PCCNTR.1234567'], santaMartaWorksiteLocation());
    DB::table('jobs')->delete();
});

afterEach(function () {
    if (tenant()) {
        tenancy()->end();
    }

    Tenant::query()->get()->each->delete();
    DB::table('contracts')->delete();
    DB::table('jobs')->delete();
    DB::table('audit_logs')->delete();
});

it('takes a report with the database queue, as in production: its jobs wait in the central database, with their organization', function () {
    $this->flushSession();
    sendReport($this->veedor)->assertCreated();
    tenancy()->end();

    $jobs = DB::table('jobs')->pluck('payload');
    expect($jobs)->not->toBeEmpty()
        ->and($jobs->first())->toContain($this->tenant->id);
});
