<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tenant Migration: Projects Table
 *
 * MULTI-DATABASE TENANCY:
 * - NO tenant_id column - isolation is at the database level
 * - user_id references the CENTRAL users table (cross-database)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->uuid('id')->primary(); // UUID primary key for consistency
            $table->uuid('user_id'); // References central.users (UUID)
            $table->string('name');
            $table->text('description')->nullable();
            $table->enum('status', ['active', 'archived', 'deleted'])->default('active');
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('user_id');
            $table->index('status');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
