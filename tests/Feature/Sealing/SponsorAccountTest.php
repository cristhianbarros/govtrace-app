<?php

use App\Application\Sealing\ContractLifetime;
use App\Application\Sealing\Exceptions\NetworkUnavailable;
use App\Application\Sealing\SealingNetwork;
use App\Domain\Configuration\Parameters;
use App\Domain\Configuration\ParameterValue;
use App\Domain\Sealing\Notifications\ContractExpiring;
use App\Domain\Sealing\Notifications\SponsorBalanceLow;
use App\Infrastructure\Notifications\WebhookChannel;
use App\Jobs\CheckContractLifetime;
use App\Jobs\CheckSponsorBalance;
use App\Models\User as SuperAdmin;
use Illuminate\Http\Client\Request;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\FakeSealingNetwork;

/*
 * Iteración 32 — US-022, ajustada a Stellar (features/US-022.feature, 5
 * casos): el saldo de la cuenta patrocinadora cada 15 minutos, con alerta
 * bajo el umbral de D12, y el aviso antes de que venza la vigencia del
 * contrato, que extiende la tesorería (D12). La red es el doble en memoria;
 * contra la red local, StellarSealingNetworkTest (make test-stellar).
 */

const SPONSOR_LOW_ALERT = '🚨 URGENTE: La cuenta patrocinadora de GovTrace tiene saldo crítico (32,5 XLM). Recargue la cuenta GCMN776RZKGVGGLLL2KB5YW322QOAA2TAHINWC6322RE2VBX4UWRSNGF inmediatamente para evitar el bloqueo en la cola de sellado.';
const CODE_EXPIRING_ALERT = '⏳ La vigencia del código del contrato de sellado vence en 20 días (19/10/2026). Extiéndala desde la cuenta de tesorería antes de esa fecha: si vence, el siguiente sello la restaura con cargo a la cuenta patrocinadora.';
const ALERT_WEBHOOK = 'https://hooks.example.test/govtrace-alertas';

/** A ledger closes every ~5 s: 17.280 a day. */
const LEDGERS_PER_DAY = 17_280;

beforeEach(function () {
    $this->artisan('migrate');
    Notification::fake();
    $this->network = new FakeSealingNetwork;
    app()->instance(SealingNetwork::class, $this->network);
    config(['services.alerts.webhook_url' => ALERT_WEBHOOK]);
    $this->superAdmin = SuperAdmin::factory()->create();
    $this->lastParameterVersion = (int) ParameterValue::query()->max('id');

    // Antecedentes: el umbral de D12, sembrado por la migración.
    expect(Parameters::current('sponsor_balance_alert_threshold_xlm'))->toBe('50')
        ->and($this->network->sponsorAddress())->toBe('GCMN776RZKGVGGLLL2KB5YW322QOAA2TAHINWC6322RE2VBX4UWRSNGF');
});

afterEach(function () {
    ParameterValue::query()->where('id', '>', $this->lastParameterVersion)->delete();
    $this->superAdmin->delete();
});

function checkSponsorBalance(): void
{
    app()->call([new CheckSponsorBalance, 'handle']);
}

function checkContractLifetime(): void
{
    app()->call([new CheckContractLifetime, 'handle']);
}

/** The instance and the code of the contract, alive for that many more days. */
function contractAliveFor(int $instanceDays, int $codeDays): ContractLifetime
{
    $latest = 50_000;

    return new ContractLifetime($latest, $latest + $instanceDays * LEDGERS_PER_DAY, $latest + $codeDays * LEDGERS_PER_DAY);
}

/** The Super Administrador changes the threshold (US-038-CFG); in force from this very second. */
function sponsorAlertThreshold(string $xlm): void
{
    Parameters::set('sponsor_balance_alert_threshold_xlm', $xlm, now()->startOfSecond());
}

/** What an alert says, by email to a Super Administrador and by webhook. */
function assertAlerted(string $class, string $message, int $times = 1): void
{
    Notification::assertSentToTimes(test()->superAdmin, $class, $times);
    Notification::assertSentTo(test()->superAdmin, $class, fn ($alert, array $channels, $notifiable) => $channels === ['mail']
        && in_array($message, $alert->toMail($notifiable)->introLines, true));

    Notification::assertSentOnDemandTimes($class, $times);
    Notification::assertSentOnDemand($class, fn ($alert, array $channels, AnonymousNotifiable $notifiable) => $channels === [WebhookChannel::class]
        && $notifiable->routes[WebhookChannel::class] === ALERT_WEBHOOK
        && $alert->toWebhook($notifiable) === $message);
}

// US-022 ---------------------------------------------------------------------

