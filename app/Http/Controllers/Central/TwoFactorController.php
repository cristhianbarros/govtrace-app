<?php

namespace App\Http\Controllers\Central;

use App\Application\Auth\SuperAdminTwoFactor;
use App\Http\Controllers\Controller;
use App\Http\Support\TwoFactorSession;
use App\Models\User as SuperAdmin;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * It. 46g (US-065-SEC, R-SEC-09): the second step of the Super
 * Administrador, after the password. The first time, configuring the app;
 * after that, its code or a recovery code. While the operator has not
 * turned it on, none of this exists (404).
 */
class TwoFactorController extends Controller
{
    public function __construct(private readonly SuperAdminTwoFactor $twoFactor)
    {
        abort_unless(SuperAdminTwoFactor::required(), 404);
    }

    public function show(Request $request): InertiaResponse|RedirectResponse
    {
        $superAdmin = TwoFactorSession::pending($request);
        if (! $superAdmin instanceof SuperAdmin) {
            return $this->toLogin($superAdmin);
        }

        if (SuperAdminTwoFactor::isConfigured($superAdmin)) {
            return Inertia::render('Auth/TwoFactorChallenge', ['email' => $superAdmin->email]);
        }

        $secret = TwoFactorSession::secret($request, $this->twoFactor->newSecret(...));

        return Inertia::render('Auth/TwoFactorSetup', [
            'email' => $superAdmin->email,
            'qr' => $this->twoFactor->qrCode($superAdmin->email, $secret),
            // En grupos de 4, para escribirla a mano sin perderse.
            'secret' => trim(chunk_split($secret, 4, ' ')),
        ]);
    }

    /** The first code: the app has the secret. Signed in, the recovery codes are shown once. */
    public function setup(Request $request): SymfonyResponse
    {
        $superAdmin = TwoFactorSession::pending($request);
        if (! $superAdmin instanceof SuperAdmin) {
            return $this->toLogin($superAdmin);
        }
        if (SuperAdminTwoFactor::isConfigured($superAdmin)) {
            return redirect()->route('two-factor.show');
        }
        $data = $request->validate(['code' => ['required', 'string']], ['code.required' => SuperAdminTwoFactor::INVALID_CODE]);

        $codes = $this->twoFactor->confirm($superAdmin, TwoFactorSession::secret($request, $this->twoFactor->newSecret(...)), $data['code']);
        TwoFactorSession::complete($request, $superAdmin);
        $request->session()->flash('two_factor.recovery_codes', $codes);

        return Inertia::location(route('two-factor.recovery-codes'));
    }

    public function challenge(Request $request): SymfonyResponse
    {
        $superAdmin = TwoFactorSession::pending($request);
        if (! $superAdmin instanceof SuperAdmin) {
            return $this->toLogin($superAdmin);
        }
        $data = $request->validate([
            'code' => ['nullable', 'string', 'max:20'],
            'recovery_code' => ['nullable', 'string', 'max:20'],
        ]);

        $remaining = $this->twoFactor->verify($superAdmin, $data['code'] ?? null, $data['recovery_code'] ?? null);
        TwoFactorSession::complete($request, $superAdmin);
        if ($remaining !== null) {
            $request->session()->flash('status', "Entró con un código de recuperación. Le quedan {$remaining}.");
        }

        return Inertia::location(redirect()->intended(route('super-admin.dashboard')));
    }

    /** Once, right after configuring it. */
    public function recoveryCodes(Request $request): InertiaResponse|RedirectResponse
    {
        $codes = $request->session()->get('two_factor.recovery_codes');

        return $codes === null
            ? redirect()->route('super-admin.dashboard')
            : Inertia::render('Auth/TwoFactorRecoveryCodes', ['codes' => $codes]);
    }

    private function toLogin(?false $expired): RedirectResponse
    {
        return $expired === false
            ? redirect('/login')->withErrors(['email' => TwoFactorSession::EXPIRED])
            : redirect('/login');
    }
}
