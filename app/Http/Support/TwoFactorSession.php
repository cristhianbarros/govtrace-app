<?php

namespace App\Http\Support;

use App\Models\User as SuperAdmin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * It. 46g (US-065-SEC): between the password and the code, the Super
 * Administrador is not signed in — the session only remembers who is
 * pending, for 10 minutes, and the secret being configured. Only the code
 * signs them in, and marks the session as having passed the second step.
 */
final class TwoFactorSession
{
    public const EXPIRED = 'Pasaron más de 10 minutos. Vuelva a escribir su contraseña.';

    public const CLOSED = 'Por seguridad, vuelva a entrar: ahora el panel global pide un código de su app autenticadora.';

    private const PENDING = 'two_factor.pending';

    private const PASSED = 'two_factor.passed';

    private const MINUTES = 10;

    public static function begin(Request $request, SuperAdmin $superAdmin): void
    {
        $request->session()->regenerate();
        $request->session()->put(self::PENDING, ['id' => $superAdmin->getKey(), 'until' => now()->addMinutes(self::MINUTES)->getTimestamp(), 'secret' => null]);
    }

    /** @return SuperAdmin|false|null who is pending; false if their 10 minutes passed; null if nobody is */
    public static function pending(Request $request): SuperAdmin|false|null
    {
        $pending = $request->session()->get(self::PENDING);
        if ($pending === null) {
            return null;
        }
        if (now()->getTimestamp() > $pending['until']) {
            $request->session()->forget(self::PENDING);

            return false;
        }

        return SuperAdmin::query()->active()->find($pending['id']) ?? null;
    }

    /** The secret being configured: the same one while the page is reloaded, so the QR code already scanned still works. */
    public static function secret(Request $request, callable $new): string
    {
        $pending = $request->session()->get(self::PENDING);
        $pending['secret'] ??= $new();
        $request->session()->put(self::PENDING, $pending);

        return $pending['secret'];
    }

    public static function complete(Request $request, SuperAdmin $superAdmin): void
    {
        $request->session()->forget(self::PENDING);
        Auth::guard('web')->login($superAdmin);
        $request->session()->regenerate();
        $request->session()->put(self::PASSED, true);
    }

    public static function passed(Request $request): bool
    {
        return $request->session()->get(self::PASSED) === true;
    }
}
