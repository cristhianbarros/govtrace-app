<?php

use App\Application\Organization\ConfigureTerritory;
use App\Application\Organization\RegisterOrganization;
use App\Domain\Audit\AuditLog;
use App\Domain\Calendar\BusinessDays;
use App\Domain\Calendar\ColombianHolidays;
use App\Domain\CitizenReports\CitizenReport;
use App\Domain\CitizenReports\Notifications\CitizenReportCode;
use App\Domain\CitizenReports\Notifications\CitizenReportOverdue;
use App\Domain\CitizenReports\Notifications\CitizenReportReceived;
use App\Domain\Organization\Roles;
use App\Infrastructure\Tenancy\Tenant;
use App\Jobs\EscalateOverdueCitizenReports;
use Carbon\CarbonImmutable;
use Database\Seeders\DivipolaSeeder;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;

/*
 * Iteración 66a — el plazo de respuesta (features/US-069.feature, R-LEG-12).
 * Cada veeduría fija en cuántos días hábiles responde a un informe ciudadano
 * (15 por defecto): una sugerencia de GovTrace, no un plazo legal. No se
 * publica: el ciudadano lo conoce al enviar y en su correo; el Administrador,
 * en su resumen; y si vence sin una primera acción, al ciudadano le llega el
 * camino a la Contraloría.
 *
 * Sin RefreshDatabase — registrar la organización ejecuta CREATE DATABASE.
 */

const DEADLINE_HOST = 'http://veeduria-smr.govtrace.localhost';
const DEADLINE_CITIZEN = 'vecina@correo.co';

beforeEach(function () {
    $this->artisan('migrate');
    (new DivipolaSeeder)->run();
    Storage::fake('evidencias');
    Notification::fake();

    $this->tenant = (new RegisterOrganization)->handle('900123456-8', 'Veeduría Ciudadana Santa Marta', 'veeduria-smr');
    (new ConfigureTerritory)->handle($this->tenant, ['47']);
    $this->administrator = reportingMember($this->tenant, 'ana.perez@veeduria-smr.org', Roles::Administrator);
    $this->veedor = reportingMember($this->tenant, 'carlos@correo.co');
    reportableContract('CO1.PCCNTR.1234567');
    $this->worksite = worksiteWithContracts($this->tenant, ['CO1.PCCNTR.1234567'], santaMartaWorksiteLocation());
});

afterEach(function () {
    if (tenant()) {
        tenancy()->end();
    }
    Tenant::query()->get()->each->delete();
    DB::table('contracts')->delete();
    DB::table('audit_logs')->delete();
});

/** A citizen sends a report, through the code by mail, at the time the test travelled to. */
function informTheVeeduria(string $email = DEADLINE_CITIZEN): TestResponse
{
    tenancy()->end();
    test()->postJson(DEADLINE_HOST.'/citizen-reports/code', ['email' => $email, 'worksite_id' => test()->worksite->public_id, 'data_authorization' => true])->assertOk();
    $code = null;
    Notification::assertSentOnDemand(CitizenReportCode::class, function (CitizenReportCode $notification, array $channels, AnonymousNotifiable $notifiable) use ($email, &$code) {
        $code = $notifiable->routes['mail'] === $email ? $notification->code : $code;

        return true;
    });
    tenancy()->end();

    return test()->postJson(DEADLINE_HOST.'/citizen-reports', [
        'email' => $email,
        'code' => $code,
        'worksite_id' => test()->worksite->public_id,
        'message' => 'La obra lleva dos semanas sin trabajadores y el cerramiento se cayó.',
    ])->assertCreated();
}

function saveResponseDays(mixed $days): TestResponse
{
    tenancy()->end();

    return test()->actingAs(test()->administrator, 'tenant')->postJson(DEADLINE_HOST.'/organization/profile', ['display_name' => 'Veeduría Ciudadana Santa Marta', 'citizen_response_days' => $days]);
}

