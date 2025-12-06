<?php

/*
|--------------------------------------------------------------------------
| Shared Settings Routes
|--------------------------------------------------------------------------
|
| These routes work in both central and tenant contexts.
| Middleware:
| - web: Web middleware group
| - universal: Flag for universal routes (required by Stancl\Tenancy\Features\UniversalRoutes)
| - InitializeTenancyByDomain: Initializes tenancy if accessed from tenant domain (skips central via $onFail)
| - auth: Requires authentication
|
| Behavior:
| - Central domain (localhost): Settings work without tenant context (for Super Admin)
| - Tenant domain (*.setor3.app): Settings work with tenant context (for regular users)
|
*/

use App\Http\Controllers\Shared\Settings\PasswordController;
use App\Http\Controllers\Shared\Settings\ProfileController;
use App\Http\Controllers\Shared\Settings\TwoFactorAuthenticationController;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware(['web', 'universal', InitializeTenancyByDomain::class, 'auth'])
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
