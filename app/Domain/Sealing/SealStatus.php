<?php

namespace App\Domain\Sealing;

/**
 * US-020b: el recorrido de un reporte hasta quedar sellado en Stellar.
 * "Sellada" es incluida con éxito en un ledger cerrado, y en Stellar eso es
 * definitivo: no hay confirmaciones extra ni reorganizaciones (R-BLK-06).
 */
enum SealStatus: string
{
    /** Pasó las validaciones del servidor. */
    case Received = 'received';

    /** Su trabajo de sellado está despachado. */
    case Queued = 'queued';

    /** La transacción está enviada a la red y tiene su hash. */
    case Transmitting = 'transmitting';

    /** La red la incluyó en un ledger cerrado. */
    case Sealed = 'sealed';

    /** US-021: falló el quinto intento. Espera al soporte técnico; no hay sexto intento automático. */
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Received => 'Recibida',
            self::Queued => 'En Cola',
            self::Transmitting => 'Transmitiendo',
            self::Sealed => 'Sellada',
            self::Failed => 'Falla de Sellado',
        };
    }

    /** US-010: lo que ve el veedor. Nunca un error de sellado: una falla se le muestra "En Cola". */
    public function veedorLabel(): string
    {
        return match ($this) {
            self::Received, self::Queued, self::Failed => 'En Cola',
            self::Transmitting => 'Sellando',
            self::Sealed => 'Sellado',
        };
    }
}
