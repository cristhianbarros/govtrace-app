<?php

use App\Infrastructure\Stellar\SendRefusal;
use Soneso\StellarSDK\Soroban\Responses\SendTransactionResponse;

/*
 * Iteración 39 — lo que responde la red cuando no toma un sello. Las
 * respuestas son las de la red local, capturadas tal cual
 * (tests/fixtures/stellar): tres dicen solo "ahora no: la selladora tiene
 * otra transacción pendiente", y no son una falla; cualquier otra sí lo es,
 * y gasta un intento (US-021).
 *
 * Con otra pendiente, la red mira el turno antes que la firma: una firma mala
 * llega como txINSUFFICIENT_FEE, y recién sin la pendiente como txBAD_AUTH
 * (send-bad-signature, capturada así). Por eso una falla de verdad se cuenta
 * igual, solo que un turno después.
 */

function sendAnswer(string $name): SendTransactionResponse
{
    return SendTransactionResponse::fromJson(json_decode(file_get_contents(__DIR__."/../../fixtures/stellar/{$name}.json"), true));
}

it('reads as "the sealer is busy" the three answers of a network that has another transaction of the sealer pending', function (string $answer) {
    expect(SendRefusal::meansSealerBusy(sendAnswer($answer)))->toBeTrue();
})->with([
    'la secuencia siguiente, con una pendiente: TRY_AGAIN_LATER' => ['send-next-sequence-while-pending'],
    'la misma secuencia que la pendiente: txINSUFFICIENT_FEE, un reemplazo que costaría 10 veces más' => ['send-same-sequence-as-pending'],
    'una secuencia ya gastada, leída de un RPC atrasado: txBAD_SEQ dentro del fee bump' => ['send-spent-sequence'],
    'una secuencia ya gastada, con otra pendiente: txBAD_SEQ del fee bump' => ['send-spent-sequence-while-pending'],
]);

it('reads any other refusal as a failure, which spends an attempt', function () {
    expect(SendRefusal::meansSealerBusy(sendAnswer('send-bad-signature')))->toBeFalse();
});

it('names the refusal by its code, the one inside the fee bump when that is where it failed', function (string $answer, string $described) {
    expect(SendRefusal::describe(sendAnswer($answer)))->toStartWith($described);
})->with([
    'la firma de otra cuenta' => ['send-bad-signature', 'ERROR, txBAD_AUTH dentro del fee bump: AAAAAAAL'],
    'un reemplazo sin la comisión' => ['send-same-sequence-as-pending', 'ERROR, txINSUFFICIENT_FEE: AAAAAACDttH'],
    'otra pendiente' => ['send-next-sequence-while-pending', 'TRY_AGAIN_LATER'],
]);
