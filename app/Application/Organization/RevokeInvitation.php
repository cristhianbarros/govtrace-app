<?php

namespace App\Application\Organization;

use App\Domain\Audit\AuditLog;
use App\Domain\Organization\Exceptions\OrganizationValidationException;
use App\Domain\Organization\User;

/**
 * US-040-USR: the Administrador revokes a pending invitation, sent by
 * mistake. The account never existed beyond the invitation — no password,
 * no report —, so it goes away: its link behaves as an expired one (US-030)
 * and the email can be invited again. The audit log keeps who and when.
 */
class RevokeInvitation
{
    public function handle(User $administrator, User $observer): void
    {
        if ($observer->invitation_token_hash === null) {
            throw OrganizationValidationException::noPendingInvitation();
        }

        $before = InvitationLink::audited($observer);

        $observer->syncRoles([]);
        $observer->delete();

        AuditLog::record(
            action: 'invitation.revoked',
            organizationId: tenant()->getTenantKey(),
            actorType: 'organization_admin',
            actorId: (string) $administrator->id,
            actorName: $administrator->name,
            before: $before,
        );
    }
}
