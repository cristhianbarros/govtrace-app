<?php

namespace App\Http\Controllers\Auth;

use App\Application\Auth\PasswordResets;
use App\Domain\Auth\Rules\StrongPassword;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * US-039-USR: "¿Olvidó su contraseña?" — the same screens in each
 * organization's subdomain and in the global panel. The rules are
 * PasswordResets'.
 */
class PasswordResetController extends Controller
{
    public const NEUTRAL = 'Si el correo existe, recibirás un enlace';

    public const LINK_EXPIRED = 'El enlace de restablecimiento de contraseña ha expirado o ya ha sido utilizado.';

    public function requestForm(): Response
    {
        return Inertia::render('Auth/ForgotPassword', ['context' => tenancy()->initialized ? tenant('name') : 'Panel global']);
    }

    public function sendLink(Request $request): RedirectResponse
    {
        $data = $request->validate(['email' => ['required', 'string', 'email']]);

        (new PasswordResets)->sendLink($data['email']);

        return back()->with('status', self::NEUTRAL);
    }

    /** The screen the link opens: an expired or used link shows the reason, not the form. */
    public function resetForm(Request $request, string $token): Response
    {
        $email = $request->string('email')->toString();

        if (! (new PasswordResets)->linkIsValid($email, $token)) {
            return Inertia::render('Auth/ResetPassword', ['valid' => false, 'message' => self::LINK_EXPIRED]);
        }

        return Inertia::render('Auth/ResetPassword', ['valid' => true, 'email' => $email, 'token' => $token]);
    }

    public function reset(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'confirmed', new StrongPassword],
        ]);

        if (! (new PasswordResets)->reset($data['email'], $data['token'], $data['password'])) {
            throw ValidationException::withMessages(['token' => self::LINK_EXPIRED]);
        }

        return redirect('/login')->with('status', 'Su contraseña fue cambiada. Ya puede iniciar sesión.');
    }
}
