<?php

namespace App\Infrastructure\Stellar;

use App\Application\Sealing\Exceptions\NetworkUnavailable;
use App\Application\Sealing\Exceptions\SealingNetworkError;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Soneso\StellarSDK\AbstractTransaction;
use Soneso\StellarSDK\Soroban\Responses\GetLedgerEntriesResponse;
use Soneso\StellarSDK\Soroban\Responses\GetTransactionResponse;
use Soneso\StellarSDK\Soroban\Responses\LedgerEntry;
use Soneso\StellarSDK\Soroban\Responses\SendTransactionResponse;
use Soneso\StellarSDK\Soroban\Responses\SimulateTransactionResponse;
use Soneso\StellarSDK\Transaction;
use Soneso\StellarSDK\Xdr\XdrAccountEntry;
use Soneso\StellarSDK\Xdr\XdrAccountID;
use Soneso\StellarSDK\Xdr\XdrContractDataDurability;
use Soneso\StellarSDK\Xdr\XdrLedgerEntryType;
use Soneso\StellarSDK\Xdr\XdrLedgerKey;
use Soneso\StellarSDK\Xdr\XdrLedgerKeyAccount;
use Soneso\StellarSDK\Xdr\XdrSCAddress;
use Soneso\StellarSDK\Xdr\XdrSCVal;

/**
 * Las llamadas JSON-RPC de Stellar que usa el sellado (it. 13), por el
 * cliente HTTP de Laravel. No se usa el SorobanServer del SDK por dos
 * razones: exige HTTPS salvo en "localhost" (la red local vive en
 * http://stellar:8000 dentro de Docker) y no envía fee bumps. Del SDK sí se
 * usan las transacciones, el XDR y la lectura de las respuestas.
 */
class StellarRpc
{
    public function __construct(private readonly string $url) {}

    public function simulate(Transaction $transaction): SimulateTransactionResponse
    {
        return SimulateTransactionResponse::fromJson($this->call('simulateTransaction', ['transaction' => $transaction->toEnvelopeXdrBase64()]));
    }

    public function send(AbstractTransaction $transaction): SendTransactionResponse
    {
        return SendTransactionResponse::fromJson($this->call('sendTransaction', ['transaction' => $transaction->toEnvelopeXdrBase64()]));
    }

    public function transaction(string $hash): GetTransactionResponse
    {
        return GetTransactionResponse::fromJson($this->call('getTransaction', ['hash' => $hash]));
    }

    /** null si la cuenta no existe en la red (nunca recibió XLM). */
    public function account(string $accountId): ?XdrAccountEntry
    {
        $key = new XdrLedgerKey(XdrLedgerEntryType::ACCOUNT());
        $key->account = new XdrLedgerKeyAccount(new XdrAccountID($accountId));

        $entries = GetLedgerEntriesResponse::fromJson($this->call('getLedgerEntries', ['keys' => [$key->toBase64Xdr()]]))->entries;

        return $entries[0] ?? null ? $entries[0]->getLedgerEntryDataXdr()->account : null;
    }

    /**
     * A persistent entry of a contract, live or archived by its TTL — the
     * RPC returns both (an archived entry keeps its data; it only has to be
     * restored before a contract can use it). null when it doesn't exist.
     */
    public function contractData(string $contractId, XdrSCVal $key): ?LedgerEntry
    {
        $ledgerKey = XdrLedgerKey::forContractData(XdrSCAddress::forContractId($contractId), $key, XdrContractDataDurability::PERSISTENT());

        return GetLedgerEntriesResponse::fromJson($this->call('getLedgerEntries', ['keys' => [$ledgerKey->toBase64Xdr()]]))->entries[0] ?? null;
    }

    /**
     * The hash of the transaction that emitted a contract event with these
     * topics in the ledger $ledger. Only that ledger is searched: the RPC
     * scans at most 10,000 per call. null when it no longer has that ledger
     * (it keeps 7 days by default) or found no such event; an RPC that
     * doesn't answer is NetworkUnavailable, as everywhere.
     *
     * @param  list<XdrSCVal>  $topics
     */
    public function eventTransactionHash(string $contractId, array $topics, int $ledger): ?string
    {
        try {
            $events = $this->call('getEvents', [
                'startLedger' => $ledger,
                'endLedger' => $ledger + 1, // exclusivo
                'filters' => [['type' => 'contract', 'contractIds' => [$contractId], 'topics' => [array_map(fn (XdrSCVal $topic) => $topic->toBase64Xdr(), $topics)]]],
                'pagination' => ['limit' => 10],
            ])['result']['events'] ?? [];
        } catch (NetworkUnavailable $e) {
            throw $e;
        } catch (SealingNetworkError) {
            return null; // "startLedger must be within the ledger range": fuera de su historia
        }

        foreach ($events as $event) {
            if (($event['inSuccessfulContractCall'] ?? true) && $event['ledger'] === $ledger) {
                return $event['txHash'];
            }
        }

        return null;
    }

    /** En stroops (1 XLM = 10.000.000); 0 si la cuenta no existe. */
    public function accountBalance(string $accountId): int
    {
        return (int) ($this->account($accountId)?->getBalance()->toString() ?? 0);
    }

    /** @param  array<string, mixed>  $params */
    private function call(string $method, array $params): array
    {
        try {
            $response = Http::timeout(30)->acceptJson()
                ->post($this->url, ['jsonrpc' => '2.0', 'id' => 1, 'method' => $method, 'params' => $params])
                ->throw()
                ->json();
        } catch (ConnectionException|RequestException $e) {
            // US-021: sin conexión, timeout o error del servidor: "la red no respondió", y el sellado reintenta.
            throw new NetworkUnavailable("La red de Stellar no respondió ({$method}): ".strtok($e->getMessage(), "\n"), previous: $e);
        }

        if (isset($response['error'])) {
            throw new SealingNetworkError("RPC {$method}: ".($response['error']['message'] ?? json_encode($response['error'])));
        }

        return $response;
    }
}
