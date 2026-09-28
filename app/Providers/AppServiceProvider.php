<?php

namespace App\Providers;

use App\Application\Sealing\SealingNetwork;
use App\Infrastructure\Stellar\StellarRpc;
use App\Infrastructure\Stellar\StellarSealingNetwork;
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
        //
    }
}
