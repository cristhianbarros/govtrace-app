<?php

namespace App\Jobs;

use App\Application\Sealing\Exceptions\RootAlreadySealed;
use App\Application\Sealing\Exceptions\SealingNetworkBusy;
use App\Application\Sealing\Exceptions\SealingNetworkError;
use App\Application\Sealing\Exceptions\SponsorOutOfFunds;
use App\Application\Sealing\PrepareReportSeal;
use App\Application\Sealing\SealingNetwork;
use App\Domain\Audit\AuditLog;
use App\Domain\Sealing\Notifications\SponsorOutOfFunds as SponsorOutOfFundsAlert;
use App\Domain\Sealing\ReportSeal;
use App\Domain\Sealing\SealingPause;
use App\Domain\Sealing\SealStatus;
use App\Infrastructure\Tenancy\Tenant;
use App\Models\User as SuperAdmin;
use DateTimeInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Notification;

/**
 * US-020b: sella un reporte "En Cola". Arma su árbol de Merkle, envía la
 * raíz a la red (firma la selladora, paga la patrocinadora con fee bump) y
 * lo deja "Transmitiendo"; ConfirmSeal lo lleva a "Sellada".
 *
 * - Sin XLM en la patrocinadora: pausa el sellado, avisa una sola vez al
 *   Super Administrador y reintenta; se reanuda solo cuando hay saldo. La
 *   pausa no gasta intentos: no es una falla.
 * - "Hash ya registrado" (un reintento del mismo reporte): no reenvía;
 *   toma el sello que la red ya tiene (US-020a).
 * - La red de Stellar falla (US-021): el intento se cuenta en el sello y se
 *   reintenta a 1 min, 5 min, 15 min y 1 h; tras el quinto, "Falla de
 *   Sellado", sin más intentos automáticos.
 * - Un reenvío (US-023): el recibo solo muestra la transacción que entró;
 *   la que no, queda en el log de auditoría, junto a la que la reemplazó.
 * - La selladora tiene otra transacción pendiente (it. 39): Stellar no
 *   admite dos, así que el sello espera su turno y vuelve en unos segundos,
 *   sin gastar un intento. El turno lo lleva la red de sellado en la base
 *   central, el mismo para todas las organizaciones y todos los workers.
 */
class SealReport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Cada cuánto vuelve a mirar el saldo mientras el sellado está en pausa. */
    public const PAUSED_RETRY_SECONDS = 300;

    public function __construct(
        public readonly string $tenantId,
        public readonly int $reportId,
    ) {}

    public function retryUntil(): DateTimeInterface
    {
        return now()->addDay();
    }

    public function handle(SealingNetwork $network, PrepareReportSeal $prepare): void
    {
        Tenant::query()->findOrFail($this->tenantId)->run(function () use ($network, $prepare) {
            $seal = ReportSeal::query()->where('report_id', $this->reportId)->firstOrFail();

            if ($seal->isInFlight() || $seal->status === SealStatus::Failed) {
                return;
            }

            $previous = $seal->tx_hash; // la de un intento anterior, si lo hubo

            // Todo lo que habla con la red, junto: cualquier falla suya cuenta como un intento.
            try {
                if (SealingPause::isActive()) {
                    if (! $network->sponsorCanPay()) {
                        $this->release(self::PAUSED_RETRY_SECONDS);

                        return;
                    }

                    SealingPause::end();
                }

                $prepare->handle($seal);
                $txHash = $this->submit($network, $seal);
            } catch (SealingNetworkBusy $e) {
                // Ocupada no es una falla: vuelve cuando la selladora esté libre, con sus intentos intactos.
                $this->release($e->retryAfterSeconds);

                return;
            } catch (SponsorOutOfFunds $e) {
                if (SealingPause::start($e->getMessage())) {
                    Notification::send(SuperAdmin::all(), new SponsorOutOfFundsAlert($network->sponsorAddress()));
                }
                $this->release(self::PAUSED_RETRY_SECONDS);

                return;
            } catch (SealingNetworkError $e) {
                $delay = $seal->recordFailedAttempt($e->getMessage());
                if ($delay !== null) {
                    $this->release($delay);
                }

                return;
            }

            if ($txHash === null) {
                // La red ya la tenía: quedó "Sellada", con la transacción que la selló.
                $this->auditResend($previous, $seal->tx_hash);

                return;
            }

            $seal->update(['status' => SealStatus::Transmitting, 'tx_hash' => $txHash, 'transmitted_at' => now()]);
            $this->auditResend($previous, $txHash);

            ConfirmSeal::dispatch($this->tenantId, $this->reportId)->delay(now()->addSeconds(ConfirmSeal::RETRY_SECONDS));
        });
    }

    private function auditResend(?string $previous, ?string $current): void
    {
        if ($previous === null || $previous === $current) {
            return;
        }

        AuditLog::record(
            action: 'seal.resent',
            organizationId: $this->tenantId,
            before: ['report_id' => $this->reportId, 'tx_hash' => $previous],
            after: ['report_id' => $this->reportId, 'tx_hash' => $current],
        );
    }

    /**
     * The hash of the transaction sent; or null when the network already had
     * this root ("Hash ya registrado", US-020a) and its seal was taken.
     */
    private function submit(SealingNetwork $network, ReportSeal $seal): ?string
    {
        try {
            return $network->submitSeal($seal->worksite_reference, $seal->merkle_root);
        } catch (RootAlreadySealed) {
            $onChain = $network->findSeal($seal->merkle_root)
                ?? throw new SealingNetworkError("La red rechazó {$seal->merkle_root} como ya registrada, pero no la encuentra.");
            $seal->markSealed($onChain->ledger, $onChain->sealedAt, $onChain->txHash, $network->contractId(), $onChain->feeStroops);

            return null;
        }
    }
}
