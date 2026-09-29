<?php

use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Central\AuditController;
use App\Http\Controllers\Central\LoginController;
use App\Http\Controllers\Central\OrganizationController;
use App\Http\Controllers\Central\ParameterController;
use App\Http\Controllers\Central\SecopHealthController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Central routes: restricted to the central domains so they don't collide with tenant routes.
foreach (config('tenancy.central_domains') as $domain) {
    Route::domain($domain)->group(function () {
        Route::get('/', fn () => Inertia::render('Home'))->name('home');

        // US-031: Super Administrator only (the screen, it. 17).
        Route::get('/login', fn () => Inertia::render('Auth/Login', ['context' => 'Panel global']))->name('login.show');
        Route::post('/login', [LoginController::class, 'store'])->name('login');

        // US-039-USR: el Super Administrador también restablece su contraseña.
        Route::get('/forgot-password', [PasswordResetController::class, 'requestForm'])->name('password.request');
        Route::post('/forgot-password', [PasswordResetController::class, 'sendLink'])->name('password.email');
        Route::get('/reset-password/{token}', [PasswordResetController::class, 'resetForm'])->name('password.reset');
        Route::post('/reset-password', [PasswordResetController::class, 'reset'])->name('password.update');

        Route::middleware('auth:web')->group(function () {
            // El panel del Super Administrador abre en el listado de organizaciones (it. 19).
            Route::get('/dashboard', fn () => redirect('/admin/organizations'))->name('super-admin.dashboard');

            Route::get('/admin/organizations', fn () => Inertia::render('SuperAdmin/Organizations'))->name('admin.organizations.show');
            Route::get('/admin/organizations/new', fn () => Inertia::render('SuperAdmin/NewOrganization'))->name('admin.organizations.new');
            Route::get('/admin/organizations/data', [OrganizationController::class, 'index'])->name('admin.organizations.index');
            Route::post('/admin/organizations', [OrganizationController::class, 'store'])->name('admin.organizations.store');
            Route::get('/admin/organizations/{tenant}', [OrganizationController::class, 'show'])->name('admin.organizations.detail');
            Route::put('/admin/organizations/{tenant}/nit', [OrganizationController::class, 'updateNit'])->name('admin.organizations.update-nit');
            Route::post('/admin/organizations/{tenant}/suspend', [OrganizationController::class, 'suspend'])->name('admin.organizations.suspend');
            Route::post('/admin/organizations/{tenant}/reactivate', [OrganizationController::class, 'reactivate'])->name('admin.organizations.reactivate');

            // US-038-CFG: parámetros globales. US-043-MON: el log de auditoría completo.
            Route::get('/admin/parameters', fn () => Inertia::render('SuperAdmin/Parameters'))->name('admin.parameters.show');
            Route::get('/admin/parameters/data', [ParameterController::class, 'index'])->name('admin.parameters.index');
            Route::put('/admin/parameters/{key}', [ParameterController::class, 'update'])->name('admin.parameters.update');
            Route::get('/admin/audit', fn () => Inertia::render('SuperAdmin/Audit'))->name('admin.audit.show');
            Route::get('/admin/audit/data', [AuditController::class, 'index'])->name('admin.audit.index');

            // US-014: salud de la sincronización con SECOP II.
            Route::get('/admin/secop-health', fn () => Inertia::render('SuperAdmin/SecopHealth'))->name('admin.secop-health.show');
            Route::get('/admin/secop-health/data', SecopHealthController::class)->name('admin.secop-health.data');
        });
    });
}
