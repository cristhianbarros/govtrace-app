<?php

namespace App\Application\Sealing;

use App\Domain\Sealing\ReportSeal;
use App\Domain\Sealing\SealStatus;
use App\Domain\Sealing\Xlm;
use App\Infrastructure\Tenancy\Tenant;

/**
 * US-004: lo que costó sellar, por mes y por organización — las comisiones
 * que la red le cobró a la cuenta patrocinadora, en XLM y estimadas en
 * pesos con el precio de XLM de hoy (o el último conocido). El mes es el de
 * la hora del ledger que incluyó el sello, en la hora de Colombia.
 */
final class SealingCosts
{
    private const TIMEZONE = 'America/Bogota';

    public function __construct(private readonly XlmPrice $price) {}

    /**
     * @return array{
     *     price: array{cop_per_xlm: float, quoted_at: string, live: bool}|null,
     *     rows: list<array{month: string, organization: string, sealed: int, fee_xlm: string, cost_cop: int|null, without_fee: int}>
     * }
     */
    public function report(): array
    {
        $price = $this->price->current();
        $rows = [];

        foreach (Tenant::query()->get() as $tenant) {
            $monthly = $tenant->run(fn () => ReportSeal::query()
                ->where('status', SealStatus::Sealed)
                ->selectRaw("to_char((sealed_at at time zone 'UTC') at time zone ?, 'YYYY-MM') as month", [self::TIMEZONE])
                ->selectRaw('count(*) as sealed, coalesce(sum(fee_stroops), 0) as fee_stroops, count(*) - count(fee_stroops) as without_fee')
                ->groupBy('month')
                ->get()
                ->toArray());

            foreach ($monthly as $month) {
                $feeStroops = (int) $month['fee_stroops'];

                $rows[] = [
                    'month' => $month['month'],
                    'organization' => $tenant->displayName(),
                    'sealed' => (int) $month['sealed'],
                    'fee_xlm' => Xlm::fromStroops($feeStroops),
                    'cost_cop' => $price === null ? null : (int) round($feeStroops * $price['cop_per_xlm'] / Xlm::STROOPS),
                    'without_fee' => (int) $month['without_fee'],
                ];
            }
        }

        // El mes más reciente primero; dentro del mes, por organización.
        usort($rows, fn (array $a, array $b) => [$b['month'], $a['organization']] <=> [$a['month'], $b['organization']]);

        return ['price' => $price, 'rows' => $rows];
    }
}
