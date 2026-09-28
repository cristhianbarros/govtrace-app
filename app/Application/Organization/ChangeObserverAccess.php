<?php

namespace App\Application\Organization;

use App\Domain\Audit\AuditLog;
use App\Domain\Organization\User;
use Illuminate\Support\Facades\DB;

/**
 * The shared step of deactivating and reactivating a veedor: the change and
 * its entry in the audit log (R-AUD-04) — who, when, before and after. If
 * the log fails, the change isn't applied.
 */
class ChangeObserverAccess
{
    /** @param  array<string, mixed>  $changes */
    public function handle(User $administrator, User $observer, string $action, array $changes): void
    {
        DB::transaction(function () use ($administrator, $observer, $action, $changes) {
            $before = ['user_id' => $observer->id, 'email' => $observer->email, 'is_active' => (bool) $observer->is_active];

            $observer->forceFill($changes)->save();

            AuditLog::record(
                action: $action,
                organizationId: tenant()->getTenantKey(),
                actorType: 'organization_admin',
                actorId: (string) $administrator->id,
                actorName: $administrator->name,
                before: $before,
                after: [...$before, 'is_active' => (bool) $observer->is_active],
            );
        });
    }
}
