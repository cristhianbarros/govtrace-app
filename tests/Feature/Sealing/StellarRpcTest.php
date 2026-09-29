<?php

use App\Application\Sealing\Exceptions\NetworkUnavailable;
use App\Infrastructure\Stellar\StellarRpc;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Soneso\StellarSDK\Xdr\XdrSCVal;

/*
 * Iteración 22 — US-021: si el RPC de Stellar no responde o responde con un
 * error del servidor, el sellado lo recibe como "la red no respondió" y lo
 * reintenta con su política, en vez de fallar con una excepción de HTTP que
 * la cola reintentaría de inmediato.
 */

it('turns a connection failure or a server error of the RPC into NetworkUnavailable', function (Closure $answer) {
    Http::fake(['*' => $answer]);

    expect(fn () => (new StellarRpc('http://stellar:8000/rpc'))->transaction(str_repeat('ab', 32)))
        ->toThrow(NetworkUnavailable::class, 'La red de Stellar no respondió');
})->with([
    // El parámetro es Closure: Pest la entrega tal cual, y Http::fake la llama con cada petición.
    'sin conexión' => [fn () => throw new ConnectionException('cURL error 7: Failed to connect')],
    'error 503' => [fn () => Http::response('Service Unavailable', 503)],
]);

// Iteración 23 — la transacción que selló una raíz, por su evento ---------

/** A contract address on the local network: the RPC only reads it here. */
const EVENT_CONTRACT = 'CC7BYKS72OXHHF66HLDLTB7MAEJYEAUFQPR56HB4DGVZ6K4E7OAPWI3E';

it('asks for the event in that single ledger: the RPC scans at most 10,000 per call', function () {
    Http::fake(['*' => Http::response(['jsonrpc' => '2.0', 'id' => 1, 'result' => ['events' => [
        ['ledger' => 40, 'txHash' => 'f2f24e663cd920ecd0f4293b5ee0b15f3449116aab56d6e1ee47ac39fc6f54d1', 'inSuccessfulContractCall' => true],
    ]]])]);

    $txHash = (new StellarRpc('http://stellar:8000/rpc'))->eventTransactionHash(EVENT_CONTRACT, [XdrSCVal::forSymbol('sealed')], 40);

    expect($txHash)->toBe('f2f24e663cd920ecd0f4293b5ee0b15f3449116aab56d6e1ee47ac39fc6f54d1');
    Http::assertSent(fn ($request) => $request['method'] === 'getEvents'
        && $request['params']['startLedger'] === 40
        && $request['params']['endLedger'] === 41
        && $request['params']['filters'] === [['type' => 'contract', 'contractIds' => [EVENT_CONTRACT], 'topics' => [['AAAADwAAAAZzZWFsZWQAAA==']]]]);
});

it('knows no transaction for a ledger older than its history: 7 days by default', function () {
    Http::fake(['*' => Http::response(['jsonrpc' => '2.0', 'id' => 1, 'error' => ['code' => -32600, 'message' => 'startLedger must be within the ledger range: 7 - 37079']])]);

    expect((new StellarRpc('http://stellar:8000/rpc'))->eventTransactionHash(EVENT_CONTRACT, [XdrSCVal::forSymbol('sealed')], 3))->toBeNull();
});

it('does not take an RPC that did not answer for one without the event: that is retried', function () {
    Http::fake(['*' => fn () => throw new ConnectionException('cURL error 28: Operation timed out')]);

    expect(fn () => (new StellarRpc('http://stellar:8000/rpc'))->eventTransactionHash(EVENT_CONTRACT, [XdrSCVal::forSymbol('sealed')], 40))
        ->toThrow(NetworkUnavailable::class);
});
