<?php

declare(strict_types=1);

use App\Models\Central\Domain;
use App\Models\Central\Tenant;
use Stancl\Tenancy\Database\Models\ImpersonationToken;
use Stancl\Tenancy\Middleware;
use Stancl\Tenancy\Resolvers;
use Stancl\Tenancy\Bootstrappers;
use Stancl\Tenancy\Enums\RouteMode;

/**
 * Tenancy for Laravel v4.
 *
 * MULTI-DATABASE TENANCY:
 * - Each tenant gets a dedicated PostgreSQL database
 * - DatabaseTenancyBootstrapper switches to tenant database on initialization
 * - Physical isolation for LGPD/HIPAA compliance
 */
return [
    /**
     * Configuration for the models used by Tenancy.
     */
    'models' => [
        'tenant' => Tenant::class, // Using custom App\Models\Tenant
        'domain' => Domain::class,
        'impersonation_token' => ImpersonationToken::class,

        /**
         * Name of the column used to relate models to tenants.
         */
        'tenant_key_column' => 'tenant_id',

        /**
         * Used for generating tenant IDs.
         * Set to null to use auto-increment (our Tenant model uses HasUuids trait).
         */
        'id_generator' => null,
    ],

    'identification' => [
        /**
         * The list of domains hosting your central app.
         *
         * Only relevant if you're using the domain or subdomain identification middleware.
         */
        'central_domains' => [
            'localhost',
        ],

        /**
         * The default middleware used for tenant identification.
         */
        'default_middleware' => Middleware\InitializeTenancyByDomain::class,

        /**
         * All of the identification middleware used by the package.
         */
        'middleware' => [
            Middleware\InitializeTenancyByDomain::class,
            Middleware\InitializeTenancyBySubdomain::class,
            Middleware\InitializeTenancyByDomainOrSubdomain::class,
            Middleware\InitializeTenancyByPath::class,
            Middleware\InitializeTenancyByRequestData::class,
            Middleware\InitializeTenancyByOriginHeader::class,
        ],

        /**
         * Identification middleware tenancy recognizes as domain identification middleware.
         */
        'domain_identification_middleware' => [
            Middleware\InitializeTenancyByDomain::class,
            Middleware\InitializeTenancyBySubdomain::class,
            Middleware\InitializeTenancyByDomainOrSubdomain::class,
        ],

        /**
         * Identification middleware tenancy recognizes as path identification middleware.
         */
        'path_identification_middleware' => [
            Middleware\InitializeTenancyByPath::class,
        ],

        /**
         * Tenant resolvers used by the package.
         */
        'resolvers' => [
            Resolvers\DomainTenantResolver::class => [
                'cache' => true,              // v4 feature: cache tenant resolution
                'cache_ttl' => 3600,          // 1 hour
                'cache_store' => 'redis',     // Use Redis for cache
            ],
            Resolvers\PathTenantResolver::class => [
                'tenant_parameter_name' => 'tenant',
                'tenant_model_column' => null,
                'tenant_route_name_prefix' => 'tenant.',
                'allowed_extra_model_columns' => [],
                'cache' => false,
                'cache_ttl' => 3600,
                'cache_store' => null,
            ],
            Resolvers\RequestDataTenantResolver::class => [
                'header' => 'X-Tenant',
                'cookie' => 'tenant',
                'query_parameter' => 'tenant',
                'tenant_model_column' => null,
                'cache' => false,
                'cache_ttl' => 3600,
                'cache_store' => null,
            ],
        ],
    ],

    /**
     * Tenancy bootstrappers are executed when tenancy is initialized.
     * Their responsibility is making Laravel features tenant-aware.
     *
     * MULTI-DATABASE TENANCY: DatabaseTenancyBootstrapper is ENABLED
     * Each tenant has its own dedicated database for LGPD/HIPAA compliance.
     *
     * TESTING: Uses a fixed `testing_tenant` database (TENANCY_TESTING_DATABASE env var)
     * Bootstrapper still runs to switch connections, but no dynamic DB creation.
     */
    'bootstrappers' => [
        // DatabaseTenancyBootstrapper: ENABLED for multi-database tenancy (physical isolation)
        Bootstrappers\DatabaseTenancyBootstrapper::class,
        Bootstrappers\CacheTenancyBootstrapper::class,
        Bootstrappers\FilesystemTenancyBootstrapper::class,
        Bootstrappers\QueueTenancyBootstrapper::class,
        Bootstrappers\RedisTenancyBootstrapper::class,

        // Adds support for the database session driver
        Bootstrappers\DatabaseSessionBootstrapper::class,

        // TenantConfigBootstrapper: Override Laravel config with tenant-specific values
        // Maps tenant.settings['config.*'] to Laravel config keys (app.locale, mail.from.*, etc.)
        Bootstrappers\TenantConfigBootstrapper::class,

        // MailConfigBootstrapper: Custom SMTP per tenant (Enterprise feature)
        // Uncomment to enable custom SMTP. Requires smtp_host, smtp_port, smtp_username,
        // smtp_password, smtp_encryption in tenant settings. See docs/CUSTOM-SMTP.md
        // Bootstrappers\MailConfigBootstrapper::class,

        // Spatie Permission: Limpa cache ao trocar de tenant
        App\Bootstrappers\SpatiePermissionsBootstrapper::class,

        // FortifyRouteBootstrapper for tenant-aware Fortify redirects (v4 feature)
        Bootstrappers\Integrations\FortifyRouteBootstrapper::class,
    ],

    /**
     * Database tenancy config. Used by DatabaseTenancyBootstrapper.
     *
     * MULTI-DATABASE TENANCY:
     * - Each tenant has a dedicated PostgreSQL database
     * - Physical isolation for LGPD/HIPAA compliance
     * - Database names: tenant_{id} (e.g., tenant_1, tenant_2)
     */
    'database' => [
        'central_connection' => env('DB_CONNECTION', 'central'),

        /**
         * Template connection for tenant databases.
         * IMPORTANT: This MUST be different from 'tenant' because
         * purgeTenantConnection() unsets 'database.connections.tenant'
         * before creating a new connection from the template.
         */
        'template_tenant_connection' => 'tenant_template',

        /**
         * The name of the temporary connection used for creating and deleting tenant databases.
         */
        'tenant_host_connection_name' => 'tenant_host_connection',

        /**
         * Tenant database names are created like this:
         * prefix + tenant_id + suffix.
         *
         * Example: tenant_1, tenant_2, tenant_3
         */
        'prefix' => 'tenant_',
        'suffix' => '',

        /**
         * TenantDatabaseManagers are classes that handle the creation & deletion of tenant databases.
         */
        'managers' => [
            'sqlite' => Stancl\Tenancy\Database\TenantDatabaseManagers\SQLiteDatabaseManager::class,
            'mysql' => Stancl\Tenancy\Database\TenantDatabaseManagers\MySQLDatabaseManager::class,
            'mariadb' => Stancl\Tenancy\Database\TenantDatabaseManagers\MySQLDatabaseManager::class,
            'pgsql' => Stancl\Tenancy\Database\TenantDatabaseManagers\PostgreSQLDatabaseManager::class,
            'sqlsrv' => Stancl\Tenancy\Database\TenantDatabaseManagers\MicrosoftSQLDatabaseManager::class,
        ],

        /*
         * Drop tenant databases when `php artisan migrate:fresh` is used.
         */
        'drop_tenant_databases_on_migrate_fresh' => false,
    ],

    /**
     * RLS (Row Level Security) config. Requires PostgreSQL with single-database tenancy.
     * Not used in multi-database tenancy mode.
     */
    'rls' => [
        'manager' => Stancl\Tenancy\RLS\PolicyManagers\TableRLSManager::class,
        'user' => [
            'username' => env('TENANCY_RLS_USERNAME'),
            'password' => env('TENANCY_RLS_PASSWORD'),
        ],
        'session_variable_name' => 'my.current_tenant',
    ],

    /**
     * Cache tenancy config. Used by CacheTenancyBootstrapper.
     *
     * IMPORTANT: scope_sessions = true enables automatic session scoping for cache-based
     * session drivers (Redis, Memcached, DynamoDB, APC). This works together with
     * RedisTenancyBootstrapper to ensure complete session isolation between tenants.
     */
    'cache' => [
        'prefix' => 'tenant_%tenant%_', // %tenant% replaced by the tenant key
        'stores' => [
            env('CACHE_STORE'),
        ],

        /*
         * Should sessions be tenant-aware (only used when your session driver is cache-based).
         */
        'scope_sessions' => in_array(env('SESSION_DRIVER'), ['redis', 'memcached', 'dynamodb', 'apc'], true),

        'tag_base' => 'tenant', // For CacheTagsBootstrapper
    ],

    /**
     * Filesystem tenancy config. Used by FilesystemTenancyBootstrapper.
     */
    'filesystem' => [
        /**
         * Each disk listed in the 'disks' array will be suffixed by the suffix_base, followed by the tenant_id.
         */
        'suffix_base' => 'tenant',
        'disks' => [
            'local',
            'public',
            // 's3',
        ],

        /**
         * Use this for local disks.
         */
        'root_override' => [
            // Disks whose roots should be overridden after storage_path() is suffixed.
            'local' => '%storage_path%/app/',
            'public' => '%storage_path%/app/public/',
        ],

        /**
         * Tenant-aware Storage::disk()->url() can be enabled for specific local disks.
         */
        'url_override' => [
            'public' => 'public-%tenant%',
        ],

        /*
         * Should the `file` cache driver be tenant-aware.
         */
        'scope_cache' => true,

        /*
         * Should the `file` session driver be tenant-aware.
         */
        'scope_sessions' => true,

        /**
         * Should storage_path() be suffixed.
         */
        'suffix_storage_path' => true,

        /**
         * By default, asset() calls are made multi-tenant too.
         * Disabled for Vite - assets served directly from /build/
         */
        'asset_helper_override' => false,
    ],

    /**
     * Redis tenancy config. Used by RedisTenancyBootstrapper.
     *
     * IMPORTANT: When using Redis queue driver, ensure the queue connection
     * is NOT listed in prefixed_connections. Use a separate Redis connection
     * for queues to prevent tenant prefixes from breaking jobs.
     */
    'redis' => [
        'prefix' => 'tenant_%tenant%_', // %tenant% replaced by the tenant key
        'prefixed_connections' => [
            'default', // Isolate direct Redis calls (Redis::get/set) and sessions by tenant
            // 'queue' is intentionally NOT here - jobs should not be tenant-prefixed
        ],
    ],

    /**
     * Features are classes that provide additional functionality
     * not needed for tenancy to be bootstrapped. They are run
     * regardless of whether tenancy has been initialized.
     */
    'features' => [
        Stancl\Tenancy\Features\UserImpersonation::class,
        Stancl\Tenancy\Features\TelescopeTags::class,
        Stancl\Tenancy\Features\CrossDomainRedirect::class,
        // Stancl\Tenancy\Features\ViteBundler::class,
        // Stancl\Tenancy\Features\DisallowSqliteAttach::class,
    ],

    /**
     * Should tenancy routes be registered.
     */
    'routes' => true,

    /**
     * Make all routes central, tenant, or universal by default.
     */
    'default_route_mode' => RouteMode::CENTRAL,

    /**
     * Pending tenants config.
     */
    'pending' => [
        'include_in_queries' => true,
        'count' => env('TENANCY_PENDING_COUNT', 5),
    ],

    /**
     * Impersonation config.
     *
     * Routes that should be blocked during admin impersonation of tenant users.
     */
    'impersonation' => [
        'blocked_routes' => [
            // Billing - prevent financial operations
            'billing.*',

            // Team management - prevent modifying team structure
            'team.remove',
            'team.update-role',

            // Password - prevent credential changes
            'shared.settings.password.*',
            'password.update',

            // Two-factor authentication - prevent security changes
            'shared.settings.two-factor.*',
            'two-factor.*',

            // Tenant deletion - prevent destructive actions
            'settings.destroy',

            // API tokens - prevent token management
            'settings.api-tokens.*',
        ],
    ],

    /**
     * Parameters used by the tenants:migrate command.
     */
    'migration_parameters' => [
        '--force' => true,
        '--path' => [database_path('migrations/tenant')],
        '--schema-path' => database_path('schema/tenant-schema.dump'),
        '--realpath' => true,
    ],

    /**
     * Parameters used by the tenants:seed command.
     */
    'seeder_parameters' => [
        '--class' => 'DatabaseSeeder',
        // '--force' => true,
    ],
];
