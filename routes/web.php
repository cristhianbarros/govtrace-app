<?php

use App\Application\Organization\OrganizationRequests;
use App\Application\Organization\PublicDirectory;
use App\Application\Privacy\DataPolicy;
use App\Domain\Organization\OrganizationRequest;
use App\Domain\Shared\PublicId;
use App\Http\Controllers\Auth\ChangePasswordController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Central\AuditController;
use App\Http\Controllers\Central\LoginController;
use App\Http\Controllers\Central\OrganizationAdministratorController;
use App\Http\Controllers\Central\OrganizationController;
use App\Http\Controllers\Central\OrganizationReportController;
use App\Http\Controllers\Central\OrganizationRequestController;
use App\Http\Controllers\Central\ParameterController;
use App\Http\Controllers\Central\SealingController;
use App\Http\Controllers\Central\SealingCostsController;
use App\Http\Controllers\Central\SecopHealthController;
use App\Http\Controllers\Central\SecopSyncNowController;
use App\Http\Controllers\Central\SetPasswordController;
use App\Http\Controllers\Central\SuperAdministratorController;
use App\Http\Controllers\Central\TwoFactorController;
use App\Http\Controllers\Central\UsageController;
use App\Http\Middleware\EnsureSuperAdministratorIsActive;
use App\Http\Middleware\EnsureTwoFactorPassed;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Central routes: restricted to the central domains so they don't collide with tenant routes.
foreach (config('tenancy.central_domains') as $domain) {
    Route::domain($domain)->group(function () {
        // It. 40d (V5): el Inicio lleva al mapa de cada veeduría (R-MAP-01: no hay un mapa global).
        Route::get('/', fn () => Inertia::render('Home', ['organizations' => (new PublicDirectory)->handle()]))->name('home');
        // It. 43k (V10, US-062-ALT): una veeduría pide su alta, sin cuenta; la decide el Super Administrador.
        Route::post('/organization-requests', [OrganizationRequestController::class, 'store'])->middleware('throttle:organization-requests')->name('organization-requests.store');

        // US-031: Super Administrator only (the screen, it. 17).
        Route::get('/login', fn () => Inertia::render('Auth/Login', ['context' => 'Panel global']))->name('login.show');
        // US-058-LEG (it. 44e): la política de tratamiento de datos (Ley 1581 de 2012).
        Route::get('/privacidad', fn () => Inertia::render('Public/Privacy', ['policy' => (new DataPolicy)->props()]))->name('privacy');
        Route::post('/login', [LoginController::class, 'store'])->name('login');

        // US-039-USR: el Super Administrador también restablece su contraseña.
        Route::get('/forgot-password', [PasswordResetController::class, 'requestForm'])->name('password.request');
        Route::post('/forgot-password', [PasswordResetController::class, 'sendLink'])->name('password.email');
        Route::get('/reset-password/{token}', [PasswordResetController::class, 'resetForm'])->name('password.reset');
        Route::post('/reset-password', [PasswordResetController::class, 'reset'])->name('password.update');
        // It. 46a (US-063-USR): el enlace de la invitación de un Super Administrador.
        Route::get('/set-password/{user}', [SetPasswordController::class, 'show'])->where('user', PublicId::PATTERN)->name('set-password.show');
        Route::post('/set-password/{user}', [SetPasswordController::class, 'store'])->where('user', PublicId::PATTERN)->middleware('throttle:6,1')->name('set-password.store');

        // It. 46g (US-065-SEC, R-SEC-09): el segundo paso del Super Administrador, después de la
        // contraseña. Solo existe si el operador lo activó (SUPER_ADMIN_TWO_FACTOR).
        Route::get('/two-factor', [TwoFactorController::class, 'show'])->name('two-factor.show');
        Route::post('/two-factor', [TwoFactorController::class, 'challenge'])->middleware('throttle:20,1')->name('two-factor.challenge');
        Route::post('/two-factor/setup', [TwoFactorController::class, 'setup'])->middleware('throttle:20,1')->name('two-factor.setup');

        // It. 45a: 'auth.session' cierra la sesión si la contraseña cambió desde que se abrió.
        // It. 46a: un Super Administrador desactivado por otro no sigue actuando con su sesión abierta.
        // It. 46g: con el segundo paso activo, una sesión que no lo pasó se cierra.
        Route::middleware(['auth:web', 'auth.session', EnsureSuperAdministratorIsActive::class, EnsureTwoFactorPassed::class])->group(function () {
            // It. 46g: los códigos de recuperación, una sola vez, al configurar la app.
            Route::get('/two-factor/recovery-codes', [TwoFactorController::class, 'recoveryCodes'])->name('two-factor.recovery-codes');
            // It. 40b (V1): cerrar sesión, como en cada organización.
            Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');
            // It. 40c (V11): cambiar la contraseña con la sesión abierta.
            Route::get('/account/password', [ChangePasswordController::class, 'show'])->name('account.password.show');
            Route::put('/account/password', [ChangePasswordController::class, 'update'])->middleware('throttle:6,1')->name('account.password.update');

            // El panel del Super Administrador abre en el listado de organizaciones (it. 19).
            Route::get('/dashboard', fn () => redirect('/admin/organizations'))->name('super-admin.dashboard');

            Route::get('/admin/organizations', fn () => Inertia::render('SuperAdmin/Organizations'))->name('admin.organizations.show');
            // It. 43k (V10): con ?request=, precargada con una solicitud de alta.
            Route::get('/admin/organizations/new', fn (Request $request) => Inertia::render('SuperAdmin/NewOrganization', [
                'request' => $request->filled('request')
                    ? (new OrganizationRequests)->prefill(OrganizationRequest::query()->where('status', 'pending')->where('public_id', $request->string('request'))->firstOrFail())
                    : null,
            ]))->name('admin.organizations.new');
            Route::get('/admin/organization-requests', [OrganizationRequestController::class, 'show'])->name('admin.organization-requests.show');
            Route::get('/admin/organization-requests/data', [OrganizationRequestController::class, 'index'])->name('admin.organization-requests.index');
            // It. 46b: lo que dice el RUES de cada solicitud, y el PDF que adjuntó.
            Route::get('/admin/organization-requests/{organizationRequest}/rues', [OrganizationRequestController::class, 'rues'])->where('organizationRequest', PublicId::PATTERN)->middleware('throttle:60,1')->name('admin.organization-requests.rues');
            Route::get('/admin/organization-requests/{organizationRequest}/document', [OrganizationRequestController::class, 'document'])->where('organizationRequest', PublicId::PATTERN)->name('admin.organization-requests.document');
            Route::get('/admin/rues', [OrganizationController::class, 'rues'])->middleware('throttle:60,1')->name('admin.rues');
            Route::get('/admin/organizations/{tenant}/registration-document', [OrganizationController::class, 'registrationDocument'])->name('admin.organizations.registration-document');
            Route::post('/admin/organization-requests/{organizationRequest}/reject', [OrganizationRequestController::class, 'reject'])->where('organizationRequest', PublicId::PATTERN)->name('admin.organization-requests.reject');
            Route::get('/admin/organizations/data', [OrganizationController::class, 'index'])->name('admin.organizations.index');
            Route::post('/admin/organizations', [OrganizationController::class, 'store'])->name('admin.organizations.store');
            Route::get('/admin/organizations/{tenant}', [OrganizationController::class, 'show'])->name('admin.organizations.detail');
            Route::put('/admin/organizations/{tenant}/nit', [OrganizationController::class, 'updateNit'])->name('admin.organizations.update-nit');
            // It. 43a (V2): su Administrador — asignarlo si no tiene, y reenviar o revocar su invitación.
            Route::post('/admin/organizations/{tenant}/administrators', [OrganizationAdministratorController::class, 'store'])->name('admin.organizations.administrators.store');
            Route::post('/admin/organizations/{tenant}/administrators/{user}/invitation/resend', [OrganizationAdministratorController::class, 'resend'])->where('user', PublicId::PATTERN)->name('admin.organizations.administrators.resend');
            Route::post('/admin/organizations/{tenant}/administrators/{user}/invitation/revoke', [OrganizationAdministratorController::class, 'revoke'])->where('user', PublicId::PATTERN)->name('admin.organizations.administrators.revoke');
            // It. 43j (V3): varios administradores; el que se fue, desactivado, sin dejar la organización sin uno activo.
            Route::post('/admin/organizations/{tenant}/administrators/{user}/deactivate', [OrganizationAdministratorController::class, 'deactivate'])->where('user', PublicId::PATTERN)->name('admin.organizations.administrators.deactivate');
            Route::post('/admin/organizations/{tenant}/administrators/{user}/reactivate', [OrganizationAdministratorController::class, 'reactivate'])->where('user', PublicId::PATTERN)->name('admin.organizations.administrators.reactivate');
            Route::post('/admin/organizations/{tenant}/suspend', [OrganizationController::class, 'suspend'])->name('admin.organizations.suspend');
            Route::post('/admin/organizations/{tenant}/reactivate', [OrganizationController::class, 'reactivate'])->name('admin.organizations.reactivate');
            // US-003b: la baja definitiva, con doble confirmación.
            Route::post('/admin/organizations/{tenant}/decommission/start', [OrganizationController::class, 'startDecommission'])->name('admin.organizations.decommission.start');
            Route::post('/admin/organizations/{tenant}/decommission', [OrganizationController::class, 'decommission'])->name('admin.organizations.decommission');
            // US-042-SEC: un reporte en nombre de una organización que lo autorizó (R-SA-02).
            Route::post('/admin/organizations/{tenant}/reports', [OrganizationReportController::class, 'store'])->name('admin.organizations.reports.store');
            // It. 43g (V7): su pantalla, y la búsqueda de la obra en el territorio de esa organización.
            Route::get('/admin/organizations/{tenant}/report', [OrganizationReportController::class, 'create'])->name('admin.organizations.reports.create');
            Route::get('/admin/organizations/{tenant}/contracts/search', [OrganizationReportController::class, 'contracts'])->name('admin.organizations.contracts.search');

            // US-038-CFG: parámetros globales. US-043-MON: el log de auditoría completo.
            Route::get('/admin/parameters', fn () => Inertia::render('SuperAdmin/Parameters'))->name('admin.parameters.show');
            Route::get('/admin/parameters/data', [ParameterController::class, 'index'])->name('admin.parameters.index');
            Route::put('/admin/parameters/{key}', [ParameterController::class, 'update'])->name('admin.parameters.update');
            Route::get('/admin/audit', fn () => Inertia::render('SuperAdmin/Audit'))->name('admin.audit.show');
            Route::get('/admin/audit/data', [AuditController::class, 'index'])->name('admin.audit.index');

            // US-014: salud de la sincronización con SECOP II.
            Route::get('/admin/secop-health', fn () => Inertia::render('SuperAdmin/SecopHealth'))->name('admin.secop-health.show');
            Route::get('/admin/secop-health/data', SecopHealthController::class)->name('admin.secop-health.data');
            // It. 43b (V15): sincronizar ahora, sin esperar a la madrugada.
            Route::post('/admin/secop-health/sync', SecopSyncNowController::class)->name('admin.secop-health.sync');

            // US-022 y US-047-MNT: la cuenta patrocinadora, la vigencia del contrato y las
            // fallas de sellado; US-004: las comisiones. Una sola pantalla, "Sellado".
            Route::get('/admin/sealing', fn () => Inertia::render('SuperAdmin/Sealing'))->name('admin.sealing.show');
            Route::get('/admin/sealing/data', [SealingController::class, 'data'])->name('admin.sealing.data');
            Route::post('/admin/sealing/requeue', [SealingController::class, 'requeue'])->name('admin.sealing.requeue');
            Route::get('/admin/costs/data', SealingCostsController::class)->name('admin.costs.data');

            // US-053-RPT: el resumen de uso por organización.
            Route::get('/admin/usage', fn () => Inertia::render('SuperAdmin/Usage'))->name('admin.usage.show');
            Route::get('/admin/usage/data', UsageController::class)->name('admin.usage.data');
            // It. 46a (US-063-USR): varios Super Administradores, y nunca ninguno.
            Route::get('/admin/super-administrators', [SuperAdministratorController::class, 'show'])->name('admin.super-administrators.show');
            Route::get('/admin/super-administrators/data', [SuperAdministratorController::class, 'index'])->name('admin.super-administrators.index');
            Route::post('/admin/super-administrators', [SuperAdministratorController::class, 'store'])->middleware('throttle:20,1')->name('admin.super-administrators.store');
            Route::post('/admin/super-administrators/{user}/invitation/resend', [SuperAdministratorController::class, 'resend'])->where('user', PublicId::PATTERN)->name('admin.super-administrators.resend');
            Route::post('/admin/super-administrators/{user}/invitation/revoke', [SuperAdministratorController::class, 'revoke'])->where('user', PublicId::PATTERN)->name('admin.super-administrators.revoke');
            Route::post('/admin/super-administrators/{user}/deactivate', [SuperAdministratorController::class, 'deactivate'])->where('user', PublicId::PATTERN)->name('admin.super-administrators.deactivate');
            Route::post('/admin/super-administrators/{user}/reactivate', [SuperAdministratorController::class, 'reactivate'])->where('user', PublicId::PATTERN)->name('admin.super-administrators.reactivate');
        });
    });
}
