<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Central Migration: Payments Table
 *
 * PROVIDER-AGNOSTIC: Records all payment transactions.
 * Supports multiple providers and payment types (card, PIX, boleto).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('tenant_id')->nullable()->constrained()->nullOnDelete();

            // Provider info
            $table->string('provider'); // stripe, asaas, pagseguro, mercadopago
            $table->string('provider_payment_id')->nullable();

            // Payment method used
            $table->foreignUuid('payment_method_id')->nullable()->constrained()->nullOnDelete();
            $table->string('payment_type'); // card, pix, boleto

            // Values (all in cents)
            $table->integer('amount');
            $table->string('currency', 3)->default('BRL');
            $table->integer('fee')->default(0); // provider fee
            $table->integer('net_amount')->storedAs('amount - fee');

            // Status
            $table->string('status'); // pending, processing, paid, failed, refunded, expired, canceled
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('refunded_at')->nullable();

            // Refund tracking
            $table->integer('amount_refunded')->default(0);

            // Reference to what was paid for (polymorphic)
            $table->string('payable_type'); // subscription, addon_purchase, invoice
            $table->uuid('payable_id');

            // Description
            $table->string('description')->nullable();

            // Provider-specific data (QR code, linha digitável, etc.)
            $table->json('provider_data')->nullable();
            $table->json('metadata')->nullable();

            // Failure tracking
            $table->string('failure_code')->nullable();
            $table->text('failure_message')->nullable();

            $table->timestamps();

            // Indexes
            $table->index(['customer_id', 'status']);
            $table->index(['provider', 'provider_payment_id']);
            $table->index(['payable_type', 'payable_id']);
            $table->index('status');
            $table->index('paid_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
