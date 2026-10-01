<?php

use App\Application\Organization\ConfigureTerritory;
use App\Application\Organization\InviteObserver;
use App\Application\Organization\RegisterOrganization;
use App\Application\Sealing\SealingNetwork;
use App\Domain\Audit\AuditLog;
use App\Domain\Configuration\Parameters;
use App\Domain\Configuration\ParameterValue;
use App\Domain\Contracts\Contract;
use App\Domain\Organization\Notifications\WelcomeNotification;
use App\Domain\Organization\Roles;
use App\Domain\Organization\User as OrganizationUser;
use App\Infrastructure\Scheduling\NightlySchedule;
use App\Infrastructure\Tenancy\Tenant;
use App\Models\User as SuperAdmin;
use Database\Seeders\DivipolaSeeder;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\FakeSealingNetwork;

/*
 * Iteración 21 — Parámetros operativos configurables (specs/PLAN.md).
 * Traduce features/US-038-CFG.feature (10 casos) contra el panel global:
 * GET /admin/parameters/data y PUT /admin/parameters/{clave}.
 *
 * "El sistema usa el nuevo valor sin un nuevo despliegue": cada fila
 * comprueba el código que usa el parámetro, no solo que se guardó.
 *
 * Los parámetros viven en la base central, que no se limpia sola: cada test
 * borra las versiones que agregó.
 *
 * Sin RefreshDatabase — registrar la organización ejecuta CREATE DATABASE.
 */

beforeEach(function () {
    $this->artisan('migrate');
    (new DivipolaSeeder)->run();
    Storage::fake('evidencias');
    Notification::fake();
    app()->instance(SealingNetwork::class, new FakeSealingNetwork);
    $this->lastParameterVersion = (int) ParameterValue::query()->max('id');

    $this->tenant = (new RegisterOrganization)->handle('900123456-8', 'Veeduría Ciudadana Santa Marta', 'veeduria-smr');
    (new ConfigureTerritory)->handle($this->tenant, ['47']);
    $this->administrator = reportingMember($this->tenant, 'ana.perez@veeduria-smr.org', Roles::Administrator);
    $this->veedor = reportingMember($this->tenant, 'carlos@correo.co');
    reportableContract('CO1.PCCNTR.1234567');
    worksiteWithContracts($this->tenant, ['CO1.PCCNTR.1234567'], santaMartaWorksiteLocation());
    $this->superAdmin = SuperAdmin::factory()->create();
});

afterEach(function () {
    if (tenant()) {
        tenancy()->end();
    }

    Tenant::query()->get()->each->delete();
    DB::table('contracts')->delete();
    DB::table('audit_logs')->delete();
    ParameterValue::query()->where('id', '>', $this->lastParameterVersion)->delete();
    $this->superAdmin->delete();
});

function changeParameter(string $key, string $value): TestResponse
{
    if (tenant()) {
        tenancy()->end();
    }

    return test()->actingAs(test()->superAdmin, 'web')->putJson("http://govtrace.localhost/admin/parameters/{$key}", ['value' => $value]);
}

/** A report 400 m from the worksite, captured at $capturedAt. */
function reportAt400Meters(string $capturedAt): TestResponse
{
    [$latitude, $longitude] = pointMetersNorthOf(santaMartaWorksiteLocation(), 400);

    return sendReport(test()->veedor, ['latitude' => $latitude, 'longitude' => $longitude, 'captured_at' => $capturedAt]);
}

function nightlySyncExpression(): string
{
    $schedule = new Schedule;
    NightlySchedule::register($schedule);

    return collect($schedule->events())->first(fn (Event $event) => $event->description === 'secop-sync-nightly')->expression;
}

it('Ajuste de un parámetro configurable: the system uses the new value without a new deployment, and the log keeps both', function (string $key, string $old, string $new, Closure $usesNewValue) {
    expect(Parameters::current($key))->toBe($old);

    changeParameter($key, $new)
        ->assertOk()
        ->assertJson(['message' => 'Parámetro actualizado. Rige desde este momento, sin un nuevo despliegue.']);

    expect($usesNewValue())->toBeTrue();

    $entry = AuditLog::query()->where('action', 'parameter.changed')->sole();
    expect($entry->actor_type)->toBe('super_admin')
        ->and($entry->actor_id)->toBe((string) $this->superAdmin->id)
        ->and($entry->before)->toBe(['parameter' => $key, 'value' => $old])
        ->and($entry->after)->toBe(['parameter' => $key, 'value' => $new]);
})->with([
    // A 400 m de la obra: con 500 m entraba; con 300 m, no.
    'radio de geocerca' => ['geofence_radius_meters', '500', '300', fn () => reportAt400Meters(now()->toIso8601String())->status() === 422],
    // Un contrato terminado hace 7 meses: con 12 meses se podía reportar; con 6, no.
    'ventana de Terminados y Liquidados' => ['closed_contract_report_window_months', '12', '6', function () {
        $contract = reportableContract('CO1.PCCNTR.7777777', ['status' => 'Terminado', 'end_date' => now()->subMonths(7)->toDateString()]);

        return ! Contract::query()->whereKey($contract->getKey())->reportableAt(now())->exists();
    }],
    // Una invitación nueva vence a las 72 horas.
    'vigencia de invitaciones' => ['invitation_validity_hours', '48', '72', function () {
        // Se lee dentro de la organización: Eloquent necesita su conexión para interpretar la fecha.
        $expiresAt = test()->tenant->run(fn () => (new InviteObserver)->handle('laura@correo.co')->invitation_expires_at);

        return abs(now()->addHours(72)->diffInSeconds($expiresAt)) < 60;
    }],
    // La alerta de saldo que la it. 32 dispara con este valor.
    'umbral de saldo de la patrocinadora' => ['sponsor_balance_alert_threshold_xlm', '50', '80', fn () => Parameters::current('sponsor_balance_alert_threshold_xlm') === '80'],
    // La próxima corrida de schedule:run (un proceso nuevo cada minuto) la programa a las 03:30.
    'hora de sincronización' => ['secop_sync_hour', '02:00', '03:30', fn () => nightlySyncExpression() === '30 3 * * *'],
]);

