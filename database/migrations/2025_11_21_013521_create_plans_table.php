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
        Schema::create('plans', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Basic info - Translatable fields use JSON
            $table->json('name'); // Translatable: {"en": "Starter", "pt_BR": "Iniciante"}
            $table->string('slug')->unique(); // "starter", "professional", "enterprise"
            $table->json('description')->nullable(); // Translatable: {"en": "...", "pt_BR": "..."}

            // Pricing
            $table->integer('price')->default(0); // In cents (2900 = $29.00)
            $table->string('currency', 3)->default('USD');
            $table->enum('billing_period', ['monthly', 'yearly'])->default('monthly');

            // Stripe/Paddle Integration
            $table->string('stripe_price_id')->nullable()->unique();
            $table->string('stripe_product_id')->nullable();
            $table->string('paddle_price_id')->nullable()->unique();

            // Features (JSON)
            // { "customRoles": true, "apiAccess": true, "advancedReports": false }
            $table->json('features')->nullable();

            // Limits (JSON)
            // { "users": 50, "projects": -1, "storage": 10240, "apiCalls": 10000 }
            // -1 = unlimited
            $table->json('limits')->nullable();

            // ⭐ Permission Mapping (JSON)
            // Maps features to permissions that should be enabled
            // {
            //   "customRoles": ["tenant.roles:*"],
            //   "apiAccess": ["tenant.apiTokens:*"],
            //   "advancedReports": ["tenant.reports:export", "tenant.reports:schedule"]
            // }
            $table->json('permission_map')->nullable();

            // Meta
            $table->boolean('is_active')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->string('badge', 50)->nullable();
            $table->string('icon', 50)->default('Layers');
            $table->string('icon_color', 20)->default('slate');
            $table->integer('sort_order')->default(0);

            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
