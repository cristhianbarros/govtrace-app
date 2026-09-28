<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            // El nombre de la organización, en su subdominio; null en el panel global.
            'organization' => tenancy()->initialized ? tenant('name') : null,
            // US-003a: el aviso de organización suspendida, en cada pantalla pública.
            'organizationNotice' => fn () => tenancy()->initialized ? tenant()->freshStatus()->publicNotice() : null,
            // Un mensaje de una sola vez tras una redirección (p. ej. "Su contraseña fue cambiada").
            'flash' => fn () => ['status' => $request->session()->get('status')],
        ];
    }
}
