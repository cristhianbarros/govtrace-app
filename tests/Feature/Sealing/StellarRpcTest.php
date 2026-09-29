<?php

use App\Application\Sealing\Exceptions\NetworkUnavailable;
use App\Infrastructure\Stellar\StellarRpc;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

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
