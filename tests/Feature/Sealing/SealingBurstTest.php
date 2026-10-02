<?php

use App\Application\Organization\ConfigureTerritory;
use App\Application\Organization\RegisterOrganization;
use App\Application\Sealing\Exceptions\NetworkUnavailable;
use App\Application\Sealing\Exceptions\SealingNetworkBusy;
use App\Application\Sealing\NetworkSeal;
use App\Application\Sealing\SealingNetwork;
use App\Domain\Sealing\ReportSeal;
use App\Infrastructure\Stellar\SealerTurn;
use App\Infrastructure\Stellar\StellarRpc;
use App\Infrastructure\Stellar\StellarSealingNetwork;
use App\Infrastructure\Tenancy\Tenant;
use App\Jobs\ConfirmSeal;
use App\Jobs\SealReport;
use Database\Seeders\DivipolaSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use phpseclib3\Math\BigInteger;
use Soneso\StellarSDK\AbstractTransaction;
use Soneso\StellarSDK\Account;
use Soneso\StellarSDK\Crypto\KeyPair;
use Soneso\StellarSDK\FeeBumpTransactionBuilder;
use Soneso\StellarSDK\InvokeContractHostFunction;
use Soneso\StellarSDK\InvokeHostFunctionOperationBuilder;
use Soneso\StellarSDK\Network;
use Soneso\StellarSDK\Soroban\Responses\GetTransactionResponse;
use Soneso\StellarSDK\Soroban\Responses\SendTransactionResponse;
use Soneso\StellarSDK\Soroban\Responses\SimulateTransactionResponse;
use Soneso\StellarSDK\TimeBounds;
use Soneso\StellarSDK\Transaction;
use Soneso\StellarSDK\TransactionBuilder;
use Soneso\StellarSDK\Xdr\XdrAccountEntry;
use Soneso\StellarSDK\Xdr\XdrSCVal;
use Soneso\StellarSDK\Xdr\XdrSequenceNumber;

/*
 * Iteración 39 — varias evidencias a la vez, contra la red local de verdad
 * (make test-stellar; requiere make stellar-up && make contract-deploy).
 * Stellar admite una sola transacción pendiente por cuenta: aquí se ve que la
 * selladora sella por turnos, una por ledger, y que lo que responde la red
 * cuando tiene otra pendiente —de otro sello, de una transacción enviada
 * desde otra parte, o leída de un RPC atrasado— es "ocupada", no una falla.
 *
 * Sin RefreshDatabase — registrar la organización ejecuta CREATE DATABASE.
 */

pest()->group('stellar');

beforeEach(function () {
    // StellarRpc habla JSON-RPC por el cliente HTTP de Laravel; tests/Pest.php
    // bloquea cualquier salida real, salvo aquí, que es el objetivo.
    Http::allowStrayRequests();
    $this->artisan('migrate');
    DB::table('sealer_turns')->delete();
    $this->network = app(SealingNetwork::class);
    $this->rpc = app(StellarRpc::class);
    $this->sealer = KeyPair::fromSeed(config('stellar.sealer_secret'));
});

afterEach(function () {
    if (tenant()) {
        tenancy()->end();
    }

    Tenant::query()->get()->each->delete();
    DB::table('contracts')->delete();
    DB::table('audit_logs')->delete();
    DB::table('sealer_turns')->delete();
});

function burstRoot(): string
{
    return bin2hex(random_bytes(32));
}

function burstWorksite(): string
{
    return hash('sha256', 'obra-de-la-rafaga');
}

/** The network with the sealer and sponsor of .env, over another RPC client. */
function networkOver(StellarRpc $rpc): StellarSealingNetwork
{
    return new StellarSealingNetwork(
        $rpc,
        config('stellar.network_passphrase'),
        config('stellar.sealing_contract_id'),
        config('stellar.sealer_secret'),
        config('stellar.sponsor_secret'),
        config('stellar.sponsor_min_balance_xlm'),
    );
}

/** Waits for the network to close a ledger with the transaction (the local network closes one per second). */
function sealedOnLedger(SealingNetwork $network, string $txHash): NetworkSeal
{
    for ($attempt = 0; $attempt < 30; $attempt++) {
        if ($seal = $network->transactionStatus($txHash)) {
            return $seal;
        }
        usleep(500_000);
    }

    throw new RuntimeException("La red no cerró un ledger con {$txHash} en 15 s.");
}

/** The same, for a transaction sent from outside the application. */
function includedOnNetwork(StellarRpc $rpc, string $txHash): void
{
    for ($attempt = 0; $attempt < 30; $attempt++) {
        if ($rpc->transaction($txHash)->status === GetTransactionResponse::STATUS_SUCCESS) {
            return;
        }
        usleep(500_000);
    }

    throw new RuntimeException("La red no incluyó {$txHash} en 15 s.");
}

