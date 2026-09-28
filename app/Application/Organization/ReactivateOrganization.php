<?php

namespace App\Application\Organization;

use App\Domain\Organization\Exceptions\OrganizationValidationException;
use App\Domain\Organization\OrganizationStatus;
use App\Infrastructure\Tenancy\Tenant;
use App\Jobs\SyncSecopContracts;

/**
 * US-003a: the Super Administrador reactivates a suspended organization;
 * its users are back at once. Its contracts stopped syncing while it was
 * suspended (WatchedTerritories only covers active organizations), so a
 * sync runs right away instead of waiting for the night (US-013).
 */
class ReactivateOrganization
{
    public function handle(Tenant $tenant): void
    {
        if (tenant()) {
            throw OrganizationValidationException::cannotChangeStatusFromTenantContext();
        }

        if ($tenant->freshStatus() !== OrganizationStatus::Suspended) {
            throw OrganizationValidationException::alreadyActive();
        }

        (new ChangeOrganizationStatus)->handle($tenant, OrganizationStatus::Active, 'organization.reactivated');

        SyncSecopContracts::dispatch($tenant->id);
    }
}
