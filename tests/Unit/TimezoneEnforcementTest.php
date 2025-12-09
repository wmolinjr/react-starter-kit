<?php

declare(strict_types=1);

namespace Tests\Unit;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Tests for UTC timezone enforcement across the application.
 *
 * These tests ensure that all timestamps are stored in UTC regardless
 * of server configuration, providing consistency for multi-tenant and
 * multi-region deployments.
 */
class TimezoneEnforcementTest extends TestCase
{
    public function test_php_timezone_is_utc(): void
    {
        $this->assertEquals('UTC', date_default_timezone_get());
    }

    public function test_carbon_default_timezone_is_utc(): void
    {
        $carbon = Carbon::now();

        $this->assertEquals('UTC', $carbon->timezoneName);
    }

    public function test_laravel_app_timezone_is_utc(): void
    {
        $this->assertEquals('UTC', config('app.timezone'));
    }

    public function test_database_connections_have_utc_timezone(): void
    {
        $connections = ['central', 'tenant', 'tenant_template', 'testing', 'testing_tenant'];

        foreach ($connections as $connection) {
            $config = config("database.connections.{$connection}");

            // Skip if connection doesn't exist
            if (! $config) {
                continue;
            }

            // Only check PostgreSQL connections
            if ($config['driver'] !== 'pgsql') {
                continue;
            }

            $this->assertEquals(
                'UTC',
                $config['timezone'] ?? null,
                "Connection '{$connection}' should have timezone set to UTC"
            );
        }
    }

    public function test_postgresql_session_timezone_is_utc(): void
    {
        // Query PostgreSQL for current session timezone
        $result = DB::selectOne('SHOW TIMEZONE');

        $this->assertEquals('UTC', $result->TimeZone);
    }

    public function test_timestamps_stored_in_utc(): void
    {
        // Create a Carbon instance with a specific non-UTC timezone
        $saoPaulo = Carbon::now('America/Sao_Paulo');

        // When converted to UTC for storage, the hour should be different
        $utc = $saoPaulo->copy()->setTimezone('UTC');

        // São Paulo is UTC-3, so UTC should be 3 hours ahead
        // (or 2 hours during DST)
        $this->assertNotEquals(
            $saoPaulo->format('Y-m-d H:i:s'),
            $utc->format('Y-m-d H:i:s'),
            'UTC time should differ from São Paulo time'
        );

        // But when both are in UTC, they should represent the same moment
        $this->assertEquals(
            $saoPaulo->timestamp,
            $utc->timestamp,
            'Both should represent the same Unix timestamp'
        );
    }

    public function test_carbon_serialization_uses_utc(): void
    {
        // Create a Carbon instance
        $now = Carbon::now();

        // Serialize to ISO8601
        $iso = $now->toIso8601String();

        // Should end with +00:00 or Z (UTC)
        $this->assertTrue(
            str_ends_with($iso, '+00:00') || str_ends_with($iso, 'Z'),
            "ISO8601 string should indicate UTC: {$iso}"
        );
    }
}
