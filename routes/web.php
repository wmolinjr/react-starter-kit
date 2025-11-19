<?php

use App\Http\Controllers\DomainController;
use App\Http\Controllers\MediaController;
use App\Http\Controllers\PageBlockController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\TenantController;
use App\Http\Controllers\TenantInvitationController;
use App\Http\Controllers\TenantMemberController;
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

        // Media Library Routes (tenant-scoped)
        Route::get('media', [MediaController::class, 'index'])->name('tenant.media.index');
        Route::post('media', [MediaController::class, 'store'])->name('tenant.media.store');
        Route::get('media/collections', [MediaController::class, 'collections'])->name('tenant.media.collections');
        Route::post('media/bulk-delete', [MediaController::class, 'bulkDelete'])->name('tenant.media.bulk-delete');
        Route::get('media/{media}', [MediaController::class, 'show'])->name('tenant.media.show');
        Route::patch('media/{media}', [MediaController::class, 'update'])->name('tenant.media.update');
        Route::delete('media/{media}', [MediaController::class, 'destroy'])->name('tenant.media.destroy');
        Route::get('media/{media}/download', [MediaController::class, 'download'])->name('tenant.media.download');
        Route::get('media/{media}/url/{conversion?}', [MediaController::class, 'url'])->name('tenant.media.url');
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

// Public invitation acceptance route
Route::get('invitations/accept/{token}', [TenantInvitationController::class, 'accept'])
    ->name('invitations.accept')
    ->middleware('auth');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', function () {
        return Inertia::render('dashboard');
    })->name('dashboard');

    // Tenant Management Routes
    Route::resource('tenants', TenantController::class)->parameters([
        'tenants' => 'slug',
    ]);

    // Domain Management Routes (tenant-scoped)
    Route::prefix('tenants/{slug}')->name('tenants.')->group(function () {
        Route::get('domains', [DomainController::class, 'index'])->name('domains.index');
        Route::post('domains', [DomainController::class, 'store'])->name('domains.store');
        Route::patch('domains/{domain}', [DomainController::class, 'update'])->name('domains.update');
        Route::post('domains/{domain}/verify', [DomainController::class, 'verify'])->name('domains.verify');
        Route::delete('domains/{domain}', [DomainController::class, 'destroy'])->name('domains.destroy');

        // Branding Management
        Route::put('branding', [TenantController::class, 'updateBranding'])->name('branding.update');

        // Invitation Management
        Route::get('invitations', [TenantInvitationController::class, 'index'])->name('invitations.index');
        Route::post('invitations', [TenantInvitationController::class, 'store'])->name('invitations.store');
        Route::post('invitations/{invitation}/resend', [TenantInvitationController::class, 'resend'])->name('invitations.resend');
        Route::delete('invitations/{invitation}', [TenantInvitationController::class, 'destroy'])->name('invitations.destroy');

        // Member Management
        Route::get('members', [TenantMemberController::class, 'index'])->name('members.index');
        Route::patch('members/{user}', [TenantMemberController::class, 'update'])->name('members.update');
        Route::delete('members/{user}', [TenantMemberController::class, 'destroy'])->name('members.destroy');
        Route::post('members/{user}/transfer-ownership', [TenantMemberController::class, 'transferOwnership'])->name('members.transfer-ownership');
    });

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

    // Media Library Routes
    Route::get('media', [MediaController::class, 'index'])->name('media.index');
    Route::post('media', [MediaController::class, 'store'])->name('media.store');
    Route::get('media/collections', [MediaController::class, 'collections'])->name('media.collections');
    Route::post('media/bulk-delete', [MediaController::class, 'bulkDelete'])->name('media.bulk-delete');
    Route::get('media/{media}', [MediaController::class, 'show'])->name('media.show');
    Route::patch('media/{media}', [MediaController::class, 'update'])->name('media.update');
    Route::delete('media/{media}', [MediaController::class, 'destroy'])->name('media.destroy');
    Route::get('media/{media}/download', [MediaController::class, 'download'])->name('media.download');
    Route::get('media/{media}/url/{conversion?}', [MediaController::class, 'url'])->name('media.url');
});

require __DIR__.'/settings.php';
