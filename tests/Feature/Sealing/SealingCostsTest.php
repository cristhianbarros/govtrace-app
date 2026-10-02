<?php

use App\Application\Organization\ConfigureTerritory;
use App\Application\Organization\RegisterOrganization;
use App\Application\Sealing\SealingNetwork;
use App\Domain\Organization\Roles;
use App\Domain\Organization\User as OrganizationUser;
use App\Domain\Reports\Report;
use App\Domain\Sealing\ReportSeal;
use App\Domain\Sealing\SealStatus;
use App\Domain\Sealing\XlmPriceQuote;
use App\Domain\Worksites\Worksite;
use App\Infrastructure\Tenancy\Tenant;
use App\Jobs\SealReport;
use App\Models\User as SuperAdmin;
use Database\Seeders\DivipolaSeeder;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\Support\FakeSealingNetwork;

/*
 * Iteración 32 — US-004, ajustada a Stellar (features/US-004.feature, 4
 * casos): las comisiones que la red le cobró a la cuenta patrocinadora por
 * cada sello, por mes y organización, en XLM y en pesos con el precio de
 * XLM de CoinGecko — o con el último conocido, si no responde. El precio
 * nunca sale a internet en make test (R-TST-02): Http::fake.
 *
 * Sin RefreshDatabase — registrar la organización ejecuta CREATE DATABASE.
 */

const XLM_PRICE_API = 'https://api.coingecko.com/api/v3/simple/price*';

beforeEach(function () {
    $this->artisan('migrate');
    (new DivipolaSeeder)->run();
    Storage::fake('evidencias');
    Notification::fake();
    $this->network = new FakeSealingNetwork;
    app()->instance(SealingNetwork::class, $this->network);

    $this->smr = (new RegisterOrganization)->handle('900123456-8', 'Veeduría Ciudadana Santa Marta', 'veeduria-smr');
    $this->superAdmin = SuperAdmin::factory()->create();
});

afterEach(function () {
    if (tenant()) {
        tenancy()->end();
    }

    Tenant::query()->get()->each->delete();
    DB::table('contracts')->delete();
    DB::table('xlm_price_quotes')->delete();
    DB::table('audit_logs')->delete();
    $this->superAdmin->delete();
});

/**
 * $count reports of $tenant, sealed at $sealedAt (UTC, the hour of the
 * ledger) with a fee of $feeStroops each. Straight in the tables: sending
 * 40 reports through POST /reports says nothing new here.
 */
function sealedWithFees(Tenant $tenant, int $count, string $sealedAt, ?int $feeStroops, SealStatus $status = SealStatus::Sealed): void
{
    $tenant->run(function () use ($count, $sealedAt, $feeStroops, $status) {
        $veedor = OrganizationUser::query()->firstOrCreate(['email' => 'carlos@correo.co'], ['name' => 'Carlos Gómez', 'password' => 'Veeduria#2026']);
        $worksite = Worksite::query()->firstOrCreate([], ['latitude' => 11.2408, 'longitude' => -74.1990, 'located_at' => now()]);

        foreach (range(1, $count) as $n) {
            $report = Report::create([
                'user_id' => $veedor->id,
                'worksite_id' => $worksite->id,
                'classification' => 'Avance',
                'latitude' => 11.2408,
                'longitude' => -74.1990,
                'accuracy_meters' => 15,
                'geofence_radius_meters' => 500,
                'captured_at' => $sealedAt,
                'received_at' => $sealedAt,
            ]);
            ReportSeal::create([
                'report_id' => $report->id,
                'status' => $status,
                'merkle_root' => hash('sha256', "{$report->id}-{$sealedAt}"),
                'ledger' => $status === SealStatus::Sealed ? 1_000 + $report->id : null,
                'sealed_at' => $status === SealStatus::Sealed ? $sealedAt : null,
                'contract_id' => FakeSealingNetwork::CONTRACT_ID,
                'fee_stroops' => $feeStroops,
            ]);
        }
    });
}

