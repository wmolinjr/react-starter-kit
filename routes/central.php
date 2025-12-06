<?php

declare(strict_types=1);

use App\Http\Controllers\Central\Admin\AddonCatalogController;
use App\Http\Controllers\Central\Admin\AddonManagementController;
use App\Http\Controllers\Central\Admin\BundleCatalogController;
use App\Http\Controllers\Central\Admin\DashboardController;
use App\Http\Controllers\Central\Admin\ImpersonationController;
use App\Http\Controllers\Central\Admin\PlanCatalogController;
use App\Http\Controllers\Central\Panel\DashboardController as PanelDashboardController;
use App\Http\Controllers\Central\Panel\TenantAccessController;
use App\Http\Controllers\Central\Admin\RoleManagementController;
use App\Http\Controllers\Central\Admin\TenantManagementController;
use App\Http\Controllers\Central\Admin\UserManagementController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Fortify\Features;

/*
|--------------------------------------------------------------------------
| Central Routes
|--------------------------------------------------------------------------
|
| Rotas da aplicação central (landing page, dashboard, painel admin).
| Estas rotas são explicitamente limitadas aos central_domains configurados.
|
| Pattern: Route::domain() para cada domínio central
|
| Sections:
| - Public routes (central.*)
| - Admin routes (central.admin.*)
|
*/

