<?php

namespace App\Application\Organization;

use App\Domain\Audit\AuditLog;
use App\Domain\Organization\Exceptions\OrganizationValidationException;
use App\Domain\Organization\Nit;
use App\Infrastructure\Tenancy\Tenant;
use Illuminate\Support\Facades\Auth;

/**
 * US-011: the Super Administrator only, at a formal request from the
 * organization (a change of legal name or NIT). Same structural guard as
 * RegisterOrganization (R-TA-01/R-TA-03) — nobody inside a tenant's own
 * context can reach this, regardless of role.
 */
class UpdateOrganizationLegalData
{
    public function handle(Tenant $tenant, string $newNit): Tenant
    {
        if (tenant()) {
            throw OrganizationValidationException::cannotEditLegalDataFromTenantContext();
        }

        $nit = Nit::fromString($newNit);

        $usedByAnotherOrganization = Tenant::query()
            ->where('nit', $nit->value())
            ->where('id', '!=', $tenant->id)
            ->exists();

        if ($usedByAnotherOrganization) {
            throw OrganizationValidationException::duplicateNit();
        }

        $previousNit = $tenant->nit;
        $tenant->update(['nit' => $nit->value()]);

        $actor = Auth::guard('web')->user();

        AuditLog::record(
            action: 'organization.legal_data_updated',
            organizationId: $tenant->id,
            actorType: 'super_admin',
            actorId: $actor ? (string) $actor->getKey() : null,
            actorName: $actor?->name,
            before: ['nit' => $previousNit],
            after: ['nit' => $nit->value()],
        );

        return $tenant->refresh();
    }
}
