<?php

namespace App\Application\Organization;

use App\Domain\Audit\AuditLog;
use App\Domain\Organization\Exceptions\OrganizationValidationException;
use App\Domain\Organization\User;

/**
 * US-040-USR: the Administrador resends a pending invitation — or one that
 * already expired, unanswered. A new link with the configured validity; the
 * previous one stops working. It goes to the audit log (R-AUD-04).
 */
class ResendInvitation
{
    /** @return int the hours the new link is valid */
    public function handle(User $administrator, User $observer): int
    {
        if ($observer->invitation_token_hash === null) {
            throw OrganizationValidationException::noPendingInvitation();
        }

        $before = InvitationLink::audited($observer);
        $validityHours = InvitationLink::issue($observer);

        AuditLog::record(
            action: 'invitation.resent',
            organizationId: tenant()->getTenantKey(),
            actorType: 'organization_admin',
            actorId: (string) $administrator->id,
            actorName: $administrator->name,
            before: $before,
            after: InvitationLink::audited($observer),
        );

        return $validityHours;
    }
}
