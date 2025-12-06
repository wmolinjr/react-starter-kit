<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tenant Migration: Personal Access Tokens Table
 *
 * TENANT-ONLY USERS ARCHITECTURE (Option C):
 * - Sanctum API tokens stored in tenant database
 * - Each tenant has isolated token management
 * - No tenant_id column needed (database isolation)
 *
 * Uses UUID for primary key and uuidMorphs for tokenable.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('personal_access_tokens', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuidMorphs('tokenable'); // UUID for User model
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
