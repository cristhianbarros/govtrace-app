<?php

namespace App\Jobs;

use App\Application\Sealing\Exceptions\RootAlreadySealed;
use App\Application\Sealing\Exceptions\SealingNetworkError;
use App\Application\Sealing\Exceptions\SponsorOutOfFunds;
use App\Application\Sealing\PrepareReportSeal;
use App\Application\Sealing\SealingNetwork;
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
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Notification;

/**
 * US-020b: sella un reporte "En Cola". Arma su árbol de Merkle, envía la
 * raíz a la red (firma la selladora, paga la patrocinadora con fee bump) y
 * lo deja "Transmitiendo"; ConfirmSeal lo lleva a "Sellada".
 *
 * - Sin XLM en la patrocinadora: pausa el sellado, avisa una sola vez al
 *   Super Administrador y reintenta; se reanuda solo cuando hay saldo.
 * - "Hash ya registrado" (un reintento del mismo reporte): no reenvía;
 *   toma el sello que la red ya tiene (US-020a).
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

    /**
     * La cuenta selladora firma con su número de secuencia: dos envíos a la
     * vez chocarían en la red. Uno por vez, para todas las organizaciones.
     *
     * @return list<object>
     */
    public function middleware(): array
    {
        return [(new WithoutOverlapping('stellar-sealer'))->releaseAfter(10)->expireAfter(120)];
    }

    public function retryUntil(): DateTimeInterface
    {
        return now()->addDay();
    }

    public function handle(SealingNetwork $network, PrepareReportSeal $prepare): void
    {
        Tenant::query()->findOrFail($this->tenantId)->run(function () use ($network, $prepare) {
            $seal = ReportSeal::query()->where('report_id', $this->reportId)->firstOrFail();

            if ($seal->isInFlight()) {
                return;
            }

            if (SealingPause::isActive()) {
                if (! $network->sponsorCanPay()) {
                    $this->release(self::PAUSED_RETRY_SECONDS);

                    return;
                }

                SealingPause::end();
            }

            $prepare->handle($seal);

            try {
                $txHash = $network->submitSeal($seal->worksite_reference, $seal->merkle_root);
            } catch (RootAlreadySealed) {
                $onChain = $network->findSeal($seal->merkle_root)
                    ?? throw new SealingNetworkError("La red rechazó {$seal->merkle_root} como ya registrada, pero no la encuentra.");
                $seal->markSealed($onChain->ledger, $onChain->sealedAt, $onChain->txHash);

                return;
            } catch (SponsorOutOfFunds $e) {
                if (SealingPause::start($e->getMessage())) {
                    Notification::send(SuperAdmin::all(), new SponsorOutOfFundsAlert($network->sponsorAddress()));
                }
                $this->release(self::PAUSED_RETRY_SECONDS);

                return;
            }

            $seal->update(['status' => SealStatus::Transmitting, 'tx_hash' => $txHash, 'transmitted_at' => now()]);

            ConfirmSeal::dispatch($this->tenantId, $this->reportId)->delay(now()->addSeconds(ConfirmSeal::RETRY_SECONDS));
        });
    }
}
