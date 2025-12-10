<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Central Migration: Subscriptions Table
 *
 * PROVIDER-AGNOSTIC ARCHITECTURE:
 * - Subscriptions belong to Customer (billing entity) and optionally a Tenant
 * - Supports multiple payment providers (Stripe, Asaas, PagSeguro, etc.)
 * - Provider-specific IDs stored in provider_* columns
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Relationships
            $table->foreignUuid('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('tenant_id')->nullable()->constrained()->nullOnDelete();

            // Provider info (agnostic)
            $table->string('provider'); // stripe, asaas, pagseguro, mercadopago
            $table->string('provider_subscription_id')->nullable();
            $table->string('provider_customer_id')->nullable();
            $table->string('provider_price_id')->nullable();

            // Subscription details
            $table->string('type')->default('default'); // default, addon, etc.
            $table->string('status'); // active, canceled, past_due, trialing, paused, incomplete
            $table->integer('quantity')->default(1);

            // Billing cycle
            $table->string('billing_period'); // monthly, yearly
            $table->integer('amount'); // in cents
            $table->string('currency', 3)->default('BRL');

            // Dates
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('current_period_start')->nullable();
            $table->timestamp('current_period_end')->nullable();
            $table->timestamp('canceled_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamp('paused_at')->nullable();

            // Metadata
            $table->json('provider_data')->nullable();
            $table->json('metadata')->nullable();

            $table->timestamps();

            // Indexes
            $table->index(['customer_id', 'type']);
            $table->index(['customer_id', 'status']);
            $table->index(['provider', 'provider_subscription_id']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
