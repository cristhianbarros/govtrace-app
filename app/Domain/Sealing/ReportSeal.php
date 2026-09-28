<?php

namespace App\Domain\Sealing;

use App\Domain\Reports\Exceptions\EvidenceIsImmutable;
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

    /** Once written, the seal stays as it is (R-TA-02): the root, what it seals, and the ledger that closed it. */
    private const WRITTEN_ONCE = ['report_id', 'merkle_root', 'metadata_json', 'worksite_reference', 'ledger', 'sealed_at'];

    protected static function booted(): void
    {
        static::deleting(fn () => throw EvidenceIsImmutable::cannotDelete());

        static::updating(function (self $seal) {
            $rewritten = array_values(array_filter(
                self::WRITTEN_ONCE,
                fn (string $field) => $seal->isDirty($field) && $seal->getOriginal($field) !== null,
            ));

            if ($rewritten) {
                throw EvidenceIsImmutable::cannotAlter($rewritten);
            }
        });
    }

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
