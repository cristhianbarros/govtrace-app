<?php

namespace App\Domain\Sealing\Notifications;

use App\Domain\Sealing\Xlm;

/** US-022: la cuenta patrocinadora bajó del umbral (D12); la tesorería debe recargarla. */
class SponsorBalanceLow extends CriticalAlert
{
    public function __construct(
        public readonly string $sponsorAddress,
        public readonly int $balanceStroops,
    ) {}

    public function subject(): string
    {
        return '[Urgente] GovTrace: saldo crítico en la cuenta patrocinadora';
    }

    public function message(): string
    {
        $balance = Xlm::display($this->balanceStroops);

        return "🚨 URGENTE: La cuenta patrocinadora de GovTrace tiene saldo crítico ({$balance} XLM). Recargue la cuenta {$this->sponsorAddress} inmediatamente para evitar el bloqueo en la cola de sellado.";
    }
}
