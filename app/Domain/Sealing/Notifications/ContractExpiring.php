<?php

namespace App\Domain\Sealing\Notifications;

use Carbon\CarbonImmutable;

/**
 * US-022, D12: a la instancia o al código del contrato de sellado le quedan
 * menos de 30 días de vigencia. Los extiende la tesorería; si vencen, el
 * siguiente sello los restaura y lo paga la cuenta patrocinadora.
 */
class ContractExpiring extends CriticalAlert
{
    /** @param  'instance'|'code'  $entry */
    public function __construct(
        public readonly string $entry,
        public readonly int $daysLeft,
        public readonly CarbonImmutable $expiresAt,
    ) {}

    public function subject(): string
    {
        return 'GovTrace: la vigencia del contrato de sellado está por vencer';
    }

    public function message(): string
    {
        $what = $this->entry === 'code' ? 'del código' : 'de la instancia';
        $date = $this->expiresAt->timezone('America/Bogota')->format('d/m/Y');

        return "⏳ La vigencia {$what} del contrato de sellado vence en {$this->daysLeft} días ({$date}). Extiéndala desde la cuenta de tesorería antes de esa fecha: si vence, el siguiente sello la restaura con cargo a la cuenta patrocinadora.";
    }
}
