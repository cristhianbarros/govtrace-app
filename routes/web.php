<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Central routes: restricted to the central domains so they don't collide with tenant routes.
foreach (config('tenancy.central_domains') as $domain) {
    Route::domain($domain)->group(function () {
        Route::get('/', fn () => Inertia::render('Home'))->name('home');
    });
}
