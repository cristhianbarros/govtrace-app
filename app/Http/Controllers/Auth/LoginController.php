<?php

namespace App\Http\Controllers\Auth;

use App\Application\Auth\AuthenticateUser;
use App\Domain\Auth\Exceptions\AuthenticationRejected;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * US-031: the actual guard and where each role lands differ between the
 * central login (Super Administrator) and the tenant login
 * (Administrador de Organización / Veedor) — see the two concrete
 * subclasses in Http/Controllers/Central and Http/Controllers/Tenant.
 */
abstract class LoginController extends Controller
{
    abstract protected function guard(): string;

    abstract protected function redirectTo(): string;

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        try {
            (new AuthenticateUser)->handle($this->guard(), $credentials['email'], $credentials['password']);
        } catch (AuthenticationRejected $e) {
            throw ValidationException::withMessages(['email' => $e->getMessage()]);
        }

        $request->session()->regenerate();

        return redirect()->intended($this->redirectTo());
    }
}
