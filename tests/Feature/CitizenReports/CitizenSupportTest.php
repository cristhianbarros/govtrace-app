<?php

use App\Application\CitizenReports\CitizenReportDesk;
use App\Application\Organization\ConfigureTerritory;
use App\Application\Organization\RegisterOrganization;
use App\Application\Sealing\SealingNetwork;
use App\Domain\CitizenReports\CitizenReport;
use App\Domain\CitizenReports\Notifications\CitizenReportCode;
use App\Domain\CitizenReports\Notifications\CitizenReportDocumented;
use App\Domain\CitizenReports\Notifications\CitizenVisitAssigned;
use App\Domain\Organization\Roles;
use App\Infrastructure\Tenancy\Tenant;
use Database\Seeders\DivipolaSeeder;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\Support\FakeSealingNetwork;

/*
 * Iteración 66b — "Yo también lo vi" (features/US-069.feature, R-LEG-12). Otro
 * ciudadano se suma a lo que ya se informó de una obra, con su correo
 * verificado, una vez por persona y obra, y recibe los mismos correos. El
 * número lo ven la veeduría y el público, con el estado de lo que hizo la
 * veeduría al lado; nunca los mensajes, las fotos, los correos ni los nombres.
 *
 * Sin RefreshDatabase — registrar la organización ejecuta CREATE DATABASE.
 */

const SUPPORT_HOST = 'http://veeduria-smr.govtrace.localhost';
const REPORTER = 'vecina@correo.co';
const NEIGHBOUR = 'vecino@correo.co';

beforeEach(function () {
    $this->artisan('migrate');
    (new DivipolaSeeder)->run();
    Storage::fake('evidencias');
    app()->instance(SealingNetwork::class, new FakeSealingNetwork);
    Notification::fake();

    $this->tenant = (new RegisterOrganization)->handle('900123456-8', 'Veeduría Ciudadana Santa Marta', 'veeduria-smr');
    (new ConfigureTerritory)->handle($this->tenant, ['47']);
    $this->administrator = reportingMember($this->tenant, 'ana.perez@veeduria-smr.org', Roles::Administrator);
    $this->veedor = reportingMember($this->tenant, 'carlos@correo.co');
    reportableContract('CO1.PCCNTR.1234567');
    $this->worksite = worksiteWithContracts($this->tenant, ['CO1.PCCNTR.1234567'], santaMartaWorksiteLocation());
    $this->report = $this->tenant->run(fn () => CitizenReport::query()->create([
        'worksite_id' => $this->worksite->id,
        'email' => REPORTER,
        'email_hash' => CitizenReportDesk::fingerprint(REPORTER),
        'message' => 'La obra lleva dos semanas sin trabajadores.',
        'photo_paths' => [],
        'status' => 'new',
        'data_authorized_at' => now(),
        'data_policy_version' => '2026-09-30.2',
    ]));
});

afterEach(function () {
    if (tenant()) {
        tenancy()->end();
    }
    Tenant::query()->get()->each->delete();
    DB::table('contracts')->delete();
    DB::table('audit_logs')->delete();
});

/** "Yo también lo vi": the code by mail, then the code. */
function joinTheReport(string $email = NEIGHBOUR): TestResponse
{
    tenancy()->end();
    test()->postJson(SUPPORT_HOST.'/citizen-reports/code', ['email' => $email, 'worksite_id' => test()->worksite->public_id, 'data_authorization' => true])->assertOk();
    $code = null;
    Notification::assertSentOnDemand(CitizenReportCode::class, function (CitizenReportCode $notification, array $channels, AnonymousNotifiable $notifiable) use ($email, &$code) {
        $code = $notifiable->routes['mail'] === $email ? $notification->code : $code;

        return true;
    });
    tenancy()->end();

    return test()->postJson(SUPPORT_HOST.'/citizen-reports/support', ['email' => $email, 'code' => $code, 'worksite_id' => test()->worksite->public_id]);
}

/** What anyone sees of the work: its page, and its card on the map. */
function publicWorksite(string $what = ''): array
{
    tenancy()->end();

    return test()->getJson(SUPPORT_HOST.'/public/worksites/'.test()->worksite->public_id.$what)->assertOk()->json('data');
}

/** How many mails of $notification reached $email. */
function mailsTo(string $email, string $notification): int
{
    $count = 0;
    Notification::assertSentOnDemand($notification, function ($sent, array $channels, AnonymousNotifiable $notifiable) use ($email, &$count) {
        $count += $notifiable->routes['mail'] === $email ? 1 : 0;

        return true;
    });

    return $count;
}

