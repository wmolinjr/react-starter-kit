<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Enable Admin Mode impersonation by making user_id nullable.
 *
 * OPTION C ARCHITECTURE:
 * - user_id = null means Admin Mode (no specific user identity)
 * - user_id = UUID means As User Mode (specific user impersonation)
 * - auth_guard nullable for Admin Mode (no login occurs)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenant_user_impersonation_tokens', function (Blueprint $table) {
            // Make user_id nullable for Admin Mode
            $table->uuid('user_id')->nullable()->change();
            // Make auth_guard nullable for Admin Mode (no authentication)
            $table->string('auth_guard')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('tenant_user_impersonation_tokens', function (Blueprint $table) {
            // Revert to non-nullable (will fail if null values exist)
            $table->uuid('user_id')->nullable(false)->change();
            $table->string('auth_guard')->nullable(false)->change();
        });
    }
};
