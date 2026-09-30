<?php

namespace App\Http\Controllers\Auth;

use App\Domain\Auth\Rules\StrongPassword;
use App\Domain\Organization\RoleBasedDashboard;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * It. 40c (V11 de docs/mapa-funcional.md): cambiar la contraseña con la sesión
 * abierta, desde "Mi cuenta", en una organización y en el panel global. Pide
 * la actual —una sesión olvidada abierta no basta— y las mismas reglas que al
 * crearla (US-030). La ruta limita los intentos, para que nadie la adivine.
 */
class ChangePasswordController extends Controller
{
    public function show(Request $request): Response
    {
        return Inertia::render('Auth/ChangePassword', ['home' => $this->home($request)]);
    }

    public function update(Request $request): JsonResponse
    {
        $guard = $this->guard();
        $data = $request->validate([
            'current_password' => ['required', 'string', "current_password:{$guard}"],
            'password' => ['required', 'confirmed', new StrongPassword],
        ], [
            'current_password.current_password' => 'La contraseña actual no es correcta.',
        ]);

        // El cast "hashed" del modelo guarda la huella, nunca la contraseña.
        $request->user($guard)->forceFill(['password' => $data['password']])->save();

        return response()->json(['message' => 'Su contraseña fue cambiada. La próxima vez entre con la nueva.']);
    }

    private function guard(): string
    {
        return tenancy()->initialized ? 'tenant' : 'web';
    }

    /** Where "Volver a mi panel" leads: the panel of the person's role. */
    private function home(Request $request): string
    {
        return tenancy()->initialized
            ? RoleBasedDashboard::routeFor($request->user('tenant'))
            : route('super-admin.dashboard');
    }
}