foreach (config('tenancy.identification.central_domains') as $domain) {
    Route::domain($domain)->middleware('web')->name('central.')->group(function () {

        /*
        |----------------------------------------------------------------------
        | Public Central Routes (central.*)
        |----------------------------------------------------------------------
        */

        // Landing page
        Route::get('/', function () {
            return Inertia::render('central/welcome', [
                'canRegister' => Features::enabled(Features::registration()),
            ]);
        })->name('home');

        // Fortify home redirect (role-based routing after auth)
        // - Users with central roles → Admin Dashboard
        // - Regular users → User Panel (manage tenants)
        Route::get('/home', function () {
            $user = auth()->user();

            if ($user && $user->roles()->exists()) {
                return redirect()->route('central.admin.dashboard');
            }

            return redirect()->route('central.panel.dashboard');
        })->middleware('auth')->name('fortify.home');

        /*
        |----------------------------------------------------------------------
        | Panel Routes (central.panel.*)
        |----------------------------------------------------------------------
        */

        Route::middleware(['auth', 'verified'])
            ->prefix('painel')
            ->name('panel.')
            ->group(function () {
                Route::get('/', PanelDashboardController::class)->name('dashboard');

                // Seamless login: redirect user to their tenant (generates token)
                Route::get('/access/{tenant}', [TenantAccessController::class, 'redirect'])->name('access');
            });

        /*
        |----------------------------------------------------------------------
        | Admin Routes (central.admin.*)
        |----------------------------------------------------------------------
        */

        // Redirect /admin to /admin/dashboard
        Route::get('/admin', function () {
            return redirect()->route('central.admin.dashboard');
        })->middleware(['auth:admin']);

        Route::middleware(['auth:admin'])
            ->prefix('admin')
            ->name('admin.')
            ->group(function () {
                // Dashboard com lista de tenants
                Route::get('/dashboard', [DashboardController::class, 'dashboard'])->name('dashboard');

                // Impersonation
                // TENANT-ONLY ARCHITECTURE (Option C): New routes for advanced impersonation
                Route::prefix('impersonate')->name('impersonate.')->group(function () {
                    // Legacy routes (kept for backward compatibility)
                    Route::post('/tenant/{tenant}', [ImpersonationController::class, 'start'])->name('start');
                    Route::post('/tenant/{tenant}/user/{user}', [ImpersonationController::class, 'start'])->name('start.user');
                    Route::post('/stop', [ImpersonationController::class, 'stop'])->name('stop');
                });

                // Advanced Impersonation Routes (Option C)
                Route::prefix('tenants/{tenant}/impersonate')->name('tenants.impersonate.')->group(function () {
                    // User selection page - lists users from tenant database
                    Route::get('/', [ImpersonationController::class, 'index'])->name('index');

                    // Admin Mode - enter tenant without specific user
                    Route::post('/admin-mode', [ImpersonationController::class, 'adminMode'])->name('admin-mode');

                    // Impersonate specific user from tenant
                    Route::post('/as/{userId}', [ImpersonationController::class, 'asUser'])->name('as-user');
                });

                // Add-on Management
                Route::prefix('addons')->name('addons.')->group(function () {
                    Route::get('/', [AddonManagementController::class, 'index'])->name('index');
                    Route::get('/revenue', [AddonManagementController::class, 'revenue'])->name('revenue');
                    Route::post('/tenant/{tenant}/grant', [AddonManagementController::class, 'grantAddon'])->name('grant');
                    Route::post('/{addon}/revoke', [AddonManagementController::class, 'revokeAddon'])->name('revoke');
                });

                // Add-on Catalog (database-driven)
                Route::prefix('catalog')->name('catalog.')->group(function () {
                    Route::get('/', [AddonCatalogController::class, 'index'])->name('index');
                    Route::get('/create', [AddonCatalogController::class, 'create'])->name('create');
                    Route::post('/', [AddonCatalogController::class, 'store'])->name('store');
                    Route::get('/{addon}/edit', [AddonCatalogController::class, 'edit'])->name('edit');
                    Route::put('/{addon}', [AddonCatalogController::class, 'update'])->name('update');
                    Route::delete('/{addon}', [AddonCatalogController::class, 'destroy'])->name('destroy');
                    Route::post('/{addon}/sync', [AddonCatalogController::class, 'sync'])->name('sync');
                    Route::post('/sync-all', [AddonCatalogController::class, 'syncAll'])->name('sync-all');
                });

                // Bundle Catalog (addon packages)
                Route::prefix('bundles')->name('bundles.')->group(function () {
                    Route::get('/', [BundleCatalogController::class, 'index'])->name('index');
                    Route::get('/create', [BundleCatalogController::class, 'create'])->name('create');
                    Route::post('/', [BundleCatalogController::class, 'store'])->name('store');
                    Route::get('/{bundle}/edit', [BundleCatalogController::class, 'edit'])->name('edit');
                    Route::put('/{bundle}', [BundleCatalogController::class, 'update'])->name('update');
                    Route::delete('/{bundle}', [BundleCatalogController::class, 'destroy'])->name('destroy');
                    Route::post('/{bundle}/sync', [BundleCatalogController::class, 'sync'])->name('sync');
                    Route::post('/sync-all', [BundleCatalogController::class, 'syncAll'])->name('sync-all');
                });

                // Plan Catalog (database-driven)
                Route::prefix('plans')->name('plans.')->group(function () {
                    Route::get('/', [PlanCatalogController::class, 'index'])->name('index');
                    Route::get('/create', [PlanCatalogController::class, 'create'])->name('create');
                    Route::post('/', [PlanCatalogController::class, 'store'])->name('store');
                    Route::get('/{plan}/edit', [PlanCatalogController::class, 'edit'])->name('edit');
                    Route::put('/{plan}', [PlanCatalogController::class, 'update'])->name('update');
                    Route::delete('/{plan}', [PlanCatalogController::class, 'destroy'])->name('destroy');
                    Route::post('/{plan}/sync', [PlanCatalogController::class, 'sync'])->name('sync');
                    Route::post('/sync-all', [PlanCatalogController::class, 'syncAll'])->name('sync-all');
                });

                // Central Roles Management
                Route::prefix('roles')->name('roles.')->group(function () {
                    Route::get('/', [RoleManagementController::class, 'index'])->name('index');
                    Route::get('/create', [RoleManagementController::class, 'create'])->name('create');
                    Route::post('/', [RoleManagementController::class, 'store'])->name('store');
                    Route::get('/{role}', [RoleManagementController::class, 'show'])->name('show');
                    Route::get('/{role}/edit', [RoleManagementController::class, 'edit'])->name('edit');
                    Route::put('/{role}', [RoleManagementController::class, 'update'])->name('update');
                    Route::delete('/{role}', [RoleManagementController::class, 'destroy'])->name('destroy');
                });

                // User Management
                Route::prefix('users')->name('users.')->group(function () {
                    Route::get('/', [UserManagementController::class, 'index'])->name('index');
                    Route::get('/{user}', [UserManagementController::class, 'show'])->name('show');
                    Route::delete('/{user}', [UserManagementController::class, 'destroy'])->name('destroy');
                });

                // Tenant Management
                Route::prefix('tenants')->name('tenants.')->group(function () {
                    Route::get('/', [TenantManagementController::class, 'index'])->name('index');
                    Route::get('/{tenant}', [TenantManagementController::class, 'show'])->name('show');
                    Route::get('/{tenant}/edit', [TenantManagementController::class, 'edit'])->name('edit');
                    Route::put('/{tenant}', [TenantManagementController::class, 'update'])->name('update');
                    Route::delete('/{tenant}', [TenantManagementController::class, 'destroy'])->name('destroy');
                });
            });
    });
}
