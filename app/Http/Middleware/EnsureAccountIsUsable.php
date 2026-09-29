<?php

namespace App\Http\Middleware;

use App\Domain\Auth\Exceptions\AuthenticationRejected;
use App\Domain\Organization\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Every signed-in request inside an organization (US-003a, US-003b, US-006):
 * if the organization was suspended or decommissioned, or the account
 * deactivated, since the session began, the session ends here — on its
 * very next request. Both are read from the database each time, never from
 * the user or tenant already in memory, which may predate the change.
 *
 * The app (JSON) gets a 403 with the reason, so it keeps its pending
 * reports and says why; a screen goes back to the login with the message.
 */
class EnsureAccountIsUsable
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($rejected = AuthenticationRejected::forOrganization(tenant()->freshStatus())) {
            return $this->endSession($request, $rejected->getMessage(), $rejected->getMessage());
        }

        $user = $request->user('tenant');

        if ($user && ! User::query()->whereKey($user->getAuthIdentifier())->value('is_active')) {
            return $this->endSession(
                $request,
                AuthenticationRejected::deactivatedWhileSignedIn()->getMessage(),
                AuthenticationRejected::accountDeactivated()->getMessage(),
            );
        }

        return $next($request);
    }

    private function endSession(Request $request, string $forTheApp, string $forTheLogin): Response
    {
        Auth::guard('tenant')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return $request->expectsJson()
            ? response()->json(['message' => $forTheApp], 403)
            : redirect('/login')->withErrors(['email' => $forTheLogin]);
    }
}
