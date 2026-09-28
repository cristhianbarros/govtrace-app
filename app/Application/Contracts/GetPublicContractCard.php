<?php

namespace App\Application\Contracts;

use App\Domain\Contracts\Contract;

/**
 * US-017 (R-SEC-01, R-VER-02): los datos del contrato para la tarjeta
 * pública de la obra. Público, sin sesión. La ficha de obra todavía no
 * existe (it. 9) ni la vista que la usa (US-029, it. 26) — esta clase
 * solo arma los datos a partir del contrato; la pantalla decide cómo
 * ubicarla y cómo formatear el valor en pesos.
 */
class GetPublicContractCard
{
    private const CANCELLED_NOTICE = '⚠️ Contrato Anulado/Retirado en SECOP';

    /**
     * @return array{entity_name: string, contractor_name: ?string, value: ?string, term_months: ?int, secop_url: ?string, cancelled: bool, cancelled_notice: ?string}
     */
    public function handle(Contract $contract): array
    {
        $cancelled = $contract->status === 'cancelled';

        return [
            'entity_name' => $contract->entity_name,
            'contractor_name' => $contract->contractor_name,
            'value' => $contract->value,
            'term_months' => $this->termInMonths($contract),
            'secop_url' => $contract->secop_url,
            'cancelled' => $cancelled,
            'cancelled_notice' => $cancelled ? self::CANCELLED_NOTICE : null,
        ];
    }

    private function termInMonths(Contract $contract): ?int
    {
        if (! $contract->signed_at || ! $contract->end_date) {
            return null;
        }

        return $contract->signed_at->diffInMonths($contract->end_date);
    }
}
