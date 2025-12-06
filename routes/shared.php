<?php

/*
|--------------------------------------------------------------------------
| Shared Settings Routes (Universal Routes)
|--------------------------------------------------------------------------
|
| These routes work in BOTH central and tenant contexts using Stancl/Tenancy v4
| Universal Routes feature.
|
| Middleware Order (IMPORTANT - order matters!):
| 1. web: Web middleware group (sessions, cookies, CSRF)
| 2. InitializeTenancyByDomain: Checks domain and determines context
| 3. universal: Flag that tells tenancy to NOT fail if tenant not found
| 4. auth: Requires authentication
|
| How it works:
| - InitializeTenancyByDomain checks if route is 'universal'
| - For universal routes, it calls requestHasTenant() to check domain
| - If domain is in central_domains (localhost) → tenancy NOT initialized
| - If domain is a tenant domain (tenant1.localhost) → tenancy IS initialized
|
| Behavior:
| - Central domain (localhost): Settings work without tenant context
| - Tenant domain (*.localhost): Settings work WITH tenant context
|
| @see https://v4.tenancyforlaravel.com/universal-routes
|
*/

use App\Http\Controllers\Shared\Settings\PasswordController;
use App\Http\Controllers\Shared\Settings\ProfileController;
use App\Http\Controllers\Shared\Settings\TwoFactorAuthenticationController;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware(['web', InitializeTenancyByDomain::class, 'universal', 'auth'])
    ->prefix('settings')
    ->name('shared.settings.')
    ->group(function () {
        Route::redirect('/', '/settings/profile');

        Route::get('profile', [ProfileController::class, 'edit'])->name('profile.edit');
        Route::patch('profile', [ProfileController::class, 'update'])->name('profile.update');
        Route::patch('profile/locale', [ProfileController::class, 'updateLocale'])->name('profile.locale');
        Route::delete('profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

        Route::get('password', [PasswordController::class, 'edit'])->name('password.edit');

        Route::put('password', [PasswordController::class, 'update'])
            ->middleware('throttle:6,1')
            ->name('password.update');

        Route::get('appearance', function () {
            return Inertia::render('shared/settings/appearance');
        })->name('appearance.edit');

        Route::get('two-factor', [TwoFactorAuthenticationController::class, 'show'])
            ->name('two-factor.show');
    });
