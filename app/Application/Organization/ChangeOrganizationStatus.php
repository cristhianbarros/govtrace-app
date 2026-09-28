<?php

namespace App\Application\Organization;

use App\Domain\Audit\AuditLog;
use App\Domain\Organization\OrganizationStatus;
use App\Infrastructure\Tenancy\Tenant;
use Illuminate\Support\Facades\Auth;

/**
 * The shared step of suspending and reactivating: the new status and its
 * entry in the audit log (R-AUD-04), with the Super Administrador who did it.
 */
class ChangeOrganizationStatus
{
    public function handle(Tenant $tenant, OrganizationStatus $status, string $action): void
    {
        $previous = $tenant->freshStatus();
        $tenant->update(['status' => $status->value]);

        $actor = Auth::guard('web')->user();

        AuditLog::record(
            action: $action,
            organizationId: $tenant->id,
            actorType: 'super_admin',
            actorId: $actor ? (string) $actor->getKey() : null,
            actorName: $actor?->name,
            before: ['status' => $previous->value],
            after: ['status' => $status->value],
        );
    }
}
