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
use Illuminate\Support\Facades\DB;
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

        // It. 47a (US-016): its kind of work and the text the veedor's search
        // compares, from what SECOP says — kept with it, never typed by hand.
        static::saving(function (self $contract) {
            $contract->work_type = WorkType::classify($contract->object, $contract->raw_payload['codigo_de_categoria_principal'] ?? null)->value;
            $contract->search_text = SearchText::of($contract->object, $contract->contractor_name, $contract->process_number, $contract->entity_name);
        });

        // US-033: not even SECOP deletes — an annulled contract becomes
        // "cancelled" and stays, because evidence may already point at it.
        static::deleting(function () {
            throw new RuntimeException('Un contrato nunca se borra; si SECOP lo anula, pasa a estado "cancelled".');
        });
    }

    protected $fillable = [
        'secop_contract_id', 'process_number', 'entity_name', 'contractor_name',
        'object', 'contract_type', 'value', 'signed_at', 'end_date', 'status',
        'department_code', 'municipality_code', 'secop_url', 'raw_payload', 'cancelled_at',
        'supervisor_name', 'entity_order', 'funding_sources',
    ];

    protected $hidden = ['search_text'];

    protected $casts = [
        'value' => 'decimal:2',
        'signed_at' => 'date',
        'end_date' => 'date',
        'raw_payload' => 'array',
        'funding_sources' => 'array',
        'cancelled_at' => 'datetime',
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
     * plus closed ones for a while after their end date; never an
     * annulled one (R-SEC-07). The window is the one in force at $moment
     * (R-AUD-05).
     *
     * US-018: one annulled after $moment still takes a report captured then
     * — it waited without signal in the phone while SECOP annulled it.
     */
    public function scopeReportableAt(Builder $query, CarbonInterface $moment): void
    {
        $windowMonths = (int) (Parameters::valueAt('closed_contract_report_window_months', $moment) ?? 12);
        $closedSince = $moment->copy()->subMonths($windowMonths);

        $query->where(fn (Builder $query) => $query
            ->statusIn(SecopContractStatus::ACTIVE)
            ->orWhere(fn (Builder $query) => $query
                ->statusIn(SecopContractStatus::CLOSED)
                ->where('end_date', '>=', $closedSince))
            ->orWhere(fn (Builder $query) => $query
                ->where('status', 'cancelled')
                ->where('cancelled_at', '>', $moment)));
    }

    /**
     * US-034 (R-SEC-07): its end date passed and SECOP still shows it
     * running. The worksite that groups it is "en riesgo", and its pin red
     * (US-027). The same rule as {@see self::isOverdueInExecution()}, for a query.
     */
    public function scopeOverdueInExecution(Builder $query, CarbonInterface $today): void
    {
        $query->statusIn(SecopContractStatus::IN_EXECUTION)->whereDate('end_date', '<', $today);
    }

    public function isOverdueInExecution(CarbonInterface $today): bool
    {
        return in_array(mb_strtolower((string) $this->status), SecopContractStatus::IN_EXECUTION, true)
            && $this->end_date !== null
            && $this->end_date->lt($today->copy()->startOfDay());
    }

    /**
     * SECOP's status compared case-insensitively ("terminado" and
     * "Terminado" are the same) against lowercase SecopContractStatus lists.
     *
     * @param  list<string>  $statuses
     */
    public function scopeStatusIn(Builder $query, array $statuses): void
    {
        $query->whereIn(DB::raw('lower(status)'), $statuses);
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
