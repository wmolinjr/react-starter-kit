<?php

use App\Http\Controllers\PageBlockController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\TenantController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Fortify\Features;

/*
|--------------------------------------------------------------------------
| Subdomain Routes (Multi-Tenancy)
|--------------------------------------------------------------------------
|
| These routes are accessed via subdomain (e.g., cliente.localhost)
| The IdentifyTenantByDomain middleware extracts the tenant from the subdomain
|
*/

Route::domain('{subdomain}.' . config('app.domain'))
    ->middleware(['auth', 'verified', App\Http\Middleware\IdentifyTenantByDomain::class, App\Http\Middleware\EnsureTenantAccess::class])
    ->group(function () {
        // Dashboard for tenant
        Route::get('/', function () {
            return Inertia::render('dashboard');
        })->name('tenant.dashboard');

        // Page Management Routes (tenant-scoped)
        Route::resource('pages', PageController::class)->names([
            'index' => 'tenant.pages.index',
            'create' => 'tenant.pages.create',
            'store' => 'tenant.pages.store',
            'show' => 'tenant.pages.show',
            'edit' => 'tenant.pages.edit',
            'update' => 'tenant.pages.update',
            'destroy' => 'tenant.pages.destroy',
        ]);

        // Additional page actions
        Route::post('pages/{page}/publish', [PageController::class, 'publish'])->name('tenant.pages.publish');
        Route::post('pages/{page}/unpublish', [PageController::class, 'unpublish'])->name('tenant.pages.unpublish');
        Route::post('pages/{page}/versions', [PageController::class, 'createVersion'])->name('tenant.pages.versions.create');

        // Page Block Routes (nested under pages)
        Route::post('pages/{page}/blocks', [PageBlockController::class, 'store'])->name('tenant.pages.blocks.store');
        Route::patch('pages/{page}/blocks/{block}', [PageBlockController::class, 'update'])->name('tenant.pages.blocks.update');
        Route::delete('pages/{page}/blocks/{block}', [PageBlockController::class, 'destroy'])->name('tenant.pages.blocks.destroy');
        Route::post('pages/{page}/blocks/{block}/move-up', [PageBlockController::class, 'moveUp'])->name('tenant.pages.blocks.move-up');
        Route::post('pages/{page}/blocks/{block}/move-down', [PageBlockController::class, 'moveDown'])->name('tenant.pages.blocks.move-down');
        Route::post('pages/{page}/blocks/{block}/duplicate', [PageBlockController::class, 'duplicate'])->name('tenant.pages.blocks.duplicate');
        Route::post('pages/{page}/blocks/reorder', [PageBlockController::class, 'reorder'])->name('tenant.pages.blocks.reorder');
    });

/*
|--------------------------------------------------------------------------
| Main Domain Routes (Central App)
|--------------------------------------------------------------------------
|
| These routes are for the central application (without subdomain)
| Used for user authentication, tenant management, etc.
|
*/

Route::get('/', function () {
    return Inertia::render('welcome', [
        'canRegister' => Features::enabled(Features::registration()),
    ]);
})->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', function () {
        return Inertia::render('dashboard');
    })->name('dashboard');

    // Tenant Management Routes
    Route::resource('tenants', TenantController::class)->parameters([
        'tenants' => 'slug',
    ]);

    // Switch current tenant
    Route::post('tenants/{tenant}/switch', function (string $slug) {
        $tenant = \App\Models\Tenant::where('slug', $slug)->firstOrFail();

        if (!auth()->user()->hasAccessToTenant($tenant)) {
            abort(403, 'You do not have access to this workspace');
        }

        auth()->user()->switchTenant($tenant);

        return back()->with('success', 'Switched to ' . $tenant->name);
    })->name('tenants.switch');

    // Page Management Routes
    Route::resource('pages', PageController::class);

    // Additional page actions
    Route::post('pages/{page}/publish', [PageController::class, 'publish'])->name('pages.publish');
    Route::post('pages/{page}/unpublish', [PageController::class, 'unpublish'])->name('pages.unpublish');
    Route::post('pages/{page}/versions', [PageController::class, 'createVersion'])->name('pages.versions.create');

    // Page Block Routes (nested under pages)
    Route::post('pages/{page}/blocks', [PageBlockController::class, 'store'])->name('pages.blocks.store');
    Route::patch('pages/{page}/blocks/{block}', [PageBlockController::class, 'update'])->name('pages.blocks.update');
    Route::delete('pages/{page}/blocks/{block}', [PageBlockController::class, 'destroy'])->name('pages.blocks.destroy');
    Route::post('pages/{page}/blocks/{block}/move-up', [PageBlockController::class, 'moveUp'])->name('pages.blocks.move-up');
    Route::post('pages/{page}/blocks/{block}/move-down', [PageBlockController::class, 'moveDown'])->name('pages.blocks.move-down');
    Route::post('pages/{page}/blocks/{block}/duplicate', [PageBlockController::class, 'duplicate'])->name('pages.blocks.duplicate');
    Route::post('pages/{page}/blocks/reorder', [PageBlockController::class, 'reorder'])->name('pages.blocks.reorder');
});

require __DIR__.'/settings.php';
