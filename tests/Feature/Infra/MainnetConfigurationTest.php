<?php

use App\Infrastructure\Stellar\MainnetConfiguration;
use App\Providers\AppServiceProvider;

/*
 * Iteración 37a — D13: en la red principal, el RPC lo da un proveedor con
 * dos endpoints. El privado (STELLAR_RPC_URL) es del servidor; el público
 * (STELLAR_PUBLIC_RPC_URL), de solo lectura y restringido al dominio, lo
 * usa el validador en el navegador (US-024). Sin el público el validador
 * no funciona, y con el privado en su lugar el token del proveedor
 * quedaría a la vista de cualquiera: la aplicación no arranca.
 */

const MAINNET = 'Public Global Stellar Network ; September 2015';
const PRIVATE_RPC = 'https://govtrace.stellar-mainnet.quiknode.pro/token-privado/';

function stellarConfig(array $overrides): array
{
    return array_merge(config('stellar'), ['network_passphrase' => MAINNET, 'rpc_url' => PRIVATE_RPC], $overrides);
}

it('refuses to start on the main network without a public RPC for the browser (D13)', function () {
    expect(fn () => MainnetConfiguration::assertSafe(stellarConfig(['public_rpc_url' => null])))
        ->toThrow(RuntimeException::class, 'Falta STELLAR_PUBLIC_RPC_URL: en la red principal, el validador del navegador necesita un endpoint de solo lectura restringido al dominio de GovTrace (D13).');
});

it('refuses the private RPC as the public one: its token would be in every browser (D13)', function (string $public) {
    expect(fn () => MainnetConfiguration::assertSafe(stellarConfig(['public_rpc_url' => $public])))
        ->toThrow(RuntimeException::class, 'STELLAR_PUBLIC_RPC_URL no puede ser el mismo endpoint que STELLAR_RPC_URL: el token del proveedor quedaría a la vista en el navegador (D13).');
})->with([
    'el mismo' => [PRIVATE_RPC],
    'el mismo, sin la barra final' => [rtrim(PRIVATE_RPC, '/')],
    'el mismo, con mayúsculas en el dominio' => ['https://GovTrace.stellar-mainnet.quiknode.pro/token-privado/'],
]);

it('starts on the main network with two different endpoints', function () {
    MainnetConfiguration::assertSafe(stellarConfig(['public_rpc_url' => 'https://govtrace-publico.stellar-mainnet.quiknode.pro/token-restringido/']));

    expect(true)->toBeTrue();
});

it('checks nothing outside the main network: testnet and the local network have their own public RPC', function (string $passphrase) {
    MainnetConfiguration::assertSafe(stellarConfig(['network_passphrase' => $passphrase, 'public_rpc_url' => null]));

    expect(true)->toBeTrue();
})->with([
    'testnet' => ['Test SDF Network ; September 2015'],
    'red local' => ['Standalone Network ; February 2017'],
]);

it('is checked when the application boots', function () {
    config(['stellar' => stellarConfig(['public_rpc_url' => null])]);

    expect(fn () => (new AppServiceProvider(app()))->boot())->toThrow(RuntimeException::class, 'Falta STELLAR_PUBLIC_RPC_URL');
});
