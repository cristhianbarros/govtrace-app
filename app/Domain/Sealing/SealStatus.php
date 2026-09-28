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

    public function label(): string
    {
        return match ($this) {
            self::Received => 'Recibida',
            self::Queued => 'En Cola',
            self::Transmitting => 'Transmitiendo',
            self::Sealed => 'Sellada',
        };
    }
}
