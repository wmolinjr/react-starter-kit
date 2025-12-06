<?php

use Illuminate\Database\Migrations\Migration;

/**
 * Central Migration: Personal Access Tokens (DEPRECATED in Option C)
 *
 * OPTION C ARCHITECTURE:
 * - API tokens are stored in tenant databases (per-tenant isolation)
 * - Users exist only in tenant databases
 * - Tokens belong to tenant users
 *
 * This migration is kept empty for compatibility with migration history.
 *
 * @see database/migrations/tenant/0001_01_01_000003_create_personal_access_tokens_table.php
 */
return new class extends Migration
{
    public function up(): void
    {
        // Option C: Personal access tokens are in tenant databases
    }

    public function down(): void
    {
        // Nothing to drop
    }
};
