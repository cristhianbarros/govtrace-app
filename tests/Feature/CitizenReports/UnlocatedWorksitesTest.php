<?php

use App\Application\Organization\ConfigureTerritory;
use App\Application\Organization\RegisterOrganization;
use App\Domain\Audit\AuditLog;
use App\Domain\CitizenReports\CitizenReport;
use App\Domain\CitizenReports\Notifications\CitizenReportCode;
use App\Domain\Organization\Roles;
use App\Domain\Reports\Report;
use App\Domain\Shared\PublicId;
use App\Domain\Worksites\Worksite;
use App\Domain\Worksites\WorksiteContract;
use App\Infrastructure\Tenancy\Tenant;
use Database\Seeders\DivipolaSeeder;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;

/*
 * Iteración 58 — El ciudadano informa sobre cualquier obra del territorio
 * (features/US-059-LEG.feature, y la confirmación en features/US-035.feature).
 * Hasta la 58, el ciudadano solo podía informar de las obras que un veedor ya
 * había visitado: el mapa y la lista muestran solo las que tienen ubicación, y
 * el informe exigía una ficha. Ahora ve también los contratos sin ubicación,
 * puede proponer dónde queda la obra, y la veeduría lo confirma.
 *
 * Sin RefreshDatabase — registrar la organización ejecuta CREATE DATABASE.
 */

const MINCA = 'CO1.PCCNTR.7654321';
const MINCA_POINT = [11.1427, -74.1173]; // a unos 11 km de la cabecera de Santa Marta
const FAR_POINT = [10.48, -73.25]; // Valledupar, a más de 100 km

beforeEach(function () {
    $this->artisan('migrate');
    (new DivipolaSeeder)->run();
    Storage::fake('evidencias');
    Notification::fake();

    $this->tenant = (new RegisterOrganization)->handle('900123456-8', 'Veeduría Ciudadana Santa Marta', 'veeduria-smr');
    (new ConfigureTerritory)->handle($this->tenant, ['47']);
    $this->administrator = reportingMember($this->tenant, 'ana.perez@veeduria-smr.org', Roles::Administrator);
    $this->veedor = reportingMember($this->tenant, 'carlos@correo.co');

    // Una obra con ubicación, otra sin ficha todavía, una de otro departamento y una terminada hace años.
    reportableContract('CO1.PCCNTR.1234567');
    $this->located = worksiteWithContracts($this->tenant, ['CO1.PCCNTR.1234567'], santaMartaWorksiteLocation());
    reportableContract(MINCA, ['object' => 'Construcción de la vía a Minca', 'entity_name' => 'Gobernación del Magdalena', 'signed_at' => '2026-03-01']);
    reportableContract('CO1.PCCNTR.0500001', ['object' => 'Construcción del parque de Belén', 'department_code' => '05', 'municipality_code' => '05001']);
    reportableContract('CO1.PCCNTR.1111111', ['object' => 'Construcción de la vía a Bonda', 'status' => 'Terminado', 'end_date' => '2022-06-30']);
});

afterEach(function () {
    if (tenant()) {
        tenancy()->end();
    }

    Tenant::query()->get()->each->delete();
    DB::table('contracts')->delete();
    DB::table('audit_logs')->delete();
});

function unlocatedHost(): string
{
    return 'http://veeduria-smr.govtrace.localhost';
}

/** POST /citizen-reports/code about a contract, as a visitor without a session. */
function codeAboutContract(string $email, string $secopContractId): TestResponse
{
    return test()->postJson(unlocatedHost().'/citizen-reports/code', ['email' => $email, 'contract_id' => $secopContractId, 'data_authorization' => true]);
}

function codeMailedTo(string $email): string
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

/**
 * A citizen informs about MINCA (or whatever $data says), with a fresh code.
 *
 * @param  array<string, mixed>  $data
 */
function informAbout(string $email, array $data = [], bool $askForCode = true): TestResponse
{
    if ($askForCode) {
        codeAboutContract($email, $data['contract_id'] ?? MINCA)->assertOk();
    }

    return test()->post(unlocatedHost().'/citizen-reports', [
        'email' => $email,
        'code' => codeMailedTo($email),
        'contract_id' => MINCA,
        'message' => 'La vía lleva tres meses sin trabajadores y hay huecos sin señalizar.',
        ...$data,
    ], ['Accept' => 'application/json']);
}

function unlocatedList(array $query = []): TestResponse
{
    return publicGet('/public/unlocated-worksites'.($query === [] ? '' : '?'.http_build_query($query)));
}

