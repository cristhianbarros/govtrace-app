<?php

namespace App\Domain\Contracts;

use App\Domain\Geography\Department;
use App\Domain\Geography\Municipality;
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
 * CentralConnection (it. 9): App\Jobs\CalculateWorksitesAtRisk reads
 * contracts from INSIDE each tenant's own context (Tenant::run()) — sin
 * esto, Eloquent usaría la conexión "tenant" que Stancl deja activa ahí,
 * y "contracts" no existe en esa base.
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

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_code', 'code');
    }

    public function municipality(): BelongsTo
    {
        return $this->belongsTo(Municipality::class, 'municipality_code', 'code');
    }
}
