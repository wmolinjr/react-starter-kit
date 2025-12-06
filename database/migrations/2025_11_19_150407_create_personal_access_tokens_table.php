<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Central Migration: Admin Personal Access Tokens
 *
 * OPTION C ARCHITECTURE:
 * - Admin API tokens are stored in central database (admin_personal_access_tokens)
 * - Tenant user tokens are stored in tenant databases (personal_access_tokens)
 *
 * Uses UUID for primary key and uuidMorphs for tokenable (Central\User).
 *
 * @see App\Models\Central\PersonalAccessToken
 * @see database/migrations/tenant/2025_12_01_000003_create_tenant_personal_access_tokens_table.php
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_personal_access_tokens', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuidMorphs('tokenable'); // UUID for Central\User model
            $table->string('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_personal_access_tokens');
    }
};