function citizenInbox(): TestResponse
{
    return test()->actingAs(test()->administrator, 'tenant')->getJson(unlocatedHost().'/citizen-reports');
}

function confirmProposal(string $reportPublicId): TestResponse
{
    return test()->actingAs(test()->administrator, 'tenant')->postJson(unlocatedHost()."/citizen-reports/{$reportPublicId}/confirm-location");
}

/** The ids of the pins of the public map. */
function pinIds(): array
{
    return array_column(publicGet('/public/worksites')->assertOk()->json('data'), 'id');
}

function mincaWorksite(): ?Worksite
{
    return test()->tenant->run(function () {
        $link = WorksiteContract::query()->where('secop_contract_id', MINCA)->first();

        return $link ? Worksite::query()->find($link->worksite_id) : null;
    });
}

it('El ciudadano encuentra una obra sin ubicación y le informa a la veeduría: listed, reported, and its sheet is born without location nor pin', function () {
    $listed = unlocatedList()->assertOk();
    expect(array_column($listed->json('data'), 'contract_id'))->toBe([MINCA])
        ->and($listed->json('data.0'))->toMatchArray([
            'contract_id' => MINCA,
            'worksite_id' => null,
            'name' => 'Construcción de la vía a Minca',
            'municipality' => 'Santa Marta',
            'entity_name' => 'Gobernación del Magdalena',
        ])
        ->and($listed->json('has_more'))->toBeFalse();

    informAbout('vecino@correo.co')->assertCreated();

    $worksite = mincaWorksite();
    expect($worksite)->not->toBeNull()
        ->and($worksite->location())->toBeNull()
        ->and($this->tenant->run(fn () => CitizenReport::query()->sole()->worksite_id))->toBe($worksite->id)
        ->and(pinIds())->toBe([$this->located->public_id]);
    // Sigue en la lista de las que no tienen ubicación, ahora con su ficha.
    expect(unlocatedList()->json('data.0'))->toMatchArray(['contract_id' => MINCA, 'worksite_id' => $worksite->public_id]);
});

it('lists the works without location of the territory by name without accents, by municipality, and 20 at a time', function () {
    foreach (range(1, 21) as $number) {
        reportableContract(sprintf('CO1.PCCNTR.20%05d', $number), ['object' => "Andén del barrio {$number}", 'municipality_code' => '47189']);
    }

    expect(array_column(unlocatedList(['q' => 'via a minca'])->json('data'), 'contract_id'))->toBe([MINCA])
        ->and(unlocatedList(['municipality' => '47001'])->json('data'))->toHaveCount(1);

    $first = unlocatedList(['municipality' => '47189']);
    expect($first->json('data'))->toHaveCount(20)
        ->and($first->json('has_more'))->toBeTrue()
        ->and(unlocatedList(['municipality' => '47189', 'page' => 2])->json('data'))->toHaveCount(1);
});

it('El ciudadano propone dónde queda una obra sin ubicación: the proposal arrives with the report, and the work has no pin until it is confirmed', function () {
    // La página de la obra empieza el mapa en la cabecera del municipio del contrato.
    expect(publicGet('/public/contracts/'.MINCA)->assertOk()->json('data'))->toMatchArray([
        'contract_id' => MINCA,
        'worksite_id' => null,
        'name' => 'Construcción de la vía a Minca',
        'seat' => ['latitude' => 11.204679, 'longitude' => -74.199829],
    ]);

    $sent = informAbout('vecino@correo.co', ['latitude' => MINCA_POINT[0], 'longitude' => MINCA_POINT[1]])->assertCreated();

    $report = $this->tenant->run(fn () => CitizenReport::query()->sole());
    expect((float) $report->proposed_latitude)->toBe(MINCA_POINT[0])
        ->and((float) $report->proposed_longitude)->toBe(MINCA_POINT[1])
        ->and(mincaWorksite()->location())->toBeNull()
        ->and(pinIds())->toBe([$this->located->public_id])
        ->and(citizenInbox()->assertOk()->json('data.0.proposed_location'))->toBe([
            'latitude' => MINCA_POINT[0],
            'longitude' => MINCA_POINT[1],
            'status' => 'unlocated',
            'others' => [],
        ]);
    expect($sent->json('number'))->toBe($report->reference());
});

it('Una ubicación propuesta lejos del municipio se rechaza: the report is not received, and the code still works', function () {
    informAbout('vecino@correo.co', ['latitude' => FAR_POINT[0], 'longitude' => FAR_POINT[1]])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['location' => 'Ese punto queda a más de 30 km de Santa Marta. Revise dónde marcó la obra, o envíe el informe sin la ubicación.']);

    expect($this->tenant->run(fn () => CitizenReport::query()->count()))->toBe(0)
        ->and(mincaWorksite())->toBeNull();

    informAbout('vecino@correo.co', [], askForCode: false)->assertCreated();
});