/** CoinGecko's answer to /simple/price?ids=stellar&vs_currencies=cop&include_last_updated_at=true. */
function xlmPriceAnswers(float $copPerXlm, int $lastUpdatedAt = 1_790_000_000): void
{
    Http::fake([XLM_PRICE_API => Http::response(['stellar' => ['cop' => $copPerXlm, 'last_updated_at' => $lastUpdatedAt]])]);
}

function openCosts(): TestResponse
{
    return test()->actingAs(test()->superAdmin, 'web')->getJson('http://govtrace.localhost/admin/costs/data');
}

// US-004 ---------------------------------------------------------------------

it('Reporte mensual por organización con costo en pesos: 40 sealed, 9,7 XLM and $11.640 at 1.200 COP', function () {
    // Antecedentes: 40 sellos de septiembre, a 0,2425 XLM cada uno. El último
    // cerró el 1 de octubre a las 03:00 UTC: el 30 de septiembre en Colombia.
    sealedWithFees($this->smr, 39, '2026-09-15 15:00:00', 2_425_000);
    sealedWithFees($this->smr, 1, '2026-10-01 03:00:00', 2_425_000);
    // Otra organización y otro mes, para ver la agrupación.
    $cienaga = (new RegisterOrganization)->handle('890000062-6', 'Veeduría Ciénaga', 'veeduria-cienaga');
    sealedWithFees($cienaga, 2, '2026-10-05 15:00:00', 2_425_000);
    xlmPriceAnswers(1200, lastUpdatedAt: 1_790_000_000); // 2026-09-21 14:13:20 UTC

    $report = openCosts()->assertOk()->json();

    expect($report['price'])->toBe(['cop_per_xlm' => 1200, 'quoted_at' => '2026-09-21T09:13:20-05:00', 'live' => true])
        ->and($report['rows'])->toBe([
            ['month' => '2026-10', 'organization' => 'Veeduría Ciénaga', 'sealed' => 2, 'fee_xlm' => '0.485', 'cost_cop' => 582, 'without_fee' => 0],
            ['month' => '2026-09', 'organization' => 'Veeduría Ciudadana Santa Marta', 'sealed' => 40, 'fee_xlm' => '9.7', 'cost_cop' => 11_640, 'without_fee' => 0],
        ]);

    Http::assertSent(fn (Request $request) => $request['ids'] === 'stellar' && $request['vs_currencies'] === 'cop');
});

it('El API de precios no responde: the last known price, 1.150 COP from 26/09/2026 10:00', function () {
    sealedWithFees($this->smr, 40, '2026-09-15 15:00:00', 2_425_000);
    XlmPriceQuote::query()->create(['currency' => 'COP', 'price' => 1150, 'quoted_at' => '2026-09-26 15:00:00']); // 10:00 en Colombia
    Http::fake([XLM_PRICE_API => Http::response('Service Unavailable', 503)]);

    $report = openCosts()->assertOk()->json();

    expect($report['price'])->toBe(['cop_per_xlm' => 1150, 'quoted_at' => '2026-09-26T10:00:00-05:00', 'live' => false])
        ->and($report['rows'][0]['cost_cop'])->toBe(11_155);
});

it('Solo el Super Administrador ve el reporte de costos', function (Roles $role) {
    $member = reportingMember($this->smr, 'miembro@veeduria-smr.org', $role);

    // Su sesión es de la organización, no del panel global: allí no entra…
    $this->actingAs($member, 'tenant')->getJson('http://govtrace.localhost/admin/costs/data')->assertUnauthorized();
    // …y el subdominio de su organización no tiene ese reporte.
    $this->actingAs($member, 'tenant')->getJson('http://veeduria-smr.govtrace.localhost/admin/costs/data')->assertNotFound();

    Http::assertNothingSent();
})->with([
    'Administrador de Organización' => [Roles::Administrator],
    'Veedor de Campo' => [Roles::Observer],
]);

// Reglas de US-004 --------------------------------------------------------------

it('keeps the price it got, so it is there the next time the API does not answer', function () {
    sealedWithFees($this->smr, 1, '2026-09-15 15:00:00', 2_425_000);
    Http::fake([XLM_PRICE_API => Http::sequence()
        ->push(['stellar' => ['cop' => 1234.5, 'last_updated_at' => 1_790_000_000]])
        ->push('Too Many Requests', 429)]);
    openCosts()->assertOk();

    expect(openCosts()->json('price'))->toBe(['cop_per_xlm' => 1234.5, 'quoted_at' => '2026-09-21T09:13:20-05:00', 'live' => false]);
});

