<?php

namespace App\Domain\Sealing;

use App\Domain\Reports\Report;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * El sellado de un reporte (US-020b): una raíz de Merkle por reporte
 * (R-BLK-05), así que su estado vive aquí y todos sus archivos lo comparten.
 * En la base de la organización, como el reporte.
 */
class ReportSeal extends Model
{
    protected $fillable = [
        'report_id', 'status', 'merkle_root', 'worksite_reference', 'metadata_json',
        'tx_hash', 'ledger', 'received_at', 'queued_at', 'transmitted_at', 'sealed_at',
    ];

    protected $casts = [
        'status' => SealStatus::class,
        'ledger' => 'integer',
        'received_at' => 'datetime',
        'queued_at' => 'datetime',
        'transmitted_at' => 'datetime',
        'sealed_at' => 'datetime',
    ];

    public function report(): BelongsTo
    {
        return $this->belongsTo(Report::class);
    }

    public function isInFlight(): bool
    {
        return in_array($this->status, [SealStatus::Transmitting, SealStatus::Sealed], true);
    }

    /** La red incluyó la raíz en el ledger $ledger, que cerró a la hora $sealedAt. */
    public function markSealed(int $ledger, CarbonInterface $sealedAt, ?string $txHash): void
    {
        $this->update([
            'status' => SealStatus::Sealed,
            'ledger' => $ledger,
            'sealed_at' => $sealedAt,
            'tx_hash' => $this->tx_hash ?? $txHash,
        ]);
    }
}
