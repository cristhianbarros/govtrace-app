<?php

namespace App\Infrastructure\Tenancy;

use App\Domain\Organization\OrganizationStatus;
use Illuminate\Support\Str;
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

    protected $fillable = ['id', 'nit', 'registration_number', 'registration_authority', 'registration_key', 'name', 'display_name', 'logo_path', 'contact_email', 'contact_phone', 'status', 'decommissioned_at', 'evidence_files_purged_at', 'registration_document_path'];

    protected $casts = [
        'decommissioned_at' => 'datetime',
        'evidence_files_purged_at' => 'datetime',
    ];

    /** US-003b: the files are kept this long after the decommission; the seals and proofs, forever. */
    public const EVIDENCE_RETENTION_YEARS = 5;

    /**
     * Without this, stancl's VirtualColumn trait shoves every attribute
     * except "id" into the "data" JSON blob — nit/name/status would be
     * unqueryable, and RegisterOrganization's uniqueness check on nit
     * needs a real, indexable column.
     */
    public static function getCustomColumns(): array
    {
        return ['id', 'nit', 'registration_number', 'registration_authority', 'registration_key', 'name', 'display_name', 'logo_path', 'contact_email', 'contact_phone', 'status', 'decommissioned_at', 'evidence_files_purged_at'];
    }

    /** R-LEG-06: how it is identified — "NIT 900123456-8 · Acta 45 de 2025, Cámara de Comercio de Santa Marta". */
    public function identification(): string
    {
        return collect([
            $this->nit ? "NIT {$this->nit}" : null,
            $this->registration_number ? "{$this->registration_number}, {$this->registration_authority}" : null,
        ])->filter()->join(' · ');
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
     * @return array{name: string, logo: ?string, contact: ?array{email: ?string, phone: ?string}}
     */
    public function freshDisplay(): array
    {
        $fresh = self::query()->findOrFail($this->getTenantKey());

        return ['name' => $fresh->displayName(), 'logo' => $fresh->logoUrl(), 'contact' => $fresh->publicContact()];
    }

    /** It. 43h (V13): its public contact, or null while it has none. */
    public function publicContact(): ?array
    {
        return $this->contact_email || $this->contact_phone ? ['email' => $this->contact_email, 'phone' => $this->contact_phone] : null;
    }

    /** The subdomain as the Super Administrador types it: "veeduria-smr". */
    public function subdomain(): string
    {
        return Str::before($this->domains()->value('domain'), '.');
    }

    /** US-003b: whether the retention policy already deleted its evidence files. */
    public function evidenceFilesPurged(): bool
    {
        return self::query()->whereKey($this->getTenantKey())->whereNotNull('evidence_files_purged_at')->exists();
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
