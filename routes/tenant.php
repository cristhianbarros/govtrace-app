<?php

declare(strict_types=1);

use App\Http\Controllers\Tenant\LoginController;
use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

/*
|--------------------------------------------------------------------------
| Tenant Routes
|--------------------------------------------------------------------------
|
| Here you can register the tenant routes for your application.
| These routes are loaded by the TenantRouteServiceProvider.
|
| Feel free to customize them however you want. Good luck!
|
*/

Route::middleware([
    'web',
    InitializeTenancyByDomain::class,
    PreventAccessFromCentralDomains::class,
])->group(function () {
    // Público, sin sesión (R-VER-02) — el mapa y el validador reales
    // llegan en it. 24-27; este placeholder solo prueba que las rutas de
    // tenant no exigen autenticación por defecto (US-031).
    Route::get('/', function () {
        return 'This is your multi-tenant application. The id of the current tenant is '.tenant('id');
    });

    // US-031: Administrador de Organización y Veedor. La pantalla Vue
    // llega en it. 17 (specs/PLAN.md) — esto es el backend al que postea
    // el formulario.
    Route::post('/login', [LoginController::class, 'store'])->name('tenant.login');

    // Placeholders hasta que it. 18/it. 19 construyan los paneles reales.
    Route::middleware('auth:tenant')->group(function () {
        Route::get('/organization/dashboard', fn () => 'Organization Dashboard')->name('organization.dashboard');
        Route::get('/veedor/dashboard', fn () => 'Veedor Dashboard')->name('veedor.dashboard');
    });
});
