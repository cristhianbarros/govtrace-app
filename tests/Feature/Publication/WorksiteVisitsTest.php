<?php

use App\Application\Organization\ConfigureTerritory;
use App\Application\Organization\RegisterOrganization;
use App\Domain\Organization\Roles;
use App\Domain\Worksites\VisitorKey;
use App\Domain\Worksites\WorksiteVisitDay;
use App\Domain\Worksites\WorksiteVisitor;
use App\Infrastructure\Tenancy\Tenant;
use App\Jobs\ForgetWorksiteVisitors;
use Carbon\CarbonImmutable;
use Database\Seeders\DivipolaSeeder;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;

/*
 * Iteración 69 — Cuántas personas ven cada obra (features/US-071.feature,
 * R-PRIV-07). El navegador avisa de la visita al abrir la página de la obra.
 * No se guardan la IP ni el navegador: con ellos se calcula una huella con la
 * clave del día, una por persona, obra y día, y al día siguiente se borran las
 * huellas y la clave. Queda el número de cada día: la veeduría lo ve en
 * "Obras"; el público, el total desde 10 personas.
 *
 * Sin RefreshDatabase — registrar la organización ejecuta CREATE DATABASE.
 */

const VISITS_HOST = 'http://veeduria-smr.govtrace.localhost';
const VISITOR_IP = '181.50.12.34';
const PHONE_BROWSER = 'Mozilla/5.0 (Linux; Android 14; V2250) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/129.0.0.0 Mobile Safari/537.36';

beforeEach(function () {
    $this->artisan('migrate');
    (new DivipolaSeeder)->run();

    $this->tenant = (new RegisterOrganization)->handle('900123456-8', 'Veeduría Ciudadana Santa Marta', 'veeduria-smr');
    (new ConfigureTerritory)->handle($this->tenant, ['47']);
    $this->administrator = reportingMember($this->tenant, 'ana.perez@veeduria-smr.org', Roles::Administrator);
    reportableContract('CO1.PCCNTR.1234567');
    reportableContract('CO1.PCCNTR.7654321', ['object' => 'Acueducto Gaira']);
    $this->worksite = worksiteWithContracts($this->tenant, ['CO1.PCCNTR.1234567'], santaMartaWorksiteLocation());
    $this->other = worksiteWithContracts($this->tenant, ['CO1.PCCNTR.7654321'], [11.2, -74.23]);
    // Un miércoles, a media mañana en Colombia.
    $this->travelTo(CarbonImmutable::parse('2026-10-21 10:00', 'America/Bogota'));
});

afterEach(function () {
    if (tenant()) {
        tenancy()->end();
    }
    Tenant::query()->get()->each->delete();
    DB::table('contracts')->delete();
    DB::table('visitor_keys')->delete();
});

/** What the browser sends when the page of a work opens. */
function visitWorksite(string $worksite, string $ip = VISITOR_IP, string $agent = PHONE_BROWSER): TestResponse
{
    tenancy()->end();

    return test()->withServerVariables(['REMOTE_ADDR' => $ip])->withHeaders(['User-Agent' => $agent])
        ->postJson(VISITS_HOST."/public/worksites/{$worksite}/visit");
}

/** @return array{today: int, last_30_days: int, total: int} what "Obras" says of the work */
function panelVisitors(?string $worksite = null): array
{
    tenancy()->end();
    $rows = test()->actingAs(test()->administrator, 'tenant')->getJson(VISITS_HOST.'/worksites')->assertOk()->json('data');

    return collect($rows)->firstWhere('id', $worksite ?? test()->worksite->public_id)['visitors'];
}

/** @param  array<int, int>  $days  people per day, by how many days ago */
function visitorsOn(array $days): void
{
    $today = CarbonImmutable::now('America/Bogota');
    test()->tenant->run(fn () => WorksiteVisitDay::query()->insert(collect($days)->map(fn (int $visitors, int $daysAgo) => [
        'worksite_id' => test()->worksite->id,
        'day' => $today->subDays($daysAgo)->toDateString(),
        'visitors' => $visitors,
    ])->values()->all()));
}

it('Quien abre la página de una obra cuenta como una persona que la vio', function () {
    visitWorksite($this->worksite->public_id)->assertNoContent();

    expect(panelVisitors())->toBe(['today' => 1, 'last_30_days' => 1, 'total' => 1])
        ->and(panelVisitors($this->other->public_id))->toBe(['today' => 0, 'last_30_days' => 0, 'total' => 0]);
});

it('La misma persona cuenta una vez al día: again the same day, still one; the next day, two in total', function () {
    visitWorksite($this->worksite->public_id)->assertNoContent();
    visitWorksite($this->worksite->public_id)->assertNoContent();
    visitWorksite($this->worksite->public_id)->assertNoContent();

    expect(panelVisitors())->toBe(['today' => 1, 'last_30_days' => 1, 'total' => 1]);

    $this->travelTo(CarbonImmutable::parse('2026-10-22 09:00', 'America/Bogota'));
    visitWorksite($this->worksite->public_id)->assertNoContent();

    expect(panelVisitors())->toBe(['today' => 1, 'last_30_days' => 2, 'total' => 2]);
});

