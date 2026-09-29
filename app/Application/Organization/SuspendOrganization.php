<?php

namespace App\Application\Organization;

use App\Domain\Organization\Exceptions\OrganizationValidationException;
use App\Domain\Organization\OrganizationStatus;
use App\Infrastructure\Tenancy\Tenant;

/**
 * US-003a: the Super Administrador suspends an organization (unpaid dues,
 * a security review). From that moment none of its users gets in and it
 * takes no new reports (EnsureAccountIsUsable, AuthenticateUser); its
 * public map stays online, read-only, with a notice (R-AUD-01). Nothing is
 * deleted: evidence already received keeps being sealed.
 */
class SuspendOrganization
{
    public function handle(Tenant $tenant): void
    {
        if (tenant()) {
            throw OrganizationValidationException::cannotChangeStatusFromTenantContext();
        }

        if ($tenant->freshStatus() === OrganizationStatus::Decommissioned) {
            throw OrganizationValidationException::alreadyDecommissioned();
        }

        if ($tenant->freshStatus() === OrganizationStatus::Suspended) {
            throw OrganizationValidationException::alreadySuspended();
        }

        (new ChangeOrganizationStatus)->handle($tenant, OrganizationStatus::Suspended, 'organization.suspended');
    }
}
