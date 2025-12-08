<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tenant Migration: Users Table
 *
 * TENANT-ONLY USERS ARCHITECTURE (Option C):
 * - Users exist ONLY in tenant databases (complete isolation)
 * - No connection to central database
 * - Roles/permissions are local (same database = no cross-db workarounds)
 *
 * BENEFITS:
 * - Maximum isolation (LGPD/HIPAA compliance)
 * - Delete tenant = delete all user data
 * - No sync complexity
 * - Performance: all queries are local
 *
 * Uses UUID for primary key (Laravel v7 ordered UUIDs).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->string('locale', 10)->default('pt_BR');

            // Tenant-specific optional fields
            $table->string('department')->nullable();
            $table->string('employee_id')->nullable();
            $table->json('custom_settings')->nullable();

            // Two-factor authentication
            $table->text('two_factor_secret')->nullable();
            $table->text('two_factor_recovery_codes')->nullable();
            $table->timestamp('two_factor_confirmed_at')->nullable();

            // Federation sync (optional - links to central federated_users table)
            // If set, this user is part of a federation group and syncs with other tenants
            $table->uuid('federated_user_id')->nullable()->index();

            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();

            // Indexes for performance
            $table->index('email');
            $table->index('created_at');
            $table->index('deleted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
