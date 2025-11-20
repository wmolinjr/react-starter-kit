<?php

/*
|--------------------------------------------------------------------------
| Universal Settings Routes
|--------------------------------------------------------------------------
|
| These routes work in both central and tenant contexts.
| Middleware:
| - web: Web middleware group
| - universal: Flag for universal routes (required by Stancl\Tenancy\Features\UniversalRoutes)
| - InitializeTenancyByDomain: Initializes tenancy if accessed from tenant domain (nativo do Stancl)
| - auth: Requires authentication
|
| Behavior:
| - Central domain (localhost): Settings work without tenant context (for Super Admin)
| - Tenant domain (*.setor3.app): Settings work with tenant context (for regular users)
|
*/

use App\Http\Controllers\Universal\Settings\PasswordController;
use App\Http\Controllers\Universal\Settings\ProfileController;
use App\Http\Controllers\Universal\Settings\TwoFactorAuthenticationController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;

Route::middleware(['web', 'universal', InitializeTenancyByDomain::class, 'auth'])->group(function () {
    Route::redirect('settings', '/settings/profile');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('settings/password', [PasswordController::class, 'edit'])->name('user-password.edit');

    Route::put('settings/password', [PasswordController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('user-password.update');

    Route::get('settings/appearance', function () {
        return Inertia::render('universal/settings/appearance');
    })->name('appearance.edit');

    Route::get('settings/two-factor', [TwoFactorAuthenticationController::class, 'show'])
        ->name('two-factor.show');
});