it('Consulta periódica del saldo: every 15 minutes, through the Stellar RPC', function () {
    Artisan::call('schedule:list');
    expect(Artisan::output())->toMatch('/\*\/15\s+\*\s+\*\s+\*\s+\*\s+sponsor-balance-check/');

    checkSponsorBalance();

    expect($this->network->balanceReads)->toBe(1);
});

it('Alerta cuando el saldo cae bajo el umbral', function (int $balanceStroops, bool $sent) {
    $this->network->sponsorBalanceStroops = $balanceStroops;

    checkSponsorBalance();

    $sent ? Notification::assertSentTo($this->superAdmin, SponsorBalanceLow::class)
        : Notification::assertNothingSent();
})->with([
    '50 XLM: no se envía' => [500_000_000, false],
    '49,9 XLM: se envía' => [499_000_000, true],
]);

it('Contenido y canales de la alerta: by email and by webhook, with the balance and the account to top up', function () {
    $this->network->sponsorBalanceStroops = 325_000_000; // 32,5 XLM

    checkSponsorBalance();

    assertAlerted(SponsorBalanceLow::class, SPONSOR_LOW_ALERT);
});

it('Aviso antes de que venza la vigencia del contrato: the code expires in 20 days, the instance in 150', function () {
    Carbon::setTestNow('2026-09-29 12:00:00'); // 07:00 en Colombia
    $this->network->lifetime = contractAliveFor(instanceDays: 150, codeDays: 20);

    checkContractLifetime();

    // Uno solo, el del código: la instancia vive 150 días más.
    assertAlerted(ContractExpiring::class, CODE_EXPIRING_ALERT);
});

// Reglas de US-022 -------------------------------------------------------------

it('alerts once while the balance stays low, and again when it falls after a top-up', function () {
    $this->network->sponsorBalanceStroops = 325_000_000;
    checkSponsorBalance();
    checkSponsorBalance();
    checkSponsorBalance();

    // La tesorería la recarga…
    $this->network->sponsorBalanceStroops = 1_000_000_000;
    checkSponsorBalance();

    // …y vuelve a caer.
    $this->network->sponsorBalanceStroops = 400_000_000;
    checkSponsorBalance();

    Notification::assertSentToTimes($this->superAdmin, SponsorBalanceLow::class, 2);
});

it('reads the threshold from the parameter the Super Administrador sets (US-038-CFG)', function () {
    sponsorAlertThreshold('100');
    $this->network->sponsorBalanceStroops = 600_000_000; // 60 XLM

    checkSponsorBalance();

    Notification::assertSentTo($this->superAdmin, SponsorBalanceLow::class);
});

it('compares with a threshold that has decimals, to the stroop', function (string $threshold, int $balanceStroops, bool $sent) {
    sponsorAlertThreshold($threshold);
    $this->network->sponsorBalanceStroops = $balanceStroops;

    checkSponsorBalance();

    $sent ? Notification::assertSentTo($this->superAdmin, SponsorBalanceLow::class)
        : Notification::assertNothingSent();
})->with([
    '32,5 XLM con un stroop menos' => ['32.5', 324_999_999, true],
    'justo 32,5 XLM' => ['32.5', 325_000_000, false],
    'bajo 32,5000001 XLM' => ['32.5000001', 325_000_000, true],
]);

it('writes the balance as XLM reads in Colombia', function (int $balanceStroops, string $written) {
    sponsorAlertThreshold('5000');
    $this->network->sponsorBalanceStroops = $balanceStroops;

    checkSponsorBalance();

    Notification::assertSentTo($this->superAdmin, SponsorBalanceLow::class, fn (SponsorBalanceLow $alert) => str_contains($alert->message(), "({$written} XLM)"));
})->with([
    'miles y siete decimales' => [12_345_678_901, '1.234,5678901'],
    'menos de 1 XLM' => [670_000, '0,067'],
    'sin decimales' => [1_000_000_000, '100'],
]);

it('sends the alert only by email when no webhook is configured', function () {
    config(['services.alerts.webhook_url' => null]);
    $this->network->sponsorBalanceStroops = 325_000_000;

    checkSponsorBalance();

    Notification::assertSentTo($this->superAdmin, SponsorBalanceLow::class);
    Notification::assertSentOnDemandTimes(SponsorBalanceLow::class, 0);
});

it('neither alerts nor forgets a low balance when the Stellar RPC does not answer', function () {
    $this->network->sponsorBalanceStroops = 325_000_000;
    checkSponsorBalance();

    $this->network->unavailableCalls = 1;
    expect(fn () => checkSponsorBalance())->toThrow(NetworkUnavailable::class);
    checkSponsorBalance();

    Notification::assertSentToTimes($this->superAdmin, SponsorBalanceLow::class, 1);
});

it('checks the lifetime of the contract every day', function () {
    Artisan::call('schedule:list');

    expect(Artisan::output())->toMatch('/0\s+12\s+\*\s+\*\s+\*\s+contract-lifetime-check/');
});

