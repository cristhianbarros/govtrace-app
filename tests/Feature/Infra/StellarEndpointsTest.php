<?php

use App\Application\Organization\RegisterOrganization;
use App\Domain\Shared\PublicId;
use App\Infrastructure\Stellar\StellarRpc;
use App\Infrastructure\Tenancy\Tenant;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * Iteración 37a — D13: la separación de los dos endpoints del RPC de
 * Stellar. El servidor sella y lee solo por el privado (STELLAR_RPC_URL);
 * el navegador recibe solo el público (STELLAR_PUBLIC_RPC_URL), el del
 * validador (US-024). El privado, con el token del proveedor, nunca llega
 * a una página.
 *
 * Sin RefreshDatabase — registrar la organización ejecuta CREATE DATABASE.
 */

const PRIVATE_ENDPOINT = 'https://privado.rpc.example/token-privado-del-servidor';
const PUBLIC_ENDPOINT = 'https://publico.rpc.example/token-restringido-al-dominio';

beforeEach(function () {
    $this->artisan('migrate');
    config([
        'stellar.network_passphrase' => 'Test SDF Network ; September 2015',
        'stellar.rpc_url' => PRIVATE_ENDPOINT,
        'stellar.public_rpc_url' => PUBLIC_ENDPOINT,
    ]);
    $this->tenant = (new RegisterOrganization)->handle('900123456-8', 'Veeduría Ciudadana Santa Marta', 'veeduria-smr');
});

afterEach(function () {
    if (tenant()) {
        tenancy()->end();
    }

    Tenant::query()->get()->each->delete();
});

it('talks to Stellar only through the private RPC of the server (D13)', function () {
    Http::fake([PRIVATE_ENDPOINT => Http::response(['jsonrpc' => '2.0', 'id' => 1, 'result' => ['entries' => [], 'latestLedger' => 1200]])]);

    app(StellarRpc::class)->account('GCMN776RZKGVGGLLL2KB5YW322QOAA2TAHINWC6322RE2VBX4UWRSNGF');

    Http::assertSent(fn (Request $request) => $request->url() === PRIVATE_ENDPOINT);
    Http::assertNotSent(fn (Request $request) => str_contains($request->url(), 'publico.rpc.example'));
});

it('gives the browser only the public RPC: the private one never reaches a page (D13)', function (string $path, string $component) {
    $response = publicGet($path)->assertOk();

    $response->assertInertia(fn (Assert $page) => $page->component($component)->where('stellar.rpc_url', PUBLIC_ENDPOINT));
    expect($response->getContent())->not->toContain('token-privado-del-servidor')
        ->not->toContain('privado.rpc.example');
})->with([
    'el validador' => ['/verify', 'Public/Validator'],
    'la vista de una obra' => [fn () => '/worksite/'.PublicId::generate(), 'Public/Worksite'],
]);
