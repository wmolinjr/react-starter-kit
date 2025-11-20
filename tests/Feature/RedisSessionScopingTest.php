<?php

namespace Tests\Feature;

use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use Tests\TenantTestCase;

/**
 * Redis Session Scoping Test Suite
 *
 * Verifies that Redis session scoping follows Stancl Tenancy v4 best practices:
 * 1. Sessions are isolated per tenant via CacheTenancyBootstrapper (scope_sessions=true)
 * 2. Redis keys are tenant-prefixed via RedisTenancyBootstrapper
 * 3. Queue connection is separate and NOT tenant-prefixed
 * 4. Multi-database strategy (DB 0: sessions, DB 1: cache, DB 2: queue)
 *
 * @see https://v4.tenancyforlaravel.com/session-scoping
 * @see https://v4.tenancyforlaravel.com/bootstrappers/queue
 * @see CLAUDE.md:586-830 (Redis Session Scoping documentation)
 */
class RedisSessionScopingTest extends TenantTestCase
{
    // Note: RefreshDatabase is already used in TenantTestCase, no need to duplicate

    /**
     * Test 1: Session data is isolated between tenants
     *
     * Verifies that sessions created in one tenant are not accessible in another tenant.
     * This is critical for multi-tenant security.
     */
    public function test_sessions_are_isolated_between_tenants(): void
    {
        // Skip if not using Redis session driver
        if (config('session.driver') !== 'redis') {
            $this->markTestSkipped('Test requires SESSION_DRIVER=redis');
        }

        // Skip if Redis is not available
        if (! $this->isRedisAvailable()) {
            $this->markTestSkipped('Redis is not available in test environment');
        }

        // Set session data in Tenant 1
        session(['tenant1_key' => 'tenant1_value']);
        session(['shared_key' => 'value_from_tenant1']);
        $tenant1SessionId = session()->getId();

        $this->assertEquals('tenant1_value', session('tenant1_key'));
        $this->assertEquals('value_from_tenant1', session('shared_key'));

        // Switch to Tenant 2
        $tenant2 = $this->createOtherTenant();
        tenancy()->end();
        tenancy()->initialize($tenant2);

        // Start new session for Tenant 2
        session()->start();
        $tenant2SessionId = session()->getId();

        // Verify Tenant 2 session is isolated
        $this->assertNull(session('tenant1_key'), 'Tenant 2 should not see Tenant 1 session data');
        $this->assertNull(session('shared_key'), 'Tenant 2 should not see Tenant 1 shared key');

        // Set different data in Tenant 2
        session(['tenant2_key' => 'tenant2_value']);
        session(['shared_key' => 'value_from_tenant2']);

        $this->assertEquals('tenant2_value', session('tenant2_key'));
        $this->assertEquals('value_from_tenant2', session('shared_key'));

        // Switch back to Tenant 1
        tenancy()->end();
        tenancy()->initialize($this->tenant);

        // Restore Tenant 1 session
        session()->setId($tenant1SessionId);
        session()->start();

        // Verify Tenant 1 data is still intact and isolated
        $this->assertEquals('tenant1_value', session('tenant1_key'));
        $this->assertEquals('value_from_tenant1', session('shared_key'));
        $this->assertNull(session('tenant2_key'), 'Tenant 1 should not see Tenant 2 session data');
    }

