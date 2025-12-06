<?php

declare(strict_types=1);

use App\Http\Controllers\Central\Auth\AdminLoginController;
use App\Http\Controllers\Central\Auth\AdminLogoutController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Central Admin Authentication Routes
|--------------------------------------------------------------------------
|
| These routes handle authentication for central administrators.
|
| TENANT-ONLY ARCHITECTURE (Option C):
| - Uses 'admin' guard for authentication (central database)
| - Separate from tenant user authentication (Fortify)
| - Admins can impersonate tenants via ImpersonationController
|
| Sections:
| - Guest routes (admin login)
| - Authenticated routes (admin logout)
|
*/

foreach (config('tenancy.identification.central_domains') as $domain) {
    Route::domain($domain)->middleware('web')->name('central.admin.auth.')->group(function () {

        /*
        |----------------------------------------------------------------------
        | Guest Routes (admin login)
        |----------------------------------------------------------------------
        */

        Route::middleware('guest:admin')->group(function () {
            // Admin login form
            Route::get('/admin/login', [AdminLoginController::class, 'create'])
                ->name('login');

            // Admin login submit
            Route::post('/admin/login', [AdminLoginController::class, 'store'])
                ->name('login.store');
        });

        /*
        |----------------------------------------------------------------------
        | Authenticated Routes (admin logout)
        |----------------------------------------------------------------------
        */

        Route::middleware('auth:admin')->group(function () {
            // Admin logout
            Route::post('/admin/logout', [AdminLogoutController::class, 'destroy'])
                ->name('logout');
        });
    });
}
