<?php

namespace App\Application\Organization;

use App\Domain\Configuration\Parameters;
use App\Domain\Organization\InvitationToken;
use App\Domain\Organization\Notifications\WelcomeNotification;
use App\Domain\Organization\User;

/**
 * US-005 and US-040-USR: a new "set your password" link for an invited
 * veedor, with the validity configured today (US-038-CFG). Only its hash is
 * kept, so the previous link — if there was one — stops working.
 */
final class InvitationLink
{
    /** @return int the hours the link is valid */
    public static function issue(User $user): int
    {
        $token = InvitationToken::generate();
        $validityHours = (int) (Parameters::current('invitation_validity_hours') ?? 48);

        $user->forceFill([
            'invitation_token_hash' => $token->hash,
            'invitation_expires_at' => now()->addHours($validityHours),
        ])->save();

        $domain = tenant()->domains()->first()->domain;
        $user->notify(new WelcomeNotification("http://{$domain}/set-password/{$user->id}?token={$token->plain}", $validityHours));

        return $validityHours;
    }

    /**
     * An invitation as the audit log keeps it (US-040-USR).
     *
     * @return array{user_id: int, email: string, invitation_expires_at: string|null}
     */
    public static function audited(User $user): array
    {
        return [
            'user_id' => $user->id,
            'email' => $user->email,
            'invitation_expires_at' => $user->invitation_expires_at?->toIso8601String(),
        ];
    }
}
