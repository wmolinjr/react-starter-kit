<?php

use Illuminate\Database\Migrations\Migration;

/**
 * Modify tenant_invitations for Option C: Tenant-Only Users
 *
 * DEPRECATED: The original tenant_invitations migration has been updated
 * to use Option C architecture. This migration is kept empty for
 * compatibility with existing migration history.
 *
 * @see database/migrations/2025_11_20_122412_create_tenant_invitations_table.php
 */
return new class extends Migration
{
    public function up(): void
    {
        // Option C changes are now in the original migration
    }

    public function down(): void
    {
        // Nothing to rollback
    }
};
