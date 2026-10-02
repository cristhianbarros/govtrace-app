<?php

namespace App\Application\Auth;

use App\Domain\Auth\Exceptions\AuthenticationRejected;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * Shared by the central login (Super Administrator, guard "web") and the
 * tenant login (Administrador de Organización / Veedor, guard "tenant") —
 * US-031. Same rules either side: 5 failed attempts lock the account for
 * 15 minutes (R-SEC-03), and a deactivated account is rejected even with
 * the right password.
 */
class AuthenticateUser
{
    private const MAX_ATTEMPTS = 5;

    private const DECAY_SECONDS = 15 * 60;

    public function handle(string $guard, string $email, string $password): void
    {
        $key = $this->throttleKey($guard, $email);

        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            throw AuthenticationRejected::tooManyAttempts();
        }

        if (! Auth::guard($guard)->attempt(['email' => $email, 'password' => $password])) {
            RateLimiter::hit($key, self::DECAY_SECONDS);

            if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
                throw AuthenticationRejected::tooManyAttempts();
            }

            throw AuthenticationRejected::invalidCredentials();
        }

        $user = Auth::guard($guard)->user();

        // US-003a / US-003b: nobody of a suspended or decommissioned organization gets in.
        if ($guard === 'tenant' && tenant() && ($rejected = AuthenticationRejected::forOrganization(tenant()->freshStatus()))) {
            Auth::guard($guard)->logout();

            throw $rejected;
        }

        if (! ($user->is_active ?? true)) {
            Auth::guard($guard)->logout();

            // It. 46a: a Super Administrador answers to another one, not to an organization.
            throw $guard === 'web' ? AuthenticationRejected::superAdministratorDeactivated() : AuthenticationRejected::accountDeactivated();
        }

        RateLimiter::clear($key);
    }

    private function throttleKey(string $guard, string $email): string
    {
        return Str::lower("{$guard}|{$email}|login");
    }
}
