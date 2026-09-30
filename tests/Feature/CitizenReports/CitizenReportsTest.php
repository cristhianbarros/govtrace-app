<?php

use App\Application\Organization\ChangeOrganizationStatus;
use App\Application\Organization\ConfigureTerritory;
use App\Application\Organization\RegisterOrganization;
use App\Application\Privacy\DataPolicy;
use App\Domain\Audit\AuditLog;
use App\Domain\CitizenReports\CitizenReport;
use App\Domain\CitizenReports\Notifications\CitizenReportAnswered;
use App\Domain\CitizenReports\Notifications\CitizenReportCode;
use App\Domain\CitizenReports\Notifications\CitizenReportReceived;
use App\Domain\Organization\OrganizationStatus;
use App\Domain\Organization\Roles;
use App\Domain\Sealing\ReportSeal;
use App\Infrastructure\Tenancy\Tenant;
use Database\Seeders\DivipolaSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;

/*
 * Iteración 44f — Informar a la veeduría (features/US-059-LEG.feature,
 * docs/proceso-actual.md A2). Toda veeduría debe "recibir los informes,
 * observaciones y sugerencias que presenten los particulares" (Ley 850 de
 * 2003, art. 18 a)). El ciudadano, sin cuenta, verifica su correo con un
 * código y le escribe a la veeduría; el informe no se sella ni se publica, y
 * la veeduría le responde sin ver su correo.
 *
 * Sin RefreshDatabase — registrar la organización ejecuta CREATE DATABASE.
 */

const CITIZEN_HOST = 'http://veeduria-smr.govtrace.localhost';
const CITIZEN = 'vecina@correo.co';
const RECEIVED = 'Su informe llegó a la veeduría. Si lo atiende, le responde a su correo.';
const INVALID_CODE = 'El código no es válido o ya venció. Pida uno nuevo.';

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

/** POST /citizen-reports/code, as a visitor without a session. */
function requestCode(array $data = []): TestResponse
{
    return test()->postJson(CITIZEN_HOST.'/citizen-reports/code', ['email' => CITIZEN, 'worksite_id' => test()->worksite->id, 'data_authorization' => true, ...$data]);
}

/** The code the citizen got by mail. */
function mailedCode(string $email = CITIZEN): string
{
    $code = null;
    Notification::assertSentOnDemand(CitizenReportCode::class, function (CitizenReportCode $notification, array $channels, AnonymousNotifiable $notifiable) use ($email, &$code) {
        if (($notifiable->routes['mail'] ?? null) === $email) {
            $code = $notification->code;
        }

        return true;
    });

    return $code;
}

/** POST /citizen-reports, as a visitor without a session. */
function sendCitizenReport(array $data = []): TestResponse
{
    return test()->post(CITIZEN_HOST.'/citizen-reports', [
        'email' => CITIZEN,
        'code' => mailedCode(),
        'worksite_id' => test()->worksite->id,
        'message' => 'La obra lleva dos semanas sin trabajadores y el cerramiento se cayó.',
        ...$data,
    ], ['Accept' => 'application/json']);
}

function citizenPhoto(): UploadedFile
{
    return UploadedFile::fake()->createWithContent('obra.jpg', file_get_contents(base_path('tests/fixtures/evidence/foto.jpg')));
}

/** The fixture photo with an APP1 segment carrying GPS coordinates, as a phone camera leaves it. */
function citizenPhotoWithLocation(): UploadedFile
{
    $jpeg = file_get_contents(base_path('tests/fixtures/evidence/foto.jpg'));
    $afterJfif = 2 + 2 + unpack('n', substr($jpeg, 4, 2))[1];
    $payload = "Exif\x00\x00GPS";

    return UploadedFile::fake()->createWithContent('con-gps.jpg', substr($jpeg, 0, $afterJfif)."\xFF\xE1".pack('n', strlen($payload) + 2).$payload.substr($jpeg, $afterJfif));
}

function adminCitizenReports(): TestResponse
{
    return test()->actingAs(test()->administrator, 'tenant')->getJson(CITIZEN_HOST.'/citizen-reports');
}

it('El ciudadano informa a la veeduría con su correo verificado: a code by mail, then the report with a photo, and its number by mail', function () {
    requestCode()->assertOk()->assertJson(['message' => 'Le enviamos un código de 6 dígitos a '.CITIZEN.'. Vence en 10 minutos.']);
    expect(mailedCode())->toMatch('/^\d{6}$/');

    $response = sendCitizenReport(['photo' => citizenPhoto()])->assertCreated()->assertJson(['message' => RECEIVED]);

    $report = $this->tenant->run(fn () => CitizenReport::query()->sole());
    expect($response->json('number'))->toBe($report->id)
        ->and($report->worksite_id)->toBe($this->worksite->id)
        ->and($report->status)->toBe('new')
        ->and($report->photo_path)->not->toBeNull()
        ->and($report->data_policy_version)->toBe(DataPolicy::VERSION);
    Notification::assertSentOnDemand(CitizenReportReceived::class, fn (CitizenReportReceived $notification, array $channels, AnonymousNotifiable $notifiable) => $notifiable->routes['mail'] === CITIZEN && $notification->number === $report->id);
});

