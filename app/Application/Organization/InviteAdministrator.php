<?php

namespace App\Application\Organization;

use App\Domain\Audit\AuditLog;
use App\Domain\Organization\Exceptions\OrganizationValidationException;
use App\Domain\Organization\Roles;
use App\Domain\Organization\User;
use Illuminate\Support\Str;

/**
 * It. 43j (V3, US-061-USR): an Administrador invites another one from their
 * own panel, so the organization does not depend on the Super Administrador
 * if one of them leaves. The same invitation as a veedor's (US-005): a link
 * by email, with the configured validity. Runs in the caller's organization.
 * The email cannot already be in it (R-USR-01).
 */
class InviteAdministrator
{
    public function handle(User $administrator, string $name, string $email): User
    {
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw OrganizationValidationException::invalidAdministratorEmail();
        }
        if (User::query()->where('email', $email)->exists()) {
            throw OrganizationValidationException::duplicateAdministratorEmail();
        }

        $invited = User::create(['name' => $name, 'email' => $email, 'password' => Str::random(40)]);
        $invited->assignRole(Roles::Administrator->value);
        InvitationLink::issue($invited);

        // R-AUD-04: quién invitó, a quién y hasta cuándo vale el enlace.
        AuditLog::record(
            action: 'organization.administrator_invited',
            organizationId: tenant()->getTenantKey(),
            actorType: 'organization_admin',
            actorId: (string) $administrator->id,
            actorName: $administrator->name,
            after: InvitationLink::audited($invited),
        );

        return $invited;
    }
}
