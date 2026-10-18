<?php

use App\Application\Organization\ConfigureTerritory;
use App\Application\Organization\RegisterOrganization;
use App\Application\Sealing\SealingNetwork;
use App\Domain\Audit\AuditLog;
use App\Domain\CitizenReports\CitizenReport;
use App\Domain\CitizenReports\Notifications\CitizenReportDocumented;
use App\Domain\CitizenReports\Notifications\CitizenVisitAssigned;
use App\Domain\Organization\Roles;
use App\Domain\Organization\User as OrganizationUser;
use App\Domain\Worksites\Worksite;
use App\Infrastructure\Tenancy\Tenant;
use Database\Seeders\DivipolaSeeder;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\Support\FakeSealingNetwork;

/*
 * Iteración 65b — del informe ciudadano a una evidencia (features/US-068.feature,
 * R-LEG-11). El Administrador le pide la visita a un veedor (el último que
 * reportó la obra viene elegido); el veedor la ve en "Nuevo reporte", con el
 * mensaje y las fotos del ciudadano, nunca su correo; su próximo reporte de esa
 * obra queda ligado al informe. Publicada la evidencia, el informe queda
 * "Documentado". Al ciudadano le llega un correo al asignar y otro al publicar.
 *
 * Sin RefreshDatabase — registrar la organización ejecuta CREATE DATABASE.
 */

const VISITS_HOST = 'http://veeduria-smr.govtrace.localhost';
const VISIT_CITIZEN = 'vecina@correo.co';

