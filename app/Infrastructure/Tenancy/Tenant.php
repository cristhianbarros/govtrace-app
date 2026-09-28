<?php

namespace App\Infrastructure\Tenancy;

use App\Domain\Organization\OrganizationStatus;
use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Concerns\HasDatabase;
use Stancl\Tenancy\Database\Concerns\HasDomains;
use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;

/**
 * A tenant IS an organization in GovTrace (specs/SPEC.md, decisión de
 * tenancy). nit/name/status are real columns (migration
 * 2026_09_28_000030), not stancl's default "data" JSON blob — nit must be
 * queryable for the uniqueness check in RegisterOrganization (US-001).
 */
class Tenant extends BaseTenant implements TenantWithDatabase
{
    use HasDatabase, HasDomains;

    protected $fillable = ['id', 'nit', 'name', 'status'];

    /**
     * Without this, stancl's VirtualColumn trait shoves every attribute
     * except "id" into the "data" JSON blob — nit/name/status would be
     * unqueryable, and RegisterOrganization's uniqueness check on nit
     * needs a real, indexable column.
     */
    public static function getCustomColumns(): array
    {
        return ['id', 'nit', 'name', 'status'];
    }

    public function statusLabel(): string
    {
        return OrganizationStatus::from($this->status)->label();
    }

    /**
     * Read from the database, not from this object: a request can hold an
     * organization loaded before the Super Administrador suspended or
     * reactivated it, and the change applies at once (US-003a).
     */
    public function freshStatus(): OrganizationStatus
    {
        return OrganizationStatus::from(self::query()->whereKey($this->getTenantKey())->value('status'));
    }
}
