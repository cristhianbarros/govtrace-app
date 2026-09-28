<?php

declare(strict_types=1);

use App\Domain\Organization\Roles;
use App\Http\Controllers\Tenant\EditorialController;
use App\Http\Controllers\Tenant\InviteObserverController;
use App\Http\Controllers\Tenant\LoginController;
use App\Http\Controllers\Tenant\ReportController;
use App\Http\Controllers\Tenant\SetPasswordController;
use App\Http\Controllers\Tenant\WorksiteLocationController;
use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;
use Stancl\Tenancy\Middleware\ScopeSessions;

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
    // Una sesión vale solo en la organización donde se abrió: los usuarios
    // viven en la base de cada una, así que la misma cookie presentada en
    // otro subdominio sería el usuario con ese id de la otra (403).
    ScopeSessions::class,
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

    // US-030: consume el enlace de US-002 (Administrador inicial) o
    // US-005 (invitación de veedor) — mismo token, mismo formulario.
    Route::post('/set-password/{user}', [SetPasswordController::class, 'store'])->name('tenant.set-password.store');

    // Placeholders hasta que it. 18/it. 19 construyan los paneles reales.
    Route::middleware('auth:tenant')->group(function () {
        Route::get('/organization/dashboard', fn () => 'Organization Dashboard')->name('organization.dashboard');
        Route::get('/veedor/dashboard', fn () => 'Veedor Dashboard')->name('veedor.dashboard');

        // US-005: solo el Administrador de Organización invita veedores.
        Route::middleware('role:'.Roles::Administrator->value.',tenant')->group(function () {
            Route::post('/observers/invite', [InviteObserverController::class, 'store'])->name('observers.invite');

            // US-035: corregir la ubicación oficial de una obra de la organización.
            Route::patch('/worksites/{worksite}/location', [WorksiteLocationController::class, 'update'])->name('worksites.location.update');

            // US-036 / US-037: la bandeja de entrada y las decisiones
            // editoriales, de a una evidencia (no hay publicación masiva).
            Route::get('/inbox', [EditorialController::class, 'inbox'])->name('inbox');
            Route::post('/reports/{report}/publish', [EditorialController::class, 'publish'])->whereNumber('report')->name('reports.publish');
            Route::post('/reports/{report}/reject', [EditorialController::class, 'reject'])->whereNumber('report')->name('reports.reject');
            Route::post('/reports/{report}/withdraw', [EditorialController::class, 'withdraw'])->whereNumber('report')->name('reports.withdraw');
        });

        // US-008: solo el Veedor de Campo crea reportes (la PWA, it. 16).
        Route::middleware('role:'.Roles::Observer->value.',tenant')->group(function () {
            Route::post('/reports', [ReportController::class, 'store'])->name('reports.store');
        });
    });
});