function deadlineReport(): CitizenReport
{
    return test()->tenant->run(fn () => CitizenReport::query()->latest('id')->firstOrFail());
}

it('Los días hábiles no cuentan fines de semana ni festivos: 15 from Friday 30 October 2026 end on Tuesday 24 November', function () {
    expect(ColombianHolidays::of(2026))->toBe([
        '2026-01-01', '2026-01-12', '2026-03-23', '2026-04-02', '2026-04-03', '2026-05-01', '2026-05-18', '2026-06-08', '2026-06-15',
        '2026-06-29', '2026-07-20', '2026-08-07', '2026-08-17', '2026-10-12', '2026-11-02', '2026-11-16', '2026-12-08', '2026-12-25',
    ])
        // Sin los lunes 2 y 16 de noviembre, festivos, ni los sábados y domingos.
        ->and(BusinessDays::after(CarbonImmutable::parse('2026-10-30'), 15)->toDateString())->toBe('2026-11-24')
        ->and(BusinessDays::after(CarbonImmutable::parse('2026-10-30'), 1)->toDateString())->toBe('2026-11-03')
        // Semana Santa de 2027: la Pascua cae el 28 de marzo.
        ->and(ColombianHolidays::of(2027))->toContain('2027-03-25', '2027-03-26', '2027-05-10');
});

it('La veeduría fija en cuántos días hábiles responde, 15 por defecto: the Administrador changes it, and the log keeps it', function () {
    tenancy()->end();
    expect($this->actingAs($this->administrator, 'tenant')->getJson(DEADLINE_HOST.'/organization/profile')->json('data.citizen_response_days'))->toBe(15);

    saveResponseDays(10)->assertOk();

    expect($this->tenant->fresh()->citizenResponseDays())->toBe(10);
    $entry = AuditLog::query()->where('action', 'organization.profile_updated')->latest('id')->firstOrFail();
    expect($entry->before['citizen_response_days'])->toBe(15)
        ->and($entry->after['citizen_response_days'])->toBe(10);
});

it('Un plazo fuera de rango se rechaza', function (mixed $days) {
    saveResponseDays($days)->assertUnprocessable()->assertJsonValidationErrors(['citizen_response_days' => 'El plazo va de 1 a 60 días hábiles.']);
    expect($this->tenant->fresh()->citizenResponseDays())->toBe(15);
})->with(['cero' => [0], 'más de 60' => [61], 'no es un número' => ['quince']]);

it('Al enviar el informe, el ciudadano sabe hasta cuándo responde la veeduría: on the screen, and in the mail with its reference', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-30 10:00', 'America/Bogota'));

    $response = informTheVeeduria();

    $commitment = 'La veeduría se compromete a responderle antes del martes 24 de noviembre de 2026 (15 días hábiles).';
    expect($response->json('commitment'))->toBe($commitment)
        ->and(deadlineReport()->due_on->toDateString())->toBe('2026-11-24');
    Notification::assertSentOnDemand(CitizenReportReceived::class, fn (CitizenReportReceived $notification, array $channels, AnonymousNotifiable $notifiable) => in_array($commitment, $notification->toMail($notifiable)->introLines, true));
});

it('Cambiar el plazo no cambia la fecha de los informes que ya llegaron: the next ones use the new one', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-30 10:00', 'America/Bogota'));
    informTheVeeduria();
    $first = deadlineReport();

    saveResponseDays(10)->assertOk();
    informTheVeeduria('vecino@correo.co');

    expect($first->fresh()->due_on->toDateString())->toBe('2026-11-24')
        ->and(deadlineReport()->due_on->toDateString())->toBe('2026-11-17');
});

