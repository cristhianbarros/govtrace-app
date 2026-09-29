<?php

namespace App\Jobs;

use App\Application\Sealing\SealingNetwork;
use App\Application\Sealing\SuperAdminAlerts;
use App\Domain\Configuration\ConfigurableParameter;
use App\Domain\Configuration\Parameters;
use App\Domain\Sealing\Notifications\SponsorBalanceLow;
use App\Domain\Sealing\Xlm;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Cache;

/**
 * US-022: cada 15 minutos, el saldo de la cuenta patrocinadora por el RPC
 * de Stellar. Bajo el umbral (D12, US-038-CFG), alerta al Super
 * Administrador por Email y Webhook: una vez por cruce, no cada 15 minutos
 * mientras siga bajo; al recargarla, la alerta se vuelve a armar.
 *
 * Si el RPC no responde, el trabajo falla sin cambiar nada: esa caída ya la
 * vigilan los reintentos del sellado (US-021).
 */
class CheckSponsorBalance implements ShouldQueue
{
    use Dispatchable, Queueable;

    /** Mientras exista, ya se avisó de este cruce. */
    public const ALERTED_KEY = 'sealing:sponsor-balance-alerted';

    public function handle(SealingNetwork $network): void
    {
        $balance = $network->sponsorBalance();
        $threshold = Xlm::toStroops(Parameters::current(ConfigurableParameter::SponsorBalanceThreshold->value));

        if ($balance >= $threshold) {
            Cache::forget(self::ALERTED_KEY);

            return;
        }

        if (Cache::add(self::ALERTED_KEY, now()->toIso8601String())) {
            SuperAdminAlerts::send(new SponsorBalanceLow($network->sponsorAddress(), $balance));
        }
    }
}
