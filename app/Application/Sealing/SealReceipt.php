<?php

namespace App\Application\Sealing;

use App\Domain\Sealing\ReportSeal;
use App\Domain\Sealing\SealStatus;

/**
 * US-023 / US-025: el Recibo de Inmutabilidad de un reporte — lo que
 * cualquiera necesita para buscar su sello en la red sin pasar por
 * GovTrace: la raíz, la transacción que la incluyó, su ledger y la hora de
 * cierre de ese ledger (no la del servidor), y el contrato que lo guarda.
 *
 * Mientras no está sellado — recibido, en cola, transmitiendo o en falla —
 * solo el mensaje: el veedor nunca ve un error (US-021).
 */
final class SealReceipt
{
    public const PENDING = '⏳ Su evidencia está en proceso de sellado en la red Stellar. Este proceso toma unos minutos. El recibo criptográfico aparecerá aquí en breve.';

    /** @return array<string, mixed> */
    public static function of(ReportSeal $seal): array
    {
        if ($seal->status !== SealStatus::Sealed) {
            return ['sealed' => false, 'message' => self::PENDING];
        }

        return [
            'sealed' => true,
            'merkle_root' => $seal->merkle_root,
            'tx_hash' => $seal->tx_hash,
            'ledger' => $seal->ledger,
            'sealed_at' => $seal->sealed_at->toImmutable()->utc()->toIso8601String(),
            'contract_id' => $seal->contract_id,
            'explorer' => self::explorer($seal),
        ];
    }

    /** @return array{label: string, url: string}|null */
    private static function explorer(ReportSeal $seal): ?array
    {
        $explorer = config('stellar.explorer_url');

        if (! $explorer) {
            return null; // la red local no tiene explorador público
        }

        return [
            'label' => 'Ver en Stellar Expert',
            // Si la red ya no recuerda la transacción, el ledger que la incluyó.
            'url' => $seal->tx_hash ? "{$explorer}/tx/{$seal->tx_hash}" : "{$explorer}/ledger/{$seal->ledger}",
        ];
    }
}
