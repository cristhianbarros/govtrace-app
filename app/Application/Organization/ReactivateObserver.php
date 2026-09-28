<?php

namespace App\Application\Organization;

use App\Domain\Organization\Exceptions\OrganizationValidationException;
use App\Domain\Organization\User;

/**
 * US-041-USR: the Administrador de Organización brings a deactivated veedor
 * back, with the same account and password — no new invitation.
 */
class ReactivateObserver
{
    public function handle(User $administrator, User $observer): void
    {
        if ($observer->is_active) {
            throw OrganizationValidationException::observerAlreadyActive();
        }

        (new ChangeObserverAccess)->handle($administrator, $observer, 'observer.reactivated', ['is_active' => true]);
    }
}