    /**
     * Test 2: Redis keys are tenant-prefixed for 'default' connection
     *
     * Verifies that direct Redis calls via the 'default' connection are automatically
     * prefixed with tenant_id, preventing cross-tenant data leakage.
     */
    public function test_redis_keys_are_tenant_prefixed_on_default_connection(): void
    {
        if (! $this->isRedisAvailable()) {
            $this->markTestSkipped('Redis is not available in test environment');
        }

        // Skip if not using phpredis
        if (config('database.redis.client') !== 'phpredis') {
            $this->markTestSkipped('Test requires REDIS_CLIENT=phpredis');
        }

        // Set a key via Redis facade (uses 'default' connection)
        Redis::set('test_key', 'tenant1_value');
        $this->assertEquals('tenant1_value', Redis::get('test_key'));

        // Get the actual Redis key with prefix
        $actualKey = $this->getRedisKeyWithPrefix('test_key', $this->tenant->id);

        // Verify key exists with tenant prefix in Redis
        $rawValue = Redis::connection('default')->client()->get($actualKey);
        $this->assertNotNull($rawValue, 'Key should exist with tenant prefix');

        // Switch to Tenant 2
        $tenant2 = $this->createOtherTenant();
        tenancy()->end();
        tenancy()->initialize($tenant2);

        // Verify Tenant 2 cannot see Tenant 1's key
        $this->assertNull(Redis::get('test_key'), 'Tenant 2 should not see Tenant 1 Redis data');

        // Set different value in Tenant 2
        Redis::set('test_key', 'tenant2_value');
        $this->assertEquals('tenant2_value', Redis::get('test_key'));

        // Switch back to Tenant 1
        tenancy()->end();
        tenancy()->initialize($this->tenant);

        // Verify Tenant 1 data is still intact
        $this->assertEquals('tenant1_value', Redis::get('test_key'));
    }

    /**
     * Test 3: Cache is tenant-scoped via tags
     *
     * Verifies that Laravel cache (which uses Redis) is properly isolated
     * between tenants using cache tags.
     */
    public function test_cache_is_tenant_scoped(): void
    {
        if (! $this->isRedisAvailable()) {
            $this->markTestSkipped('Redis is not available in test environment');
        }

        // Set cache in Tenant 1
        Cache::put('cached_data', 'tenant1_cached_value', 3600);
        $this->assertEquals('tenant1_cached_value', Cache::get('cached_data'));

        // Switch to Tenant 2
        $tenant2 = $this->createOtherTenant();
        tenancy()->end();
        tenancy()->initialize($tenant2);

        // Verify Tenant 2 cannot see Tenant 1's cache
        $this->assertNull(Cache::get('cached_data'), 'Tenant 2 should not see Tenant 1 cache');

        // Set different cache in Tenant 2
        Cache::put('cached_data', 'tenant2_cached_value', 3600);
        $this->assertEquals('tenant2_cached_value', Cache::get('cached_data'));

        // Switch back to Tenant 1
        tenancy()->end();
        tenancy()->initialize($this->tenant);

        // Verify Tenant 1 cache is still intact
        $this->assertEquals('tenant1_cached_value', Cache::get('cached_data'));
    }

    /**
     * Test 4: Queue connection is NOT tenant-prefixed
     *
     * Critical test: Verifies that queue jobs are stored in a separate Redis connection
     * WITHOUT tenant prefixes, allowing workers to process jobs globally.
     *
     * This prevents the bug where jobs dispatched with tenant prefix (tenant_1:queues:default:job)
     * cannot be found by workers looking for (queues:default:job).
     */
    public function test_queue_connection_is_not_tenant_prefixed(): void
    {
        // Skip if not using Redis queue
        if (config('queue.default') !== 'redis') {
            $this->markTestSkipped('Test requires QUEUE_CONNECTION=redis');
        }

        // Verify queue connection is 'queue' (not 'default')
        $queueConnection = config('queue.connections.redis.connection');
        $this->assertEquals(
            'queue',
            $queueConnection,
            'Queue must use separate "queue" connection (not "default") to avoid tenant prefixes'
        );

        // Verify 'queue' connection is NOT in prefixed_connections
        $prefixedConnections = config('tenancy.redis.prefixed_connections', []);
        $this->assertNotContains(
            'queue',
            $prefixedConnections,
            'Queue connection must NOT be in tenancy.redis.prefixed_connections'
        );

        // Verify 'default' IS in prefixed_connections (for sessions/direct Redis)
        $this->assertContains(
            'default',
            $prefixedConnections,
            'Default connection should be in prefixed_connections for session isolation'
        );
    }