it('shows the fees in XLM without a cost in pesos when no price was ever known', function () {
    sealedWithFees($this->smr, 1, '2026-09-15 15:00:00', 2_425_000);
    Http::fake([XLM_PRICE_API => Http::response(['stellar' => []])]); // una respuesta sin precio

    $report = openCosts()->assertOk()->json();

    expect($report['price'])->toBeNull()
        ->and($report['rows'][0])->toMatchArray(['sealed' => 1, 'fee_xlm' => '0.2425', 'cost_cop' => null]);
});

it('counts a seal whose fee is not known, and says how many there are', function () {
    sealedWithFees($this->smr, 2, '2026-09-15 15:00:00', 2_425_000);
    sealedWithFees($this->smr, 1, '2026-09-16 15:00:00', null);
    xlmPriceAnswers(1200);

    expect(openCosts()->json('rows'))->toBe([
        ['month' => '2026-09', 'organization' => 'Veeduría Ciudadana Santa Marta', 'sealed' => 3, 'fee_xlm' => '0.485', 'cost_cop' => 582, 'without_fee' => 1],
    ]);
});

it('only counts sealed reports: one in the queue or failed has no fee yet', function () {
    sealedWithFees($this->smr, 1, '2026-09-15 15:00:00', 2_425_000);
    sealedWithFees($this->smr, 1, '2026-09-15 16:00:00', null, SealStatus::Transmitting);
    sealedWithFees($this->smr, 1, '2026-09-15 17:00:00', null, SealStatus::Failed);
    xlmPriceAnswers(1200);

    expect(openCosts()->json('rows'))->toBe([
        ['month' => '2026-09', 'organization' => 'Veeduría Ciudadana Santa Marta', 'sealed' => 1, 'fee_xlm' => '0.2425', 'cost_cop' => 291, 'without_fee' => 0],
    ]);
});

// La comisión de cada sello -----------------------------------------------------

it('records the fee the network charged the sponsor account for each seal', function () {
    (new ConfigureTerritory)->handle($this->smr, ['47']);
    $veedor = reportingMember($this->smr, 'carlos@correo.co');
    reportableContract('CO1.PCCNTR.1234567');
    worksiteWithContracts($this->smr, ['CO1.PCCNTR.1234567'], santaMartaWorksiteLocation());

    $reportId = sealedReport($this->smr, $veedor);

    expect($this->smr->run(fn () => ReportSeal::query()->where('report_id', $reportId)->sole()->fee_stroops))
        ->toBe(FakeSealingNetwork::FEE_STROOPS);
});

it('records the fee also when a resend finds the seal already on the network', function () {
    (new ConfigureTerritory)->handle($this->smr, ['47']);
    $veedor = reportingMember($this->smr, 'carlos@correo.co');
    reportableContract('CO1.PCCNTR.1234567');
    worksiteWithContracts($this->smr, ['CO1.PCCNTR.1234567'], santaMartaWorksiteLocation());
    $reportId = createdReportId(sendReport($veedor));
    tenancy()->end();

    // La red recibe el envío y lo sella, pero la respuesta no llega: el reenvío encuentra "Hash ya registrado".
    $this->network->timeoutsAfterSending = 1;
    app()->call([(new SealReport($this->smr->id, $reportId))->withFakeQueueInteractions(), 'handle']);
    app()->call([(new SealReport($this->smr->id, $reportId))->withFakeQueueInteractions(), 'handle']);

    $seal = $this->smr->run(fn () => ReportSeal::query()->where('report_id', $reportId)->sole());
    expect($seal->status)->toBe(SealStatus::Sealed)
        ->and($seal->fee_stroops)->toBe(FakeSealingNetwork::FEE_STROOPS);
});

it('serves the costs to the sealing screen of the global panel', function () {
    xlmPriceAnswers(1200);

    openCosts()->assertOk()->assertJsonStructure(['price', 'rows']);
});
