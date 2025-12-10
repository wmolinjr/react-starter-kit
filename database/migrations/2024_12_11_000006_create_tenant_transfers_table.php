<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Central Migration: Tenant Transfers Table
 *
 * Tracks tenant ownership transfers between customers.
 * Supports:
 * - Transfer to existing customer (by email lookup)
 * - Transfer to new user (sends invitation)
 * - Transfer fees and subscription value calculation
 * - Token-based acceptance flow
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_transfers', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('tenant_id')
                ->constrained('tenants')
                ->cascadeOnDelete();

            $table->foreignUuid('from_customer_id')
                ->constrained('customers')
                ->cascadeOnDelete();

            // Target (can be existing customer or new email)
            $table->foreignUuid('to_customer_id')
                ->nullable()
                ->constrained('customers')
                ->nullOnDelete();
            $table->string('to_email');

            // Transfer details
            $table->decimal('transfer_fee', 10, 2)->default(0);
            $table->string('transfer_fee_currency', 3)->default('brl');
            $table->decimal('remaining_subscription_value', 10, 2)->default(0);

            // Security
            $table->string('token', 64)->unique();
            $table->timestamp('expires_at');

            // Status
            $table->enum('status', [
                'pending',      // Waiting for recipient to accept
                'accepted',     // Recipient accepted
                'completed',    // Transfer finalized
                'cancelled',    // Cancelled by initiator
                'expired',      // Token expired
                'rejected',     // Rejected by recipient
            ])->default('pending');

            $table->text('notes')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index('token');
            $table->index('status');
            $table->index('to_email');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_transfers');
    }
};
