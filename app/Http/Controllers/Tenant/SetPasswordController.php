<?php

namespace App\Http\Controllers\Tenant;

use App\Application\Organization\AcceptInvitation;
use App\Domain\Auth\Rules\StrongPassword;
use App\Domain\Organization\Exceptions\InvitationRejected;
use App\Domain\Organization\RoleBasedDashboard;
use App\Domain\Organization\User;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * US-030: the link every WelcomeNotification sends (US-002, US-005) ends
 * up here. {user} is looked up in this organization's users — this route
 * only exists inside routes/tenant.php, so tenancy is already the right one.
 */
class SetPasswordController extends Controller
{
    /**
     * The screen the link opens (it. 17). An unknown user, a wrong token
     * and an expired link all look the same: the screen never tells
     * whether an account exists.
     */
    public function show(Request $request, string $user): InertiaResponse
    {
        $account = User::query()->find($user);
        $token = $request->string('token')->toString();

        if ($account === null || ! (new AcceptInvitation)->isValid($account, $token)) {
            return Inertia::render('Auth/SetPassword', [
                'valid' => false,
                'message' => InvitationRejected::expiredOrInvalid()->getMessage(),
            ]);
        }

        return Inertia::render('Auth/SetPassword', [
            'valid' => true,
            'email' => $account->email,
            'token' => $token,
            'action' => "/set-password/{$account->id}",
        ]);
    }

    /** An account that no longer exists — a revoked invitation (US-040-USR) — reads like an expired link too. */
    public function store(Request $request, string $user): SymfonyResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'password' => ['required', 'confirmed', new StrongPassword],
        ]);

        $account = User::query()->find($user);

        try {
            (new AcceptInvitation)->handle($account ?? throw InvitationRejected::expiredOrInvalid(), $data['token'], $data['password']);
        } catch (InvitationRejected $e) {
            throw ValidationException::withMessages(['token' => $e->getMessage()]);
        }

        Auth::guard('tenant')->login($account);
        $request->session()->regenerate();

        // Una visita completa: la sesión y su token CSRF acaban de cambiar.
        return Inertia::location(redirect()->intended(RoleBasedDashboard::routeFor($account)));
    }
}
