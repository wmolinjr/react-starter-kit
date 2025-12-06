<?php

use Illuminate\Database\Migrations\Migration;

/**
 * Central Migration: Users Table (DEPRECATED in Option C)
 *
 * OPTION C ARCHITECTURE:
 * - Users exist ONLY in tenant databases (complete isolation)
 * - Admin users are stored in the 'admins' table (separate migration)
 * - password_reset_tokens and sessions are in tenant databases
 *
 * This migration is kept empty for compatibility with Laravel's
 * expected migration structure but creates no tables.
 *
 * @see database/migrations/2025_12_05_000001_create_admins_table.php
 * @see database/migrations/tenant/0001_01_01_000000_create_users_table.php
 */
return new class extends Migration
{
    public function up(): void
    {
        // Option C: No users table in central database
        // Users exist only in tenant databases
        // Admins use separate 'admins' table
    }

    public function down(): void
    {
        // Nothing to drop
    }
};
