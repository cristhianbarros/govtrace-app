<?php

namespace App\Application\Organization;

use App\Domain\Audit\AuditLog;
use App\Domain\Configuration\Parameters;
use App\Domain\Organization\Exceptions\OrganizationValidationException;
use App\Domain\Organization\InvitationToken;
use App\Domain\Organization\Notifications\WelcomeNotification;
use App\Domain\Organization\Roles;
use App\Domain\Organization\User;
use App\Infrastructure\Tenancy\Tenant;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * US-002: only the Super Administrator runs this, right after (or in the
 * same step as) US-001's alta. The Administrador inicial's email is
 * unique WITHIN this organization only (R-USR-01 — the same address can
 * already be a veedor somewhere else), so the check runs inside the
 * tenant's own database via $tenant->run().
 */
class AssignInitialAdministrator
{
    public function handle(Tenant $tenant, string $name, string $email): User
    {
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw OrganizationValidationException::invalidAdministratorEmail();
        }

        // Not $tenant->run(): that helper only reverts to the original
        // context on the happy path — if the callback throws (e.g. the
        // duplicate-email check below), tenancy stays switched to $tenant
        // for the rest of the request. The try/finally here guarantees the
        // revert either way.
        $originalTenant = tenant();
        tenancy()->initialize($tenant);

        try {
            if (User::query()->where('email', $email)->exists()) {
                throw OrganizationValidationException::duplicateAdministratorEmail();
            }

            $token = InvitationToken::generate();

            // US-038-CFG: la vigencia configurada hoy (48 h por defecto).
            $validityHours = (int) (Parameters::current('invitation_validity_hours') ?? 48);

            $user = User::create([
                'name' => $name,
                'email' => $email,
                // Random and never disclosed: nobody can log in until the
                // invitation link (below) is used to set a real one.
                'password' => Str::random(40),
                'invitation_token_hash' => $token->hash,
                'invitation_expires_at' => now()->addHours($validityHours),
            ]);

            $user->assignRole(Roles::Administrator->value);

            // R-AUD-04: quién lo asignó, y a quién.
            $actor = Auth::guard('web')->user();
            AuditLog::record(
                action: 'organization.administrator_assigned',
                organizationId: $tenant->id,
                actorType: 'super_admin',
                actorId: $actor ? (string) $actor->getKey() : null,
                actorName: $actor?->name,
                after: ['user_id' => $user->id, 'name' => $name, 'email' => $email],
            );

            $domain = $tenant->domains()->first()->domain;
            $url = "http://{$domain}/set-password/{$user->id}?token={$token->plain}";

            $user->notify(new WelcomeNotification($url, $validityHours));

            return $user;
        } finally {
            $originalTenant ? tenancy()->initialize($originalTenant) : tenancy()->end();
        }
    }
}
