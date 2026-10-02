<?php

namespace App\Http\Controllers\Central;

use App\Application\Platform\SuperAdministrators;
use App\Http\Controllers\Controller;
use App\Models\User as SuperAdmin;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

/**
 * It. 46a (US-063-USR): "Super Administradores", en el panel global —
 * quiénes son, invitar a otro, desactivar al que se fue y reactivarlo, y
 * reenviar o revocar una invitación. Las reglas, en SuperAdministrators.
 */
class SuperAdministratorController extends Controller
{
    public function show(): InertiaResponse
    {
        return Inertia::render('SuperAdmin/SuperAdministrators');
    }

    public function index(Request $request): JsonResponse
    {
        return response()->json(['data' => (new SuperAdministrators)->list($this->actor($request))]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'email' => ['required', 'string', 'email', 'max:255'],
        ], [
            'name.required' => 'Escriba su nombre: al menos 2 letras.',
            'name.min' => 'Escriba su nombre: al menos 2 letras.',
            'email.required' => 'Escriba un correo electrónico válido.',
            'email.email' => 'Escriba un correo electrónico válido.',
        ]);

        $hours = (new SuperAdministrators)->invite($this->actor($request), $data['name'], $data['email']);

        return response()->json(['message' => "Invitación enviada a {$data['email']}. El enlace vence en {$hours} horas."], 201);
    }

    public function resend(Request $request, int $user): JsonResponse
    {
        $hours = (new SuperAdministrators)->resend($this->actor($request), $user);

        return response()->json(['message' => "Le enviamos un enlace nuevo. Vence en {$hours} horas; el anterior ya no sirve."]);
    }

    public function revoke(Request $request, int $user): JsonResponse
    {
        $email = (new SuperAdministrators)->revoke($this->actor($request), $user);

        return response()->json(['message' => "Invitación a {$email} revocada. El enlace ya no sirve."]);
    }

    public function deactivate(Request $request, int $user): JsonResponse
    {
        try {
            (new SuperAdministrators)->deactivate($this->actor($request), $user);
        } catch (DomainException $refused) {
            return response()->json(['message' => $refused->getMessage()], 422);
        }

        return response()->json(['message' => 'Ya no puede entrar al panel global. Lo que hizo queda en el registro de auditoría.']);
    }

    public function reactivate(Request $request, int $user): JsonResponse
    {
        (new SuperAdministrators)->reactivate($this->actor($request), $user);

        return response()->json(['message' => 'Puede entrar otra vez al panel global.']);
    }

    private function actor(Request $request): SuperAdmin
    {
        return $request->user('web');
    }
}