it('refuses a proposal that is not a place on Earth', function () {
    informAbout('vecino@correo.co', ['latitude' => 95, 'longitude' => -74.1])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['location']);
});

it('Una obra que ya tiene ubicación no la pide: its page has no starting point, and a proposal that comes anyway is not kept', function () {
    expect(publicGet("/public/worksites/{$this->located->public_id}")->assertOk()->json('data'))->toMatchArray(['located' => true, 'seat' => null])
        ->and(publicGet('/public/contracts/'.MINCA)->json('data.seat'))->not->toBeNull();

    $this->postJson(unlocatedHost().'/citizen-reports/code', ['email' => 'vecino@correo.co', 'worksite_id' => $this->located->public_id, 'data_authorization' => true])->assertOk();
    informAbout('vecino@correo.co', ['contract_id' => null, 'worksite_id' => $this->located->public_id, 'latitude' => MINCA_POINT[0], 'longitude' => MINCA_POINT[1]], askForCode: false)->assertCreated();

    $this->tenant->run(function () {
        $report = CitizenReport::query()->sole();
        expect($report->worksite_id)->toBe($this->located->id)
            ->and($report->proposed_latitude)->toBeNull()
            ->and(Worksite::query()->find($this->located->id)->location()->latitude)->toBe(santaMartaWorksiteLocation()[0]);
    });
});

it('asks where an unlocated sheet is: its page starts the map at the seat of its municipality', function () {
    $sheet = worksiteWithContracts($this->tenant, [MINCA], null);

    expect(publicGet("/public/worksites/{$sheet->public_id}")->assertOk()->json('data'))->toMatchArray([
        'located' => false,
        'seat' => ['latitude' => 11.204679, 'longitude' => -74.199829],
    ]);
});

it('La ficha de una obra se crea una sola vez: a veedor who creates it while the citizen is informing gets the same one', function () {
    // El veedor crea la ficha en otra transacción, ya confirmada, justo antes de
    // que el informe inserte la suya: la del informe choca con la restricción
    // única del contrato y usa la del veedor.
    $raced = false;
    WorksiteContract::creating(function (WorksiteContract $link) use (&$raced) {
        if ($raced || $link->secop_contract_id !== MINCA) {
            return;
        }
        $raced = true;

        config(['database.connections.race' => config('database.connections.tenant')]);
        $race = DB::connection('race');
        $worksiteId = $race->table('worksites')->insertGetId(['public_id' => PublicId::generate(), 'created_at' => now(), 'updated_at' => now()]);
        $race->table('worksite_contracts')->insert(['worksite_id' => $worksiteId, 'secop_contract_id' => MINCA, 'created_at' => now(), 'updated_at' => now()]);
        DB::disconnect('race');
    });

    informAbout('vecino@correo.co')->assertCreated();
    expect($raced)->toBeTrue();

    // Después, el primer reporte del veedor llega a la misma ficha.
    sendReport($this->veedor, [
        'secop_contract_id' => MINCA,
        'latitude' => MINCA_POINT[0],
        'longitude' => MINCA_POINT[1],
    ])->assertCreated();

    $this->tenant->run(function () {
        $sheet = WorksiteContract::query()->where('secop_contract_id', MINCA)->sole()->worksite_id;

        expect(Worksite::query()->whereHas('contracts', fn ($contracts) => $contracts->where('secop_contract_id', MINCA))->count())->toBe(1)
            ->and(CitizenReport::query()->sole()->worksite_id)->toBe($sheet)
            ->and(Report::query()->sole()->worksite_id)->toBe($sheet);
    });
});

it('No se informa de un contrato que la veeduría no vigila: outside its territory, or no longer reportable', function (string $secopContractId) {
    codeAboutContract('vecino@correo.co', $secopContractId)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['contract_id' => 'Esa obra no está entre las que vigila esta veeduría.']);
    Notification::assertNothingSent();

    publicGet("/public/contracts/{$secopContractId}")->assertNotFound();
    expect(array_column(unlocatedList()->json('data'), 'contract_id'))->not->toContain($secopContractId);
})->with([
    'otro departamento' => 'CO1.PCCNTR.0500001',
    'terminado hace años' => 'CO1.PCCNTR.1111111',
    'no existe' => 'CO1.PCCNTR.0000000',
]);

