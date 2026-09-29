<?php

namespace App\Http\Middleware;

use App\Domain\Organization\Roles;
use App\Domain\Sealing\ReportSeal;
use App\Domain\Sealing\SealStatus;
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
        // Una sola lectura por respuesta, compartida por el nombre y el logo.
        $display = null;
        $fresh = function () use (&$display) {
            return $display ??= tenant()->freshDisplay();
        };

        return [
            ...parent::share($request),
            // La organización, en su subdominio: su nombre de fantasía (o el legal)
            // y su logo (US-007); null en el panel global.
            'organization' => fn () => tenancy()->initialized ? $fresh()['name'] : null,
            'organizationLogo' => fn () => tenancy()->initialized ? $fresh()['logo'] : null,
            // US-003a: el aviso de organización suspendida, en cada pantalla pública.
            'organizationNotice' => fn () => tenancy()->initialized ? tenant()->freshStatus()->publicNotice() : null,
            // Un mensaje de una sola vez tras una redirección (p. ej. "Su contraseña fue cambiada").
            'flash' => fn () => ['status' => $request->session()->get('status')],
            // US-021: cuántas evidencias quedaron en "Falla de Sellado", para el banner
            // del Administrador. Al veedor nunca: él no ve errores de sellado.
            'sealingFailures' => fn () => $this->sealingFailures($request),
        ];
    }

    private function sealingFailures(Request $request): ?int
    {
        $user = tenancy()->initialized ? $request->user('tenant') : null;

        if (! $user || ! $user->hasRole(Roles::Administrator->value)) {
            return null;
        }

        return ReportSeal::query()->where('status', SealStatus::Failed)->count();
    }
}
