<?php

use App\Enums\AddonStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Central Migration: Addon Subscriptions Table
 *
 * Stores active add-on subscriptions for tenants.
 * Uses tenant_id for relationship with tenants table.
 *
 * NOTE: This is a CENTRAL database table (not tenant database).
 * The table stores add-on subscriptions that belong to tenants.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('addon_subscriptions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->onDelete('cascade');

            // Addon identification
            $table->string('addon_slug');
            $table->string('addon_type');
            $table->string('name');
            $table->text('description')->nullable();

            // Pricing
            $table->integer('quantity')->default(1);
            $table->integer('price');
            $table->string('currency')->default('usd');
            $table->string('billing_period');

            // Status & lifecycle
            $table->string('status')->default(AddonStatus::ACTIVE->value);
            $table->timestamp('started_at');
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('canceled_at')->nullable();

            // External billing references (provider-agnostic)
            $table->string('provider')->nullable(); // 'stripe', 'asaas', etc.
            $table->string('provider_item_id')->nullable()->unique(); // subscription item ID
            $table->string('provider_price_id')->nullable(); // price/plan ID

            // Usage tracking (for metered billing)
            $table->integer('metered_usage')->default(0);
            $table->timestamp('metered_reset_at')->nullable();

            // Metadata
            $table->json('metadata')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('status');
            $table->index('addon_type');
            $table->index('addon_slug');
            $table->index('billing_period');
            $table->index(['started_at', 'expires_at']);
            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('addon_subscriptions');
    }
};