it('takes the citizen to the page of the work when the contract already has its sheet', function () {
    $sheet = worksiteWithContracts($this->tenant, [MINCA], null);

    publicGet('/contract/'.MINCA)->assertRedirectContains("/worksite/{$sheet->public_id}");
    publicGet('/contract/CO1.PCCNTR.1234567')->assertRedirectContains("/worksite/{$this->located->public_id}");
});

// US-035 (it. 58): la ubicación que propuso un ciudadano, confirmada por el Administrador.

it('Confirmar la ubicación que propuso un ciudadano: it becomes the official location, the work appears on the map, and the log has it', function () {
    informAbout('vecino@correo.co', ['latitude' => MINCA_POINT[0], 'longitude' => MINCA_POINT[1]])->assertCreated();
    $report = citizenInbox()->json('data.0.id');

    confirmProposal($report)->assertOk()->assertJson(['message' => 'La ubicación de la obra quedó confirmada: ya aparece en el mapa, y la geocerca de 500 m está activa para los veedores.']);

    $worksite = mincaWorksite();
    expect($worksite->location()->latitude)->toBe(MINCA_POINT[0])
        ->and($worksite->location()->longitude)->toBe(MINCA_POINT[1])
        ->and(pinIds())->toContain($worksite->public_id)
        ->and(citizenInbox()->json('data.0.proposed_location.status'))->toBe('confirmed')
        ->and(array_column(unlocatedList()->json('data'), 'contract_id'))->not->toContain(MINCA);

    $log = AuditLog::query()->where('action', 'worksite.location_corrected')->sole();
    expect($log->actor_id)->toBe((string) $this->administrator->id)
        ->and($log->before)->toMatchArray(['latitude' => null, 'longitude' => null])
        ->and($log->after)->toMatchArray(['worksite_id' => $worksite->id, 'latitude' => MINCA_POINT[0], 'longitude' => MINCA_POINT[1]]);
});

it('Con varias ubicaciones propuestas, confirmar una cierra las demás: each map shows the other one, and once one is confirmed the other cannot be', function () {
    $other = [11.1500, -74.1200];
    informAbout('vecino@correo.co', ['latitude' => MINCA_POINT[0], 'longitude' => MINCA_POINT[1]])->assertCreated();
    informAbout('vecina@correo.co', ['latitude' => $other[0], 'longitude' => $other[1]])->assertCreated();

    // El más reciente primero.
    [$second, $first] = citizenInbox()->json('data');
    expect($second['proposed_location']['others'])->toBe([['latitude' => MINCA_POINT[0], 'longitude' => MINCA_POINT[1]]])
        ->and($first['proposed_location']['others'])->toBe([['latitude' => $other[0], 'longitude' => $other[1]]]);

    confirmProposal($first['id'])->assertOk();

    [$second] = citizenInbox()->json('data');
    expect($second['proposed_location'])->toMatchArray(['status' => 'elsewhere', 'others' => []]);
    confirmProposal($second['id'])->assertConflict()->assertJson(['message' => 'La obra ya tiene ubicación. Si está mal, corríjala en Obras.']);
    expect(mincaWorksite()->location()->latitude)->toBe(MINCA_POINT[0]);
});

it('does not confirm the proposal of a discarded report, nor of a report without one', function () {
    informAbout('vecino@correo.co', ['latitude' => MINCA_POINT[0], 'longitude' => MINCA_POINT[1]])->assertCreated();
    informAbout('vecina@correo.co')->assertCreated();
    [$withoutProposal, $withProposal] = citizenInbox()->json('data');

    expect($withoutProposal['proposed_location'])->toBeNull();
    confirmProposal($withoutProposal['id'])->assertConflict()->assertJson(['message' => 'Este informe no trae una ubicación para confirmar.']);

    $this->actingAs($this->administrator, 'tenant')->postJson(unlocatedHost()."/citizen-reports/{$withProposal['id']}/discard")->assertOk();
    confirmProposal($withProposal['id'])->assertConflict()->assertJson(['message' => 'Este informe no trae una ubicación para confirmar.']);
    expect(mincaWorksite()->location())->toBeNull();
});

it('lets only the Administrador confirm a proposal', function () {
    informAbout('vecino@correo.co', ['latitude' => MINCA_POINT[0], 'longitude' => MINCA_POINT[1]])->assertCreated();
    $report = citizenInbox()->json('data.0.id');

    $this->actingAs($this->veedor, 'tenant')->postJson(unlocatedHost()."/citizen-reports/{$report}/confirm-location")->assertForbidden();
    expect(mincaWorksite()->location())->toBeNull();
});
