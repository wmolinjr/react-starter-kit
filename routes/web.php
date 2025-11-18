<?php

use App\Http\Controllers\PageController;
use App\Http\Controllers\TenantController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Fortify\Features;

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
});

require __DIR__.'/settings.php';
