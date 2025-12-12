<?php

declare(strict_types=1);

use App\Http\Controllers\Central\Admin\AddonCatalogController;
use App\Http\Controllers\Central\Admin\AddonManagementController;
use App\Http\Controllers\Central\Admin\AuditLogController;
use App\Http\Controllers\Central\Admin\BundleCatalogController;
use App\Http\Controllers\Central\Admin\DashboardController;
use App\Http\Controllers\Central\Admin\FederationConflictController;
use App\Http\Controllers\Central\Admin\FederationGroupController;
use App\Http\Controllers\Central\Admin\ImpersonationController;
use App\Http\Controllers\Central\Admin\PaymentController;
use App\Http\Controllers\Central\Admin\PaymentSettingsController;
use App\Http\Controllers\Central\Admin\PlanCatalogController;
use App\Http\Controllers\Central\Admin\RoleManagementController;
use App\Http\Controllers\Central\Admin\TenantManagementController;
use App\Http\Controllers\Central\Admin\UserManagementController;
use App\Http\Controllers\Central\Auth\AdminLoginController;
use App\Http\Controllers\Central\Auth\AdminLogoutController;
use App\Http\Controllers\Central\Auth\ConfirmPasswordController;
use App\Http\Controllers\Central\Auth\ForgotPasswordController;
use App\Http\Controllers\Central\Auth\ResetPasswordController;
use App\Http\Controllers\Central\Auth\TwoFactorChallengeController;
use App\Http\Controllers\Central\Auth\VerifyEmailController;
use App\Http\Controllers\Central\PricingController;
use App\Http\Controllers\Central\Settings\PasswordController;
use App\Http\Controllers\Central\Settings\ProfileController;
use App\Http\Controllers\Central\Settings\TwoFactorAuthenticationController;
use App\Http\Controllers\Central\Settings\TwoFactorController;
use App\Http\Controllers\Central\SignupController;
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

        /*
        |----------------------------------------------------------------------
        | Public Pricing & Signup Routes (central.pricing.*, central.signup.*)
        |----------------------------------------------------------------------
        |
        | WIX-like signup flow:
        | 1. /pricing - Public pricing page
        | 2. /signup - Multi-step wizard (account, workspace, payment)
        | 3. /signup/success - Success page after payment
        |
        */

        // Public pricing page
        Route::get('/pricing', [PricingController::class, 'index'])->name('pricing');

        // Signup wizard routes
        Route::prefix('signup')->name('signup.')->group(function () {
            // Show wizard (with optional plan slug and signup ID in URL)
            // Format: /signup/{plan}/{signup?} e.g. /signup/professional/019b135d-24c8-7015-8c93-abae4d3049a0
            Route::get('/{plan}/{signup?}', [SignupController::class, 'create'])
                ->where('plan', '^(?!account|success|validate)[a-z0-9-]+$')
                ->name('index');

            // Step 1: Account creation
            Route::post('/account', [SignupController::class, 'storeAccount'])->name('account.store');

            // Step 2: Workspace setup
            Route::patch('/{signup}/workspace', [SignupController::class, 'updateWorkspace'])->name('workspace.update');

            // Step 3: Payment processing
            Route::post('/{signup}/payment', [SignupController::class, 'processPayment'])->name('payment.process');

            // Status polling
            Route::get('/{signup}/status', [SignupController::class, 'checkStatus'])->name('status');

            // Refresh PIX QR code
            Route::post('/{signup}/refresh-pix', [SignupController::class, 'refreshPix'])->name('refresh-pix');

            // Success page
            Route::get('/success', [SignupController::class, 'success'])->name('success');

            // Validation endpoints (AJAX)
            Route::post('/validate/email', [SignupController::class, 'validateEmail'])->name('validate.email');
            Route::post('/validate/slug', [SignupController::class, 'validateSlug'])->name('validate.slug');
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

        // Guest routes (admin login, password reset, 2FA challenge)
        Route::middleware('guest:central')->prefix('admin')->name('admin.auth.')->group(function () {
            // Login
            Route::get('/login', [AdminLoginController::class, 'create'])->name('login');
            Route::post('/login', [AdminLoginController::class, 'store'])->name('login.store');

            // Forgot Password
            Route::get('/forgot-password', [ForgotPasswordController::class, 'create'])->name('password.request');
            Route::post('/forgot-password', [ForgotPasswordController::class, 'store'])->name('password.email');

            // Reset Password
            Route::get('/reset-password/{token}', [ResetPasswordController::class, 'create'])->name('password.reset');
            Route::post('/reset-password', [ResetPasswordController::class, 'store'])->name('password.update');

            // Two-Factor Challenge (during login)
            Route::get('/two-factor-challenge', [TwoFactorChallengeController::class, 'create'])->name('two-factor.challenge');
            Route::post('/two-factor-challenge', [TwoFactorChallengeController::class, 'store'])->name('two-factor.challenge.store');
        });

        // Authenticated routes (admin logout, password confirmation, email verification)
        Route::middleware('auth:central')->prefix('admin')->name('admin.auth.')->group(function () {
            Route::post('/logout', [AdminLogoutController::class, 'destroy'])->name('logout');

            // Password confirmation (separate from Fortify's password.confirm for tenant users)
            Route::get('/confirm-password', [ConfirmPasswordController::class, 'show'])->name('confirm-password');
            Route::post('/confirm-password', [ConfirmPasswordController::class, 'store'])->name('confirm-password.store');

            // Email Verification
            Route::get('/email/verify', [VerifyEmailController::class, 'notice'])->name('verification.notice');
            Route::get('/email/verify/{id}/{hash}', [VerifyEmailController::class, 'verify'])
                ->middleware(['signed', 'throttle:6,1'])
                ->name('verification.verify');
            Route::post('/email/verification-notification', [VerifyEmailController::class, 'send'])
                ->middleware('throttle:6,1')
                ->name('verification.send');
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

                // Federation Groups Management
                Route::prefix('federation')->name('federation.')->group(function () {
                    Route::get('/', [FederationGroupController::class, 'index'])->name('index');
                    Route::get('/create', [FederationGroupController::class, 'create'])->name('create');
                    Route::post('/', [FederationGroupController::class, 'store'])->name('store');
                    Route::get('/{group}', [FederationGroupController::class, 'show'])->name('show');
                    Route::get('/{group}/edit', [FederationGroupController::class, 'edit'])->name('edit');
                    Route::put('/{group}', [FederationGroupController::class, 'update'])->name('update');
                    Route::delete('/{group}', [FederationGroupController::class, 'destroy'])->name('destroy');

                    // Tenant management within group
                    Route::post('/{group}/tenants', [FederationGroupController::class, 'addTenant'])->name('tenants.add');
                    Route::post('/{group}/tenants/{tenant}/toggle-sync', [FederationGroupController::class, 'toggleTenantSync'])->name('tenants.toggle-sync');
                    Route::delete('/{group}/tenants/{tenant}', [FederationGroupController::class, 'removeTenant'])->name('tenants.remove');

                    // Change master tenant
                    Route::post('/{group}/change-master', [FederationGroupController::class, 'changeMaster'])->name('change-master');

                    // User management within group
                    Route::get('/{group}/users/{user}', [FederationGroupController::class, 'showUser'])->name('users.show');
                    Route::post('/{group}/users/{user}/sync', [FederationGroupController::class, 'syncUser'])->name('users.sync');

                    // Retry failed syncs
                    Route::post('/{group}/retry-sync', [FederationGroupController::class, 'retrySync'])->name('retry-sync');

                    // Conflicts
                    Route::get('/{group}/conflicts', [FederationConflictController::class, 'index'])->name('conflicts.index');
                    Route::get('/{group}/conflicts/{conflict}', [FederationConflictController::class, 'show'])->name('conflicts.show');
                    Route::post('/{group}/conflicts/{conflict}/resolve', [FederationConflictController::class, 'resolve'])->name('conflicts.resolve');
                    Route::post('/{group}/conflicts/{conflict}/dismiss', [FederationConflictController::class, 'dismiss'])->name('conflicts.dismiss');
                });

                // Audit Log Management
                Route::prefix('audit')->name('audit.')->group(function () {
                    Route::get('/', [AuditLogController::class, 'index'])->name('index');
                    Route::get('/export', [AuditLogController::class, 'export'])->name('export');
                    Route::get('/{activity}', [AuditLogController::class, 'show'])->name('show');
                });

                // Payment Management
                Route::prefix('payments')->name('payments.')->group(function () {
                    Route::get('/', [PaymentController::class, 'index'])->name('index');
                    Route::get('/export', [PaymentController::class, 'export'])->name('export');
                    Route::get('/{payment}', [PaymentController::class, 'show'])->name('show');
                    Route::post('/{payment}/refund', [PaymentController::class, 'refund'])->name('refund');
                });

                // Payment Gateway Settings
                Route::prefix('payment-settings')->name('payment-settings.')->group(function () {
                    Route::get('/', [PaymentSettingsController::class, 'index'])->name('index');
                    Route::put('/{gateway}', [PaymentSettingsController::class, 'update'])->name('update');
                    Route::post('/{gateway}/toggle-sandbox', [PaymentSettingsController::class, 'toggleSandbox'])->name('toggle-sandbox');
                    Route::post('/{gateway}/test', [PaymentSettingsController::class, 'test'])->name('test');
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

                        // Two-Factor Authentication routes
                        // Show page doesn't require password confirmation
                        Route::get('two-factor', [TwoFactorAuthenticationController::class, 'show'])->name('two-factor.show');

                        // All 2FA actions require password confirmation (same as Fortify for tenant users)
                        Route::middleware('central.password.confirm')->group(function () {
                            Route::post('two-factor/enable', [TwoFactorController::class, 'enable'])->name('two-factor.enable');
                            Route::post('two-factor/confirm', [TwoFactorController::class, 'confirm'])->name('two-factor.confirm');
                            Route::delete('two-factor/disable', [TwoFactorController::class, 'disable'])->name('two-factor.disable');
                            Route::get('two-factor/qr-code', [TwoFactorController::class, 'qrCode'])->name('two-factor.qr-code');
                            Route::get('two-factor/recovery-codes', [TwoFactorController::class, 'recoveryCodes'])->name('two-factor.recovery-codes');
                            Route::post('two-factor/recovery-codes', [TwoFactorController::class, 'regenerateRecoveryCodes'])->name('two-factor.recovery-codes.store');
                        });
                    });
            });
    });
}
