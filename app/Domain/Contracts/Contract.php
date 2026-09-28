<?php

namespace App\Domain\Contracts;

use App\Domain\Configuration\Parameters;
use App\Domain\Geography\Department;
use App\Domain\Geography\Municipality;
use App\Domain\Organization\WatchedTerritories;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * A SECOP II public works contract. Central, one copy shared by every
 * organization (specs/SPEC.md, decisión de tenancy) — R-SEC-01: SECOP is
 * the only source of truth, nobody edits these fields by hand. Enforced
 * below, not just documented: any update outside {@see self::fromSecop()}
 * throws, and a delete always does.
 *
 * CentralConnection: contracts are read from INSIDE a tenant's context
 * too (the risk job, a veedor creating a report) — sin esto, Eloquent
 * usaría la conexión "tenant" que Stancl deja activa ahí, y "contracts"
 * no existe en esa base.
 */
class Contract extends Model
{
    use CentralConnection;

    /** US-016: statuses a veedor can always pick to report on. */
    public const ALWAYS_REPORTABLE_STATUSES = ['En ejecución', 'Celebrado', 'Adjudicado'];

    /** US-016: closed statuses, reportable only within closed_contract_report_window_months of their end date. */
    public const RECENTLY_CLOSED_STATUSES = ['Terminado', 'Liquidado'];

    private static bool $allowingSecopWrite = false;

    /**
     * The only door through which these rows may be written — used by
     * ProcessSecopContractRow (US-013). Nothing else, not even the Super
     * Administrator, has a path around this.
     */
    public static function fromSecop(callable $callback): mixed
    {
        self::$allowingSecopWrite = true;

        try {
            return $callback();
        } finally {
            self::$allowingSecopWrite = false;
        }
    }

    protected static function booted(): void
    {
        $guard = function (self $contract) {
            if (! self::$allowingSecopWrite) {
                throw new RuntimeException(
                    'Los datos de un contrato no se pueden editar manualmente; SECOP es la única fuente de verdad.'
                );
            }
        };

        static::creating($guard);
        static::updating($guard);

        // US-033: not even SECOP deletes — an annulled contract becomes
        // "cancelled" and stays, because evidence may already point at it.
        static::deleting(function () {
            throw new RuntimeException('Un contrato nunca se borra; si SECOP lo anula, pasa a estado "cancelled".');
        });
    }

    protected $fillable = [
        'secop_contract_id', 'process_number', 'entity_name', 'contractor_name',
        'object', 'contract_type', 'value', 'signed_at', 'end_date', 'status',
        'department_code', 'municipality_code', 'secop_url', 'raw_payload',
    ];

    protected $casts = [
        'value' => 'decimal:2',
        'signed_at' => 'date',
        'end_date' => 'date',
        'raw_payload' => 'array',
    ];

    /**
     * R-VC-04: contracts inside the territory an organization watches. A
     * department watched whole brings its Gobernación (no municipality)
     * and every municipality; a municipality watched on its own, only its
     * contracts.
     */
    public function scopeInTerritory(Builder $query, WatchedTerritories $watched): void
    {
        $query->where(fn (Builder $query) => $query
            ->whereIn('department_code', $watched->departmentCodes())
            ->orWhereIn('municipality_code', $watched->municipalityCodes()));
    }

    /**
     * US-016: contracts a veedor may report on at $moment — active ones,
     * plus Terminado/Liquidado for a while after their end date; never an
     * annulled one. The window is the one in force at $moment (R-AUD-05).
     */
    public function scopeReportableAt(Builder $query, CarbonInterface $moment): void
    {
        $windowMonths = (int) (Parameters::valueAt('closed_contract_report_window_months', $moment) ?? 12);
        $closedSince = $moment->copy()->subMonths($windowMonths);

        $query->where(fn (Builder $query) => $query
            ->whereIn('status', self::ALWAYS_REPORTABLE_STATUSES)
            ->orWhere(fn (Builder $query) => $query
                ->whereIn('status', self::RECENTLY_CLOSED_STATUSES)
                ->where('end_date', '>=', $closedSince)));
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_code', 'code');
    }

    public function municipality(): BelongsTo
    {
        return $this->belongsTo(Municipality::class, 'municipality_code', 'code');
    }
}
