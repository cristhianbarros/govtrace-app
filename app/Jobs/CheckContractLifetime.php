<?php

namespace App\Jobs;

use App\Application\Sealing\SealingNetwork;
use App\Application\Sealing\SuperAdminAlerts;
use App\Domain\Sealing\Notifications\ContractExpiring;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Cache;

/**
 * US-022, D12: cada día, cuánto les queda de vigencia a la instancia y al
 * código del contrato de sellado. Con menos de 30 días avisa al Super
 * Administrador, para que la tesorería las extienda (make testnet-extend,
 * o su equivalente en producción). Un aviso por cada fecha de vencimiento:
 * tras una extensión, la siguiente vuelve a avisar.
 */
class CheckContractLifetime implements ShouldQueue
{
    use Dispatchable, Queueable;

    public const WARN_UNDER_DAYS = 30;

    public function handle(SealingNetwork $network): void
    {
        $lifetime = $network->contractLifetime();
        $now = CarbonImmutable::now();

        foreach (['instance' => $lifetime->instanceLiveUntil, 'code' => $lifetime->codeLiveUntil] as $entry => $liveUntil) {
            $daysLeft = $lifetime->daysLeft($liveUntil);

            if ($daysLeft >= self::WARN_UNDER_DAYS || ! Cache::add("sealing:contract-expiring:{$entry}:{$liveUntil}", true, $now->addDays(self::WARN_UNDER_DAYS + 1))) {
                continue;
            }

            SuperAdminAlerts::send(new ContractExpiring($entry, $daysLeft, $lifetime->expiresAt($liveUntil, $now)));
        }
    }
}
