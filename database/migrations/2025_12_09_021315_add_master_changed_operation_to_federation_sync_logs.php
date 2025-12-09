<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Add 'master_changed' operation to federation_sync_logs operation enum.
 *
 * This operation is logged when the master tenant of a federation group is changed.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Drop the existing CHECK constraint
        DB::statement('ALTER TABLE federation_sync_logs DROP CONSTRAINT IF EXISTS federation_sync_logs_operation_check');

        // Create new CHECK constraint with the additional operation value
        DB::statement("
            ALTER TABLE federation_sync_logs
            ADD CONSTRAINT federation_sync_logs_operation_check
            CHECK (operation IN (
                'user_created',
                'user_updated',
                'user_deleted',
                'password_changed',
                'two_factor_changed',
                'tenant_joined',
                'tenant_left',
                'conflict_detected',
                'conflict_resolved',
                'sync_failed',
                'sync_retry',
                'master_changed'
            ))
        ");
    }

    public function down(): void
    {
        // Revert to original CHECK constraint
        DB::statement('ALTER TABLE federation_sync_logs DROP CONSTRAINT IF EXISTS federation_sync_logs_operation_check');

        DB::statement("
            ALTER TABLE federation_sync_logs
            ADD CONSTRAINT federation_sync_logs_operation_check
            CHECK (operation IN (
                'user_created',
                'user_updated',
                'user_deleted',
                'password_changed',
                'two_factor_changed',
                'tenant_joined',
                'tenant_left',
                'conflict_detected',
                'conflict_resolved',
                'sync_failed',
                'sync_retry'
            ))
        ");
    }
};
