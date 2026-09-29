<?php

namespace App\Domain\Contracts;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * US-048-MNT (R-MNT-04): los contratos cerrados hace más de 5 años salen de
 * "contracts" a "archived_contracts", tal cual y con su mismo id, para
 * contener el crecimiento de la base central; y vuelven cuando hace falta.
 *
 * Es la otra puerta, además de {@see Contract::fromSecop()}, por la que
 * cambian las filas de "contracts": las mueve con el constructor de
 * consultas, sin pasar por los guardias del modelo, porque archivar no es
 * editar ni borrar — la fila sigue existiendo, en otra tabla.
 */
final class ContractArchive
{
    public const AFTER_YEARS = 5;

    /** Every column but archived_at: the same in both tables. */
    private const COLUMNS = [
        'id', 'secop_contract_id', 'process_number', 'entity_name', 'contractor_name', 'object',
        'contract_type', 'value', 'signed_at', 'end_date', 'status', 'department_code',
        'municipality_code', 'secop_url', 'raw_payload', 'cancelled_at', 'created_at', 'updated_at',
    ];

    /** Closed per SECOP (R-SEC-07), since more than 5 years ago. */
    public static function isArchivable(?string $status, ?CarbonInterface $endDate, CarbonInterface $today): bool
    {
        return in_array(mb_strtolower((string) $status), SecopContractStatus::CLOSED, true)
            && $endDate !== null
            && $endDate->lt($today->copy()->startOfDay()->subYears(self::AFTER_YEARS));
    }

    /** The same rule, for a query on "contracts". */
    public static function candidates(CarbonInterface $today): Builder
    {
        return Contract::query()
            ->statusIn(SecopContractStatus::CLOSED)
            ->whereDate('end_date', '<', $today->copy()->subYears(self::AFTER_YEARS));
    }

    /** @param  iterable<string>  $secopContractIds */
    public static function archive(iterable $secopContractIds): void
    {
        $ids = collect($secopContractIds)->values()->all();
        if ($ids === []) {
            return;
        }

        DB::connection(self::connection())->transaction(function () use ($ids) {
            $columns = implode(', ', self::COLUMNS);
            $db = DB::connection(self::connection());
            $placeholders = implode(', ', array_fill(0, count($ids), '?'));

            $db->insert("insert into archived_contracts ({$columns}, archived_at) select {$columns}, ? from contracts where secop_contract_id in ({$placeholders})", [now(), ...$ids]);
            $db->table('contracts')->whereIn('secop_contract_id', $ids)->delete();
        });
    }

    public static function isArchived(string $secopContractId): bool
    {
        return ArchivedContract::query()->where('secop_contract_id', $secopContractId)->exists();
    }

    /** Back to "contracts", as it was; null if it isn't archived. */
    public static function restore(string $secopContractId): ?Contract
    {
        DB::connection(self::connection())->transaction(function () use ($secopContractId) {
            $columns = implode(', ', self::COLUMNS);
            $db = DB::connection(self::connection());

            $db->insert("insert into contracts ({$columns}) select {$columns} from archived_contracts where secop_contract_id = ?", [$secopContractId]);
            $db->table('archived_contracts')->where('secop_contract_id', $secopContractId)->delete();
        });

        return Contract::query()->where('secop_contract_id', $secopContractId)->first();
    }

    /**
     * The nightly sync keeps the archived copy current (R-SEC-01: SECOP is
     * the source of truth, archived or not).
     *
     * @param  array<string, mixed>  $attributes  as ProcessSecopContractRow maps them
     */
    public static function refresh(string $secopContractId, array $attributes): void
    {
        ArchivedContract::query()->where('secop_contract_id', $secopContractId)->firstOrFail()->update($attributes);
    }

    private static function connection(): string
    {
        return (new Contract)->getConnectionName();
    }
}
