<?php

namespace App\Application\Organization;

use App\Domain\Configuration\Parameters;
use App\Domain\Organization\Exceptions\OrganizationValidationException;
use App\Domain\Organization\InvitationToken;
use App\Domain\Organization\Notifications\WelcomeNotification;
use App\Domain\Organization\Roles;
use App\Domain\Organization\User;
use Illuminate\Support\Str;

/**
 * US-005: the Administrador de Organización runs this from their own
 * subdomain — tenancy is already the caller's tenant (no
 * $tenant->run()/context switch needed, unlike AssignInitialAdministrator,
 * which is invoked from the central panel for a DIFFERENT tenant).
 *
 * Uniqueness is scoped to THIS organization's own users table (R-USR-01):
 * the same email can already be a veedor elsewhere.
 */
class InviteObserver
{
    public function handle(string $email): User
    {
        if (User::query()->where('email', $email)->exists()) {
            throw OrganizationValidationException::duplicateObserverEmail();
        }

        $token = InvitationToken::generate();

        // US-038-CFG: la vigencia configurada hoy (48 h por defecto).
        $validityHours = (int) (Parameters::current('invitation_validity_hours') ?? 48);

        $user = User::create([
            // No name is collected at invite time (US-005's Gherkin only
            // asks for the email); the local-part is a placeholder until
            // the veedor edits their profile — no historia does that yet.
            'name' => Str::before($email, '@'),
            'email' => $email,
            'password' => Str::random(40),
            'invitation_token_hash' => $token->hash,
            'invitation_expires_at' => now()->addHours($validityHours),
        ]);

        $user->assignRole(Roles::Observer->value);

        $domain = tenant()->domains()->first()->domain;
        $url = "http://{$domain}/set-password/{$user->id}?token={$token->plain}";

        $user->notify(new WelcomeNotification($url, $validityHours));

        return $user;
    }
}
