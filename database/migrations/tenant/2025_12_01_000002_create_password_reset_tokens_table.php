<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tenant Migration: Password Reset Tokens Table
 *
 * TENANT-ONLY USERS ARCHITECTURE (Option C):
 * - Password reset tokens stored in tenant database
 * - Users can only reset password in their tenant
 * - Complete isolation (no cross-tenant password reset)
 *
 * Standard Laravel password reset schema.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('password_reset_tokens');
    }
};