    /**
     * Test 5: Queue connection uses separate Redis database
     *
     * Verifies that queue uses DB 2, separate from sessions (DB 0) and cache (DB 1).
     */
    public function test_queue_uses_separate_redis_database(): void
    {
        // Get database numbers from config
        $defaultDb = config('database.redis.default.database', '0');
        $cacheDb = config('database.redis.cache.database', '1');
        $queueDb = config('database.redis.queue.database', '2');

        // Verify multi-database strategy
        $this->assertNotEquals($defaultDb, $queueDb, 'Queue DB must be different from default DB');
        $this->assertNotEquals($cacheDb, $queueDb, 'Queue DB must be different from cache DB');
        $this->assertEquals('0', $defaultDb, 'Default DB should be 0 (sessions + direct Redis)');
        $this->assertEquals('1', $cacheDb, 'Cache DB should be 1');
        $this->assertEquals('2', $queueDb, 'Queue DB should be 2');
    }

    /**
     * Test 6: CacheTenancyBootstrapper scope_sessions is enabled
     *
     * Verifies that the critical config 'tenancy.cache.scope_sessions' is set to true,
     * which enables automatic session scoping for cache-based session drivers.
     */
    public function test_cache_scope_sessions_is_enabled(): void
    {
        $scopeSessions = config('tenancy.cache.scope_sessions');

        $this->assertTrue(
            $scopeSessions,
            'tenancy.cache.scope_sessions MUST be true for Redis session scoping. '.
            'See: https://v4.tenancyforlaravel.com/session-scoping'
        );
    }

    /**
     * Test 7: RedisTenancyBootstrapper is enabled
     *
     * Verifies that RedisTenancyBootstrapper is registered in tenancy bootstrappers.
     */
    public function test_redis_tenancy_bootstrapper_is_enabled(): void
    {
        $bootstrappers = config('tenancy.bootstrappers', []);

        $this->assertContains(
            \Stancl\Tenancy\Bootstrappers\RedisTenancyBootstrapper::class,
            $bootstrappers,
            'RedisTenancyBootstrapper must be enabled for Redis key prefixing'
        );
    }

    /**
     * Test 8: CacheTenancyBootstrapper is enabled
     *
     * Verifies that CacheTenancyBootstrapper is registered in tenancy bootstrappers.
     */
    public function test_cache_tenancy_bootstrapper_is_enabled(): void
    {
        $bootstrappers = config('tenancy.bootstrappers', []);

        $this->assertContains(
            \Stancl\Tenancy\Bootstrappers\CacheTenancyBootstrapper::class,
            $bootstrappers,
            'CacheTenancyBootstrapper must be enabled for session scoping (when scope_sessions=true)'
        );
    }

    /**
     * Test 9: Session keys in Redis have tenant prefix
     *
     * Verifies that actual session keys stored in Redis contain the tenant prefix,
     * proving that session isolation is working at the Redis level.
     */
    public function test_session_keys_in_redis_have_tenant_prefix(): void
    {
        if (! $this->isRedisAvailable()) {
            $this->markTestSkipped('Redis is not available in test environment');
        }

        // Skip if not using Redis session driver
        if (config('session.driver') !== 'redis') {
            $this->markTestSkipped('Test requires SESSION_DRIVER=redis');
        }

        // Create a session with data
        session(['test_isolation' => 'value1']);
        $sessionId = session()->getId();

        // Get expected Redis key with tenant prefix
        $expectedPrefix = config('tenancy.redis.prefix_base', 'tenant');
        $tenantId = $this->tenant->id;

        // Session keys format: tenant_{id}:laravel_session:{session_id}
        $sessionKeyPattern = "{$expectedPrefix}_{$tenantId}:*{$sessionId}*";

        // Verify key exists with pattern in Redis DB 0
        $keys = Redis::connection('default')->client()->keys($sessionKeyPattern);

        $this->assertNotEmpty(
            $keys,
            "Session key should exist with tenant prefix pattern: {$sessionKeyPattern}"
        );
    }

