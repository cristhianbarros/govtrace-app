<?php

use App\Http\Controllers\Central\LoginController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Central routes: restricted to the central domains so they don't collide with tenant routes.
foreach (config('tenancy.central_domains') as $domain) {
    Route::domain($domain)->group(function () {
        Route::get('/', fn () => Inertia::render('Home'))->name('home');

        // US-031: Super Administrator only. The Vue login page arrives in
        // it. 17 (specs/PLAN.md) — this is the backend the form will post to.
        Route::post('/login', [LoginController::class, 'store'])->name('login');

        // Placeholder until it. 19 builds the real panel.
        Route::middleware('auth:web')
            ->get('/dashboard', fn () => 'Super Admin Dashboard')
            ->name('super-admin.dashboard');
    });
}