it('warns under 30 days of lifetime, not at 30', function (int $codeDays, bool $warned) {
    $this->network->lifetime = contractAliveFor(instanceDays: 150, codeDays: $codeDays);

    checkContractLifetime();

    $warned ? Notification::assertSentTo($this->superAdmin, ContractExpiring::class)
        : Notification::assertNothingSent();
})->with([
    '30 días: no avisa' => [30, false],
    '29 días: avisa' => [29, true],
]);

it('warns about the instance too, and once for each expiry: an extension arms the warning again', function () {
    Carbon::setTestNow('2026-09-29 12:00:00');
    $this->network->lifetime = contractAliveFor(instanceDays: 10, codeDays: 150);
    checkContractLifetime();
    checkContractLifetime(); // al día siguiente, la misma fecha: ya se avisó

    // La tesorería extiende la instancia, y el tiempo pasa hasta que vuelve a vencer.
    $this->network->lifetime = contractAliveFor(instanceDays: 25, codeDays: 150);
    checkContractLifetime();

    Notification::assertSentToTimes($this->superAdmin, ContractExpiring::class, 2);
    Notification::assertSentTo($this->superAdmin, ContractExpiring::class, fn (ContractExpiring $alert) => $alert->message()
        === '⏳ La vigencia de la instancia del contrato de sellado vence en 10 días (09/10/2026). Extiéndala desde la cuenta de tesorería antes de esa fecha: si vence, el siguiente sello la restaura con cargo a la cuenta patrocinadora.');
});

// El canal de webhook ----------------------------------------------------------

it('posts the alert to the webhook as Slack and Discord read it', function () {
    Http::fake([ALERT_WEBHOOK => Http::response('ok')]);

    (new WebhookChannel)->send(
        (new AnonymousNotifiable)->route(WebhookChannel::class, ALERT_WEBHOOK),
        new SponsorBalanceLow('GCMN776RZKGVGGLLL2KB5YW322QOAA2TAHINWC6322RE2VBX4UWRSNGF', 325_000_000),
    );

    // Slack lee "text"; Discord, "content".
    Http::assertSent(fn (Request $request) => $request->url() === ALERT_WEBHOOK
        && $request->method() === 'POST'
        && $request['text'] === SPONSOR_LOW_ALERT
        && $request['content'] === SPONSOR_LOW_ALERT);
});

it('does not fail the alert when the webhook does not answer: the email already went out', function () {
    Http::fake([ALERT_WEBHOOK => Http::response('caído', 503)]);

    (new WebhookChannel)->send(
        (new AnonymousNotifiable)->route(WebhookChannel::class, ALERT_WEBHOOK),
        new SponsorBalanceLow('GCMN776RZKGVGGLLL2KB5YW322QOAA2TAHINWC6322RE2VBX4UWRSNGF', 325_000_000),
    );

    Http::assertSentCount(1);
});

// El panel del Super Administrador ------------------------------------------------

it('shows the Super Administrador the balance, the threshold and the lifetime of the contract', function () {
    Carbon::setTestNow('2026-09-29 12:00:00');
    $this->network->sponsorBalanceStroops = 325_000_000;
    $this->network->lifetime = contractAliveFor(instanceDays: 150, codeDays: 20);

    $data = $this->actingAs($this->superAdmin, 'web')->getJson('http://govtrace.localhost/admin/sealing/data')->assertOk()->json();

    expect($data['sponsor'])->toBe([
        'address' => 'GCMN776RZKGVGGLLL2KB5YW322QOAA2TAHINWC6322RE2VBX4UWRSNGF',
        'balance_xlm' => '32.5',
        'threshold_xlm' => '50',
        'low' => true,
    ])->and($data['contract'])->toBe([
        'id' => FakeSealingNetwork::CONTRACT_ID,
        'instance' => ['days' => 150, 'expires_on' => '2027-02-26'],
        'code' => ['days' => 20, 'expires_on' => '2026-10-19'],
    ])->and($data['network_error'])->toBeNull();
});

it('still shows the failed seals when the Stellar RPC does not answer, and says so', function () {
    $this->network->unavailableCalls = 1;

    $data = $this->actingAs($this->superAdmin, 'web')->getJson('http://govtrace.localhost/admin/sealing/data')->assertOk()->json();

    expect($data['sponsor'])->toBeNull()
        ->and($data['contract'])->toBeNull()
        ->and($data['network_error'])->toBe('No se pudo consultar la red de Stellar. Intente de nuevo en unos minutos.')
        ->and($data['failures'])->toBe([]);
});

it('serves the sealing screen of the global panel', function () {
    $this->withoutVite()->actingAs($this->superAdmin, 'web')
        ->get('http://govtrace.localhost/admin/sealing')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('SuperAdmin/Sealing'));
});
