<?php

namespace App\Application\Organization;

use App\Domain\Organization\Exceptions\InvitationRejected;
use App\Domain\Organization\InvitationToken;
use App\Domain\Organization\User;

/**
 * US-030: the last step of both US-002 (Administrador inicial) and
 * US-005 (invitar veedor) — same link shape, same 48-hour token, checked
 * here regardless of which one created the user.
 */
class AcceptInvitation
{
    public function handle(User $user, string $plainToken, string $newPassword): void
    {
        if (! $this->tokenIsValid($user, $plainToken)) {
            throw InvitationRejected::expiredOrInvalid();
        }

        $user->forceFill([
            'password' => $newPassword, // the "hashed" cast takes care of it
            'invitation_token_hash' => null,
            'invitation_expires_at' => null,
            'is_active' => true,
        ])->save();
    }

    private function tokenIsValid(User $user, string $plainToken): bool
    {
        if (! $user->invitation_token_hash || ! $user->invitation_expires_at) {
            return false;
        }

        return hash_equals($user->invitation_token_hash, InvitationToken::hashOf($plainToken))
            && $user->invitation_expires_at->isFuture();
    }
}