beforeEach(function () {
    $this->artisan('migrate');
    (new DivipolaSeeder)->run();
    Storage::fake('evidencias');
    app()->instance(SealingNetwork::class, new FakeSealingNetwork);
    Notification::fake();

    $this->tenant = (new RegisterOrganization)->handle('900123456-8', 'Veeduría Ciudadana Santa Marta', 'veeduria-smr');
    (new ConfigureTerritory)->handle($this->tenant, ['47']);
    $this->administrator = reportingMember($this->tenant, 'ana.perez@veeduria-smr.org', Roles::Administrator);
    $this->carlos = namedVeedor($this->tenant, 'carlos@correo.co', 'Carlos Rojas');
    $this->lucia = namedVeedor($this->tenant, 'lucia@correo.co', 'Lucía Méndez');
    reportableContract('CO1.PCCNTR.1234567');
    $this->worksite = worksiteWithContracts($this->tenant, ['CO1.PCCNTR.1234567'], santaMartaWorksiteLocation());
    $this->tenant->run(fn () => Storage::disk('evidencias')->put('citizen/foto-1.jpg', 'foto'));
    $this->citizenReport = $this->tenant->run(fn () => CitizenReport::query()->create([
        'worksite_id' => $this->worksite->id,
        'email' => VISIT_CITIZEN,
        'email_hash' => hash('sha256', VISIT_CITIZEN),
        'message' => 'La obra lleva dos semanas sin trabajadores.',
        'photo_paths' => ['citizen/foto-1.jpg', 'citizen/foto-1.jpg'],
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

function namedVeedor(Tenant $tenant, string $email, string $name): OrganizationUser
{
    $veedor = reportingMember($tenant, $email);
    $tenant->run(fn () => $veedor->forceFill(['name' => $name])->save());

    return $veedor;
}

/** GET /citizen-reports, as the Administrador. */
function citizenReportsList(array $query = []): TestResponse
{
    tenancy()->end();

    return test()->actingAs(test()->administrator, 'tenant')->getJson(VISITS_HOST.'/citizen-reports?'.http_build_query($query))->assertOk();
}

/** POST /citizen-reports/{id}/assign, as the Administrador. */
function assignVisit(?OrganizationUser $veedor): TestResponse
{
    tenancy()->end();

    return test()->actingAs(test()->administrator, 'tenant')->postJson(VISITS_HOST.'/citizen-reports/'.test()->citizenReport->public_id.'/assign', $veedor ? ['observer' => $veedor->public_id] : []);
}

/** The visits $veedor sees in "Nuevo reporte". */
function visitsOf(OrganizationUser $veedor): array
{
    tenancy()->end();
    test()->flushSession();
    $page = test()->withoutVite()->actingAs($veedor, 'tenant')->get(VISITS_HOST.'/reports/new')->assertOk()->viewData('page');
    tenancy()->end();

    return $page['props']['visits'];
}

function freshCitizenReport(): CitizenReport
{
    return test()->tenant->run(fn () => test()->citizenReport->fresh());
}

it('El Administrador pide a un veedor que documente un informe: the last veedor who reported the work comes chosen; assigning emails the citizen, without the veedor, and is audited', function () {
    sealedReport($this->tenant, $this->lucia);
    sealedReport($this->tenant, $this->carlos);

    $listed = citizenReportsList(['status' => 'new']);
    expect($listed->json('data.0.visit.suggested'))->toBe($this->carlos->public_id)
        ->and(array_column($listed->json('observers'), 'name'))->toBe(['Carlos Rojas', 'Lucía Méndez']);

    assignVisit($this->carlos)->assertOk()->assertJson(['message' => 'Le pedimos la visita a Carlos Rojas. Al ciudadano le avisamos que un veedor va a visitar la obra.']);

    $report = freshCitizenReport();
    expect($report->status)->toBe('assigned')
        ->and($report->assigned_to)->toBe($this->carlos->id);
    expect(citizenReportsList(['status' => 'assigned'])->json('data.0'))->toMatchArray(['status' => 'assigned', 'status_label' => 'Visita asignada'])
        ->and(citizenReportsList(['status' => 'assigned'])->json('data.0.visit.observer'))->toBe(['id' => $this->carlos->public_id, 'name' => 'Carlos Rojas']);
    Notification::assertSentOnDemand(CitizenVisitAssigned::class, function (CitizenVisitAssigned $notification, array $channels, AnonymousNotifiable $notifiable) {
        $mail = $notification->toMail($notifiable);
        $text = implode(' ', [$mail->subject, ...$mail->introLines, ...$mail->outroLines]);

        return $notifiable->routes['mail'] === VISIT_CITIZEN
            && str_contains($text, 'Un veedor de Veeduría Ciudadana Santa Marta va a visitar la obra')
            && ! str_contains($text, 'Carlos');
    });
    expect(AuditLog::query()->where('action', 'citizen_report.visit_assigned')->sole()->after)->toMatchArray(['citizen_report_id' => $report->id, 'observer_id' => $this->carlos->id]);
});

it('Si nadie ha reportado la obra, el Administrador elige al veedor: none comes chosen, and assigning without one is refused', function () {
    expect(citizenReportsList(['status' => 'new'])->json('data.0.visit.suggested'))->toBeNull();

    assignVisit(null)->assertUnprocessable()->assertJsonValidationErrors(['observer' => 'Elija al veedor que va a visitar la obra.']);
    expect(freshCitizenReport()->status)->toBe('new');
});

it('Solo un veedor activo que ya declaró sus impedimentos se puede elegir: a deactivated one and one who did not declare are not offered, nor accepted', function () {
    $this->tenant->run(function () {
        $this->lucia->forceFill(['is_active' => false])->save();
        $this->carlos->forceFill(['impediments_declared_at' => null])->save();
    });
    $sofia = namedVeedor($this->tenant, 'sofia@correo.co', 'Sofía Pardo');

    expect(array_column(citizenReportsList()->json('observers'), 'name'))->toBe(['Sofía Pardo']);
    assignVisit($this->lucia)->assertUnprocessable()->assertJsonValidationErrors('observer');
    assignVisit($this->carlos)->assertUnprocessable()->assertJsonValidationErrors('observer');
    assignVisit($sofia)->assertOk();
});

it('El veedor ve su visita pendiente, con el mensaje y las fotos del ciudadano: in Nuevo reporte, with the work to report, and never the email', function () {
    assignVisit($this->carlos)->assertOk();

    $visits = visitsOf($this->carlos);
    expect($visits)->toHaveCount(1)
        ->and($visits[0])->toMatchArray([
            'id' => $this->citizenReport->public_id,
            'reference' => $this->citizenReport->reference(),
            'worksite' => 'Pavimentación Calle 30',
            'message' => 'La obra lleva dos semanas sin trabajadores.',
        ])
        ->and($visits[0]['contract'])->toMatchArray(['secop_contract_id' => 'CO1.PCCNTR.1234567', 'object' => 'Pavimentación Calle 30', 'entity_name' => 'Alcaldía Distrital de Santa Marta'])
        ->and($visits[0]['photo_urls'])->toHaveCount(2)
        ->and(json_encode($visits))->not->toContain(VISIT_CITIZEN);

    $this->actingAs($this->carlos, 'tenant')->get(VISITS_HOST.$visits[0]['photo_urls'][0])->assertOk();
});

it('El reporte del veedor sobre esa obra queda ligado al informe: sealed like any report, and the visit leaves the pending ones', function () {
    assignVisit($this->carlos)->assertOk();

    $reportId = sealedReport($this->tenant, $this->carlos);

    $report = freshCitizenReport();
    expect($report->documenting_report_id)->toBe($reportId)
        ->and($report->status)->toBe('assigned')
        ->and(visitsOf($this->carlos))->toBe([]);
});

it('El reporte de otro veedor sobre la obra no se liga al informe: the visit stays pending for Carlos', function () {
    assignVisit($this->carlos)->assertOk();

    sealedReport($this->tenant, $this->lucia);

    expect(freshCitizenReport()->documenting_report_id)->toBeNull()
        ->and(visitsOf($this->carlos))->toHaveCount(1);
});

it('El veedor no puede ir y la visita vuelve al Administrador: Nuevo again, with a note, counted again, and audited', function () {
    assignVisit($this->carlos)->assertOk();
    tenancy()->end();

    $this->actingAs($this->carlos, 'tenant')->postJson(VISITS_HOST.'/visits/'.$this->citizenReport->public_id.'/unable')
        ->assertOk()->assertJson(['message' => 'Listo: la veeduría le pedirá la visita a otro veedor.']);

    $report = freshCitizenReport();
    expect($report->status)->toBe('new')
        ->and($report->assigned_to)->toBeNull()
        ->and(citizenReportsList(['status' => 'new'])->json('data.0.visit.note'))->toBe('Carlos Rojas no pudo ir.')
        ->and(visitsOf($this->carlos))->toBe([])
        ->and(AuditLog::query()->where('action', 'citizen_report.visit_unable')->count())->toBe(1);
});

it('El Administrador le pasa la visita a otro veedor: it leaves Carlos and reaches Lucía, and the citizen gets no second mail', function () {
    assignVisit($this->carlos)->assertOk();
    assignVisit($this->lucia)->assertOk()->assertJson(['message' => 'Le pedimos la visita a Lucía Méndez.']);

    expect(visitsOf($this->carlos))->toBe([])
        ->and(visitsOf($this->lucia))->toHaveCount(1);
    Notification::assertSentOnDemandTimes(CitizenVisitAssigned::class, 1);
});

it('La Bandeja dice a qué informe responde la evidencia: its reference and the message of the citizen', function () {
    assignVisit($this->carlos)->assertOk();
    sealedReport($this->tenant, $this->carlos);

    expect(inbox($this->administrator)->json('data.0.citizen_reports'))->toBe([[
        'reference' => $this->citizenReport->reference(),
        'message' => 'La obra lleva dos semanas sin trabajadores.',
    ]]);
});

it('Al publicar la evidencia, el informe queda documentado y el ciudadano recibe el enlace: to the published evidence, without the veedor', function () {
    assignVisit($this->carlos)->assertOk();
    $reportId = sealedReport($this->tenant, $this->carlos);

    editorialDecision($this->administrator, 'publish', $reportId)->assertOk();

    $report = freshCitizenReport();
    expect($report->status)->toBe('documented')
        ->and($report->documented_at)->not->toBeNull();
    Notification::assertSentOnDemand(CitizenReportDocumented::class, function (CitizenReportDocumented $notification, array $channels, AnonymousNotifiable $notifiable) {
        $mail = $notification->toMail($notifiable);
        $text = implode(' ', [$mail->subject, ...$mail->introLines, ...$mail->outroLines]);

        return $notifiable->routes['mail'] === VISIT_CITIZEN
            && $mail->actionUrl === 'http://veeduria-smr.govtrace.localhost/worksite/'.test()->worksite->public_id
            && str_contains($text, 'documentó lo que usted informó')
            && ! str_contains($text, 'Carlos');
    });
    expect(AuditLog::query()->where('action', 'citizen_report.documented')->count())->toBe(1);
});

it('Si la Bandeja rechaza la evidencia, la visita sigue pendiente: still assigned to Carlos, back in his visits, and no mail to the citizen', function () {
    assignVisit($this->carlos)->assertOk();
    $reportId = sealedReport($this->tenant, $this->carlos);

    editorialDecision($this->administrator, 'reject', $reportId, ['reason' => 'La foto no deja ver la obra.'])->assertOk();

    $report = freshCitizenReport();
    expect($report->status)->toBe('assigned')
        ->and($report->assigned_to)->toBe($this->carlos->id)
        ->and($report->documenting_report_id)->toBeNull()
        ->and(visitsOf($this->carlos))->toHaveCount(1);
    Notification::assertNotSentTo(new AnonymousNotifiable, CitizenReportDocumented::class);
});

it('Una obra sin ubicación: la visita muestra el punto que propuso el ciudadano, as a clue to find it', function () {
    $this->tenant->run(function () {
        Worksite::query()->whereKey($this->worksite->id)->update(['latitude' => null, 'longitude' => null, 'located_at' => null]);
        $this->citizenReport->update(['proposed_latitude' => 11.2411, 'proposed_longitude' => -74.1987]);
    });
    assignVisit($this->carlos)->assertOk();

    expect(visitsOf($this->carlos)[0]['proposed_location'])->toBe(['latitude' => 11.2411, 'longitude' => -74.1987]);
});

it('Un veedor solo ve sus propias visitas: another veedor sees none, and cannot open the photos', function () {
    assignVisit($this->carlos)->assertOk();
    $photo = visitsOf($this->carlos)[0]['photo_urls'][0];

    expect(visitsOf($this->lucia))->toBe([]);
    $this->actingAs($this->lucia, 'tenant')->get(VISITS_HOST.$photo)->assertNotFound();
    tenancy()->end();
    $this->actingAs($this->lucia, 'tenant')->postJson(VISITS_HOST.'/visits/'.$this->citizenReport->public_id.'/unable')->assertNotFound();
    tenancy()->end();
    $this->actingAs($this->lucia, 'tenant')->postJson(VISITS_HOST.'/citizen-reports/'.$this->citizenReport->public_id.'/assign', ['observer' => $this->lucia->public_id])->assertForbidden();
});

it('Filtro los informes por obra y por veedor, como en la Bandeja: the veedor of the visit, within the tab', function () {
    assignVisit($this->carlos)->assertOk();

    $assigned = citizenReportsList(['status' => 'assigned', 'observer' => $this->carlos->public_id]);
    expect($assigned->json('total'))->toBe(1)
        ->and($assigned->json('filters.observers'))->toBe([['id' => $this->carlos->public_id, 'name' => 'Carlos Rojas']])
        ->and(citizenReportsList(['status' => 'assigned', 'observer' => $this->lucia->public_id])->json('total'))->toBe(0)
        ->and(citizenReportsList(['status' => 'assigned', 'observer' => $this->carlos->public_id, 'worksite' => $this->worksite->public_id])->json('total'))->toBe(1);
});

it('sends the visits of a deactivated veedor back to Nuevo, saying why', function () {
    assignVisit($this->carlos)->assertOk();
    tenancy()->end();

    $this->actingAs($this->administrator, 'tenant')->postJson(VISITS_HOST.'/observers/'.$this->carlos->public_id.'/deactivate')->assertOk();

    $report = freshCitizenReport();
    expect($report->status)->toBe('new')
        ->and($report->assigned_to)->toBeNull()
        ->and($report->visit_note)->toBe('El veedor ya no está activo.');
});