it('El día es el de Colombia: 23:30 and 00:30 are two days, though both are the same day in UTC', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-21 23:30', 'America/Bogota'));
    visitWorksite($this->worksite->public_id)->assertNoContent();
    $this->travelTo(CarbonImmutable::parse('2026-10-22 00:30', 'America/Bogota'));
    visitWorksite($this->worksite->public_id)->assertNoContent();

    $days = $this->tenant->run(fn () => WorksiteVisitDay::query()->orderBy('day')->get()->map(fn (WorksiteVisitDay $day) => $day->day->toDateString())->all());
    expect($days)->toBe(['2026-10-21', '2026-10-22'])
        ->and(panelVisitors())->toBe(['today' => 1, 'last_30_days' => 2, 'total' => 2]);
});

it('Dos personas distintas cuentan como dos: another connection, or another browser on the same one', function () {
    visitWorksite($this->worksite->public_id);
    visitWorksite($this->worksite->public_id, '190.85.4.2');
    visitWorksite($this->worksite->public_id, VISITOR_IP, 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.0 Mobile/15E148 Safari/604.1');

    expect(panelVisitors()['today'])->toBe(3);
});

it('GovTrace no guarda la IP de quien ve una obra: a fingerprint with the key of the day, another in another work and the next day', function () {
    visitWorksite($this->worksite->public_id);
    visitWorksite($this->other->public_id);
    $this->travelTo(CarbonImmutable::parse('2026-10-22 09:00', 'America/Bogota'));
    visitWorksite($this->worksite->public_id);

    $stored = $this->tenant->run(fn () => WorksiteVisitor::query()->get()->toArray());
    $everything = json_encode([$stored, $this->tenant->run(fn () => WorksiteVisitDay::query()->get()->toArray()), VisitorKey::query()->get()->toArray()]);
    $fingerprints = array_column($stored, 'fingerprint');

    expect($stored)->toHaveCount(3)
        ->and($everything)->not->toContain(VISITOR_IP)
        ->and($everything)->not->toContain('Android')
        ->and($fingerprints)->each->toMatch('/^[0-9a-f]{64}$/')
        ->and(array_unique($fingerprints))->toHaveCount(3)
        // Una clave por día, aleatoria: no sale de la fecha ni de APP_KEY.
        ->and(VisitorKey::query()->pluck('key')->all())->toHaveCount(2)->each->toMatch('/^[0-9a-f]{64}$/');
});

it('Al día siguiente solo queda el número: at 00:05 the fingerprints and the key of the day before go away', function () {
    visitWorksite($this->worksite->public_id, '181.50.12.1');
    visitWorksite($this->worksite->public_id, '181.50.12.2');
    visitWorksite($this->worksite->public_id, '181.50.12.3');

    $this->travelTo(CarbonImmutable::parse('2026-10-22 00:05', 'America/Bogota'));
    (new ForgetWorksiteVisitors)->handle();

    expect($this->tenant->run(fn () => WorksiteVisitor::query()->count()))->toBe(0)
        ->and(VisitorKey::query()->count())->toBe(0)
        ->and(panelVisitors())->toBe(['today' => 0, 'last_30_days' => 3, 'total' => 3]);

    // Y el calendario lo corre cada día a las 00:05, en hora de Colombia.
    Artisan::call('schedule:list');
    $event = collect(app(Schedule::class)->events())->firstWhere('description', 'worksite-visitors-forget');
    expect($event->expression)->toBe('5 0 * * *')
        ->and($event->timezone)->toBe('America/Bogota');
});

it('Un robot o la vista previa de un enlace no cuenta: nor the page served without its browser, nor a request without one', function () {
    foreach ([
        'WhatsApp/2.24.21.80 A',
        'facebookexternalhit/1.1 (+http://www.facebook.com/externalhit_uatext.php)',
        'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)',
        'Twitterbot/1.0',
        '',
    ] as $agent) {
        visitWorksite($this->worksite->public_id, VISITOR_IP, $agent)->assertNoContent();
    }
    // La página misma, sin el navegador que la abre (la vista previa, it. 66c), tampoco cuenta.
    $this->get(VISITS_HOST."/worksite/{$this->worksite->public_id}", ['User-Agent' => PHONE_BROWSER])->assertOk();

    expect(panelVisitors()['total'])->toBe(0);
});

it('Una obra que no existe no cuenta', function () {
    visitWorksite('01j9xq3m7v8k2d4f6g8h0jkm12')->assertNotFound();

    expect($this->tenant->run(fn () => WorksiteVisitor::query()->count()))->toBe(0)
        ->and(VisitorKey::query()->count())->toBe(0);
});

it('La veeduría ve cuántas personas vieron cada obra: today, the last 30 days (today and the 29 before) and all of them', function () {
    visitorsOn([0 => 3, 29 => 9, 30 => 100, 400 => 28]);

    expect(panelVisitors())->toBe(['today' => 3, 'last_30_days' => 12, 'total' => 140]);
});

it('El público ve cuántas personas han visto la obra', function () {
    visitorsOn([0 => 3, 29 => 9, 30 => 128]);

    expect(publicGet("/public/worksites/{$this->worksite->public_id}")->assertOk()->json('data.visitors'))->toBe(140);
});

it('Con menos de 10 personas, la página pública no dice el número, and the veeduría still sees it', function () {
    visitorsOn([0 => 4, 3 => 5]);

    expect(publicGet("/public/worksites/{$this->worksite->public_id}")->assertOk()->json('data.visitors'))->toBeNull()
        ->and(panelVisitors()['total'])->toBe(9);

    visitorsOn([5 => 1]);
    expect(publicGet("/public/worksites/{$this->worksite->public_id}")->json('data.visitors'))->toBe(10);
});
