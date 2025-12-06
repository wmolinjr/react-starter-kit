<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tenant Migration: Sessions Table
 *
 * TENANT-ONLY USERS ARCHITECTURE (Option C):
 * - Sessions stored in tenant database
 * - DatabaseSessionBootstrapper switches session storage per tenant
 * - Complete session isolation between tenants
 *
 * Used when SESSION_DRIVER=database in tenant context.
 *
 * NOTE: Uses hasTable() check for compatibility with tests where
 * central and tenant migrations run on the same database.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignUuid('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
    }
};
