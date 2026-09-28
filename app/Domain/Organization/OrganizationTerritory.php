<?php

namespace App\Domain\Organization;

use App\Domain\Geography\Department;
use App\Domain\Geography\Municipality;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * One department OR one municipality an organization vigila (US-012).
 * Central table (not per-tenant): the SECOP sync (it. 7) needs to read
 * every organization's territory without opening each tenant database.
 */
class OrganizationTerritory extends Model
{
    use CentralConnection;

    protected $fillable = ['tenant_id', 'department_code', 'municipality_code'];

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_code', 'code');
    }

    public function municipality(): BelongsTo
    {
        return $this->belongsTo(Municipality::class, 'municipality_code', 'code');
    }
}
