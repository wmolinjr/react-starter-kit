<?php

use Illuminate\Database\Migrations\Migration;

/**
 * Central Migration: Tenant-User Pivot Table (DEPRECATED in Option C)
 *
 * OPTION C ARCHITECTURE:
 * - Users exist ONLY in tenant databases (complete isolation)
 * - No pivot table needed - users belong to exactly one tenant
 * - Their tenant is determined by which database they're in
 *
 * This migration is kept empty for compatibility with existing migrations
 * but creates no tables.
 *
 * @see docs/TENANT-USERS-OPTION-C-IMPLEMENTATION.md
 */
return new class extends Migration
{
    public function up(): void
    {
        // Option C: No pivot table needed
        // Users exist only in their tenant's database
    }

    public function down(): void
    {
        // Nothing to drop
    }
};
