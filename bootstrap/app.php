<?php

use App\Http\Middleware\AddSecurityHeaders;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\PreventActionsWhileImpersonating;
use App\Http\Middleware\VerifyTenantAccess;
use App\Models\Project;
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
        web: __DIR__.'/../routes/web.php',
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

            // Admin routes - managed by domain scoping in routes/admin.php
            require base_path('routes/admin.php');

            // Universal routes (work in both central and tenant contexts)
            require base_path('routes/settings.php');

            // Tenant routes - managed by TenancyServiceProvider->mapRoutes()
            // No need to load here as it's already loaded by the service provider

            // Route Model Binding tenant-aware
            // Garante que models com BelongsToTenant trait sejam filtrados pelo tenant atual
            Route::bind('project', function (string $value) {
                if (tenancy()->initialized) {
                    return Project::where('id', $value)
                        ->where('tenant_id', tenant('id'))
                        ->firstOrFail();
                }

                return Project::findOrFail($value);
            });
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        // CRITICAL: Initialize tenancy BEFORE StartSession middleware
        // Ensures Redis session prefixing works correctly for tenant-scoped sessions
        // Uses priority() to guarantee execution order
        // @see https://v4.tenancyforlaravel.com/version-4 (Early Identification Middleware)
        $middleware->priority([
            \App\Http\Middleware\InitializeTenancyByDomainExceptTests::class,
            \Illuminate\Session\Middleware\StartSession::class,
        ]);

        $middleware->prepend(\App\Http\Middleware\InitializeTenancyByDomainExceptTests::class);

        $middleware->web(append: [
            AddSecurityHeaders::class,
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        // Universal routes middleware group (for routes that work in both central and tenant contexts)
        // This is required by Stancl\Tenancy\Features\UniversalRoutes
        $middleware->group('universal', []);

        // Middleware aliases
        $middleware->alias([
            // Custom middleware
            'tenant.access' => VerifyTenantAccess::class,
            'prevent.impersonation' => PreventActionsWhileImpersonating::class,

            // Plan middleware (feature and limit enforcement)
            'feature' => \App\Http\Middleware\CheckFeature::class,
            'limit' => \App\Http\Middleware\CheckLimit::class,

            // Spatie Permission middleware (for permission-based authorization)
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
