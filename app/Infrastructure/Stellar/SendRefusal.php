<?php

namespace App\Infrastructure\Stellar;

use Soneso\StellarSDK\Soroban\Responses\SendTransactionResponse;
use Soneso\StellarSDK\Xdr\XdrTransactionResult;
use Throwable;

/**
 * Lo que respondió la red cuando no tomó un sello (it. 39). Tres respuestas
 * solo dicen "ahora no: la selladora tiene otra transacción pendiente"; cada
 * una se probó contra la red local y está, tal cual llegó, en
 * tests/fixtures/stellar:
 *  - TRY_AGAIN_LATER: la selladora ya tiene una pendiente (Stellar admite una
 *    por cuenta);
 *  - txINSUFFICIENT_FEE: repite el número de secuencia de la pendiente, y la
 *    red la lee como un reemplazo que tendría que pagar 10 veces la comisión
 *    (también puede ser que los ledgers vayan llenos y pidan más: igual, el
 *    turno llega);
 *  - txBAD_SEQ, del fee bump o de la transacción que envuelve: su número de
 *    secuencia ya se gastó, leído de un RPC que no había visto el último
 *    ledger.
 * Cualquier otra es un rechazo, y gasta un intento (US-021).
 */
final class SendRefusal
{
    private const TX_BAD_SEQ = -5;

    private const TX_INSUFFICIENT_FEE = -9;

    private const TX_FEE_BUMP_INNER_FAILED = -13;

    /** The result codes of a transaction, by the names Stellar gives them. */
    private const NAMES = [
        -1 => 'txFAILED', -2 => 'txTOO_EARLY', -3 => 'txTOO_LATE', -4 => 'txMISSING_OPERATION',
        -5 => 'txBAD_SEQ', -6 => 'txBAD_AUTH', -7 => 'txINSUFFICIENT_BALANCE', -8 => 'txNO_ACCOUNT',
        -9 => 'txINSUFFICIENT_FEE', -10 => 'txBAD_AUTH_EXTRA', -11 => 'txINTERNAL_ERROR', -12 => 'txNOT_SUPPORTED',
        -13 => 'txFEE_BUMP_INNER_FAILED', -14 => 'txBAD_SPONSORSHIP', -15 => 'txBAD_MIN_SEQ_AGE_OR_GAP',
        -16 => 'txMALFORMED', -17 => 'txSOROBAN_INVALID',
    ];

    public static function meansSealerBusy(SendTransactionResponse $sent): bool
    {
        if ($sent->status === SendTransactionResponse::STATUS_TRY_AGAIN_LATER) {
            return true;
        }

        [$outer, $inner] = self::codes($sent);

        return $outer === self::TX_INSUFFICIENT_FEE
            || $outer === self::TX_BAD_SEQ
            || ($outer === self::TX_FEE_BUMP_INNER_FAILED && $inner === self::TX_BAD_SEQ);
    }

    /** "ERROR, txBAD_AUTH dentro del fee bump: AAAA…": what the Super Administrador reads in the sealing failures. */
    public static function describe(SendTransactionResponse $sent): string
    {
        [$outer, $inner] = self::codes($sent);

        $code = match (true) {
            $outer === null => null,
            $outer === self::TX_FEE_BUMP_INNER_FAILED && $inner !== null => self::name($inner).' dentro del fee bump',
            default => self::name($outer),
        };

        return $sent->status.($code === null ? '' : ", {$code}").($sent->errorResultXdr === null ? '' : ": {$sent->errorResultXdr}");
    }

    /** @return array{0: int|null, 1: int|null} the code of the fee bump, and of the transaction inside it when that is what failed */
    private static function codes(SendTransactionResponse $sent): array
    {
        if ($sent->errorResultXdr === null) {
            return [null, null];
        }

        try {
            $result = XdrTransactionResult::fromBase64Xdr($sent->errorResultXdr)->result;
        } catch (Throwable) {
            return [null, null]; // an answer that can't be read is a rejection, as it always was
        }

        return [$result->resultCode->getValue(), $result->innerResultPair?->result->result->resultCode->getValue()];
    }

    private static function name(int $code): string
    {
        return self::NAMES[$code] ?? "código {$code}";
    }
}
