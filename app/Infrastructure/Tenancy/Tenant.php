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

    protected $fillable = ['id', 'nit', 'name', 'display_name', 'logo_path', 'status'];

    /**
     * Without this, stancl's VirtualColumn trait shoves every attribute
     * except "id" into the "data" JSON blob — nit/name/status would be
     * unqueryable, and RegisterOrganization's uniqueness check on nit
     * needs a real, indexable column.
     */
    public static function getCustomColumns(): array
    {
        return ['id', 'nit', 'name', 'display_name', 'logo_path', 'status'];
    }

    /** What veedores and the public see: the display name (US-007), or the legal name until there is one. */
    public function displayName(): string
    {
        return $this->display_name ?? $this->name;
    }

    /** The public URL of the logo; its file name changes with the logo, so no old one stays cached. */
    public function logoUrl(): ?string
    {
        return $this->logo_path === null ? null : '/organization/logo?v='.pathinfo($this->logo_path, PATHINFO_FILENAME);
    }

    /**
     * The name and logo as they are NOW in the database: veedores see a
     * change on their next screen (US-007), even if this request loaded
     * the organization before it.
     *
     * @return array{name: string, logo: ?string}
     */
    public function freshDisplay(): array
    {
        $fresh = self::query()->findOrFail($this->getTenantKey());

        return ['name' => $fresh->displayName(), 'logo' => $fresh->logoUrl()];
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
