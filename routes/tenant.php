<?php

declare(strict_types=1);

use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromUnwantedDomains;
use App\Http\Middleware\Tenant\VerifyTenantAccess;
use App\Models\Tenant\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Stancl\Tenancy\Database\Models\ImpersonationToken;
use Stancl\Tenancy\Features\UserImpersonation;

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
        // User exists in TENANT database (already in tenant context)
        $user = User::find($impersonationToken->user_id);

        if (! $user) {
            $impersonationToken->delete();
            abort(403, __('User not found in this tenant.'));
        }

        // Login as the tenant user
        Auth::guard($impersonationToken->auth_guard ?? 'tenant')->login($user);

        // Set impersonation flag, ensure NOT in admin mode
        session()->put('tenancy_impersonating', true);
        session()->forget('tenancy_admin_mode');

        $redirectUrl = $impersonationToken->redirect_url;
        $impersonationToken->delete();

        return redirect($redirectUrl);
    })->name('impersonate.consume');

    // Stop impersonation and redirect back to central admin (Stancl/Tenancy v4 native)
    Route::post('/impersonate/stop', function () {
        // Clear admin mode session flags
        session()->forget('tenancy_impersonating');
        session()->forget('tenancy_admin_mode');

        $centralUrl = config('app.url') . '/admin/tenants';

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
                Route::get('/invoices', [\App\Http\Controllers\Tenant\Admin\BillingController::class, 'invoices'])->name('invoices');
                Route::post('/checkout', [\App\Http\Controllers\Tenant\Admin\BillingController::class, 'checkout'])->name('checkout');
                Route::get('/success', [\App\Http\Controllers\Tenant\Admin\BillingController::class, 'success'])->name('success');
                Route::get('/portal', [\App\Http\Controllers\Tenant\Admin\BillingController::class, 'portal'])->name('portal');
                Route::get('/invoice/{invoiceId}', [\App\Http\Controllers\Tenant\Admin\BillingController::class, 'invoice'])->name('invoice');
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
                Route::get('/language', [\App\Http\Controllers\Tenant\Admin\TenantSettingsController::class, 'language'])->name('language');
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
                    Route::post('/language', [\App\Http\Controllers\Tenant\Admin\TenantSettingsController::class, 'updateLanguage'])->name('language.update');
                    Route::post('/config', [\App\Http\Controllers\Tenant\Admin\TenantSettingsController::class, 'updateConfig'])->name('config.update');
                    Route::delete('/delete', [\App\Http\Controllers\Tenant\Admin\TenantSettingsController::class, 'destroy'])->name('destroy');
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
