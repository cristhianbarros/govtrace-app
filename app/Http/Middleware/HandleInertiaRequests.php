<?php

namespace App\Http\Middleware;

use App\Application\CitizenReports\CitizenReportInbox;
use App\Application\Organization\OrganizationRequests;
use App\Application\Platform\SuperAdministrators;
use App\Domain\Organization\Roles;
use App\Domain\Reports\PendingReview;
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
            // It. 62 (US-067): el Inicio de GovTrace, en su propio dominio, para la ruta de
            // arriba: desde el subdominio de una veeduría, "/" sería su mapa.
            'govtraceUrl' => fn () => rtrim((string) config('app.url'), '/').'/',
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
            // It. 65a (US-068): cuántos informes ciudadanos esperan, para su pestaña. Solo al Administrador.
            'citizenReportsPending' => fn () => $this->administrator($request) ? CitizenReportInbox::waiting() : null,
            // It. 43k (V10): cuántas solicitudes de alta esperan al Super Administrador, para su menú.
            'organizationRequestsPending' => fn () => ! tenancy()->initialized && $request->user('web') ? OrganizationRequests::pendingCount() : null,
            // It. 46a (US-063-USR): con uno solo activo, el panel global lo avisa en todas sus pantallas.
            'superAdministratorsActive' => fn () => ! tenancy()->initialized && $request->user('web') ? SuperAdministrators::activeCount() : null,
        ];
    }

    private function inboxPending(Request $request): ?int
    {
        return $this->administrator($request) ? PendingReview::count() : null;
    }

    private function administrator(Request $request): bool
    {
        $user = tenancy()->initialized ? $request->user('tenant') : null;

        return $user !== null && $user->hasRole(Roles::Administrator->value);
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
