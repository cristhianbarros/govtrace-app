<?php

namespace App\Http\Middleware;

use App\Application\Auth\SuperAdminTwoFactor;
use App\Http\Support\TwoFactorSession;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * It. 46g (R-SEC-09): with the second step on, a session of the global panel
 * that did not pass it — opened before the operator turned it on — ends on
 * its next request, with the reason.
 */
class EnsureTwoFactorPassed
{
    public function handle(Request $request, Closure $next): Response
    {
        if (SuperAdminTwoFactor::required() && $request->user('web') && ! TwoFactorSession::passed($request)) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return $request->expectsJson()
                ? response()->json(['message' => TwoFactorSession::CLOSED], 401)
                : redirect('/login')->withErrors(['email' => TwoFactorSession::CLOSED]);
        }

        return $next($request);
    }
}
