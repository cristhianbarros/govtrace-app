<?php

namespace App\Application\Organization;

use App\Domain\Organization\Exceptions\OrganizationValidationException;
use App\Domain\Organization\User;

/**
 * US-006: the Administrador de Organización deactivates a veedor. Their open
 * session stops working on its very next request (EnsureAccountIsUsable
 * reads is_active from the database each time), they can't log in, and the
 * app can't sync. Their reports stay as they are: sealed, with their
 * authorship. A pending invitation is voided too, so accepting the old link
 * can't bring the account back.
 */
class DeactivateObserver
{
    public function handle(User $administrator, User $observer): void
    {
        if (! $observer->is_active) {
            throw OrganizationValidationException::observerAlreadyInactive();
        }

        (new ChangeObserverAccess)->handle($administrator, $observer, 'observer.deactivated', [
            'is_active' => false,
            'invitation_token_hash' => null,
            'invitation_expires_at' => null,
        ]);
    }
}
