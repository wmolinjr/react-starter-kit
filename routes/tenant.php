<?php

declare(strict_types=1);

use App\Http\Controllers\Tenant\Auth\ConfirmPasswordController;
use App\Http\Controllers\Tenant\Auth\ForgotPasswordController;
use App\Http\Controllers\Tenant\Auth\LoginController;
use App\Http\Controllers\Tenant\Auth\LogoutController;
use App\Http\Controllers\Tenant\Auth\RegisterController;
use App\Http\Controllers\Tenant\Auth\ResetPasswordController;
use App\Http\Controllers\Tenant\Auth\TwoFactorChallengeController;
use App\Http\Controllers\Tenant\Auth\VerifyEmailController;
use App\Http\Controllers\Tenant\Settings\PasswordController;
use App\Http\Controllers\Tenant\Settings\ProfileController;
use App\Http\Controllers\Tenant\Settings\TwoFactorAuthenticationController;
use App\Http\Controllers\Tenant\Settings\TwoFactorController;
use App\Http\Middleware\Tenant\VerifyTenantAccess;
use App\Models\Tenant\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Stancl\Tenancy\Database\Models\ImpersonationToken;
use Stancl\Tenancy\Features\UserImpersonation;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromUnwantedDomains;

/*
|--------------------------------------------------------------------------
| Tenant Routes (*.setor3.app)
|--------------------------------------------------------------------------
|
| Rotas tenant-scoped. Todas as rotas aqui têm acesso ao tenant context.
|
| Sections:
| - Public routes (tenant.impersonate.*, tenant.invitation.*)
| - Admin routes (tenant.admin.dashboard, tenant.admin.projects.*, tenant.admin.team.*, etc.)
| - Settings routes (tenant.admin.settings.*)
| - API routes (tenant.api.*)
|
*/