it('Otro ciudadano se suma a lo que ya se informó de una obra: the work counts both, and says what the veeduría is doing', function () {
    expect(publicWorksite()['citizens'])->toBe(['count' => 1, 'status' => 'Esperando respuesta de la veeduría', 'open' => true]);

    joinTheReport()->assertCreated()->assertJson(['message' => 'Quedó sumado: le avisamos a su correo cuando la veeduría actúe.']);

    expect(publicWorksite()['citizens'])->toBe(['count' => 2, 'status' => 'Esperando respuesta de la veeduría', 'open' => true])
        ->and($this->tenant->run(fn () => $this->report->supports()->count()))->toBe(1);
});

it('Cada persona se suma una sola vez por obra: neither twice, nor whoever already reported it', function () {
    joinTheReport()->assertCreated();

    $already = 'Usted ya informó sobre esta obra: le avisamos a su correo cuando la veeduría actúe.';
    joinTheReport()->assertUnprocessable()->assertJsonValidationErrors(['email' => $already]);
    joinTheReport(REPORTER)->assertUnprocessable()->assertJsonValidationErrors(['email' => $already]);
    expect(publicWorksite()['citizens']['count'])->toBe(2);
});

it('refuses to join a work without open reports, and a wrong code', function () {
    $this->tenant->run(fn () => $this->report->update(['status' => 'answered']));

    joinTheReport()->assertUnprocessable()->assertJsonValidationErrors(['worksite_id' => 'Esta obra no tiene informes abiertos. Si vio algo nuevo, infórmeselo a la veeduría.']);

    $this->tenant->run(fn () => $this->report->update(['status' => 'new']));
    tenancy()->end();
    $this->postJson(SUPPORT_HOST.'/citizen-reports/support', ['email' => 'otra@correo.co', 'code' => '000000', 'worksite_id' => $this->worksite->public_id])
        ->assertUnprocessable()->assertJsonValidationErrors(['code' => 'El código no es válido o ya venció. Pida uno nuevo.']);
});

it('Quien se suma recibe los mismos correos: the visit and the published evidence reach both', function () {
    joinTheReport()->assertCreated();
    tenancy()->end();
    $this->actingAs($this->administrator, 'tenant')->postJson(SUPPORT_HOST."/citizen-reports/{$this->report->public_id}/assign", ['observer' => $this->veedor->public_id])->assertOk();
    $reportId = sealedReport($this->tenant, $this->veedor);
    editorialDecision($this->administrator, 'publish', $reportId)->assertOk();

    expect(mailsTo(REPORTER, CitizenVisitAssigned::class))->toBe(1)
        ->and(mailsTo(NEIGHBOUR, CitizenVisitAssigned::class))->toBe(1)
        ->and(mailsTo(REPORTER, CitizenReportDocumented::class))->toBe(1)
        ->and(mailsTo(NEIGHBOUR, CitizenReportDocumented::class))->toBe(1);
});

it('La veeduría ve cuántos ciudadanos lo vieron: whoever reported and whoever joined', function () {
    joinTheReport()->assertCreated();
    joinTheReport('vecina2@correo.co')->assertCreated();

    tenancy()->end();
    expect($this->actingAs($this->administrator, 'tenant')->getJson(SUPPORT_HOST.'/citizen-reports')->json('data.0.citizens'))->toBe(3);
});

it('El número público es solo eso: sin mensajes, fotos ni nombres — on the page of the work and on its card', function () {
    joinTheReport()->assertCreated();
    joinTheReport('vecina2@correo.co')->assertCreated();
    tenancy()->end();
    $this->actingAs($this->administrator, 'tenant')->postJson(SUPPORT_HOST."/citizen-reports/{$this->report->public_id}/assign", ['observer' => $this->veedor->public_id])->assertOk();

    $page = publicWorksite();
    $card = publicWorksite('/card');
    $voices = ['count' => 3, 'status' => 'Un veedor la está documentando', 'open' => true];
    expect($page['citizens'])->toBe($voices)
        ->and($card['citizens'])->toBe($voices);
    foreach ([json_encode($page), json_encode($card)] as $public) {
        expect($public)->not->toContain('dos semanas sin trabajadores')
            ->not->toContain('correo.co')
            ->not->toContain('vecin');
    }
});

it('Un informe descartado no cuenta en público', function () {
    tenancy()->end();
    $this->actingAs($this->administrator, 'tenant')->postJson(SUPPORT_HOST."/citizen-reports/{$this->report->public_id}/discard")->assertOk();

    expect(publicWorksite()['citizens'])->toBeNull()
        ->and(publicWorksite('/card')['citizens'])->toBeNull();
});
