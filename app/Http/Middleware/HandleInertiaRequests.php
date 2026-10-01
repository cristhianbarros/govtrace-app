<?php

namespace App\Http\Middleware;

use App\Domain\Organization\Roles;
use App\Domain\Reports\EditorialStatus;
use App\Domain\Reports\Report;
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
            // It. 43h (V13): su contacto público, en el pie de su sitio.
            'organizationContact' => fn () => tenancy()->initialized ? $fresh()['contact'] : null,
            // US-003a: el aviso de organización suspendida, en cada pantalla pública.
            'organizationNotice' => fn () => tenancy()->initialized ? tenant()->freshStatus()->publicNotice() : null,
            // Un mensaje de una sola vez tras una redirección (p. ej. "Su contraseña fue cambiada").
            'flash' => fn () => ['status' => $request->session()->get('status')],
            // US-021: cuántas evidencias quedaron en "Falla de Sellado", para el banner
            // del Administrador. Al veedor nunca: él no ve errores de sellado.
            'sealingFailures' => fn () => $this->sealingFailures($request),
            // It. 40b: quién tiene la sesión abierta, para nombrarlo en la cabecera
            // junto a "Salir". null en las pantallas públicas.
            'account' => fn () => $this->account($request),
            // It. 40c: cuántas evidencias esperan en la Bandeja, para su pestaña.
            // Solo al Administrador: al veedor no le toca revisar.
            'inboxPending' => fn () => $this->inboxPending($request),
        ];
    }

    private function inboxPending(Request $request): ?int
    {
        $user = tenancy()->initialized ? $request->user('tenant') : null;

        if (! $user || ! $user->hasRole(Roles::Administrator->value)) {
            return null;
        }

        return Report::query()
            ->where('editorial_status', EditorialStatus::Hidden)
            ->whereHas('seal', fn ($seal) => $seal->where('status', SealStatus::Sealed))
            ->count();
    }

    /** @return array{name: string, email: string, role: string}|null */
    private function account(Request $request): ?array
    {
        if (tenancy()->initialized) {
            $user = $request->user('tenant');

            return $user ? ['name' => $user->name, 'email' => $user->email, 'role' => (string) $user->getRoleNames()->first()] : null;
        }

        $user = $request->user('web');

        return $user ? ['name' => $user->name, 'email' => $user->email, 'role' => 'Super Administrador'] : null;
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
