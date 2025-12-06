<?php

declare(strict_types=1);

namespace App\Providers;

use App\Enums\TenantConfigKey;
use App\Jobs\Central\SeedTenantDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Stancl\JobPipeline\JobPipeline;
use Stancl\Tenancy\Bootstrappers\MailConfigBootstrapper;
use Stancl\Tenancy\Bootstrappers\TenantConfigBootstrapper;
use Stancl\Tenancy\Events;
use Stancl\Tenancy\Jobs;
use Stancl\Tenancy\Listeners;
use Stancl\Tenancy\Middleware;

/**
 * TenancyServiceProvider
 *
 * MULTI-DATABASE TENANCY:
 * - Each tenant gets a dedicated PostgreSQL database
 * - DatabaseTenancyBootstrapper switches to tenant database on initialization
 * - Tenant database is created, migrated, and seeded on tenant creation
 *
 * TESTING:
 * - Tests use a fixed `testing_tenant` database (TENANCY_TESTING_DATABASE env var)
 * - Bootstrapper still switches connections, but no dynamic DB creation
 */
class TenancyServiceProvider extends ServiceProvider
{
    public static string $controllerNamespace = '';

    /**
     * Check if dynamic database creation should be enabled.
     *
     * In tests, we use a fixed testing_tenant database instead of creating
     * dynamic databases per tenant. This is controlled by TENANCY_TESTING_DATABASE.
     */
    protected function shouldCreateDynamicDatabases(): bool
    {
        return !env('TENANCY_TESTING_DATABASE');
    }

    public function events()
    {
        // Database jobs only run when dynamic database creation is enabled
        // In tests with TENANCY_TESTING_DATABASE, we skip dynamic DB creation
        $tenantCreatedListeners = [];
        $tenantDeletedListeners = [];

        if ($this->shouldCreateDynamicDatabases()) {
            $tenantCreatedListeners = [
                JobPipeline::make([
                    Jobs\CreateDatabase::class,
                    Jobs\MigrateDatabase::class,
                    SeedTenantDatabase::class,
                ])->send(function (Events\TenantCreated $event) {
                    return $event->tenant;
                })->shouldBeQueued(false), // Sync for MVP, make true for production
            ];

            $tenantDeletedListeners = [
                JobPipeline::make([
                    Jobs\DeleteDatabase::class,
                ])->send(function (Events\TenantDeleted $event) {
                    return $event->tenant;
                })->shouldBeQueued(false),
            ];
        }

        // Bootstrappers always run (switches to tenant database connection)
        $tenancyInitializedListeners = [Listeners\BootstrapTenancy::class];
        $tenancyEndedListeners = [Listeners\RevertToCentralContext::class];

        return [
            // Tenant events
            Events\CreatingTenant::class => [],
            Events\TenantCreated::class => $tenantCreatedListeners,
            Events\SavingTenant::class => [],
            Events\TenantSaved::class => [],
            Events\UpdatingTenant::class => [],
            Events\TenantUpdated::class => [],
            Events\DeletingTenant::class => [],
            Events\TenantDeleted::class => $tenantDeletedListeners,

            // Domain events
            Events\CreatingDomain::class => [],
            Events\DomainCreated::class => [],
            Events\SavingDomain::class => [],
            Events\DomainSaved::class => [],
            Events\UpdatingDomain::class => [],
            Events\DomainUpdated::class => [],
            Events\DeletingDomain::class => [],
            Events\DomainDeleted::class => [],

            // Database events
            Events\DatabaseCreated::class => [],
            Events\DatabaseMigrated::class => [],
            Events\DatabaseSeeded::class => [],
            Events\DatabaseRolledBack::class => [],
            Events\DatabaseDeleted::class => [],

            // Tenancy events
            Events\InitializingTenancy::class => [],
            Events\TenancyInitialized::class => $tenancyInitializedListeners,
            Events\EndingTenancy::class => [],
            Events\TenancyEnded::class => $tenancyEndedListeners,

            Events\BootstrappingTenancy::class => [],
            Events\TenancyBootstrapped::class => [
                // Configure Spatie Permission cache key per tenant database
                function (Events\TenancyBootstrapped $event) {
                    $tenantKey = $event->tenancy->tenant->getTenantKey();
                    $permissionRegistrar = app(\Spatie\Permission\PermissionRegistrar::class);
                    $permissionRegistrar->cacheKey = 'spatie.permission.cache.tenant.' . $tenantKey;
                },
            ],
            Events\RevertingToCentralContext::class => [],
            Events\RevertedToCentralContext::class => [
                // Reset Spatie Permission cache key when leaving tenant context
                function (Events\RevertedToCentralContext $event) {
                    $permissionRegistrar = app(\Spatie\Permission\PermissionRegistrar::class);
                    $permissionRegistrar->cacheKey = 'spatie.permission.cache';
                },
            ],

            // Resource syncing
            Events\SyncedResourceSaved::class => [
                Listeners\UpdateSyncedResource::class,
            ],
            Events\SyncedResourceChangedInForeignDatabase::class => [],
        ];
    }

