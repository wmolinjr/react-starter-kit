<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Central Migration: Pending Signups Table
 *
 * Customer-First Flow: Stores workspace/payment data while waiting for payment.
 * Customer is created FIRST (Step 1), then workspace configured, then payment.
 *
 * Flow:
 * 1. User creates account → Customer created + logged in + PendingSignup created
 * 2. User configures workspace → PendingSignup updated with workspace data
 * 3. User initiates payment → payment session created
 * 4. Webhook confirms payment → Tenant created (Customer already exists)
 * 5. PendingSignup updated with tenant_id (status: completed)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pending_signups', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Customer reference (REQUIRED - customer-first flow)
            // Customer is created in Step 1, before workspace/payment
            $table->foreignUuid('customer_id')->constrained('customers')->cascadeOnDelete();

            // Workspace data (Step 2)
            $table->string('workspace_name')->nullable();
            $table->string('workspace_slug')->nullable()->unique();
            $table->string('business_sector')->nullable();

            // Plan selection (Step 2)
            $table->foreignUuid('plan_id')->nullable()->constrained('plans')->nullOnDelete();
            $table->string('billing_period')->default('monthly'); // monthly, yearly

            // Payment tracking (Step 3)
            $table->string('payment_method')->nullable(); // card, pix, boleto
            $table->string('payment_provider')->default('stripe'); // stripe, asaas
            $table->string('provider_session_id')->nullable(); // Stripe checkout session ID
            $table->string('provider_payment_id')->nullable(); // PIX/Boleto payment ID
            $table->string('status')->default('pending'); // pending, processing, completed, failed, expired

            // Result (after completion)
            $table->foreignUuid('tenant_id')->nullable()->constrained('tenants')->nullOnDelete();
            $table->string('failure_reason')->nullable();

            // Metadata
            $table->json('metadata')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            // Indexes
            $table->index(['customer_id', 'status']);
            $table->index(['provider_session_id', 'payment_provider']);
            $table->index(['provider_payment_id', 'payment_provider']);
            $table->index(['status', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pending_signups');
    }
};
