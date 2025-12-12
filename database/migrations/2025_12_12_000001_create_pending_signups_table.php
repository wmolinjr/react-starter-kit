<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Central Migration: Pending Signups Table
 *
 * Stores signup data while waiting for payment confirmation.
 * After payment is confirmed, data is used to create Customer + Tenant.
 *
 * Flow:
 * 1. User fills account + workspace data → PendingSignup created (status: pending)
 * 2. User initiates payment → payment session created
 * 3. Webhook confirms payment → Customer + Tenant created
 * 4. PendingSignup updated with customer_id + tenant_id (status: completed)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pending_signups', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Account data (Step 1)
            $table->string('email')->unique();
            $table->string('name');
            $table->string('password'); // Already hashed
            $table->string('locale', 10)->default('pt_BR');

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
            $table->foreignUuid('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->foreignUuid('tenant_id')->nullable()->constrained('tenants')->nullOnDelete();
            $table->string('failure_reason')->nullable();

            // Metadata
            $table->json('metadata')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            // Indexes for webhook lookups
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
