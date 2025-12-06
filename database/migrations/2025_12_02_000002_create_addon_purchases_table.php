<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Central Migration: Addon Purchases Table
 *
 * Stores one-time purchase history for add-ons.
 * Uses tenant_id for relationship with tenants table.
 *
 * NOTE: This is a CENTRAL database table (not tenant database).
 * The table stores purchase history for tenant add-ons.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('addon_purchases', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->onDelete('cascade');
            $table->foreignUuid('addon_subscription_id')->nullable()->constrained('addon_subscriptions')->onDelete('set null');

            // Purchase details
            $table->string('addon_slug');
            $table->string('addon_type');
            $table->integer('quantity');

            // Payment
            $table->integer('amount_paid');
            $table->string('currency')->default('usd');
            $table->string('payment_method');

            // Stripe references
            $table->string('stripe_checkout_session_id')->nullable()->unique();
            $table->string('stripe_payment_intent_id')->nullable();
            $table->string('stripe_invoice_id')->nullable();

            // Status
            $table->enum('status', ['pending', 'completed', 'failed', 'refunded'])->default('pending');
            $table->timestamp('purchased_at')->nullable();
            $table->timestamp('refunded_at')->nullable();

            // Validity (for one-time credits)
            $table->timestamp('valid_from')->nullable();
            $table->timestamp('valid_until')->nullable();
            $table->boolean('is_consumed')->default(false);

            // Metadata
            $table->json('metadata')->nullable();
            $table->text('failure_reason')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('status');
            $table->index('purchased_at');
            $table->index(['valid_from', 'valid_until']);
            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('addon_purchases');
    }
};
