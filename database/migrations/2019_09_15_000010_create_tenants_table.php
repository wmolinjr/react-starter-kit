<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Central Migration: Tenants Table
 *
 * Uses UUID for primary key (best practice for multi-tenant):
 * - Security: Doesn't expose tenant count
 * - Distributed: Works across multiple servers
 * - Database naming: Clean `tenant_uuid` format
 */
class CreateTenantsTable extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->json('data')->nullable(); // Stancl internal keys (tenancy_db_name, etc.)
            $table->json('settings')->nullable();
            $table->string('stripe_id')->nullable()->index();
            $table->string('pm_type')->nullable();
            $table->string('pm_last_four', 4)->nullable();
            $table->timestamp('trial_ends_at')->nullable();

            $table->foreignUuid('plan_id')
                ->nullable()
                ->after('id')
                ->constrained('plans')
                ->nullOnDelete();

            // Custom overrides for this tenant (optional)
            // Overrides plan defaults when non-null
            $table->json('plan_features_override')->nullable();
            $table->json('plan_limits_override')->nullable();

            // Note: trial_ends_at already exists in create_tenants_table migration

            // Usage tracking (for quotas)
            $table->json('current_usage')->nullable();
            // { "users": 5, "projects": 23, "storage": 2048, "apiCalls": 1523 }

            // ⭐ Cache of permissions enabled by plan
            // Regenerated when plan changes
            $table->json('plan_enabled_permissions')->nullable();

            $table->index('plan_id');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
}
