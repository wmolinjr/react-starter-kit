<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->foreignId('plan_id')
                ->nullable()
                ->after('id')
                ->constrained('plans')
                ->nullOnDelete();

            // Custom overrides for this tenant (optional)
            // Overrides plan defaults when non-null
            $table->json('plan_features_override')->nullable();
            $table->json('plan_limits_override')->nullable();

            // Trial
            $table->timestamp('trial_ends_at')->nullable();

            // Usage tracking (for quotas)
            $table->json('current_usage')->nullable();
            // { "users": 5, "projects": 23, "storage": 2048, "apiCalls": 1523 }

            // ⭐ Cache of permissions enabled by plan
            // Regenerated when plan changes
            $table->json('plan_enabled_permissions')->nullable();

            $table->index('plan_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropForeign(['plan_id']);
            $table->dropColumn([
                'plan_id',
                'plan_features_override',
                'plan_limits_override',
                'trial_ends_at',
                'current_usage',
                'plan_enabled_permissions',
            ]);
        });
    }
};
