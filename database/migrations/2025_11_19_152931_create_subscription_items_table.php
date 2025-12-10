<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Central Migration: Subscription Items Table
 *
 * PROVIDER-AGNOSTIC: Supports multiple payment providers.
 * Each item represents a line item in a subscription (e.g., base plan, add-on).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('subscription_id')->constrained()->cascadeOnDelete();

            // Provider info (agnostic)
            $table->string('provider_item_id')->nullable();
            $table->string('provider_price_id');
            $table->string('provider_product_id')->nullable();

            // Item details
            $table->integer('quantity')->default(1);

            // Metered billing (optional)
            $table->string('meter_id')->nullable();
            $table->string('meter_event_name')->nullable();

            $table->timestamps();

            $table->index(['subscription_id', 'provider_price_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_items');
    }
};
