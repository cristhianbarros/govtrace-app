<?php

namespace App\Http\Middleware;

use App\Domain\Auth\Exceptions\AuthenticationRejected;
use App\Models\User as SuperAdmin;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * It. 46a (US-063-USR): a Super Administrador deactivated by another one no
 * longer acts in the global panel — the open session ends on its next
 * request, read fresh from the database, as EnsureAccountIsUsable does for
 * the members of an organization.
 */
class EnsureSuperAdministratorIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $superAdmin = $request->user('web');

        if ($superAdmin && ! SuperAdmin::query()->whereKey($superAdmin->getAuthIdentifier())->value('is_active')) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return $request->expectsJson()
                ? response()->json(['message' => AuthenticationRejected::superAdministratorDeactivatedWhileSignedIn()->getMessage()], 403)
                : redirect('/login')->withErrors(['email' => AuthenticationRejected::superAdministratorDeactivated()->getMessage()]);
        }

        return $next($request);
    }
}
