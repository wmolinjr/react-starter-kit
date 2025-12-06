<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Central Migration: Personal Access Tokens (Sanctum)
 *
 * OPTION C ARCHITECTURE:
 * - Central user API tokens are stored in central database
 * - Tenant user tokens are stored in tenant databases
 *
 * Uses UUID for primary key and uuidMorphs for tokenable (Central\User).
 * Smart model detects context automatically.
 *
 * @see App\Models\Shared\PersonalAccessToken
 * @see database/migrations/tenant/2025_12_01_000003_create_tenant_personal_access_tokens_table.php
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('personal_access_tokens', function (Blueprint $table) {
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
        Schema::dropIfExists('personal_access_tokens');
    }
};