it('Sin un código válido no se recibe el informe: a wrong or expired code, and 5 wrong attempts void it', function () {
    requestCode()->assertOk();
    $code = mailedCode();

    sendCitizenReport(['code' => $code === '000000' ? '111111' : '000000'])->assertUnprocessable()->assertJsonValidationErrors(['code' => INVALID_CODE]);

    // Vence a los 10 minutos.
    $this->travel(11)->minutes();
    sendCitizenReport(['code' => $code])->assertUnprocessable()->assertJsonValidationErrors(['code' => INVALID_CODE]);
    $this->travelBack();

    // Un código nuevo anula el anterior; con 5 intentos fallidos, también el nuevo.
    Notification::fake();
    requestCode()->assertOk();
    $fresh = mailedCode();
    foreach (range(1, 5) as $attempt) {
        sendCitizenReport(['code' => $fresh === '000000' ? '111111' : '000000'])->assertUnprocessable();
    }
    sendCitizenReport(['code' => $fresh])->assertUnprocessable()->assertJsonValidationErrors(['code' => INVALID_CODE]);
    expect($this->tenant->run(fn () => CitizenReport::query()->count()))->toBe(0);
});

it('Sin autorizar el tratamiento de datos no se pide el código', function () {
    requestCode(['data_authorization' => false])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['data_authorization' => 'Para informar a la veeduría, autorice el tratamiento de sus datos personales.']);

    Notification::assertNothingSent();
});

it('La foto del informe llega sin metadatos: one with the location of the phone, another format or too large is refused', function (Closure $photo, string $message) {
    requestCode()->assertOk();

    sendCitizenReport(['photo' => $photo()])->assertUnprocessable()->assertJsonValidationErrors(['photo' => $message]);
})->with([
    'con GPS' => [fn () => citizenPhotoWithLocation(), 'La foto conserva metadatos, como la ubicación del teléfono. Elíjala desde esta página, que los quita.'],
    'un PDF' => [fn () => UploadedFile::fake()->create('acta.pdf', 100, 'application/pdf'), 'Solo se acepta una foto en JPEG, de hasta 10 MB.'],
    'muy grande' => [fn () => UploadedFile::fake()->create('grande.jpg', 11 * 1024, 'image/jpeg'), 'Solo se acepta una foto en JPEG, de hasta 10 MB.'],
]);

it('Límites contra el spam: 3 reports a day per email, and 1 per worksite', function () {
    // Un contrato va en una sola ficha: cada obra de más, con el suyo.
    $other = function (string $contract) {
        reportableContract($contract);

        return worksiteWithContracts($this->tenant, [$contract], santaMartaWorksiteLocation());
    };
    $worksites = [$this->worksite->id, $other('CO1.PCCNTR.2000001')->id, $other('CO1.PCCNTR.2000002')->id, $other('CO1.PCCNTR.2000003')->id];

    // Una mañana, un informe por hora: cada uno necesita su código, y hay 3 códigos por hora.
    $this->travelTo('2026-10-01 08:00:00');
    foreach (array_slice($worksites, 0, 3) as $worksiteId) {
        Notification::fake();
        requestCode(['worksite_id' => $worksiteId])->assertOk();
        sendCitizenReport(['worksite_id' => $worksiteId])->assertCreated();
        $this->travel(61)->minutes();
    }

    Notification::fake();
    requestCode(['worksite_id' => $worksites[3]])->assertOk();
    sendCitizenReport(['worksite_id' => $worksites[3]])->assertStatus(429)->assertJson(['message' => 'Llegó al límite de 3 informes por día. Puede enviar más mañana.']);

    // Mañana puede otra vez, pero no dos veces de la misma obra en el día.
    $this->travel(1)->days();
    Notification::fake();
    requestCode()->assertOk();
    sendCitizenReport()->assertCreated();
    Notification::fake();
    requestCode()->assertOk();
    sendCitizenReport()->assertStatus(429)->assertJson(['message' => 'Ya informó hoy de esta obra. Puede volver a hacerlo mañana.']);
});

