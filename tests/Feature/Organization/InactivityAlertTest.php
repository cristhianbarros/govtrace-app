<?php

use App\Application\Organization\ConfigureTerritory;
use App\Application\Organization\RegisterOrganization;
use App\Application\Sealing\SealingNetwork;
use App\Domain\Organization\Notifications\OrganizationInactive;
use App\Domain\Organization\Roles;
use App\Infrastructure\Tenancy\Tenant;
use App\Jobs\CheckOrganizationActivity;
use App\Models\User as SuperAdmin;
use Database\Seeders\DivipolaSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\Support\FakeSealingNetwork;

/*
 * Iteración 34 — US-054-RPT (features/US-054-RPT.feature, 4 casos): cada
 * día se revisa la actividad de las organizaciones activas; la que lleva
 * 30 días sin recibir ni publicar evidencias le llega al Super
 * Administrador por Email. Iniciar sesión no es actividad.
 *
 * Sin RefreshDatabase — registrar la organización ejecuta CREATE DATABASE.
 */

const CIENAGA_INACTIVE = 'La organización Veeduría Ciénaga no recibe ni publica evidencias desde hace 30 días (última actividad: 30/08/2026).';

beforeEach(function () {
    $this->artisan('migrate');
    (new DivipolaSeeder)->run();
    Storage::fake('evidencias');
    Notification::fake();
    app()->instance(SealingNetwork::class, new FakeSealingNetwork);
    $this->superAdmin = SuperAdmin::factory()->create();

    // "Veeduría Ciénaga", desde julio; su última evidencia llegó el 30 de agosto.
    Carbon::setTestNow('2026-07-01 12:00:00');
    $this->tenant = (new RegisterOrganization)->handle('890000062-6', 'Veeduría Ciénaga', 'veeduria-cienaga');
    (new ConfigureTerritory)->handle($this->tenant, ['47']);
    $this->administrator = reportingMember($this->tenant, 'ana.perez@veeduria-cienaga.org', Roles::Administrator);
    $this->veedor = reportingMember($this->tenant, 'carlos@correo.co');
    reportableContract('CO1.PCCNTR.1234567');
    worksiteWithContracts($this->tenant, ['CO1.PCCNTR.1234567'], santaMartaWorksiteLocation());

    Carbon::setTestNow('2026-08-30 12:00:00');
    $this->lastReport = sealedReport($this->tenant, $this->veedor);

    // Hoy: 30 días después.
    Carbon::setTestNow('2026-09-29 12:00:00');
});

afterEach(function () {
    if (tenant()) {
        tenancy()->end();
    }

    Tenant::query()->get()->each->delete();
    DB::table('contracts')->delete();
    DB::table('audit_logs')->delete();
    $this->superAdmin->delete();
    Carbon::setTestNow();
});

function reviewActivity(): void
{
    app()->call([new CheckOrganizationActivity, 'handle']);
}

// US-054-RPT ---------------------------------------------------------------------

it('Organización sin recibir ni publicar evidencias durante 30 días: the Super Administrador gets an email about it', function () {
    reviewActivity();

    Notification::assertSentTo($this->superAdmin, OrganizationInactive::class, fn (OrganizationInactive $alert, array $channels) => $channels === ['mail']
        && $alert->organization === 'Veeduría Ciénaga'
        && in_array(CIENAGA_INACTIVE, $alert->toMail($this->superAdmin)->introLines, true));
});

it('Solo recibir o publicar evidencias cuenta como actividad', function (Closure $twoDaysAgo, bool $sent) {
    Carbon::setTestNow('2026-09-27 12:00:00');
    $twoDaysAgo();
    tenancy()->end();
    Carbon::setTestNow('2026-09-29 12:00:00');

    reviewActivity();

    $sent ? Notification::assertSentTo($this->superAdmin, OrganizationInactive::class)
        : Notification::assertNotSentTo($this->superAdmin, OrganizationInactive::class);
})->with([
    'su administrador inició sesión: se envía' => [fn () => test()->post('http://veeduria-cienaga.govtrace.localhost/login', ['email' => 'ana.perez@veeduria-cienaga.org', 'password' => 'Veeduria#2026'])->assertSessionHasNoErrors(), true],
    'recibió una evidencia: no se envía' => [fn () => sendReport(test()->veedor, [], 'veeduria-cienaga.govtrace.localhost')->assertCreated(), false],
    'su administrador publicó una evidencia: no se envía' => [fn () => editorialDecision(test()->administrator, 'publish', test()->lastReport, [], 'veeduria-cienaga.govtrace.localhost')->assertOk(), false],
]);

// Reglas de US-054-RPT -------------------------------------------------------------

it('does not alert a minute before the 30 days', function () {
    Carbon::setTestNow('2026-09-29 11:59:00'); // 29 días y 23 horas 59 después del 30 de agosto a mediodía

    reviewActivity();

    Notification::assertNothingSent();
});

it('alerts once while the organization stays inactive, and again after it comes back and stops', function () {
    reviewActivity();
    Carbon::setTestNow('2026-09-30 12:00:00');
    reviewActivity();

    // Vuelve: recibe una evidencia…
    sendReport($this->veedor, [], 'veeduria-cienaga.govtrace.localhost')->assertCreated();
    tenancy()->end();
    // …y se detiene otros 30 días.
    Carbon::setTestNow('2026-10-30 12:00:00');
    reviewActivity();

    Notification::assertSentToTimes($this->superAdmin, OrganizationInactive::class, 2);
});

it('counts from the day it was registered when it never had activity', function () {
    Carbon::setTestNow('2026-09-01 12:00:00');
    $new = (new RegisterOrganization)->handle('900123456-8', 'Veeduría Ciudadana Santa Marta', 'veeduria-smr');
    Carbon::setTestNow('2026-09-29 12:00:00');

    reviewActivity();

    Notification::assertNotSentTo($this->superAdmin, OrganizationInactive::class, fn (OrganizationInactive $alert) => $alert->organization === $new->displayName());
});

it('leaves out a suspended or decommissioned organization: it is not expected to work', function (string $status) {
    $this->tenant->update(['status' => $status]);

    reviewActivity();

    Notification::assertNothingSent();
})->with(['suspended', 'decommissioned']);

it('reviews the activity every day', function () {
    Artisan::call('schedule:list', ['--timezone' => 'America/Bogota']); // it. 45a: la hora de Colombia

    expect(Artisan::output())->toMatch('/0\s+8\s+\*\s+\*\s+\*\s+organization-activity-check/');
});
