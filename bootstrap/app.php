<?php

use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\VerifyTenantAccess;
use App\Models\Project;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            // Tenant routes
            Route::middleware('web')
                ->group(base_path('routes/tenant.php'));

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

        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        // Alias para middleware customizado
        $middleware->alias([
            'tenant.access' => VerifyTenantAccess::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
