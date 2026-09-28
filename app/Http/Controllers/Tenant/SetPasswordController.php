<?php

namespace App\Http\Controllers\Tenant;

use App\Application\Organization\AcceptInvitation;
use App\Domain\Auth\Rules\StrongPassword;
use App\Domain\Organization\Exceptions\InvitationRejected;
use App\Domain\Organization\RoleBasedDashboard;
use App\Domain\Organization\User;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * US-030: the link every WelcomeNotification sends (US-002, US-005) ends
 * up here. {user} route-model-binds against App\Domain\Organization\User
 * — safe because this route only exists inside routes/tenant.php, so
 * tenancy is already the right one by the time Laravel resolves it.
 */
class SetPasswordController extends Controller
{
    public function store(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'password' => ['required', 'confirmed', new StrongPassword],
        ]);

        try {
            (new AcceptInvitation)->handle($user, $data['token'], $data['password']);
        } catch (InvitationRejected $e) {
            throw ValidationException::withMessages(['token' => $e->getMessage()]);
        }

        Auth::guard('tenant')->login($user);
        $request->session()->regenerate();

        return redirect()->intended(RoleBasedDashboard::routeFor($user));
    }
}
