<?php

declare(strict_types=1);

namespace App\Listeners\Shared;

use Illuminate\Database\Events\ConnectionEstablished;
use Illuminate\Support\Facades\Log;

/**
 * Ensures all database connections use UTC timezone.
 *
 * This listener is a safety net for dynamically created connections
 * (like tenant databases) that might not inherit the timezone setting
 * from config/database.php.
 *
 * Multi-layered UTC enforcement:
 * 1. config/database.php: timezone => 'UTC' on all PostgreSQL connections
 * 2. AppServiceProvider: date_default_timezone_set('UTC')
 * 3. This listener: SET TIMEZONE on connection establishment
 */
class SetDatabaseTimezone
{
    /**
     * Handle the ConnectionEstablished event.
     */
    public function handle(ConnectionEstablished $event): void
    {
        $connection = $event->connection;
        $driverName = $connection->getDriverName();

        // Only apply to PostgreSQL connections
        if ($driverName !== 'pgsql') {
            return;
        }

        try {
            // Set session timezone to UTC for this connection
            $connection->statement("SET TIMEZONE TO 'UTC'");
        } catch (\Exception $e) {
            // Log but don't fail - the config-level setting should still work
            Log::warning('Failed to set database timezone to UTC', [
                'connection' => $connection->getName(),
                'error' => $e->getMessage(),
            ]);
        }
    }
}
