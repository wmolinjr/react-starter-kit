<?php

use Illuminate\Support\Str;

return [

    /*
    |--------------------------------------------------------------------------
    | Default Database Connection Name
    |--------------------------------------------------------------------------
    |
    | Here you may specify which of the database connections below you wish
    | to use as your default connection for database operations. This is
    | the connection which will be utilized unless another connection
    | is explicitly specified when you execute a query / statement.
    |
    | For multi-database tenancy, this should be 'central' (the central database).
    |
    */

    'default' => env('DB_CONNECTION', 'central'),

    /*
    |--------------------------------------------------------------------------
    | Database Connections (PostgreSQL Only)
    |--------------------------------------------------------------------------
    |
    | This project uses PostgreSQL exclusively for multi-database tenancy.
    | Unused Laravel default connections (sqlite, mysql, mariadb, sqlsrv)
    | are explicitly set to null to prevent accidental usage.
    |
    | Active Connections:
    | - 'central': Central database (tenants, plans, subscriptions, central admins)
    | - 'tenant': Dynamic per-tenant databases (users, projects, activity_log)
    | - 'tenant_template': Template for tenant connection configuration
    | - 'testing' / 'testing_tenant': Parallel test databases
    |
    */

    'connections' => [

        // Explicitly disable unused connections (override Laravel defaults)
        'sqlite' => null,
        'mysql' => null,
        'mariadb' => null,
        'sqlsrv' => null,

        /*
        |--------------------------------------------------------------------------
        | Testing Database Connections (PostgreSQL)
        |--------------------------------------------------------------------------
        |
        | Dedicated connections for PHPUnit tests. Uses separate databases
        | to mirror production multi-database tenancy architecture:
        | - 'testing': Central database (Central\User, tenants, plans, etc.)
        | - 'testing_tenant': Tenant database (Tenant\User, projects, etc.)
        |
        | PARALLEL TESTING SUPPORT:
        | When running `php artisan test --parallel`, Laravel sets TEST_TOKEN
        | to a unique value per process (1, 2, 3, etc.). This creates isolated
        | databases per process: testing_1, testing_tenant_1, etc.
        |
        | This avoids PHP 8.4 SQLite transaction issues and properly tests
        | multi-database isolation.
        |
        */
        'testing' => [
            'driver' => 'pgsql',
            'url' => env('DB_URL'),
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '5432'),
            'database' => 'testing'.(env('TEST_TOKEN') ? '_'.env('TEST_TOKEN') : ''),
            'username' => env('DB_USERNAME', 'sail'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => env('DB_CHARSET', 'utf8'),
            'prefix' => '',
            'prefix_indexes' => true,
            'search_path' => 'public',
            'sslmode' => 'prefer',
            'timezone' => 'UTC',
        ],

        'testing_tenant' => [
            'driver' => 'pgsql',
            'url' => env('DB_URL'),
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '5432'),
            'database' => 'testing_tenant'.(env('TEST_TOKEN') ? '_'.env('TEST_TOKEN') : ''),
            'username' => env('DB_USERNAME', 'sail'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => env('DB_CHARSET', 'utf8'),
            'prefix' => '',
            'prefix_indexes' => true,
            'search_path' => 'public',
            'sslmode' => 'prefer',
            'timezone' => 'UTC',
        ],

        /*
        |--------------------------------------------------------------------------
        | Central Database Connection (PostgreSQL)
        |--------------------------------------------------------------------------
        |
        | This is the primary connection for central/global tables:
        | - tenants, domains, tenant_user, tenant_invitations
        | - users, password_reset_tokens, sessions
        | - plans, addons, subscriptions, subscription_items
        | - telescope_*, jobs, cache, features
        |
        */
        'central' => [
            'driver' => 'pgsql',
            'url' => env('DB_URL'),
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '5432'),
            'database' => env('DB_DATABASE', 'laravel'),
            'username' => env('DB_USERNAME', 'sail'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => env('DB_CHARSET', 'utf8'),
            'prefix' => '',
            'prefix_indexes' => true,
            'search_path' => 'public',
            'sslmode' => 'prefer',
            'timezone' => 'UTC',
        ],

        /*
        |--------------------------------------------------------------------------
        | Tenant Template Connection (PostgreSQL)
        |--------------------------------------------------------------------------
        |
        | Template connection used as base for per-tenant database connections.
        | This connection is NEVER modified - it serves as a template only.
        | The 'tenant' connection (below) is dynamically overwritten.
        |
        | IMPORTANT: This MUST be separate from 'tenant' because purgeTenantConnection()
        | unsets 'database.connections.tenant' before creating a new connection.
        |
        */
        'tenant_template' => [
            'driver' => 'pgsql',
            'url' => env('DB_URL'),
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '5432'),
            'database' => env('DB_DATABASE', 'laravel'), // Not used - just for reference
            'username' => env('DB_USERNAME', 'sail'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => env('DB_CHARSET', 'utf8'),
            'prefix' => '',
            'prefix_indexes' => true,
            'search_path' => 'public',
            'sslmode' => 'prefer',
            'timezone' => 'UTC',
        ],

        /*
        |--------------------------------------------------------------------------
        | Tenant Database Connection (Dynamic)
        |--------------------------------------------------------------------------
        |
        | This connection is dynamically overwritten by DatabaseTenancyBootstrapper
        | when a tenant is initialized. The 'database' key is set to the tenant's
        | database (e.g., 'tenant_1', 'tenant_2').
        |
        | Each tenant has its own database with tables:
        | - projects, media, activity_log
        | - roles, permissions, model_has_roles, model_has_permissions
        | - tenant_addons, tenant_addon_purchases
        | - translation_overrides
        |
        */
        'tenant' => [
            'driver' => 'pgsql',
            'url' => env('DB_URL'),
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '5432'),
            'database' => null, // Set dynamically by DatabaseTenancyBootstrapper
            'username' => env('DB_USERNAME', 'sail'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => env('DB_CHARSET', 'utf8'),
            'prefix' => '',
            'prefix_indexes' => true,
            'search_path' => 'public',
            'sslmode' => 'prefer',
            'timezone' => 'UTC',
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Migration Repository Table
    |--------------------------------------------------------------------------
    |
    | This table keeps track of all the migrations that have already run for
    | your application. Using this information, we can determine which of
    | the migrations on disk haven't actually been run on the database.
    |
    */

    'migrations' => [
        'table' => 'migrations',
        'update_date_on_publish' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Redis Databases
    |--------------------------------------------------------------------------
    |
    | Redis is an open source, fast, and advanced key-value store that also
    | provides a richer body of commands than a typical key-value system
    | such as Memcached. You may define your connection settings here.
    |
    */

    'redis' => [

        'client' => env('REDIS_CLIENT', 'phpredis'),

        'options' => [
            'cluster' => env('REDIS_CLUSTER', 'redis'),
            'prefix' => env('REDIS_PREFIX', Str::slug((string) env('APP_NAME', 'laravel')).'-database-'),
            'persistent' => env('REDIS_PERSISTENT', false),
        ],

        'default' => [
            'url' => env('REDIS_URL'),
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'username' => env('REDIS_USERNAME'),
            'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_DB', '0'),
            'max_retries' => env('REDIS_MAX_RETRIES', 3),
            'backoff_algorithm' => env('REDIS_BACKOFF_ALGORITHM', 'decorrelated_jitter'),
            'backoff_base' => env('REDIS_BACKOFF_BASE', 100),
            'backoff_cap' => env('REDIS_BACKOFF_CAP', 1000),
        ],

        'cache' => [
            'url' => env('REDIS_URL'),
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'username' => env('REDIS_USERNAME'),
            'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_CACHE_DB', '1'),
            'max_retries' => env('REDIS_MAX_RETRIES', 3),
            'backoff_algorithm' => env('REDIS_BACKOFF_ALGORITHM', 'decorrelated_jitter'),
            'backoff_base' => env('REDIS_BACKOFF_BASE', 100),
            'backoff_cap' => env('REDIS_BACKOFF_CAP', 1000),
        ],

        // Queue connection: NÃO deve estar em tenancy.redis.prefixed_connections
        // para evitar conflitos com tenant prefixes em jobs
        'queue' => [
            'url' => env('REDIS_URL'),
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'username' => env('REDIS_USERNAME'),
            'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_QUEUE_DB', '2'),
            'max_retries' => env('REDIS_MAX_RETRIES', 3),
            'backoff_algorithm' => env('REDIS_BACKOFF_ALGORITHM', 'decorrelated_jitter'),
            'backoff_base' => env('REDIS_BACKOFF_BASE', 100),
            'backoff_cap' => env('REDIS_BACKOFF_CAP', 1000),
        ],

    ],

];
