<?php

namespace App\Providers;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\ParallelTesting;
use Illuminate\Support\ServiceProvider;

/**
 * ParallelTestingServiceProvider
 *
 * Configures parallel testing support for multi-database tenancy architecture.
 *
 * When running `php artisan test --parallel`, this provider:
 * 1. Creates isolated databases per process (testing_1, testing_tenant_1, etc.)
 * 2. Runs migrations on each database
 * 3. Seeds initial data required for tests
 *
 * This enables running tests in parallel without database conflicts.
 *
 * @see https://laravel.com/docs/12.x/testing#running-tests-in-parallel
 */
class ParallelTestingServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Only configure parallel testing hooks in testing environment
        if (! $this->app->runningUnitTests()) {
            return;
        }

        // Called when a new test process is started
        ParallelTesting::setUpProcess(function (int $token) {
            // Create the testing databases for this process
            $this->createTestDatabases($token);
        });

        // Called when test databases are created (after migrations)
        ParallelTesting::setUpTestDatabase(function (string $database, int $token) {
            // Run migrations for tenant database as well
            // The central database is already migrated by Laravel's parallel testing
            $this->migrateTenantDatabase($token);
        });

        // Called when a test process is ending
        ParallelTesting::tearDownProcess(function (int $token) {
            // Optionally drop databases after tests
            // Keeping them allows faster subsequent test runs (--recreate-databases to force)
        });
    }

    /**
     * Create test databases for a parallel process.
     */
    protected function createTestDatabases(int $token): void
    {
        $centralDb = "testing_{$token}";
        $tenantDb = "testing_tenant_{$token}";

        // Connect to postgres database to create new databases
        $pdo = DB::connection('pgsql')->getPdo();

        // Create central testing database if not exists
        $this->createDatabaseIfNotExists($pdo, $centralDb);

        // Create tenant testing database if not exists
        $this->createDatabaseIfNotExists($pdo, $tenantDb);
    }

    /**
     * Create a database if it doesn't exist.
     */
    protected function createDatabaseIfNotExists(\PDO $pdo, string $database): void
    {
        // Check if database exists
        $stmt = $pdo->prepare("SELECT 1 FROM pg_database WHERE datname = ?");
        $stmt->execute([$database]);

        if (! $stmt->fetch()) {
            // Create database (can't use prepared statements for CREATE DATABASE)
            $pdo->exec("CREATE DATABASE \"{$database}\"");
        }
    }

    /**
     * Run migrations for tenant database.
     */
    protected function migrateTenantDatabase(int $token): void
    {
        // Run tenant migrations on the tenant testing database
        Artisan::call('migrate:fresh', [
            '--path' => 'database/migrations/tenant',
            '--database' => 'testing_tenant',
            '--seed' => false,
            '--force' => true,
        ]);
    }
}
