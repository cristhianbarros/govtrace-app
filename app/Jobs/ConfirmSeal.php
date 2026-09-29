<?php

namespace App\Jobs;

use App\Application\Sealing\Exceptions\NetworkUnavailable;
use App\Application\Sealing\Exceptions\SealingNetworkError;
use App\Application\Sealing\SealingNetwork;
use App\Domain\Sealing\ReportSeal;
use App\Domain\Sealing\SealingRetryPolicy;
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
 *
 * US-021: una transacción rechazada, o que la red no incluye en 5 minutos,
 * es un intento fallido: vuelve a la cola con la política de reintentos.
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

            // Sellada, de vuelta en la cola o en falla: nada que confirmar.
            if ($seal->status !== SealStatus::Transmitting) {
                return;
            }

            try {
                $onChain = $network->transactionStatus($seal->tx_hash);
            } catch (NetworkUnavailable $e) {
                // El RPC no responde: la transacción puede estar bien. Se espera, sin gastar intentos.
                $this->waitOrGiveUp($seal, $e->getMessage());

                return;
            } catch (SealingNetworkError $e) {
                // La red procesó la transacción y la rechazó.
                $this->backToQueue($seal, $e->getMessage());

                return;
            }

            if ($onChain === null) {
                $this->waitOrGiveUp($seal, 'La red de Stellar no incluyó la transacción en un ledger en 5 minutos.');

                return;
            }

            $seal->markSealed($onChain->ledger, $onChain->sealedAt, $seal->tx_hash);
        });
    }

    /** Sigue "Transmitiendo" hasta 5 minutos; después cuenta como un intento fallido (US-021). */
    private function waitOrGiveUp(ReportSeal $seal, string $reason): void
    {
        if ($seal->transmitted_at->copy()->addSeconds(SealingRetryPolicy::CONFIRMATION_DEADLINE_SECONDS)->isFuture()) {
            $this->release(self::RETRY_SECONDS);

            return;
        }

        $this->backToQueue($seal, $reason);
    }

    /**
     * Un intento fallido: a la cola, y otro SealReport con el retraso que toca.
     * Si la primera transacción entra después, el reenvío recibe "Hash ya
     * registrado" y toma el sello que la red ya tiene.
     */
    private function backToQueue(ReportSeal $seal, string $reason): void
    {
        $delay = $seal->recordFailedAttempt($reason);

        if ($delay !== null) {
            SealReport::dispatch($this->tenantId, $this->reportId)->delay(now()->addSeconds($delay));
        }
    }
}
