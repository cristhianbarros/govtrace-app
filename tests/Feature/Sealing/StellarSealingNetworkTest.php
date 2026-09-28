<?php

use App\Application\Organization\ConfigureTerritory;
use App\Application\Organization\RegisterOrganization;
use App\Application\Sealing\Exceptions\RootAlreadySealed;
use App\Application\Sealing\Exceptions\SponsorOutOfFunds;
use App\Application\Sealing\NetworkSeal;
use App\Application\Sealing\SealingNetwork;
use App\Domain\Sealing\ReportSeal;
use App\Infrastructure\Stellar\StellarRpc;
use App\Infrastructure\Stellar\StellarSealingNetwork;
use App\Infrastructure\Tenancy\Tenant;
use App\Jobs\ConfirmSeal;
use App\Jobs\SealReport;
use Database\Seeders\DivipolaSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Soneso\StellarSDK\AbstractTransaction;
use Soneso\StellarSDK\Crypto\KeyPair;
use Soneso\StellarSDK\FeeBumpTransaction;

/*
 * Iteración 13 — el sellado contra la red local standalone de verdad
 * (Done-when: "US-020b en verde contra la red local"). Requiere:
 *   make stellar-up && make contract-deploy
 * y se corre con make test-stellar; make test lo excluye (grupo "stellar").
 *
 * Aquí se prueba lo que un doble no puede: que la selladora firma la
 * invocación y la patrocinadora paga con fee bump, que la red rechaza el
 * duplicado, que una patrocinadora sin XLM se detecta, y un reporte de
 * punta a punta hasta "Sellada" con un ledger real.
 */

pest()->group('stellar');

beforeEach(function () {
    // StellarRpc habla JSON-RPC por el cliente HTTP de Laravel; tests/Pest.php
    // bloquea cualquier salida real, salvo aquí, que es el objetivo.
    Http::allowStrayRequests();
    $this->network = app(SealingNetwork::class);
});

function randomRoot(): string
{
    return bin2hex(random_bytes(32));
}

/** Espera a que la red cierre el ledger con la transacción (la red local cierra uno por segundo). */
function waitForSeal(SealingNetwork $network, string $txHash): NetworkSeal
{
    for ($attempt = 0; $attempt < 30; $attempt++) {
        if ($seal = $network->transactionStatus($txHash)) {
            return $seal;
        }
        usleep(500_000);
    }

    throw new RuntimeException("La red no cerró un ledger con {$txHash} en 15 s.");
}

it('the sealer account signs the invocation and the sponsor account pays it with a fee bump', function () {
    $rpc = app(StellarRpc::class);
    $sealer = KeyPair::fromSeed(config('stellar.sealer_secret'))->getAccountId();
    $sponsor = KeyPair::fromSeed(config('stellar.sponsor_secret'))->getAccountId();
    $sealerBefore = $rpc->accountBalance($sealer);
    $sponsorBefore = $rpc->accountBalance($sponsor);

    $txHash = $this->network->submitSeal(hash('sha256', 'obra-de-prueba'), randomRoot());
    $seal = waitForSeal($this->network, $txHash);

    $envelope = AbstractTransaction::fromEnvelopeBase64XdrString($rpc->transaction($txHash)->envelopeXdr);

    expect($seal->ledger)->toBeGreaterThan(0)
        ->and($envelope)->toBeInstanceOf(FeeBumpTransaction::class)
        ->and($envelope->getFeeAccount()->getAccountId())->toBe($sponsor)
        ->and($envelope->getInnerTx()->getSourceAccount()->getAccountId())->toBe($sealer)
        // R-BLK-04 / D5: la selladora no paga nada; la patrocinadora, la comisión.
        ->and($rpc->accountBalance($sealer))->toBe($sealerBefore)
        ->and($rpc->accountBalance($sponsor))->toBeLessThan($sponsorBefore);
});

it('rejects a root that is already on the network as "Hash ya registrado", and finds its seal', function () {
    $root = randomRoot();
    $first = waitForSeal($this->network, $this->network->submitSeal(hash('sha256', 'obra-de-prueba'), $root));

    expect(fn () => $this->network->submitSeal(hash('sha256', 'otra-obra'), $root))->toThrow(RootAlreadySealed::class);

    $found = $this->network->findSeal($root);
    expect($found->ledger)->toBe($first->ledger)
        ->and($this->network->findSeal(randomRoot()))->toBeNull();
});

it('reports the sponsor out of funds when its account has no XLM', function () {
    $broke = new StellarSealingNetwork(
        app(StellarRpc::class),
        config('stellar.network_passphrase'),
        config('stellar.sealing_contract_id'),
        config('stellar.sealer_secret'),
        KeyPair::random()->getSecretSeed(), // una cuenta que nunca recibió XLM
        config('stellar.sponsor_min_balance_xlm'),
    );

    expect($broke->sponsorCanPay())->toBeFalse()
        ->and(fn () => $broke->submitSeal(hash('sha256', 'obra-de-prueba'), randomRoot()))->toThrow(SponsorOutOfFunds::class)
        ->and($this->network->sponsorCanPay())->toBeTrue();
});

it('seals a report end to end on the local network, up to "Sellada" with a real ledger', function () {
    $this->artisan('migrate');
    (new DivipolaSeeder)->run();
    Storage::fake('evidencias');

    $tenant = (new RegisterOrganization)->handle('900123456-8', 'Veeduría Ciudadana Santa Marta', 'veeduria-smr');
    (new ConfigureTerritory)->handle($tenant, ['47']);
    $veedor = reportingMember($tenant, 'carlos@correo.co');
    reportableContract('CO1.PCCNTR.1234567');
    worksiteWithContracts($tenant, ['CO1.PCCNTR.1234567'], santaMartaWorksiteLocation());

    try {
        $reportId = sendReport($veedor)->assertCreated()->json('id');

        app()->call([new SealReport($tenant->id, $reportId), 'handle']);
        for ($attempt = 0; $attempt < 30; $attempt++) {
            app()->call([new ConfirmSeal($tenant->id, $reportId), 'handle']);
            if ($tenant->run(fn () => ReportSeal::query()->where('report_id', $reportId)->sole()->status->label()) === 'Sellada') {
                break;
            }
            usleep(500_000);
        }

        $seal = $tenant->run(fn () => ReportSeal::query()->where('report_id', $reportId)->sole());

        expect($seal->status->label())->toBe('Sellada')
            ->and($seal->ledger)->toBe($this->network->findSeal($seal->merkle_root)->ledger);
    } finally {
        if (tenant()) {
            tenancy()->end();
        }
        Tenant::query()->get()->each->delete();
        DB::table('contracts')->delete();
    }
});
