<?php

declare(strict_types=1);

use App\Http\Controllers\Central\Admin\AddonCatalogController;
use App\Http\Controllers\Central\Admin\AddonManagementController;
use App\Http\Controllers\Central\Admin\BundleCatalogController;
use App\Http\Controllers\Central\Admin\DashboardController;
use App\Http\Controllers\Central\Admin\ImpersonationController;
use App\Http\Controllers\Central\Admin\PlanCatalogController;
use App\Http\Controllers\Central\Admin\RoleManagementController;
use App\Http\Controllers\Central\Admin\TenantManagementController;
use App\Http\Controllers\Central\Admin\UserManagementController;
use App\Http\Controllers\Central\Auth\AdminLoginController;
use App\Http\Controllers\Central\Auth\AdminLogoutController;
use App\Http\Controllers\Central\Panel\DashboardController as PanelDashboardController;
use App\Http\Controllers\Central\Panel\TenantAccessController;
use App\Http\Controllers\Shared\Settings\PasswordController;
use App\Http\Controllers\Shared\Settings\ProfileController;
use App\Http\Controllers\Shared\Settings\TwoFactorAuthenticationController;
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

        /*
        |----------------------------------------------------------------------
        | Fortify Route Redirects (Central Domain)
        |----------------------------------------------------------------------
        |
        | NOTE: Fortify routes (/login, /register) on central domain are now
        | handled by RedirectFortifyOnCentral middleware in config/fortify.php.
        |
        | The middleware intercepts these routes and redirects to admin login
        | since there are no tenant users in central database.
        |
        | @see App\Http\Middleware\Central\RedirectFortifyOnCentral
        |
        */

        /**
         * Sanctum CSRF Cookie Route for Central Context.
         *
         * Required for SPA authentication with central admin API.
         * Uses the same pattern as tenant routes for consistency.
         *
         * @see https://v4.tenancyforlaravel.com/integrations/sanctum/
         */
        Route::get('/sanctum/csrf-cookie', [\Laravel\Sanctum\Http\Controllers\CsrfCookieController::class, 'show'])
            ->middleware('web')
            ->name('sanctum.csrf-cookie');

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
        | Admin Authentication Routes (central.admin.auth.*)
        |----------------------------------------------------------------------
        |
        | TENANT-ONLY ARCHITECTURE (Option C):
        | - Uses 'central' guard for authentication (central database)
        | - Separate from tenant user authentication (Fortify)
        | - Admins can impersonate tenants via ImpersonationController
        |
        */

        // Guest routes (admin login)
        Route::middleware('guest:central')->prefix('admin')->name('admin.auth.')->group(function () {
            Route::get('/login', [AdminLoginController::class, 'create'])->name('login');
            Route::post('/login', [AdminLoginController::class, 'store'])->name('login.store');
        });

        // Authenticated routes (admin logout)
        Route::middleware('auth:central')->prefix('admin')->name('admin.auth.')->group(function () {
            Route::post('/logout', [AdminLogoutController::class, 'destroy'])->name('logout');
        });

        /*
        |----------------------------------------------------------------------
        | Admin Routes (central.admin.*)
        |----------------------------------------------------------------------
        */

        // Redirect /admin to /admin/dashboard
        Route::get('/admin', function () {
            return redirect()->route('central.admin.dashboard');
        })->middleware(['auth:central']);

        Route::middleware(['auth:central'])
            ->prefix('admin')
            ->name('admin.')
            ->group(function () {
                // Dashboard com lista de tenants
                Route::get('/dashboard', [DashboardController::class, 'dashboard'])->name('dashboard');

                // Impersonation - TENANT-ONLY ARCHITECTURE (Option C)
                // Stop impersonation route (shared)
                Route::post('/impersonate/stop', [ImpersonationController::class, 'stop'])->name('impersonate.stop');

                // Impersonation Routes
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

                /*
                |------------------------------------------------------------------
                | Admin User Settings Routes (central.admin.settings.*)
                |------------------------------------------------------------------
                |
                | Personal settings for central admin users (profile, password, etc.)
                | Uses shared controllers that work with auth()->user().
                |
                */

                Route::prefix('settings')
                    ->name('settings.')
                    ->group(function () {
                        Route::redirect('/', '/admin/settings/profile');

                        Route::get('profile', [ProfileController::class, 'edit'])->name('profile.edit');
                        Route::patch('profile', [ProfileController::class, 'update'])->name('profile.update');
                        Route::patch('profile/locale', [ProfileController::class, 'updateLocale'])->name('profile.locale');
                        Route::delete('profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

                        Route::get('password', [PasswordController::class, 'edit'])->name('password.edit');
                        Route::put('password', [PasswordController::class, 'update'])
                            ->middleware('throttle:6,1')
                            ->name('password.update');

                        Route::get('appearance', function () {
                            return Inertia::render('central/admin/user-settings/appearance');
                        })->name('appearance.edit');

                        Route::get('two-factor', [TwoFactorAuthenticationController::class, 'show'])->name('two-factor.show');
                    });
            });
    });
}
