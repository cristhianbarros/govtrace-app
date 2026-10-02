<?php

namespace App\Http\Controllers\Central;

use App\Application\Platform\SuperAdministrators;
use App\Application\Privacy\DataPolicy;
use App\Domain\Auth\Rules\StrongPassword;
use App\Domain\Organization\Exceptions\InvitationRejected;
use App\Http\Controllers\Controller;
use App\Models\User as SuperAdmin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * It. 46a (US-063-USR): el enlace de la invitación de un Super Administrador
 * — la misma pantalla de los miembros de una organización (US-030), en el
 * panel global. Un usuario que no existe, un token equivocado y un enlace
 * vencido se ven igual: la pantalla nunca dice si una cuenta existe.
 */
class SetPasswordController extends Controller
{
    private const NAME_REQUIRED = 'Escriba su nombre: al menos 2 letras.';

    private const NAME_HINT = 'Lo ven los demás Super Administradores, y queda en el registro de auditoría.';

    public function show(Request $request, string $user): InertiaResponse
    {
        $invited = SuperAdmin::query()->where('public_id', $user)->first();
        $token = $request->string('token')->toString();

        if ($invited === null || ! (new SuperAdministrators)->isValidInvitation($invited, $token)) {
            return Inertia::render('Auth/SetPassword', ['valid' => false, 'message' => InvitationRejected::expiredOrInvalid()->getMessage()]);
        }

        return Inertia::render('Auth/SetPassword', [
            'valid' => true,
            'email' => $invited->email,
            'name' => $invited->name === Str::before($invited->email, '@') ? '' : $invited->name,
            'token' => $token,
            'action' => "/set-password/{$invited->public_id}",
            'declaration' => false,
            'nameHint' => self::NAME_HINT,
            'dataPolicyUrl' => '/privacidad',
        ]);
    }

    public function store(Request $request, string $user): SymfonyResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'password' => ['required', 'confirmed', new StrongPassword],
        ], [
            'name.required' => self::NAME_REQUIRED,
            'name.min' => self::NAME_REQUIRED,
            'name.max' => 'Su nombre puede tener hasta 120 caracteres.',
        ]);

        $invited = SuperAdmin::query()->where('public_id', $user)->first();
        $superAdministrators = new SuperAdministrators;

        if ($invited === null || ! $superAdministrators->isValidInvitation($invited, $data['token'])) {
            throw ValidationException::withMessages(['token' => InvitationRejected::expiredOrInvalid()->getMessage()]);
        }
        // Solo con un enlace válido se dice qué falta: la pantalla nunca revela si una cuenta existe.
        if (! $request->boolean('data_authorization')) {
            throw ValidationException::withMessages(['data_authorization' => DataPolicy::AUTHORIZATION_REQUIRED]);
        }

        try {
            $superAdministrators->accept($invited, $data['token'], $data['name'], $data['password']);
        } catch (InvitationRejected $rejected) {
            throw ValidationException::withMessages(['token' => $rejected->getMessage()]);
        }

        Auth::guard('web')->login($invited);
        $request->session()->regenerate();

        // Una visita completa: la sesión y su token CSRF acaban de cambiar.
        return Inertia::location(route('super-admin.dashboard'));
    }
}
