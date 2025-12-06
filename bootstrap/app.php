<?php

use App\Http\Middleware\AddSecurityHeaders;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SetLocale;
use App\Http\Middleware\VerifyTenantAccess;
use App\Models\Tenant\Project;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            // Configure global rate limiting for API and general routes
            // Implements tenant-aware rate limiting to prevent abuse

            // API rate limiting: tenant-aware
            RateLimiter::for('api', function (Request $request) {
                $tenantId = tenancy()->initialized ? tenant('id') : 'global';
                return Limit::perMinute(60)->by($tenantId.':'.$request->user()?->id ?: $request->ip());
            });

            // General web rate limiting
            RateLimiter::for('global', function (Request $request) {
                return Limit::perMinute(100)->by($request->user()?->id ?: $request->ip());
            });

            // Tenant-specific actions rate limiting
            RateLimiter::for('tenant-actions', function (Request $request) {
                if (! tenancy()->initialized) {
                    return Limit::none();
                }
                $tenantId = tenant('id');
                $userId = $request->user()?->id;
                return [
                    Limit::perMinute(30)->by($tenantId.':'.$userId),
                    Limit::perMinute(100)->by($tenantId),
                ];
            });

            // File upload rate limiting
            RateLimiter::for('uploads', function (Request $request) {
                $tenantId = tenancy()->initialized ? tenant('id') : 'global';
                return [
                    Limit::perMinute(10)->by($tenantId.':'.$request->user()?->id ?: $request->ip()),
                    Limit::perHour(50)->by($tenantId.':'.$request->user()?->id ?: $request->ip()),
                ];
            });

            // Central routes (admin + panel + auth) - managed by domain scoping
            require base_path('routes/central.php');

            // Shared routes (work in both central and tenant contexts)
            require base_path('routes/shared.php');

            // Webhook routes (Stripe, etc.) - no CSRF, no auth
            Route::middleware('api')->group(base_path('routes/webhooks.php'));

            // Tenant routes - managed by TenancyServiceProvider->mapRoutes()
            // No need to load here as it's already loaded by the service provider

            // Route Model Binding for Project
            // MULTI-DATABASE TENANCY: Project lives in tenant database, no tenant_id column needed.
            // Isolation is guaranteed at the database level (each tenant has its own DB).
            // Simply use findOrFail - the correct database is already selected via tenancy.
            Route::bind('project', function (string $value) {
                return Project::findOrFail($value);
            });
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        // Configure redirect for unauthenticated users
        // Admin guard redirects to /admin/login, others to /login
        $middleware->redirectGuestsTo(function (Request $request) {
            if ($request->routeIs('central.admin.*')) {
                return route('central.admin.auth.login');
            }
            return route('login');
        });

        // Configure redirect for authenticated users (trying to access login pages)
        // Check which guard the user is authenticated with to redirect to correct dashboard
        $middleware->redirectUsersTo(function (Request $request) {
            // If authenticated as central admin, redirect to central admin dashboard
            if (auth('central')->check()) {
                return route('central.admin.dashboard');
            }
            // If authenticated as regular user, redirect to tenant dashboard
            if (auth()->check()) {
                return route('tenant.admin.dashboard');
            }
            // Fallback (should not reach here)
            return '/';
        });

        // CRITICAL: Initialize tenancy BEFORE StartSession middleware
        // Ensures Redis session prefixing works correctly for tenant-scoped sessions
        // Uses priority() to guarantee execution order
        // @see https://v4.tenancyforlaravel.com/version-4 (Early Identification Middleware)
        $middleware->priority([
            \Stancl\Tenancy\Middleware\InitializeTenancyByDomain::class,
            \Illuminate\Session\Middleware\StartSession::class,
        ]);

        $middleware->prepend(\Stancl\Tenancy\Middleware\InitializeTenancyByDomain::class);

        $middleware->web(append: [
            AddSecurityHeaders::class,
            HandleAppearance::class,
            SetLocale::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        // Universal routes middleware group (for routes that work in both central and tenant contexts)
        // This is required by Stancl\Tenancy\Features\UniversalRoutes
        $middleware->group('universal', []);

        // Middleware aliases
        $middleware->alias([
            // Custom middleware (unified tenant access + impersonation restrictions)
            'tenant.access' => VerifyTenantAccess::class,

            // Plan middleware (unified feature and limit enforcement)
            // Usage: plan:feature,customRoles | plan:limit,users
            'plan' => \App\Http\Middleware\CheckPlan::class,

            // Spatie Permission middleware (for permission-based authorization)
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,

            // Stancl/Tenancy v4: Prevent session hijacking between tenants
            'scope.sessions' => \Stancl\Tenancy\Middleware\ScopeSessions::class,

            // Admin Mode: Allow access when impersonating without specific user (Option C)
            'admin.mode' => \App\Http\Middleware\AllowAdminMode::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
