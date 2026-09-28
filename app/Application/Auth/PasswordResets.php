<?php

namespace App\Application\Auth;

use App\Domain\Organization\User as OrganizationUser;
use Illuminate\Auth\Passwords\DatabaseTokenRepository;
use Illuminate\Auth\Passwords\PasswordBroker;
use Illuminate\Contracts\Auth\CanResetPassword;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;

/**
 * US-039-USR: a user resets their own password with a link by email — in
 * their organization's subdomain, or the Super Administrador in the global
 * panel. The link lasts 60 minutes and works once.
 *
 * Laravel's broker is built here on every call, not taken from its
 * manager: the manager keeps each broker, with its database connection,
 * for the life of the process — and inside an organization that connection
 * has to be ITS database, the one where its users and tokens live.
 */
class PasswordResets
{
    private const EXPIRES_SECONDS = 60 * 60;

    private const THROTTLE_SECONDS = 60;

    /**
     * Sends the link only to an account that can use it: active and done
     * with its invitation — a pending invitation is accepted through its
     * own link, within its 48 hours. The caller answers the same either
     * way, so nobody learns whether an email is registered.
     */
    public function sendLink(string $email): void
    {
        if (tenancy()->initialized) {
            $eligible = OrganizationUser::query()
                ->where('email', $email)
                ->where('is_active', true)
                ->whereNull('invitation_token_hash')
                ->exists();

            if (! $eligible) {
                return;
            }
        }

        // INVALID_USER or RESET_THROTTLED: the answer stays the same.
        $this->broker()->sendResetLink(['email' => $email]);
    }

    public function linkIsValid(string $email, string $token): bool
    {
        $broker = $this->broker();
        $user = $broker->getUser(['email' => $email]);

        return $user instanceof CanResetPassword && $broker->tokenExists($user, $token);
    }

    /** False when the link expired, was already used or is not this account's. */
    public function reset(string $email, #[\SensitiveParameter] string $token, #[\SensitiveParameter] string $password): bool
    {
        $status = $this->broker()->reset(
            ['email' => $email, 'token' => $token, 'password' => $password],
            fn ($user, $newPassword) => $user->forceFill(['password' => $newPassword])->save(), // the "hashed" cast hashes it
        );

        return $status === Password::PASSWORD_RESET;
    }

    private function broker(): PasswordBroker
    {
        $key = (string) config('app.key');
        if (str_starts_with($key, 'base64:')) {
            $key = base64_decode(substr($key, 7));
        }

        $tokens = new DatabaseTokenRepository(DB::connection(), app('hash'), 'password_reset_tokens', $key, self::EXPIRES_SECONDS, self::THROTTLE_SECONDS);

        return new PasswordBroker($tokens, Auth::createUserProvider(tenancy()->initialized ? 'tenant_users' : 'users'), app('events'));
    }
}