    /**
     * Test 10: Clearing tenant cache doesn't affect other tenants
     *
     * Verifies that cache::flush() or cache clearing operations only affect
     * the current tenant's cache, not other tenants.
     */
    public function test_clearing_tenant_cache_doesnt_affect_other_tenants(): void
    {
        if (! $this->isRedisAvailable()) {
            $this->markTestSkipped('Redis is not available in test environment');
        }

        // Set cache in Tenant 1
        Cache::put('persistent_data', 'tenant1_important_data', 3600);
        $this->assertEquals('tenant1_important_data', Cache::get('persistent_data'));

        // Switch to Tenant 2 and set cache
        $tenant2 = $this->createOtherTenant();
        tenancy()->end();
        tenancy()->initialize($tenant2);

        Cache::put('persistent_data', 'tenant2_important_data', 3600);
        $this->assertEquals('tenant2_important_data', Cache::get('persistent_data'));

        // Clear Tenant 2's cache
        Cache::flush();

        // Verify Tenant 2's cache is cleared
        $this->assertNull(Cache::get('persistent_data'));

        // Switch back to Tenant 1
        tenancy()->end();
        tenancy()->initialize($this->tenant);

        // Verify Tenant 1's cache is STILL intact (not affected by Tenant 2's flush)
        $this->assertEquals(
            'tenant1_important_data',
            Cache::get('persistent_data'),
            'Tenant 1 cache should not be affected by Tenant 2 cache flush'
        );
    }

    /**
     * Test 11: Multiple tenants can have sessions simultaneously
     *
     * Simulates real-world scenario where multiple tenants have active sessions
     * at the same time, verifying complete isolation.
     */
    public function test_multiple_tenants_can_have_simultaneous_sessions(): void
    {
        if (! $this->isRedisAvailable()) {
            $this->markTestSkipped('Redis is not available in test environment');
        }

        // Skip if not using Redis session driver
        if (config('session.driver') !== 'redis') {
            $this->markTestSkipped('Test requires SESSION_DRIVER=redis');
        }

        // Create 3 tenants with session data
        $tenants = [
            ['tenant' => $this->tenant, 'data' => ['name' => 'Tenant 1', 'value' => 100]],
            ['tenant' => $this->createOtherTenant(), 'data' => ['name' => 'Tenant 2', 'value' => 200]],
            ['tenant' => $this->createOtherTenant(), 'data' => ['name' => 'Tenant 3', 'value' => 300]],
        ];

        $sessionIds = [];

        // Set session data for each tenant
        foreach ($tenants as $index => $tenantData) {
            tenancy()->end();
            tenancy()->initialize($tenantData['tenant']);

            session()->start();
            session($tenantData['data']);

            $sessionIds[$index] = session()->getId();

            // Verify data was set correctly
            $this->assertEquals($tenantData['data']['name'], session('name'));
            $this->assertEquals($tenantData['data']['value'], session('value'));
        }

        // Verify each tenant's session is isolated
        foreach ($tenants as $index => $tenantData) {
            tenancy()->end();
            tenancy()->initialize($tenantData['tenant']);

            session()->setId($sessionIds[$index]);
            session()->start();

            // Verify correct data
            $this->assertEquals($tenantData['data']['name'], session('name'));
            $this->assertEquals($tenantData['data']['value'], session('value'));

            // Verify no data leakage from other tenants
            foreach ($tenants as $otherIndex => $otherTenantData) {
                if ($index !== $otherIndex) {
                    $this->assertNotEquals(
                        $otherTenantData['data']['name'],
                        session('name'),
                        "Tenant {$index} should not see Tenant {$otherIndex} session data"
                    );
                }
            }
        }
    }

    /**
     * Helper: Check if Redis is available
     */
    protected function isRedisAvailable(): bool
    {
        try {
            Redis::connection('default')->ping();
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Helper: Get Redis key with tenant prefix
     */
    protected function getRedisKeyWithPrefix(string $key, int $tenantId): string
    {
        $prefixBase = config('tenancy.redis.prefix_base', 'tenant');
        $appPrefix = config('database.redis.options.prefix', '');

        // Format: {app_prefix}tenant_{id}:{key}
        return $appPrefix . $prefixBase . '_' . $tenantId . ':' . $key;
    }
}