    public function register()
    {
        //
    }

    public function boot()
    {
        $this->bootEvents();
        $this->mapRoutes();

        $this->makeTenancyMiddlewareHighestPriority();
        $this->configureTestingDatabase();

        // Configure TenantConfigBootstrapper to map tenant settings to Laravel config keys
        // This enables automatic config overrides when tenancy is initialized
        // Example: tenant.settings['config.locale'] -> config('app.locale')
        TenantConfigBootstrapper::$storageToConfigMap = TenantConfigKey::toStorageConfigMap();

        // Configure MailConfigBootstrapper for custom SMTP per tenant (Enterprise feature)
        // Requires enabling MailConfigBootstrapper in config/tenancy.php bootstrappers array
        // Note: Default mailer 'smtp' already has preset mappings in MailConfigBootstrapper::$mapPresets
        // We only add smtp_encryption here since it's not in the default preset
        // See docs/CUSTOM-SMTP.md for full implementation guide
        MailConfigBootstrapper::$credentialsMap = [
            'mail.mailers.smtp.encryption' => 'smtp_encryption',
        ];

        // Configure InitializeTenancyByDomain to handle failures gracefully
        // This covers: central domains, already initialized tenancy, or unknown domains
        Middleware\InitializeTenancyByDomain::$onFail = function ($exception, $request, $next) {
            // If tenancy is already initialized (e.g., manually in tests), continue
            if (tenancy()->initialized) {
                return $next($request);
            }

            // For central domains or unknown domains, just continue without tenant context
            return $next($request);
        };

        // Configure ScopeSessions to handle session hijacking attempts gracefully
        // v4 feature: Prevents a session from one tenant being used on another tenant
        Middleware\ScopeSessions::$onFail = function ($request) {
            // Clear the compromised session
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            // Redirect to login with error message
            return redirect()->route('login')
                ->with('error', __('Your session has expired. Please log in again.'));
        };
    }

    protected function bootEvents()
    {
        foreach ($this->events() as $event => $listeners) {
            foreach ($listeners as $listener) {
                if ($listener instanceof JobPipeline) {
                    $listener = $listener->toListener();
                }

                Event::listen($event, $listener);
            }
        }
    }

    protected function mapRoutes()
    {
        $this->app->booted(function () {
            if (file_exists(base_path('routes/tenant.php'))) {
                Route::namespace(static::$controllerNamespace)
                    ->group(base_path('routes/tenant.php'));
            }
        });
    }

    protected function makeTenancyMiddlewareHighestPriority()
    {
        $tenancyMiddleware = [
            Middleware\PreventAccessFromUnwantedDomains::class,
            Middleware\InitializeTenancyByDomain::class,
            Middleware\InitializeTenancyBySubdomain::class,
            Middleware\InitializeTenancyByDomainOrSubdomain::class,
            Middleware\InitializeTenancyByPath::class,
            Middleware\InitializeTenancyByRequestData::class,
        ];

        foreach (array_reverse($tenancyMiddleware) as $middleware) {
            $this->app[\Illuminate\Contracts\Http\Kernel::class]->prependToMiddlewarePriority($middleware);
        }
    }

    /**
     * Configure testing database for multi-database tenancy tests.
     *
     * When TENANCY_TESTING_DATABASE is set, all tenants use a fixed
     * `testing_tenant` database instead of dynamic `tenant_{id}` databases.
     * This allows running multi-database tests without creating databases
     * dynamically for each test tenant.
     *
     * @see https://v4.tenancyforlaravel.com/customizing-databases
     */
    protected function configureTestingDatabase(): void
    {
        $testingDb = env('TENANCY_TESTING_DATABASE');

        if (! $testingDb) {
            return;
        }

        // Configure template_tenant_connection to use testing_tenant
        // This ensures DatabaseTenancyBootstrapper creates connections with correct config
        config([
            'tenancy.database.template_tenant_connection' => 'testing_tenant',
        ]);

        // Also configure the tenant connection directly for immediate use
        config([
            'database.connections.tenant' => array_merge(
                config('database.connections.testing_tenant', []),
                ['database' => $testingDb]
            ),
        ]);
    }
}