it('limits the codes a visitor can ask for, by email and by address', function () {
    foreach (range(1, 3) as $request) {
        requestCode()->assertOk();
    }
    requestCode()->assertStatus(429)->assertJson(['message' => 'Ya pidió 3 códigos en la última hora. Espere un poco para pedir otro.']);
});

it('El informe ciudadano no se sella ni se publica: no seal, not on the map, the state of the worksite does not change', function () {
    requestCode()->assertOk();
    sendCitizenReport()->assertCreated();

    expect($this->tenant->run(fn () => ReportSeal::query()->count()))->toBe(0)
        ->and(publicGet('/public/worksites/'.$this->worksite->id)->assertOk()->json('data.timeline'))->toBe([])
        ->and(publicGet('/public/worksites/'.$this->worksite->id)->json('data.condition.color'))->toBe('green');
});

it('La veeduría recibe los informes sin ver el correo del ciudadano: the worksite, the date, the message and the photo', function () {
    requestCode()->assertOk();
    sendCitizenReport(['photo' => citizenPhoto()])->assertCreated();

    $response = adminCitizenReports()->assertOk();
    $row = $response->json('data.0');
    expect($row)->toMatchArray(['worksite' => 'Pavimentación Calle 30', 'message' => 'La obra lleva dos semanas sin trabajadores y el cerramiento se cayó.', 'status' => 'new', 'status_label' => 'Nuevo'])
        ->and($row['photo_url'])->toBe("/citizen-reports/{$row['id']}/photo")
        ->and($response->getContent())->not->toContain(CITIZEN);

    $this->actingAs($this->administrator, 'tenant')->get(CITIZEN_HOST.$row['photo_url'])->assertOk()->assertHeader('Content-Type', 'image/jpeg');

    // El correo, cifrado en la base: nadie lo lee en claro.
    expect($this->tenant->run(fn () => DB::table('citizen_reports')->value('email')))->not->toContain('vecina');
});

it('La veeduría responde al ciudadano: the answer goes to their mail, the report is answered, and it is audited', function () {
    requestCode()->assertOk();
    sendCitizenReport()->assertCreated();
    $id = adminCitizenReports()->json('data.0.id');

    $this->actingAs($this->administrator, 'tenant')->postJson(CITIZEN_HOST."/citizen-reports/{$id}/answer", ['answer' => 'Gracias. El sábado va un veedor a documentarlo.'])
        ->assertOk()->assertJson(['message' => 'Respuesta enviada al ciudadano.']);

    Notification::assertSentOnDemand(CitizenReportAnswered::class, fn (CitizenReportAnswered $notification, array $channels, AnonymousNotifiable $notifiable) => $notifiable->routes['mail'] === CITIZEN && $notification->answer === 'Gracias. El sábado va un veedor a documentarlo.');
    expect(adminCitizenReports()->json('data.0'))->toMatchArray(['status' => 'answered', 'status_label' => 'Atendido', 'answer' => 'Gracias. El sábado va un veedor a documentarlo.'])
        ->and(AuditLog::query()->where('action', 'citizen_report.answered')->where('actor_type', 'organization_admin')->exists())->toBeTrue();
});

it('La veeduría descarta un informe: no mail to the citizen, and it is audited', function () {
    requestCode()->assertOk();
    sendCitizenReport()->assertCreated();
    $id = adminCitizenReports()->json('data.0.id');
    Notification::fake();

    $this->actingAs($this->administrator, 'tenant')->postJson(CITIZEN_HOST."/citizen-reports/{$id}/discard")->assertOk()->assertJson(['message' => 'Informe descartado.']);

    Notification::assertNothingSent();
    expect(adminCitizenReports()->json('data.0'))->toMatchArray(['status' => 'discarded', 'status_label' => 'Descartado'])
        ->and(AuditLog::query()->where('action', 'citizen_report.discarded')->exists())->toBeTrue();
});

it('Una veeduría suspendida no recibe informes', function () {
    (new ChangeOrganizationStatus)->handle($this->tenant, OrganizationStatus::Suspended, 'organization.suspended');

    requestCode()->assertStatus(409)->assertJson(['message' => 'Esta veeduría está suspendida y no recibe informes por ahora.']);
    Notification::assertNothingSent();
});

it('Solo el Administrador ve los informes: not a veedor, nor a visitor', function () {
    $this->actingAs($this->veedor, 'tenant')->getJson(CITIZEN_HOST.'/citizen-reports')->assertForbidden();
    publicGet('/citizen-reports')->assertUnauthorized();
});

it('does not take a report for a worksite that does not exist', function () {
    requestCode(['worksite_id' => 999999])->assertUnprocessable()->assertJsonValidationErrors(['worksite_id']);
});
