<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Add 'pending_master_sync' status to federated_users status enum.
 *
 * This status is used when changing the master tenant of a federation group.
 * Users that don't exist in the new master tenant are marked with this status
 * until the SyncFromNewMaster job creates them in the new master.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Drop the existing CHECK constraint
        DB::statement('ALTER TABLE federated_users DROP CONSTRAINT IF EXISTS federated_users_status_check');

        // Create new CHECK constraint with the additional status value
        DB::statement("
            ALTER TABLE federated_users
            ADD CONSTRAINT federated_users_status_check
            CHECK (status IN ('active', 'suspended', 'pending_review', 'pending_master_sync'))
        ");
    }

    public function down(): void
    {
        // Revert to original CHECK constraint (must ensure no rows use pending_master_sync first)
        DB::statement('ALTER TABLE federated_users DROP CONSTRAINT IF EXISTS federated_users_status_check');

        DB::statement("
            ALTER TABLE federated_users
            ADD CONSTRAINT federated_users_status_check
            CHECK (status IN ('active', 'suspended', 'pending_review'))
        ");
    }
};
