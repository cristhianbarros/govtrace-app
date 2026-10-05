<?php

namespace App\Http\Controllers\Central;

use App\Application\Auth\SuperAdminTwoFactor;
use App\Http\Controllers\Auth\LoginController as BaseLoginController;
use App\Http\Support\TwoFactorSession;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class LoginController extends BaseLoginController
{
    /** It. 46g (R-SEC-09): with the second step on, the password only opens the way to the code. */
    protected function signIn(Request $request, Authenticatable $user): SymfonyResponse
    {
        if (! SuperAdminTwoFactor::required()) {
            return parent::signIn($request, $user);
        }

        TwoFactorSession::begin($request, $user);

        return Inertia::location(route('two-factor.show'));
    }

    protected function guard(): string
    {
        return 'web';
    }

    protected function redirectTo(): string
    {
        return route('super-admin.dashboard');
    }
}