Route::middleware([
    'web',
    InitializeTenancyByDomain::class,
    PreventAccessFromUnwantedDomains::class,
    'scope.sessions', // v4: Prevent session hijacking between tenants
])->name('tenant.')->group(function () {

    /*
    |----------------------------------------------------------------------
    | Public Tenant Routes (tenant.*)
    |----------------------------------------------------------------------
    */

    /**
     * CSRF Cookie route for Sanctum SPA authentication.
     *
     * TENANCY v4 INTEGRATION:
     * Sanctum's default /sanctum/csrf-cookie route doesn't work with tenancy
     * because it runs outside tenant context. This route provides the same
     * functionality but with proper tenancy middleware.
     *
     * @see https://v4.tenancyforlaravel.com/integrations/sanctum/
     */
    Route::get('/sanctum/csrf-cookie', [\Laravel\Sanctum\Http\Controllers\CsrfCookieController::class, 'show'])
        ->middleware('web')
        ->name('sanctum.csrf-cookie');

    /**
     * Impersonation token consumption with Admin Mode support.
     *
     * TENANT-ONLY ARCHITECTURE (Option C):
     * Supports two scenarios:
     * 1. Admin Mode: user_id = null, sets session('tenancy_admin_mode', true)
     * 2. User Impersonation: user_id = UUID, logs in as that tenant user
     *
     * Session flags set:
     * - 'tenancy_impersonating': Always set (both modes)
     * - 'tenancy_admin_mode': Only set for Admin Mode
     */
    Route::get('/impersonate/{token}', function (string $token) {
        // Find the token in central database
        $impersonationToken = ImpersonationToken::where('token', $token)->first();

        if (! $impersonationToken) {
            abort(403, __('Invalid or expired impersonation token.'));
        }

        // SCENARIO 1: Admin Mode (user_id is null)
        if ($impersonationToken->user_id === null) {
            // Set session flags for Admin Mode
            session()->put('tenancy_impersonating', true);
            session()->put('tenancy_admin_mode', true);

            $redirectUrl = $impersonationToken->redirect_url;
            $impersonationToken->delete();

            return redirect($redirectUrl);
        }

        // SCENARIO 2: Impersonate specific tenant user
        // Use native Stancl/Tenancy makeResponse() as per best practices (Context7)
        // This handles token validation, auth login, and session management correctly
        session()->forget('tenancy_admin_mode');

        return UserImpersonation::makeResponse($token);
    })->name('impersonate.consume');

    // Stop impersonation and redirect back to central admin (Stancl/Tenancy v4 native)
    Route::post('/impersonate/stop', function () {
        // Clear admin mode session flags
        session()->forget('tenancy_impersonating');
        session()->forget('tenancy_admin_mode');

        $centralUrl = config('app.url').'/admin/tenants';

        return Inertia::location($centralUrl);
    })->middleware('admin.mode')->name('impersonate.stop');

    /**
     * Seamless login: Consume token and login WITHOUT impersonation flag.
     *
     * This is for tenant members accessing their OWN tenant from central domain.
     * Unlike impersonation, this does NOT set the `tenancy_impersonating` session flag,
     * so the user won't see the impersonation banner.
     */
    Route::get('/auth/seamless/{token}', function (string $token) {
        // Find the token
        $impersonationToken = ImpersonationToken::where('token', $token)->first();

        if (! $impersonationToken) {
            abort(403, __('Invalid or expired access token.'));
        }

        // Get the user (from central database)
        $user = User::find($impersonationToken->user_id);

        if (! $user) {
            $impersonationToken->delete();
            abort(403, __('User not found.'));
        }

        // Login the user (WITHOUT setting impersonation flag)
        Auth::guard($impersonationToken->auth_guard)->login($user, $impersonationToken->remember);

        // IMPORTANT: Ensure no impersonation flag is set
        // This is the key difference from regular impersonation
        session()->forget('tenancy_impersonating');

        // Get redirect URL and delete token
        $redirectUrl = $impersonationToken->redirect_url;
        $impersonationToken->delete();

        // Redirect to the intended URL
        return redirect($redirectUrl);
    })->name('auth.seamless');

    // Accept team invitation (public route)
    Route::get('/accept-invitation', function () {
        return Inertia::render('tenant/accept-invitation', [
            'token' => request()->query('token'),
        ]);
    })->name('invitation.show');

    Route::post('/accept-invitation', [\App\Http\Controllers\Tenant\Admin\TeamController::class, 'acceptInvitation'])
        ->middleware('auth')
        ->name('invitation.accept');

    // Redirect root to dashboard
    Route::get('/', function () {
        return redirect()->route('tenant.admin.dashboard');
    });

    /*
    |----------------------------------------------------------------------
    | Tenant Authentication Routes (tenant.admin.auth.*)
    |----------------------------------------------------------------------
    |
    | All auth routes use /admin prefix to match central domain structure.
    | This provides consistent URLs across both domains:
    | - Central: localhost/admin/login
    | - Tenant:  tenant.localhost/admin/login
    |
    */

    // Guest routes (login, register, password reset, 2FA challenge)
    Route::middleware('guest:tenant')->prefix('admin')->name('admin.auth.')->group(function () {
        // Login
        Route::get('/login', [LoginController::class, 'create'])->name('login');
        Route::post('/login', [LoginController::class, 'store'])->name('login.store');

        // Registration (if enabled via Features::registration())
        Route::get('/register', [RegisterController::class, 'create'])->name('register');
        Route::post('/register', [RegisterController::class, 'store'])->name('register.store');

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

    // Authenticated routes (logout, password confirmation, email verification)
    Route::middleware('auth:tenant')->prefix('admin')->name('admin.auth.')->group(function () {
        Route::post('/logout', [LogoutController::class, 'destroy'])->name('logout');

        // Password confirmation
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
    | Panel Routes (*)
    |----------------------------------------------------------------------
    */

    // Redirect /admin to /admin/dashboard
    Route::get('/admin', function () {
        return redirect()->route('tenant.admin.dashboard');
    })->middleware(['admin.mode', VerifyTenantAccess::class]);

    // NOTE: 'verified' middleware removed - Admin Mode has no user to verify
    // Email verification is enforced per-route or via VerifyTenantAccess for regular users
    Route::middleware(['admin.mode', VerifyTenantAccess::class])
        ->prefix('admin')
        ->name('admin.')
        ->group(function () {
            // Dashboard
            Route::get('/dashboard', \App\Http\Controllers\Tenant\Admin\DashboardController::class)->name('dashboard');

            // Projects (CRUD + File Upload)
            Route::prefix('projects')->name('projects.')->group(function () {
                Route::get('/', [\App\Http\Controllers\Tenant\Admin\ProjectController::class, 'index'])->name('index');
                Route::get('/create', [\App\Http\Controllers\Tenant\Admin\ProjectController::class, 'create'])->name('create');
                Route::get('/{project}', [\App\Http\Controllers\Tenant\Admin\ProjectController::class, 'show'])->name('show');
                Route::get('/{project}/edit', [\App\Http\Controllers\Tenant\Admin\ProjectController::class, 'edit'])->name('edit');

                Route::middleware('throttle:tenant-actions')->group(function () {
                    Route::post('/', [\App\Http\Controllers\Tenant\Admin\ProjectController::class, 'store'])->name('store');
                    Route::patch('/{project}', [\App\Http\Controllers\Tenant\Admin\ProjectController::class, 'update'])->name('update');
                    Route::delete('/{project}', [\App\Http\Controllers\Tenant\Admin\ProjectController::class, 'destroy'])->name('destroy');
                });

                Route::middleware('throttle:uploads')->post('/{project}/media', [\App\Http\Controllers\Tenant\Admin\ProjectController::class, 'uploadFile'])->name('media.upload');
                Route::get('/{project}/media/{media}', [\App\Http\Controllers\Tenant\Admin\ProjectController::class, 'downloadFile'])->name('media.download');
                Route::middleware('throttle:tenant-actions')->delete('/{project}/media/{media}', [\App\Http\Controllers\Tenant\Admin\ProjectController::class, 'deleteFile'])->name('media.delete');
            });

            // Team Management
            Route::prefix('team')->name('team.')->group(function () {
                Route::get('/', [\App\Http\Controllers\Tenant\Admin\TeamController::class, 'index'])->name('index');
                Route::get('/activity', [\App\Http\Controllers\Tenant\Admin\TeamActivityController::class, 'index'])->name('activity');

                Route::middleware('throttle:tenant-actions')->group(function () {
                    Route::post('/invite', [\App\Http\Controllers\Tenant\Admin\TeamController::class, 'invite'])->name('invite');
                    Route::patch('/{user}/role', [\App\Http\Controllers\Tenant\Admin\TeamController::class, 'updateRole'])->name('update-role');
                    Route::delete('/{user}', [\App\Http\Controllers\Tenant\Admin\TeamController::class, 'remove'])->name('remove');
                });
            });

            // Billing (Laravel Cashier)
            Route::prefix('billing')->name('billing.')->group(function () {
                Route::get('/', [\App\Http\Controllers\Tenant\Admin\BillingController::class, 'index'])->name('index');
                Route::get('/plans', [\App\Http\Controllers\Tenant\Admin\BillingController::class, 'plans'])->name('plans');
                Route::get('/bundles', [\App\Http\Controllers\Tenant\Admin\BillingController::class, 'bundles'])->name('bundles');
                Route::get('/invoices', [\App\Http\Controllers\Tenant\Admin\BillingController::class, 'invoices'])->name('invoices');
                Route::post('/checkout', [\App\Http\Controllers\Tenant\Admin\BillingController::class, 'checkout'])->name('checkout');
                Route::get('/success', [\App\Http\Controllers\Tenant\Admin\BillingController::class, 'success'])->name('success');
                Route::get('/portal', [\App\Http\Controllers\Tenant\Admin\BillingController::class, 'portal'])->name('portal');
                Route::get('/invoice/{invoiceId}', [\App\Http\Controllers\Tenant\Admin\BillingController::class, 'invoice'])->name('invoice');
                // Cart checkout routes
                Route::post('/cart-checkout', [\App\Http\Controllers\Tenant\Admin\BillingController::class, 'cartCheckout'])->name('cart-checkout');
                Route::get('/cart-success', [\App\Http\Controllers\Tenant\Admin\BillingController::class, 'cartSuccess'])->name('cart-success');
                // Async payment status (PIX/Boleto)
                Route::post('/cart-payment-status', [\App\Http\Controllers\Tenant\Admin\BillingController::class, 'checkCartPaymentStatus'])->name('cart-payment-status');
                Route::post('/pix-refresh', [\App\Http\Controllers\Tenant\Admin\BillingController::class, 'refreshPixQrCode'])->name('pix-refresh');
                // Asaas card payment completion
                Route::post('/asaas-card-payment', [\App\Http\Controllers\Tenant\Admin\BillingController::class, 'completeAsaasCardPayment'])->name('asaas-card-payment');

                // Subscription management
                Route::get('/subscription', [\App\Http\Controllers\Tenant\Admin\BillingController::class, 'subscription'])->name('subscription');
                Route::post('/subscription/cancel', [\App\Http\Controllers\Tenant\Admin\BillingController::class, 'cancelSubscription'])->name('subscription.cancel');
                Route::post('/subscription/resume', [\App\Http\Controllers\Tenant\Admin\BillingController::class, 'resumeSubscription'])->name('subscription.resume');
                Route::post('/subscription/pause', [\App\Http\Controllers\Tenant\Admin\BillingController::class, 'pauseSubscription'])->name('subscription.pause');
                Route::post('/subscription/unpause', [\App\Http\Controllers\Tenant\Admin\BillingController::class, 'unpauseSubscription'])->name('subscription.unpause');
                Route::post('/subscription/change-plan', [\App\Http\Controllers\Tenant\Admin\BillingController::class, 'changePlan'])->name('subscription.change-plan');
            });

            // Add-ons
            Route::prefix('addons')->name('addons.')->group(function () {
                Route::get('/', [\App\Http\Controllers\Tenant\Admin\AddonController::class, 'index'])->name('index');
                Route::get('/usage', [\App\Http\Controllers\Tenant\Admin\AddonController::class, 'usage'])->name('usage');
                Route::get('/success', [\App\Http\Controllers\Tenant\Admin\AddonController::class, 'success'])->name('success');
                Route::middleware('throttle:tenant-actions')->group(function () {
                    Route::post('/purchase', [\App\Http\Controllers\Tenant\Admin\AddonController::class, 'purchase'])->name('purchase');
                    Route::post('/{addon}/cancel', [\App\Http\Controllers\Tenant\Admin\AddonController::class, 'cancel'])->name('cancel');
                    Route::patch('/{addon}', [\App\Http\Controllers\Tenant\Admin\AddonController::class, 'update'])->name('update');
                });
            });

            // Audit Log (Enterprise feature - middleware in controller)
            Route::prefix('audit')->name('audit.')->group(function () {
                Route::get('/', [\App\Http\Controllers\Tenant\Admin\AuditLogController::class, 'index'])->name('index');
                Route::get('/export', [\App\Http\Controllers\Tenant\Admin\AuditLogController::class, 'export'])->name('export');
                Route::get('/{activity}', [\App\Http\Controllers\Tenant\Admin\AuditLogController::class, 'show'])->name('show');
            });

            /*
            |------------------------------------------------------------------
            | Settings Routes (settings.*)
            |------------------------------------------------------------------
            */

            Route::prefix('tenant-settings')->name('settings.')->group(function () {
                Route::get('/', [\App\Http\Controllers\Tenant\Admin\TenantSettingsController::class, 'index'])->name('index');
                Route::get('/branding', [\App\Http\Controllers\Tenant\Admin\TenantSettingsController::class, 'branding'])->name('branding');
                Route::get('/domains', [\App\Http\Controllers\Tenant\Admin\TenantSettingsController::class, 'domains'])->name('domains');
                Route::get('/config', [\App\Http\Controllers\Tenant\Admin\TenantSettingsController::class, 'config'])->name('config');
                Route::get('/api-tokens', [\App\Http\Controllers\Tenant\Admin\TenantSettingsController::class, 'apiTokens'])->name('api-tokens');
                Route::get('/danger', [\App\Http\Controllers\Tenant\Admin\TenantSettingsController::class, 'danger'])->name('danger');

                // Custom Roles Management (Pro+ feature - middleware in controller)
                Route::prefix('roles')->name('roles.')->group(function () {
                    Route::get('/', [\App\Http\Controllers\Tenant\Admin\TenantRoleController::class, 'index'])->name('index');
                    Route::get('/create', [\App\Http\Controllers\Tenant\Admin\TenantRoleController::class, 'create'])->name('create');
                    Route::post('/', [\App\Http\Controllers\Tenant\Admin\TenantRoleController::class, 'store'])->name('store');
                    Route::get('/{role}', [\App\Http\Controllers\Tenant\Admin\TenantRoleController::class, 'show'])->name('show');
                    Route::get('/{role}/edit', [\App\Http\Controllers\Tenant\Admin\TenantRoleController::class, 'edit'])->name('edit');
                    Route::put('/{role}', [\App\Http\Controllers\Tenant\Admin\TenantRoleController::class, 'update'])->name('update');
                    Route::delete('/{role}', [\App\Http\Controllers\Tenant\Admin\TenantRoleController::class, 'destroy'])->name('destroy');
                });

                // Write operations (rate limited)
                Route::middleware('throttle:tenant-actions')->group(function () {
                    Route::post('/branding', [\App\Http\Controllers\Tenant\Admin\TenantSettingsController::class, 'updateBranding'])->name('branding.update');
                    Route::post('/domains', [\App\Http\Controllers\Tenant\Admin\TenantSettingsController::class, 'addDomain'])->name('domains.add');
                    Route::delete('/domains/{domainId}', [\App\Http\Controllers\Tenant\Admin\TenantSettingsController::class, 'removeDomain'])->name('domains.remove');
                    Route::post('/features', [\App\Http\Controllers\Tenant\Admin\TenantSettingsController::class, 'updateFeatures'])->name('features.update');
                    Route::post('/notifications', [\App\Http\Controllers\Tenant\Admin\TenantSettingsController::class, 'updateNotifications'])->name('notifications.update');
                    Route::post('/config', [\App\Http\Controllers\Tenant\Admin\TenantSettingsController::class, 'updateConfig'])->name('config.update');
                    Route::delete('/delete', [\App\Http\Controllers\Tenant\Admin\TenantSettingsController::class, 'destroy'])->name('destroy');
                });

                // Federation Settings
                Route::prefix('federation')->name('federation.')->group(function () {
                    Route::get('/', [\App\Http\Controllers\Tenant\Admin\FederationController::class, 'index'])->name('index');
                    Route::get('/users/{user}', [\App\Http\Controllers\Tenant\Admin\FederationController::class, 'show'])->name('show');
                    Route::post('/users/federate', [\App\Http\Controllers\Tenant\Admin\FederationController::class, 'federateUser'])->name('users.federate');
                    Route::post('/users/federate-all', [\App\Http\Controllers\Tenant\Admin\FederationController::class, 'federateAll'])->name('users.federate-all');
                    Route::post('/users/federate-bulk', [\App\Http\Controllers\Tenant\Admin\FederationController::class, 'federateBulk'])->name('users.federate-bulk');
                    Route::delete('/users/{user}/unfederate', [\App\Http\Controllers\Tenant\Admin\FederationController::class, 'unfederateUser'])->name('users.unfederate');
                    Route::post('/users/{user}/sync', [\App\Http\Controllers\Tenant\Admin\FederationController::class, 'syncUser'])->name('users.sync');
                });
            });

            /*
            |------------------------------------------------------------------
            | User Settings Routes (tenant.admin.user-settings.*)
            |------------------------------------------------------------------
            |
            | Personal settings for tenant users (profile, password, etc.)
            | Uses shared controllers that work with auth()->user().
            |
            | NOTE: Uses 'user-settings' prefix to avoid conflict with
            | tenant organization settings (tenant.admin.settings.*).
            |
            */

            Route::prefix('settings')
                ->name('user-settings.')
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
                        return Inertia::render('tenant/admin/user-settings/appearance');
                    })->name('appearance.edit');

                    Route::get('two-factor', [TwoFactorAuthenticationController::class, 'show'])
                        ->name('two-factor.show');

                    // Two-Factor Authentication API routes
                    // All 2FA actions require password confirmation
                    Route::middleware('password.confirm')->prefix('two-factor')->name('two-factor.')->group(function () {
                        Route::post('enable', [TwoFactorController::class, 'enable'])->name('enable');
                        Route::post('confirm', [TwoFactorController::class, 'confirm'])->name('confirm');
                        Route::delete('disable', [TwoFactorController::class, 'disable'])->name('disable');
                        Route::get('qr-code', [TwoFactorController::class, 'qrCode'])->name('qr-code');
                        Route::get('secret-key', [TwoFactorController::class, 'secretKey'])->name('secret-key');
                        Route::get('recovery-codes', [TwoFactorController::class, 'recoveryCodes'])->name('recovery-codes');
                        Route::post('recovery-codes', [TwoFactorController::class, 'regenerateRecoveryCodes'])->name('recovery-codes.store');
                    });
                });
        });

    /*
    |----------------------------------------------------------------------
    | API Routes (tenant.api.*)
    |----------------------------------------------------------------------
    */

    Route::middleware(['auth:sanctum', 'throttle:api'])
        ->prefix('api')
        ->name('api.')
        ->group(function () {
            Route::get('/tokens', [\App\Http\Controllers\Tenant\ApiTokenController::class, 'index'])->name('tokens.index');
            Route::post('/tokens', [\App\Http\Controllers\Tenant\ApiTokenController::class, 'store'])->name('tokens.store');
            Route::patch('/tokens/{tokenId}', [\App\Http\Controllers\Tenant\ApiTokenController::class, 'update'])->name('tokens.update');
            Route::delete('/tokens/{tokenId}', [\App\Http\Controllers\Tenant\ApiTokenController::class, 'destroy'])->name('tokens.destroy');

            Route::apiResource('projects', \App\Http\Controllers\Tenant\Api\ProjectController::class);
        });
});
