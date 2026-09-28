<?php

use App\Http\Controllers\Central\LoginController;
use App\Http\Controllers\Central\OrganizationController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Central routes: restricted to the central domains so they don't collide with tenant routes.
foreach (config('tenancy.central_domains') as $domain) {
    Route::domain($domain)->group(function () {
        Route::get('/', fn () => Inertia::render('Home'))->name('home');

        // US-031: Super Administrator only (the screen, it. 17).
        Route::get('/login', fn () => Inertia::render('Auth/Login', ['context' => 'Panel global']))->name('login.show');
        Route::post('/login', [LoginController::class, 'store'])->name('login');

        Route::middleware('auth:web')->group(function () {
            // El panel del Super Administrador abre en el listado de organizaciones (it. 19).
            Route::get('/dashboard', fn () => redirect('/admin/organizations'))->name('super-admin.dashboard');

            Route::get('/admin/organizations', fn () => Inertia::render('SuperAdmin/Organizations'))->name('admin.organizations.show');
            Route::get('/admin/organizations/new', fn () => Inertia::render('SuperAdmin/NewOrganization'))->name('admin.organizations.new');
            Route::get('/admin/organizations/data', [OrganizationController::class, 'index'])->name('admin.organizations.index');
            Route::post('/admin/organizations', [OrganizationController::class, 'store'])->name('admin.organizations.store');
            Route::get('/admin/organizations/{tenant}', [OrganizationController::class, 'show'])->name('admin.organizations.detail');
            Route::put('/admin/organizations/{tenant}/nit', [OrganizationController::class, 'updateNit'])->name('admin.organizations.update-nit');
        });
    });
}
