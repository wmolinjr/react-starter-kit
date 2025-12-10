<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Indicates if migrations have been run for this test class.
     * Each test class gets a fresh database to avoid data leakage.
     */
    protected static bool $classMigrationsRun = false;

    /**
     * Setup the test environment.
     *
     * Runs migrate:fresh once per test class to ensure clean state.
     * Uses separate databases to mirror production multi-database tenancy:
     * - 'testing': Central database (Central\User, tenants, plans, etc.)
     * - 'testing_tenant': Tenant database (Tenant\User, projects, etc.)
     *
     * The TenancyServiceProvider configures the 'tenant' connection to use
     * 'testing_tenant' when TENANCY_TESTING_DATABASE is set (see phpunit.xml).
     * This enables tenancy()->run() to use the correct test database.
     *
     * No database transactions used to avoid deadlocks with stancl/tenancy.
     *
     * Trade-off: Data accumulates within a test class, but each class starts fresh.
     * Tests should be written to not depend on absolute counts.
     *
     * @see https://v4.tenancyforlaravel.com/customizing-databases
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Disable queued database operations for tests (no dynamic DB creation)
        config(['tenancy.queue_database_creation' => false]);
        config(['tenancy.queue_database_deletion' => false]);

        // Disable domain resolver cache for tests (avoids stale cache issues)
        config(['tenancy.identification.resolvers.'.\Stancl\Tenancy\Resolvers\DomainTenantResolver::class.'.cache' => false]);

        // Run migrations once per test class
        if (! static::$classMigrationsRun) {
            // Fresh migration for central testing database
            $this->artisan('migrate:fresh', [
                '--database' => 'testing',
                '--seed' => false,
            ]);

            // Fresh migration for tenant testing database (separate DB)
            $this->artisan('migrate:fresh', [
                '--path' => 'database/migrations/tenant',
                '--database' => 'testing_tenant',
                '--seed' => false,
            ]);

            static::$classMigrationsRun = true;
        }
    }

    /**
     * Reset migrations flag before each test class.
     * Ensures each test class gets a fresh database.
     */
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        static::$classMigrationsRun = false;
    }

    /**
     * Clean up after each test.
     *
     * Ends tenant context if initialized.
     */
    protected function tearDown(): void
    {
        // Clean up any tenants created during tests
        if (function_exists('tenancy') && tenancy()->initialized) {
            tenancy()->end();
        }

        parent::tearDown();
    }

    /**
     * Central domain for tests (matches APP_DOMAIN in Laravel Sail).
     *
     * Used to ensure HTTP requests go to the correct domain
     * when there are conflicting routes between central and tenant.
     */
    protected string $centralDomain = 'app.test';

    /**
     * Generate a full URL for central routes.
     *
     * IMPORTANT: Use this instead of route() for central routes when there are
     * conflicting tenant routes (e.g., /admin/addons exists in both central and tenant).
     *
     * @param  string  $path  The path (e.g., '/admin/addons' or 'admin/addons')
     * @return string Full URL with central domain
     */
    protected function centralUrl(string $path): string
    {
        $path = ltrim($path, '/');

        return "http://{$this->centralDomain}/{$path}";
    }

    /**
     * Generate a central URL from a route name.
     *
     * @param  string  $name  Route name (e.g., 'addons.index')
     * @param  array  $parameters  Route parameters
     * @return string Full URL with central domain
     */
    protected function centralRoute(string $name, array $parameters = []): string
    {
        // Get the path from the route
        $url = route($name, $parameters);
        $path = parse_url($url, PHP_URL_PATH);

        return $this->centralUrl($path);
    }
}