it('Los parámetros fijos no se pueden configurar', function (string $key, string $label) {
    $panel = $this->actingAs($this->superAdmin, 'web')->getJson('http://govtrace.localhost/admin/parameters/data')->assertOk()->json();

    expect(array_column($panel['configurable'], 'key'))->not->toContain($key)
        ->and(array_column($panel['fixed'], 'label'))->toContain($label);

    changeParameter($key, '100')->assertNotFound();
})->with([
    'precisión mínima del GPS' => ['gps_max_accuracy_meters', 'Precisión mínima del GPS'],
    'archivos por reporte' => ['files_per_report', 'Archivos por reporte'],
    'vigencia de reportes sin conexión' => ['offline_validity_days', 'Vigencia de reportes sin conexión'],
]);

it('Un Administrador de Organización no puede cambiar parámetros globales', function () {
    $this->actingAs($this->administrator, 'tenant')
        ->putJson('http://govtrace.localhost/admin/parameters/geofence_radius_meters', ['value' => '300'])
        ->assertUnauthorized();
    $this->actingAs($this->administrator, 'tenant')
        ->putJson('http://veeduria-smr.govtrace.localhost/admin/parameters/geofence_radius_meters', ['value' => '300'])
        ->assertNotFound();

    expect(Parameters::current('geofence_radius_meters'))->toBe('500');
});

it('Un reporte capturado antes del cambio se valida con el radio vigente al capturar', function () {
    // El veedor tomó la foto hace una hora, sin conexión, a 400 m: regía 500 m.
    $capturedOffline = now()->subHour()->toIso8601String();

    changeParameter('geofence_radius_meters', '300')->assertOk();

    reportAt400Meters($capturedOffline)->assertCreated();
    reportAt400Meters(now()->toIso8601String())
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['location' => 'Se encuentra a 0.4 km de la ubicación oficial de la obra. Para prevenir fraudes, debe acercarse a un radio de 300 metros del proyecto.']);
});

// Reglas derivadas ------------------------------------------------------

it('shows each configurable parameter with its label, unit and current value', function () {
    $panel = $this->actingAs($this->superAdmin, 'web')->getJson('http://govtrace.localhost/admin/parameters/data')->assertOk()->json();

    expect($panel['configurable'])->toBe([
        ['key' => 'geofence_radius_meters', 'label' => 'Radio de geocerca', 'unit' => 'm', 'value' => '500'],
        ['key' => 'closed_contract_report_window_months', 'label' => 'Ventana de Terminados y Liquidados', 'unit' => 'meses', 'value' => '12'],
        ['key' => 'invitation_validity_hours', 'label' => 'Vigencia de invitaciones', 'unit' => 'h', 'value' => '48'],
        ['key' => 'sponsor_balance_alert_threshold_xlm', 'label' => 'Umbral de saldo de la patrocinadora', 'unit' => 'XLM', 'value' => '50'],
        ['key' => 'secop_sync_hour', 'label' => 'Hora de sincronización SECOP (hora de Colombia)', 'unit' => 'HH:MM', 'value' => '02:00'],
    ]);
});

it('rejects a value that does not fit the parameter', function (string $key, string $value, string $message) {
    changeParameter($key, $value)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['value' => $message]);

    expect(AuditLog::query()->where('action', 'parameter.changed')->exists())->toBeFalse();
})->with([
    ['geofence_radius_meters', 'abc', 'El radio de geocerca debe ser un número entero de metros entre 50 y 5000.'],
    ['geofence_radius_meters', '20', 'El radio de geocerca debe ser un número entero de metros entre 50 y 5000.'],
    ['closed_contract_report_window_months', '0', 'La ventana de Terminados y Liquidados debe ser un número entero de meses entre 1 y 60.'],
    ['invitation_validity_hours', '0', 'La vigencia de invitaciones debe ser un número entero de horas entre 1 y 720.'],
    ['sponsor_balance_alert_threshold_xlm', '-1', 'El umbral de saldo de la patrocinadora debe ser un número de XLM mayor que 0.'],
    ['secop_sync_hour', '25:00', 'La hora de sincronización SECOP debe tener el formato HH:MM, entre 00:00 y 23:59.'],
]);

it('serves the parameters screen to the Super Administrador', function () {
    $this->withoutVite()->actingAs($this->superAdmin, 'web')
        ->get('http://govtrace.localhost/admin/parameters')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('SuperAdmin/Parameters'));
});

it('writes the invitation validity in the welcome email', function () {
    changeParameter('invitation_validity_hours', '72')->assertOk();

    $this->tenant->run(fn () => (new InviteObserver)->handle('laura@correo.co'));

    $laura = $this->tenant->run(fn () => OrganizationUser::query()->where('email', 'laura@correo.co')->sole());

    Notification::assertSentTo($laura, WelcomeNotification::class, function (WelcomeNotification $notification) use ($laura) {
        $mail = $notification->toMail($laura);

        return in_array('Este enlace expira en 72 horas.', [...$mail->introLines, ...$mail->outroLines], true);
    });
});
