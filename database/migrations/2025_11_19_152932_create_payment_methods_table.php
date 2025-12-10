<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Central Migration: Payment Methods Table
 *
 * PROVIDER-AGNOSTIC: Supports multiple payment providers and types.
 * Stores payment method details for cards, PIX, boleto, etc.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_methods', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('customer_id')->constrained()->cascadeOnDelete();

            // Provider info
            $table->string('provider'); // stripe, asaas, pagseguro, mercadopago
            $table->string('provider_method_id')->nullable(); // ID in the provider

            // Payment method type
            $table->string('type'); // card, pix, boleto, bank_transfer

            // Card-specific details (encrypted in details JSON)
            $table->string('brand')->nullable(); // visa, mastercard, etc.
            $table->string('last4', 4)->nullable();
            $table->smallInteger('exp_month')->nullable();
            $table->smallInteger('exp_year')->nullable();

            // Bank-specific details
            $table->string('bank_name')->nullable();

            // General details (casts to encrypted array)
            $table->json('details')->nullable();

            // Status
            $table->boolean('is_default')->default(false);
            $table->boolean('is_verified')->default(false);
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('expires_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index(['customer_id', 'provider']);
            $table->index(['customer_id', 'is_default']);
            $table->index(['provider', 'provider_method_id']);
        });

        // Add foreign key constraint for customers.default_payment_method_id
        Schema::table('customers', function (Blueprint $table) {
            $table->foreign('default_payment_method_id')
                ->references('id')
                ->on('payment_methods')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropForeign(['default_payment_method_id']);
        });

        Schema::dropIfExists('payment_methods');
    }
};
