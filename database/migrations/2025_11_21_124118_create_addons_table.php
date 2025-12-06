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
        Schema::create('addons', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('slug')->unique();
            $table->json('name'); // Translatable: {"en": "Extra Users", "pt_BR": "Usuários Extras"}
            $table->json('description')->nullable(); // Translatable: {"en": "...", "pt_BR": "..."}
            $table->string('type'); // AddonType enum: quota, feature, metered, credit
            $table->boolean('active')->default(true);
            $table->integer('sort_order')->default(0);

            // For QUOTA type: which plan limit this addon increases
            $table->string('limit_key')->nullable(); // e.g., 'storage', 'users', 'projects'
            $table->integer('unit_value')->nullable(); // e.g., 50000 for "50GB" in MB
            $table->json('unit_label')->nullable(); // Translatable: {"en": "GB", "pt_BR": "GB"}

            // Quantity limits
            $table->integer('min_quantity')->default(1);
            $table->integer('max_quantity')->nullable();
            $table->boolean('stackable')->default(false);

            // Pricing (stored in cents)
            $table->integer('price_monthly')->nullable();
            $table->integer('price_yearly')->nullable();
            $table->integer('price_one_time')->nullable();
            $table->integer('price_metered')->nullable(); // Per-unit price for metered billing
            $table->string('currency', 3)->default('brl');
            $table->integer('free_tier')->nullable(); // Free tier before metered kicks in
            $table->integer('validity_months')->nullable(); // For one-time purchases

            // Stripe integration (populated by stripe:sync command)
            $table->string('stripe_product_id')->nullable();
            $table->string('stripe_price_monthly_id')->nullable();
            $table->string('stripe_price_yearly_id')->nullable();
            $table->string('stripe_price_one_time_id')->nullable();
            $table->string('stripe_price_metered_id')->nullable();
            $table->string('stripe_meter_id')->nullable();

            // Feature unlock specific
            $table->json('features')->nullable(); // List of feature flags to enable

            // UI/Marketing
            $table->string('icon')->nullable();
            $table->string('badge')->nullable();
            $table->string('icon_color', 20)->nullable();
            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->index(['active', 'type']);
        });

        // Pivot table for addon-plan availability
        Schema::create('addon_plan', function (Blueprint $table) {
            $table->foreignUuid('addon_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('plan_id')->constrained()->cascadeOnDelete();
            $table->integer('price_override_monthly')->nullable();
            $table->integer('price_override_yearly')->nullable();
            $table->integer('price_override_one_time')->nullable();
            $table->integer('discount_percent')->nullable();
            $table->boolean('included')->default(false); // Included free in plan
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->primary(['addon_id', 'plan_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('addon_plan');
        Schema::dropIfExists('addons');
    }
};
