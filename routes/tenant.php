<?php

declare(strict_types=1);

use App\Domain\Organization\Roles;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Tenant\AuditController;
use App\Http\Controllers\Tenant\ContractListController;
use App\Http\Controllers\Tenant\ContractSearchController;
use App\Http\Controllers\Tenant\EditorialController;
use App\Http\Controllers\Tenant\EvidenceFileController;
use App\Http\Controllers\Tenant\InviteObserverController;
use App\Http\Controllers\Tenant\LoginController;
use App\Http\Controllers\Tenant\ObserverController;
use App\Http\Controllers\Tenant\OrganizationLogoController;
use App\Http\Controllers\Tenant\OrganizationProfileController;
use App\Http\Controllers\Tenant\PublicEvidenceController;
use App\Http\Controllers\Tenant\PublicWorksiteController;
use App\Http\Controllers\Tenant\ReceiptController;
use App\Http\Controllers\Tenant\ReportController;
use App\Http\Controllers\Tenant\SetPasswordController;
use App\Http\Controllers\Tenant\SummaryController;
use App\Http\Controllers\Tenant\SuperAdminAuthorizationController;
use App\Http\Controllers\Tenant\TerritoryController;
use App\Http\Controllers\Tenant\WorksiteController;
use App\Http\Controllers\Tenant\WorksiteGroupController;
use App\Http\Controllers\Tenant\WorksiteLocationController;
use App\Http\Middleware\EnsureAccountIsUsable;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
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

    // US-031: Administrador de Organización y Veedor (la pantalla, it. 17).
    Route::get('/login', fn () => Inertia::render('Auth/Login', ['context' => tenant()->displayName()]))->name('tenant.login.show');

    // US-007: el logo, público (lo muestra el mapa de la organización).
    Route::get('/organization/logo', [OrganizationLogoController::class, 'show'])->name('organization.logo');

    // US-025 / US-026: el recibo de lo publicado (y retirado), y cada
    // archivo publicado con su prueba de inclusión (it. 23).
    Route::get('/public/reports/{report}/receipt', [ReceiptController::class, 'public'])->whereNumber('report')->name('public.reports.receipt');
    Route::get('/public/evidences/{evidence}/download', [PublicEvidenceController::class, 'download'])->whereNumber('evidence')->name('public.evidences.download');
    Route::get('/public/evidences/{evidence}/proof', [PublicEvidenceController::class, 'proof'])->whereNumber('evidence')->name('public.evidences.proof');

    // US-027 / US-029: el mapa público — los pines, y lo que pide un clic en uno (it. 24).
    Route::get('/public/worksites', [PublicWorksiteController::class, 'index'])->name('public.worksites.index');
    Route::get('/public/worksites/{worksite}', [PublicWorksiteController::class, 'show'])->whereNumber('worksite')->name('public.worksites.show');
    Route::get('/public/evidences/{evidence}/photo', [PublicEvidenceController::class, 'photo'])->whereNumber('evidence')->name('public.evidences.photo');
    Route::post('/login', [LoginController::class, 'store'])->name('tenant.login');

    // US-030: el enlace de US-002 (Administrador inicial) o US-005
    // (invitación de veedor) — mismo token, misma pantalla.
    Route::get('/set-password/{user}', [SetPasswordController::class, 'show'])->whereNumber('user')->name('tenant.set-password.show');
    Route::post('/set-password/{user}', [SetPasswordController::class, 'store'])->name('tenant.set-password.store');

    // US-039-USR: restablecer la contraseña con un enlace por correo.
    Route::get('/forgot-password', [PasswordResetController::class, 'requestForm'])->name('tenant.password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'sendLink'])->name('tenant.password.email');
    Route::get('/reset-password/{token}', [PasswordResetController::class, 'resetForm'])->name('tenant.password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'reset'])->name('tenant.password.update');

    // Placeholders hasta que it. 18/it. 19 construyan los paneles reales.
    // EnsureAccountIsUsable: una organización suspendida o una cuenta
    // desactivada cierran la sesión en su siguiente petición (US-003a, US-006).
    Route::middleware(['auth:tenant', EnsureAccountIsUsable::class])->group(function () {
        // El panel del Administrador abre en la bandeja de entrada (it. 18).
        Route::get('/organization/dashboard', fn () => redirect('/admin/inbox'))->name('organization.dashboard');
        // El panel del veedor es "Nuevo Reporte" hasta "Mis Reportes" (it. 28).
        Route::get('/veedor/dashboard', fn () => redirect('/reports/new'))->name('veedor.dashboard');

        // US-005: solo el Administrador de Organización invita veedores.
        Route::middleware('role:'.Roles::Administrator->value.',tenant')->group(function () {
            Route::post('/observers/invite', [InviteObserverController::class, 'store'])->name('observers.invite');

            // US-035: corregir la ubicación oficial de una obra de la organización.
            Route::patch('/worksites/{worksite}/location', [WorksiteLocationController::class, 'update'])->name('worksites.location.update');
            // US-045-INT: agrupar varios contratos en una ficha de obra.
            Route::post('/worksites/group', [WorksiteGroupController::class, 'store'])->name('worksites.group');

            // US-042-SEC: autorizar al Super Administrador a reportar en nombre de la organización (30 días).
            Route::get('/authorizations/super-admin', [SuperAdminAuthorizationController::class, 'show'])->name('authorizations.super-admin.show');
            Route::post('/authorizations/super-admin', [SuperAdminAuthorizationController::class, 'store'])->name('authorizations.super-admin.store');
            Route::delete('/authorizations/super-admin', [SuperAdminAuthorizationController::class, 'destroy'])->name('authorizations.super-admin.destroy');

            // US-049-RPT: el resumen del territorio.
            Route::get('/summary', SummaryController::class)->name('summary');

            // US-036 / US-037: la bandeja de entrada y las decisiones
            // editoriales, de a una evidencia (no hay publicación masiva).
            Route::get('/inbox', [EditorialController::class, 'inbox'])->name('inbox');
            Route::post('/reports/{report}/publish', [EditorialController::class, 'publish'])->whereNumber('report')->name('reports.publish');
            Route::post('/reports/{report}/reject', [EditorialController::class, 'reject'])->whereNumber('report')->name('reports.reject');
            Route::post('/reports/{report}/withdraw', [EditorialController::class, 'withdraw'])->whereNumber('report')->name('reports.withdraw');
            Route::get('/evidences/{evidence}/file', [EvidenceFileController::class, 'show'])->whereNumber('evidence')->name('evidences.file');

            // El panel del Administrador (it. 18): cada pantalla pide sus datos al JSON de abajo.
            foreach ([
                'inbox' => 'Admin/Inbox', 'observers' => 'Admin/Observers', 'territory' => 'Admin/Territory', 'contracts' => 'Admin/Contracts',
                'worksites' => 'Admin/Worksites', 'organization' => 'Admin/Organization', 'audit' => 'Admin/Audit',
            ] as $screen => $component) {
                Route::get("/admin/{$screen}", fn () => Inertia::render($component))->name("admin.{$screen}");
            }
            Route::get('/observers', [ObserverController::class, 'index'])->name('observers.index');
            Route::post('/observers/{observer}/deactivate', [ObserverController::class, 'deactivate'])->whereNumber('observer')->name('observers.deactivate');
            Route::post('/observers/{observer}/reactivate', [ObserverController::class, 'reactivate'])->whereNumber('observer')->name('observers.reactivate');
            Route::get('/territory', [TerritoryController::class, 'show'])->name('territory.show');
            Route::get('/territory/search', [TerritoryController::class, 'search'])->name('territory.search');
            Route::put('/territory', [TerritoryController::class, 'update'])->name('territory.update');
            Route::get('/contracts', ContractListController::class)->name('contracts.index');
            Route::get('/worksites', [WorksiteController::class, 'index'])->name('worksites.index');

            // US-007: nombre de fantasía y logo. US-043-MON: el log de auditoría de la organización.
            Route::get('/organization/profile', [OrganizationProfileController::class, 'show'])->name('organization.profile.show');
            Route::post('/organization/profile', [OrganizationProfileController::class, 'update'])->name('organization.profile.update');
            Route::get('/audit', [AuditController::class, 'index'])->name('audit.index');
            Route::get('/audit/{entry}', [AuditController::class, 'show'])->whereNumber('entry')->name('audit.show');
        });

        // US-008: solo el Veedor de Campo crea reportes, desde la PWA (it. 16).
        Route::middleware('role:'.Roles::Observer->value.',tenant')->group(function () {
            Route::get('/reports/new', fn () => Inertia::render('Veedor/NewReport'))->name('reports.new');
            Route::post('/reports', [ReportController::class, 'store'])->name('reports.store');

            // US-023: el recibo de uno de sus reportes (la pantalla, it. 28).
            Route::get('/reports/{report}/receipt', [ReceiptController::class, 'mine'])->whereNumber('report')->name('reports.receipt');

            // US-016: "Buscar Obra".
            Route::get('/contracts/search', ContractSearchController::class)->name('contracts.search');
        });
    });
});