function turnOfTheSealer(): ?SealerTurn
{
    return SealerTurn::query()->find(test()->sealer->getAccountId());
}

/**
 * A seal sent with the keys of the sealer and the sponsor, but from outside the application — another
 * process, a script, an operator: the turn knows nothing about it. Returns its hash, already pending.
 */
function sealFromElsewhere(): string
{
    $rpc = app(StellarRpc::class);
    $network = new Network(config('stellar.network_passphrase'));
    $sealer = KeyPair::fromSeed(config('stellar.sealer_secret'));
    $sponsor = KeyPair::fromSeed(config('stellar.sponsor_secret'));

    $invocation = new InvokeContractHostFunction(config('stellar.sealing_contract_id'), 'seal', [XdrSCVal::forBytes(hex2bin(burstWorksite())), XdrSCVal::forBytes(random_bytes(32))]);
    $transaction = (new TransactionBuilder(new Account($sealer->getAccountId(), $rpc->account($sealer->getAccountId())->seqNum->sequenceNumber)))
        ->addOperation((new InvokeHostFunctionOperationBuilder($invocation))->build())
        ->setTimeBounds(new TimeBounds(new DateTime('@0'), new DateTime('@'.(time() + 240))))
        ->build();
    $simulation = $rpc->simulate($transaction);
    $transaction->setSorobanTransactionData($simulation->getTransactionData());
    $transaction->addResourceFee($simulation->getMinResourceFee());
    $transaction->setSorobanAuth($simulation->getSorobanAuth());
    $transaction->sign($sealer, $network);
    $feeBump = (new FeeBumpTransactionBuilder($transaction))->setBaseFee(max(100, $transaction->getFee()))->setFeeAccount($sponsor->getAccountId())->build();
    $feeBump->sign($sponsor, $network);

    $sent = $rpc->send($feeBump);
    expect($sent->status)->toBe(SendTransactionResponse::STATUS_PENDING);

    return $sent->hash;
}

/**
 * The queue worker, in the test: runs the sealing jobs when they are due — after the delay each one was
 * dispatched or released with, as `php artisan queue:work` would — until none is left or $seconds pass.
 */
function workSealingQueue(int $seconds): void
{
    $due = [];
    $pulled = [SealReport::class => 0, ConfirmSeal::class => 0];
    $pull = function () use (&$due, &$pulled) {
        foreach ($pulled as $class => $count) {
            $pushed = Queue::pushed($class)->values()->all();
            foreach (array_slice($pushed, $count) as $job) {
                $due[] = [$job->delay instanceof DateTimeInterface ? $job->delay->getTimestamp() : time() + (int) $job->delay, $job];
            }
            $pulled[$class] = count($pushed);
        }
    };

    $deadline = time() + $seconds;
    $pull();
    while ($due !== [] && time() < $deadline) {
        usort($due, fn (array $a, array $b) => $a[0] <=> $b[0]);
        if ($due[0][0] > time()) {
            usleep(200_000);

            continue;
        }

        [, $job] = array_shift($due);
        $run = (new ($job::class)($job->tenantId, $job->reportId))->withFakeQueueInteractions();
        app()->call([$run, 'handle']);
        if ($run->job->isReleased()) {
            $due[] = [time() + (int) $run->job->releaseDelay, $run];
        }
        $pull();
    }
}

it('Varias evidencias a la vez esperan su turno sin gastar intentos: 7 evidences of two organizations, sent at once, reach "Sellada" on the local network, each in its own ledger', function () {
    (new DivipolaSeeder)->run();
    Storage::fake('evidencias');
    Notification::fake();
    reportableContract('CO1.PCCNTR.1234567');

    $reports = [];
    foreach ([['900123456-8', 'Veeduría Ciudadana Santa Marta', 'veeduria-smr', 4], ['890000062-6', 'Veeduría Ciénaga', 'veeduria-cienaga', 3]] as [$nit, $name, $subdomain, $count]) {
        $tenant = (new RegisterOrganization)->handle($nit, $name, $subdomain);
        (new ConfigureTerritory)->handle($tenant, ['47']);
        worksiteWithContracts($tenant, ['CO1.PCCNTR.1234567'], santaMartaWorksiteLocation());
        $veedor = reportingMember($tenant, "veedor@{$subdomain}.org");

        foreach (range(1, $count) as $number) {
            $this->flushSession();
            $reports[] = [$tenant, createdReportId(sendReport($veedor, ['comment' => "Reporte {$number} de {$subdomain}"], "{$subdomain}.govtrace.localhost"), "{$subdomain}.govtrace.localhost")];
            tenancy()->end();
        }
    }

    // Termina en cuanto se vacía la cola: en la red local, unos 26 s; en testnet (ledgers de ~5 s), cerca de un minuto.
    workSealingQueue(seconds: 180);

    $seals = collect($reports)->map(fn (array $report) => $report[0]->run(fn () => ReportSeal::query()->where('report_id', $report[1])->sole()));
    expect($seals->map(fn (ReportSeal $seal) => $seal->status->label())->unique()->all())->toBe(['Sellada'])
        ->and($seals->pluck('attempts')->unique()->all())->toBe([0])
        ->and($seals->pluck('last_error')->filter()->all())->toBe([])
        // Una transacción pendiente a la vez: cada sello entró en su propio ledger.
        ->and($seals->pluck('ledger')->unique())->toHaveCount(7);
});

