<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Addon Bundles - Packages of multiple addons with discount
 *
 * Bundles allow grouping multiple addons into a single purchasable package
 * with an optional discount. Each addon in the bundle maintains its own
 * functionality (QUOTA increases limits, FEATURE unlocks features).
 */
return new class extends Migration
{
    public function up(): void
    {
        // Bundles table
        Schema::create('addon_bundles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('slug')->unique();
            $table->json('name'); // Translatable
            $table->json('description')->nullable(); // Translatable
            $table->boolean('active')->default(true);

            // Pricing (calculated from items, with optional discount)
            $table->integer('discount_percent')->default(0); // 0-100
            $table->integer('price_monthly')->nullable(); // Override calculated price
            $table->integer('price_yearly')->nullable(); // Override calculated price
            $table->string('currency', 3)->default('brl');

            // Stripe IDs (populated by stripe:sync)
            $table->string('stripe_product_id')->nullable();
            $table->string('stripe_price_monthly_id')->nullable();
            $table->string('stripe_price_yearly_id')->nullable();

            // Display
            $table->string('badge')->nullable(); // "Most Popular", "Best Value"
            $table->string('icon')->nullable(); // Lucide icon name
            $table->string('icon_color', 20)->default('slate');
            $table->json('features')->nullable(); // Bullet points for marketing
            $table->integer('sort_order')->default(0);

            // Metadata
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('active');
            $table->index('sort_order');
        });

        // Pivot table: which addons are in which bundles
        Schema::create('addon_bundle_items', function (Blueprint $table) {
            $table->foreignUuid('bundle_id')->constrained('addon_bundles')->onDelete('cascade');
            $table->foreignUuid('addon_id')->constrained('addons')->onDelete('cascade');

            // Override quantity for this addon in this bundle
            $table->integer('quantity')->default(1);

            // Optional: specific billing period for this item
            $table->string('billing_period')->nullable();

            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->primary(['bundle_id', 'addon_id']);
        });

        // Pivot table: which plans can purchase which bundles
        Schema::create('addon_bundle_plan', function (Blueprint $table) {
            $table->foreignUuid('bundle_id')->constrained('addon_bundles')->onDelete('cascade');
            $table->foreignUuid('plan_id')->constrained('plans')->onDelete('cascade');
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->primary(['bundle_id', 'plan_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('addon_bundle_plan');
        Schema::dropIfExists('addon_bundle_items');
        Schema::dropIfExists('addon_bundles');
    }
};
