<?php

namespace App\Http\Controllers\Auth;

use App\Application\Auth\AuthenticateUser;
use App\Domain\Auth\Exceptions\AuthenticationRejected;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

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

    public function store(Request $request): SymfonyResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        try {
            $user = (new AuthenticateUser)->verify($this->guard(), $credentials['email'], $credentials['password']);
        } catch (AuthenticationRejected $e) {
            throw ValidationException::withMessages(['email' => $e->getMessage()]);
        }

        return $this->signIn($request, $user);
    }

    /** The password was right. It. 46g: the global panel may still ask for a second step. */
    protected function signIn(Request $request, Authenticatable $user): SymfonyResponse
    {
        Auth::guard($this->guard())->login($user);
        $request->session()->regenerate();

        // Una visita completa, no una de Inertia: la sesión y su token CSRF
        // acaban de cambiar (a un POST normal le llega la redirección de siempre).
        return Inertia::location(redirect()->intended($this->redirectTo()));
    }

    /**
     * Cerrar sesión: la sesión y su token quedan inválidos. La app del veedor
     * avisa antes si tiene reportes sin enviar en el teléfono (US-018).
     */
    public function destroy(Request $request): SymfonyResponse
    {
        Auth::guard($this->guard())->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->noContent();
    }
}
