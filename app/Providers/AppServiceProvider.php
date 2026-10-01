<?php

namespace App\Providers;

use App\Application\Sealing\SealingNetwork;
use App\Infrastructure\Mail\CopyToMailpit;
use App\Infrastructure\Stellar\MainnetConfiguration;
use App\Infrastructure\Stellar\StellarRpc;
use App\Infrastructure\Stellar\StellarSealingNetwork;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use RuntimeException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Sellado en Stellar (it. 13). Los tests enlazan un doble en memoria.
        $this->app->bind(StellarRpc::class, fn () => new StellarRpc(config('stellar.rpc_url')));

        $this->app->bind(SealingNetwork::class, fn ($app) => new StellarSealingNetwork(
            $app->make(StellarRpc::class),
            config('stellar.network_passphrase'),
            config('stellar.sealing_contract_id') ?? throw new RuntimeException('Falta STELLAR_SEALING_CONTRACT_ID: corre make contract-deploy.'),
            config('stellar.sealer_secret') ?? throw new RuntimeException('Falta STELLAR_SEALER_SECRET: corre make contract-deploy (en producción, ver D11).'),
            config('stellar.sponsor_secret') ?? throw new RuntimeException('Falta STELLAR_SPONSOR_SECRET: corre make contract-deploy (en producción, ver D11).'),
            config('stellar.sponsor_min_balance_xlm'),
        ));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // D13: en la red principal, el RPC del navegador no puede faltar ni ser el privado.
        MainnetConfiguration::assertSafe(config('stellar'));

        // It. 41: detrás del proxy de la red privada, y solo su For y su Proto, que nginx
        // sobrescribe. Host y Port los deja pasar tal como los manda el visitante.
        TrustProxies::at(config('app.trusted_proxies'));
        TrustProxies::withHeaders(Request::HEADER_X_FORWARDED_FOR | Request::HEADER_X_FORWARDED_PROTO);

        $this->limitAbuse();

        // It. 38b: en desarrollo, una copia de cada correo al buzón de Mailpit.
        Event::listen(MessageSent::class, CopyToMailpit::class);
    }

    /**
     * It. 41: cada reporte cuesta XLM y un turno de la selladora, y las API
     * públicas sirven a cualquiera. Pasado un límite, 429 con un mensaje en
     * español; un visitante es su IP real (la que dice el proxy de confianza).
     */
    private function limitAbuse(): void
    {
        RateLimiter::for('reports', function (Request $request) {
            $perHour = (int) config('limits.reports_per_veedor_per_hour');

            // Los ids de los veedores se repiten entre organizaciones: la organización va en la llave.
            return Limit::perHour($perHour)
                ->by(tenant()?->getTenantKey().':'.$request->user('tenant')?->getAuthIdentifier())
                ->response(fn (Request $request, array $headers) => response()->json(
                    ['message' => "Llegó al límite de {$perHour} reportes por hora. Los siguientes se pueden enviar más tarde."],
                    429,
                    $headers,
                ));
        });

        $tooMany = fn (Request $request, array $headers) => response()->json(
            ['message' => 'Demasiadas consultas seguidas. Intente de nuevo en un minuto.'],
            429,
            $headers,
        );
        RateLimiter::for('public', fn (Request $request) => Limit::perMinute((int) config('limits.public_requests_per_minute'))->by($request->ip())->response($tooMany));
        RateLimiter::for('open-data', fn (Request $request) => Limit::perMinute((int) config('limits.open_data_per_minute'))->by($request->ip())->response($tooMany));

        // It. 44f: el canal del ciudadano. Cada código es un correo que sale; cada informe, trabajo para la veeduría.
        $tooManyToday = fn (Request $request, array $headers) => response()->json(['message' => 'Demasiados intentos desde esta conexión. Intente de nuevo en una hora.'], 429, $headers);
        RateLimiter::for('citizen-codes', fn (Request $request) => Limit::perHour((int) config('limits.citizen_codes_per_hour'))->by(tenant()?->getTenantKey().':'.$request->ip())->response($tooManyToday));
        RateLimiter::for('citizen-reports', fn (Request $request) => Limit::perHour((int) config('limits.citizen_reports_per_hour'))->by(tenant()?->getTenantKey().':'.$request->ip())->response($tooManyToday));
        // It. 43k (V10): las solicitudes de alta, desde el Inicio.
        RateLimiter::for('organization-requests', fn (Request $request) => Limit::perHour((int) config('limits.organization_requests_per_hour'))->by($request->ip())->response($tooManyToday));
    }
}
