<?php

namespace App\Jobs;

use App\Application\Sealing\SealingNetwork;
use App\Domain\Sealing\ReportSeal;
use App\Domain\Sealing\SealStatus;
use App\Infrastructure\Tenancy\Tenant;
use DateTimeInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * US-020b: lleva un reporte "Transmitiendo" a "Sellada" en cuanto la red
 * incluye su transacción en un ledger cerrado. En Stellar eso es
 * definitivo: nada que esperar después (R-BLK-06).
 */
class ConfirmSeal implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** La red cierra un ledger cada ~5 s (la local, más rápido). */
    public const RETRY_SECONDS = 5;

    public function __construct(
        public readonly string $tenantId,
        public readonly int $reportId,
    ) {}

    public function retryUntil(): DateTimeInterface
    {
        return now()->addHour();
    }

    public function handle(SealingNetwork $network): void
    {
        Tenant::query()->findOrFail($this->tenantId)->run(function () use ($network) {
            $seal = ReportSeal::query()->where('report_id', $this->reportId)->firstOrFail();

            if ($seal->status === SealStatus::Sealed) {
                return;
            }

            $onChain = $network->transactionStatus($seal->tx_hash);

            if ($onChain === null) {
                $this->release(self::RETRY_SECONDS); // sigue "Transmitiendo"

                return;
            }

            $seal->markSealed($onChain->ledger, $onChain->sealedAt, $seal->tx_hash);
        });
    }
}