it('Si vence sin respuesta, al ciudadano le llega el camino a la Contraloría: with the work, its contract, the entity and what was reported; only once', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-30 10:00', 'America/Bogota'));
    informTheVeeduria();

    $this->travelTo(CarbonImmutable::parse('2026-11-24 23:00', 'America/Bogota'));
    (new EscalateOverdueCitizenReports)->handle();
    Notification::assertNotSentTo(new AnonymousNotifiable, CitizenReportOverdue::class);

    $this->travelTo(CarbonImmutable::parse('2026-11-25 08:00', 'America/Bogota'));
    (new EscalateOverdueCitizenReports)->handle();
    (new EscalateOverdueCitizenReports)->handle();

    Notification::assertSentOnDemandTimes(CitizenReportOverdue::class, 1);
    Notification::assertSentOnDemand(CitizenReportOverdue::class, function (CitizenReportOverdue $notification, array $channels, AnonymousNotifiable $notifiable) {
        $mail = $notification->toMail($notifiable);
        $text = implode(' ', [$mail->subject, ...$mail->introLines, ...$mail->outroLines]);

        return $notifiable->routes['mail'] === DEADLINE_CITIZEN
            && str_contains($text, 'Veeduría Ciudadana Santa Marta aún no ha respondido')
            && str_contains($text, 'Pavimentación Calle 30')
            && str_contains($text, 'CO1.PCCNTR.1234567')
            && str_contains($text, 'Alcaldía Distrital de Santa Marta')
            && str_contains($text, 'La obra lleva dos semanas sin trabajadores')
            && str_contains($text, 'Línea gratuita 199')
            && $mail->actionUrl === 'https://denuncie.contraloria.gov.co:8443/sipar/';
    });
    expect(deadlineReport()->escalated_at)->not->toBeNull();
});

it('Una primera acción a tiempo detiene el plazo: answered, asked for a visit or discarded in time, no mail to the Contraloría, even if the visit goes back to Nuevo', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-30 10:00', 'America/Bogota'));
    informTheVeeduria();
    informTheVeeduria('vecino@correo.co');
    $answered = $this->tenant->run(fn () => CitizenReport::query()->orderBy('id')->first());
    $visited = deadlineReport();

    tenancy()->end();
    $this->actingAs($this->administrator, 'tenant')->postJson(DEADLINE_HOST."/citizen-reports/{$answered->public_id}/answer", ['answer' => 'Gracias, esta semana vamos.'])->assertOk();
    tenancy()->end();
    $this->actingAs($this->administrator, 'tenant')->postJson(DEADLINE_HOST."/citizen-reports/{$visited->public_id}/assign", ['observer' => $this->veedor->public_id])->assertOk();
    tenancy()->end();
    $this->actingAs($this->veedor, 'tenant')->postJson(DEADLINE_HOST."/visits/{$visited->public_id}/unable")->assertOk();

    $this->travelTo(CarbonImmutable::parse('2026-11-26 08:00', 'America/Bogota'));
    (new EscalateOverdueCitizenReports)->handle();

    expect($visited->fresh()->status)->toBe('new')
        ->and($visited->fresh()->first_action_at)->not->toBeNull();
    Notification::assertNotSentTo(new AnonymousNotifiable, CitizenReportOverdue::class);
    Notification::assertSentOnDemandTimes(CitizenReportOverdue::class, 0);
});

it('looks at the deadlines every morning at 08:00 in Colombia, and leaves out a suspended organization', function () {
    Artisan::call('schedule:list');
    $event = collect(app(Schedule::class)->events())->first(fn (Event $event) => $event->description === 'citizen-reports-deadlines');
    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('0 8 * * *')
        ->and($event->timezone)->toBe('America/Bogota');

    $this->travelTo(CarbonImmutable::parse('2026-10-30 10:00', 'America/Bogota'));
    informTheVeeduria();
    $this->tenant->update(['status' => 'suspended']);
    $this->travelTo(CarbonImmutable::parse('2026-12-01 08:00', 'America/Bogota'));
    (new EscalateOverdueCitizenReports)->handle();

    Notification::assertSentOnDemandTimes(CitizenReportOverdue::class, 0);
});
