<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stancl/Tenancy v4 UserImpersonation tokens table.
 *
 * OPTION C ARCHITECTURE:
 * - Tokens stored in central database
 * - tenant_id references central tenants table
 * - user_id is UUID reference (NO FK constraint - users are in tenant databases)
 *
 * This enables admins to impersonate users in tenant databases without
 * requiring cross-database foreign key constraints.
 */
class CreateTenantUserImpersonationTokensTable extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_user_impersonation_tokens', function (Blueprint $table) {
            $table->string('token', 128)->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            // Make user_id nullable for Admin Mode
            $table->uuid('user_id')->nullable()->index();
            // Make auth_guard nullable for Admin Mode (no authentication)
            $table->string('auth_guard')->nullable();
            $table->boolean('remember')->default(false);
            $table->string('redirect_url');
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_user_impersonation_tokens');
    }
}
