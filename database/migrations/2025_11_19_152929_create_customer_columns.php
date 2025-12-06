<?php

use Illuminate\Database\Migrations\Migration;

/**
 * Customer/Stripe Columns (DEPRECATED in Option C)
 *
 * OPTION C ARCHITECTURE:
 * - Tenants are the billable entities, not users
 * - Stripe columns (stripe_id, pm_*, trial_ends_at) are on tenants table
 * - Users don't need billing columns
 *
 * This migration is kept empty for compatibility with migration history.
 *
 * @see database/migrations/2019_09_15_000010_create_tenants_table.php
 */
return new class extends Migration
{
    public function up(): void
    {
        // Option C: Tenants are billable, not users
        // Stripe columns are on tenants table
    }

    public function down(): void
    {
        // Nothing to drop
    }
};