it('tells a seal to wait its turn while the sealer has a transaction pending, without building or sending another', function () {
    // Una transacción que la red todavía no incluye (no la conoce: para ella, sigue sin entrar).
    SealerTurn::exclusively($this->sealer->getAccountId(), fn (SealerTurn $turn) => $turn->takeFor(str_repeat('7', 64), now()->addSeconds(270)));
    $counting = new class(config('stellar.rpc_url')) extends StellarRpc
    {
        public int $sent = 0;

        public int $simulated = 0;

        public function send(AbstractTransaction $transaction): SendTransactionResponse
        {
            $this->sent++;

            return parent::send($transaction);
        }

        public function simulate(Transaction $transaction): SimulateTransactionResponse
        {
            $this->simulated++;

            return parent::simulate($transaction);
        }
    };

    try {
        networkOver($counting)->submitSeal(burstWorksite(), burstRoot());
        $this->fail('Envió un sello con otra transacción de la selladora pendiente.');
    } catch (SealingNetworkBusy $busy) {
        expect($busy->retryAfterSeconds)->toBe(SealerTurn::RETRY_SECONDS);
    }

    expect($counting->sent)->toBe(0)
        ->and($counting->simulated)->toBe(0);
});

it('frees the turn as soon as it sees its transaction in a ledger, and the next seal goes right away', function () {
    $first = $this->network->submitSeal(burstWorksite(), burstRoot());
    expect(turnOfTheSealer()->tx_hash)->toBe($first)
        ->and(turnOfTheSealer()->isTaken())->toBeTrue();

    $sealed = sealedOnLedger($this->network, $first);

    expect(turnOfTheSealer()->isTaken())->toBeFalse()
        ->and(sealedOnLedger($this->network, $this->network->submitSeal(burstWorksite(), burstRoot()))->ledger)->toBeGreaterThan($sealed->ledger);
});

it('keeps the turn with a transaction whose answer never came: it may be on its way — and in fact it enters', function () {
    $answerLost = new class(config('stellar.rpc_url')) extends StellarRpc
    {
        public function send(AbstractTransaction $transaction): SendTransactionResponse
        {
            parent::send($transaction);

            throw new NetworkUnavailable('La red de Stellar no respondió (sendTransaction): cURL error 28: Operation timed out');
        }
    };

    expect(fn () => networkOver($answerLost)->submitSeal(burstWorksite(), burstRoot()))->toThrow(NetworkUnavailable::class);

    $turn = turnOfTheSealer();
    expect($turn->isTaken())->toBeTrue();
    // La anotada es la que la red tiene: se calcula antes de enviarla.
    includedOnNetwork($this->rpc, $turn->tx_hash);
});

it('waits its turn when the sealer has a transaction the turn does not know of — sent from elsewhere — and goes once it entered', function () {
    $refusedAsBusy = false;

    // La de otra parte tiene que seguir pendiente cuando llega este sello: si la red la incluyó antes, no hubo choque.
    for ($try = 0; $try < 5 && ! $refusedAsBusy; $try++) {
        $elsewhere = sealFromElsewhere();
        try {
            sealedOnLedger($this->network, $this->network->submitSeal(burstWorksite(), burstRoot()));
        } catch (SealingNetworkBusy $busy) {
            $refusedAsBusy = true;
            expect($busy->retryAfterSeconds)->toBe(SealerTurn::RETRY_SECONDS)
                ->and(turnOfTheSealer()->isTaken())->toBeTrue();
        }
        includedOnNetwork($this->rpc, $elsewhere);
    }

    expect($refusedAsBusy)->toBeTrue();

    // Pasado un ledger, el sello va.
    sleep(SealerTurn::HOLD_SECONDS);
    sealedOnLedger($this->network, $this->network->submitSeal(burstWorksite(), burstRoot()));
});

it('reads a spent sequence number — from an RPC that has not seen the last ledger yet — as "wait your turn", not as a failure', function () {
    $lagging = new class(config('stellar.rpc_url')) extends StellarRpc
    {
        public function account(string $accountId): ?XdrAccountEntry
        {
            $entry = parent::account($accountId);
            $entry->seqNum = new XdrSequenceNumber($entry->seqNum->sequenceNumber->subtract(new BigInteger(1)));

            return $entry;
        }
    };

    expect(fn () => networkOver($lagging)->submitSeal(burstWorksite(), burstRoot()))->toThrow(SealingNetworkBusy::class);
});
