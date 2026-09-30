<?php

declare(strict_types=1);

use App\Application\Privacy\DataPolicy;
use App\Application\Publication\PublicStats;
use App\Application\Publication\StellarForBrowser;
use App\Domain\Organization\Roles;
use App\Http\Controllers\Auth\ChangePasswordController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Tenant\AuditController;
use App\Http\Controllers\Tenant\CitizenReportController;
use App\Http\Controllers\Tenant\CitizenReportInboxController;
use App\Http\Controllers\Tenant\ContractListController;
use App\Http\Controllers\Tenant\ContractSearchController;
use App\Http\Controllers\Tenant\DeclarationController;
use App\Http\Controllers\Tenant\EditorialController;
use App\Http\Controllers\Tenant\EvidenceFileController;
use App\Http\Controllers\Tenant\ExportController;
use App\Http\Controllers\Tenant\InviteObserverController;
use App\Http\Controllers\Tenant\LoginController;
use App\Http\Controllers\Tenant\MyReportsController;
use App\Http\Controllers\Tenant\NearbyWorksitesController;
use App\Http\Controllers\Tenant\ObserverController;
use App\Http\Controllers\Tenant\OpenDataController;
use App\Http\Controllers\Tenant\OrganizationLogoController;
use App\Http\Controllers\Tenant\OrganizationProfileController;
use App\Http\Controllers\Tenant\PublicEvidenceController;
use App\Http\Controllers\Tenant\PublicProofController;
use App\Http\Controllers\Tenant\PublicWorksiteController;
use App\Http\Controllers\Tenant\ReceiptController;
use App\Http\Controllers\Tenant\ReportController;
use App\Http\Controllers\Tenant\SetPasswordController;
use App\Http\Controllers\Tenant\SummaryController;
use App\Http\Controllers\Tenant\SuperAdminAuthorizationController;
use App\Http\Controllers\Tenant\TerritoryController;
use App\Http\Controllers\Tenant\WebManifestController;
use App\Http\Controllers\Tenant\WorksiteController;
use App\Http\Controllers\Tenant\WorksiteDossierController;
use App\Http\Controllers\Tenant\WorksiteGroupController;
use App\Http\Controllers\Tenant\WorksiteLocationController;
use App\Http\Middleware\EnsureAccountIsUsable;
use App\Http\Middleware\EnsureImpedimentsDeclared;
use App\Http\Middleware\EnsureMapIsOnline;
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
    // Público, sin sesión (R-VER-02): el mapa de la organización (US-027) y
    // la vista de cada obra (US-029, US-017). Piden sus datos al API de abajo.
    // US-003b: el mapa sale de línea con la baja de la organización; el resto, no.
    Route::middleware(EnsureMapIsOnline::class.':screen')->group(function () {
        Route::get('/', fn () => Inertia::render('Public/Map'))->name('public.map');
        Route::get('/worksite/{worksite}', fn (int $worksite) => Inertia::render('Public/Worksite', ['worksiteId' => $worksite, 'stellar' => StellarForBrowser::props()]))->whereNumber('worksite')->name('public.worksite');
        // US-051-RPT: las estadísticas del territorio.
        Route::get('/stats', fn () => Inertia::render('Public/Stats'))->name('public.stats');
    });
    // It. 41: las API públicas, con un límite por visitante.
    Route::middleware([EnsureMapIsOnline::class, 'throttle:public'])->group(function () {
        // US-027 / US-029: el mapa público — los pines, y lo que pide un clic en uno (it. 24).
        Route::get('/public/worksites', [PublicWorksiteController::class, 'index'])->name('public.worksites.index');
        Route::get('/public/worksites/filters', [PublicWorksiteController::class, 'filters'])->name('public.worksites.filters');
        // It. 40c: el mapa como lista, al abrirla (la carga del mapa no cambia, R-MAP-02).
        Route::get('/public/worksites/list', [PublicWorksiteController::class, 'listing'])->name('public.worksites.list');
        Route::get('/public/worksites/{worksite}', [PublicWorksiteController::class, 'show'])->whereNumber('worksite')->name('public.worksites.show');
        Route::get('/public/evidences/{evidence}/photo', [PublicEvidenceController::class, 'photo'])->whereNumber('evidence')->name('public.evidences.photo');
        Route::get('/public/stats', fn (PublicStats $stats) => response()->json($stats->handle()))->name('public.stats.data');
    });
    // US-059-LEG (it. 44f): el ciudadano informa a la veeduría, con su correo verificado por un código.
    Route::middleware(EnsureMapIsOnline::class)->group(function () {
        Route::post('/citizen-reports/code', [CitizenReportController::class, 'code'])->middleware('throttle:citizen-codes')->name('citizen-reports.code');
        Route::post('/citizen-reports', [CitizenReportController::class, 'store'])->middleware('throttle:citizen-reports')->name('citizen-reports.store');
    });

    // US-052-RPT: los datos abiertos, en CSV o JSON.
    Route::get('/open-data.{format}', OpenDataController::class)->whereIn('format', ['csv', 'json'])->middleware('throttle:open-data')->name('public.open-data');
    // US-024: el validador público — el navegador lee el sello en la red por su cuenta.
    // It. 43b (V14): con el enlace al verificador independiente (US-046-INT).
    Route::get('/verify', fn () => Inertia::render('Public/Validator', ['stellar' => StellarForBrowser::props(), 'verifierUrl' => config('app.verifier_url')]))->name('public.validator');

    // US-031: Administrador de Organización y Veedor (la pantalla, it. 17).
    Route::get('/login', fn () => Inertia::render('Auth/Login', ['context' => tenant()->displayName()]))->name('tenant.login.show');
    // US-058-LEG (it. 44e): la política de tratamiento de datos, también en cada veeduría.
    Route::get('/privacidad', fn () => Inertia::render('Public/Privacy', ['policy' => (new DataPolicy)->props()]))->name('tenant.privacy');

    // It. 43b (V6): el manifiesto de la app del veedor, con el nombre de su veeduría, para instalarla.
    Route::get('/manifest.webmanifest', WebManifestController::class)->name('tenant.manifest');

    // US-007: el logo, público (lo muestra el mapa de la organización).
    Route::get('/organization/logo', [OrganizationLogoController::class, 'show'])->name('organization.logo');

    // US-025 / US-026: el recibo de lo publicado (y retirado), y cada
    // archivo publicado con su prueba de inclusión (it. 23).
    Route::middleware('throttle:public')->group(function () {
        Route::get('/public/reports/{report}/receipt', [ReceiptController::class, 'public'])->whereNumber('report')->name('public.reports.receipt');
        Route::get('/public/evidences/{evidence}/download', [PublicEvidenceController::class, 'download'])->whereNumber('evidence')->name('public.evidences.download');
        Route::get('/public/evidences/{evidence}/proof', [PublicEvidenceController::class, 'proof'])->whereNumber('evidence')->name('public.evidences.proof');
        Route::get('/public/proofs/{sha256}', [PublicProofController::class, 'show'])->name('public.proofs.show');
    });
    Route::post('/login', [LoginController::class, 'store'])->name('tenant.login');

    // US-030: el enlace de US-002 (Administrador inicial) o US-005
    // (invitación de veedor) — mismo token, misma pantalla.
    Route::get('/set-password/{user}', [SetPasswordController::class, 'show'])->whereNumber('user')->name('tenant.set-password.show');
    Route::post('/set-password/{user}', [SetPasswordController::class, 'store'])->whereNumber('user')->name('tenant.set-password.store');

    // US-039-USR: restablecer la contraseña con un enlace por correo.
    Route::get('/forgot-password', [PasswordResetController::class, 'requestForm'])->name('tenant.password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'sendLink'])->name('tenant.password.email');
    Route::get('/reset-password/{token}', [PasswordResetController::class, 'resetForm'])->name('tenant.password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'reset'])->name('tenant.password.update');

    // Con sesión, dentro de la organización. EnsureAccountIsUsable: una
    // organización suspendida o dada de baja, o una cuenta desactivada,
    // cierran la sesión en su siguiente petición (US-003a, US-003b, US-006).
    Route::middleware(['auth:tenant', EnsureAccountIsUsable::class])->group(function () {
        // US-018: cerrar sesión (la app del veedor avisa antes si tiene reportes sin enviar).
        Route::post('/logout', [LoginController::class, 'destroy'])->name('tenant.logout');
        // It. 40c (V11): cambiar la contraseña con la sesión abierta, desde "Mi cuenta".
        Route::get('/account/password', [ChangePasswordController::class, 'show'])->name('tenant.account.password.show');
        Route::put('/account/password', [ChangePasswordController::class, 'update'])->middleware('throttle:6,1')->name('tenant.account.password.update');

        // El panel del Administrador abre en la bandeja de entrada (it. 18).
        Route::get('/organization/dashboard', fn () => redirect('/admin/inbox'))->name('organization.dashboard');
        // El veedor entra a "Nuevo Reporte", su pantalla central; desde ahí llega a "Mis Reportes".
        Route::get('/veedor/dashboard', fn () => redirect('/reports/new'))->name('veedor.dashboard');

        // US-005: solo el Administrador de Organización invita veedores.
        Route::middleware('role:'.Roles::Administrator->value.',tenant')->group(function () {
            Route::post('/observers/invite', [InviteObserverController::class, 'store'])->name('observers.invite');

            // US-035: corregir la ubicación oficial de una obra de la organización.
            Route::patch('/worksites/{worksite}/location', [WorksiteLocationController::class, 'update'])->name('worksites.location.update');
            // US-045-INT: agrupar varios contratos en una ficha de obra.
            Route::post('/worksites/group', [WorksiteGroupController::class, 'store'])->name('worksites.group');
            // US-056-LEG (it. 44b): el expediente de una obra, para el derecho de petición y la denuncia.
            Route::get('/worksites/{worksite}/dossier.zip', WorksiteDossierController::class)->whereNumber('worksite')->name('worksites.dossier');
            // US-059-LEG (it. 44f): los informes de los ciudadanos, sin su correo.
            Route::get('/citizen-reports', [CitizenReportInboxController::class, 'index'])->name('citizen-reports.index');
            Route::get('/citizen-reports/{report}/photo', [CitizenReportInboxController::class, 'photo'])->whereNumber('report')->name('citizen-reports.photo');
            Route::post('/citizen-reports/{report}/answer', [CitizenReportInboxController::class, 'answer'])->whereNumber('report')->name('citizen-reports.answer');
            Route::post('/citizen-reports/{report}/discard', [CitizenReportInboxController::class, 'discard'])->whereNumber('report')->name('citizen-reports.discard');

            // US-042-SEC: autorizar al Super Administrador a reportar en nombre de la organización (30 días).
            Route::get('/authorizations/super-admin', [SuperAdminAuthorizationController::class, 'show'])->name('authorizations.super-admin.show');
            Route::post('/authorizations/super-admin', [SuperAdminAuthorizationController::class, 'store'])->name('authorizations.super-admin.store');
            Route::delete('/authorizations/super-admin', [SuperAdminAuthorizationController::class, 'destroy'])->name('authorizations.super-admin.destroy');

            // US-049-RPT: el resumen del territorio.
            Route::get('/summary', SummaryController::class)->name('summary');
            // US-050-RPT: las obras y evidencias de la organización, en CSV.
            Route::get('/export.csv', ExportController::class)->name('export');

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
                'worksites' => 'Admin/Worksites', 'organization' => 'Admin/Organization', 'audit' => 'Admin/Audit', 'citizen-reports' => 'Admin/CitizenReports',
                'summary' => 'Admin/Summary', 'authorization' => 'Admin/SuperAdminAuthorization',
            ] as $screen => $component) {
                Route::get("/admin/{$screen}", fn () => Inertia::render($component))->name("admin.{$screen}");
            }
            Route::get('/observers', [ObserverController::class, 'index'])->name('observers.index');
            Route::post('/observers/{observer}/deactivate', [ObserverController::class, 'deactivate'])->whereNumber('observer')->name('observers.deactivate');
            Route::post('/observers/{observer}/reactivate', [ObserverController::class, 'reactivate'])->whereNumber('observer')->name('observers.reactivate');
            // US-040-USR: reenviar o revocar una invitación pendiente.
            Route::post('/observers/{observer}/invitation/resend', [ObserverController::class, 'resendInvitation'])->whereNumber('observer')->name('observers.invitation.resend');
            Route::post('/observers/{observer}/invitation/revoke', [ObserverController::class, 'revokeInvitation'])->whereNumber('observer')->name('observers.invitation.revoke');
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
            // US-057-LEG (it. 44c): un veedor reporta después de declarar que no tiene impedimentos para serlo.
            Route::get('/declaration', [DeclarationController::class, 'show'])->name('declaration.show');
            Route::post('/declaration', [DeclarationController::class, 'store'])->name('declaration.store');
            Route::get('/reports/new', fn () => Inertia::render('Veedor/NewReport'))->middleware(EnsureImpedimentsDeclared::class)->name('reports.new');
            // It. 41: cada reporte cuesta XLM y un turno de la selladora: un límite por veedor y por hora.
            Route::post('/reports', [ReportController::class, 'store'])->middleware([EnsureImpedimentsDeclared::class, 'throttle:reports'])->name('reports.store');

            // US-010 / US-023: "Mis Reportes", y el recibo de cada uno.
            Route::get('/my-reports', fn () => Inertia::render('Veedor/MyReports'))->name('reports.mine.show');
            Route::get('/me/reports', [MyReportsController::class, 'index'])->name('reports.mine');
            Route::get('/reports/{report}/receipt', [ReceiptController::class, 'mine'])->whereNumber('report')->name('reports.receipt');

            // US-016: "Buscar Obra". US-019: las obras cercanas.
            Route::get('/contracts/search', ContractSearchController::class)->name('contracts.search');
            Route::get('/worksites/nearby', NearbyWorksitesController::class)->name('worksites.nearby');
        });
    });
});
