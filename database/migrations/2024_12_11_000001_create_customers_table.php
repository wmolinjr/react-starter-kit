<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Central Migration: Customers Table
 *
 * Customer is the BILLING ENTITY in the architecture:
 * - One Customer = Multiple Payment Provider Customers (via provider_ids)
 * - Customer can own multiple Tenants
 * - Implements SyncMaster for Resource Syncing with Tenant\User
 *
 * PROVIDER-AGNOSTIC: No stripe_* columns. Uses provider_ids JSON for multi-provider support.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Resource Syncing identifier
            // Links Customer to Tenant\User via global_id
            $table->string('global_id')->unique();

            // Identity
            $table->string('name');
            $table->string('email')->unique();
            $table->string('phone')->nullable();
            $table->string('password');
            $table->rememberToken();

            // Provider-Agnostic Billing
            // Stores customer IDs in each payment provider
            // Example: {"stripe": "cus_xxx", "asaas": "cus_yyy"}
            $table->json('provider_ids')->nullable();

            // Default payment method reference
            $table->uuid('default_payment_method_id')->nullable();

            // Billing Information
            $table->json('billing_address')->nullable();
            $table->string('locale', 10)->default('pt_BR');
            $table->string('currency', 3)->default('brl');
            $table->json('tax_ids')->nullable();

            // Authentication
            $table->timestamp('email_verified_at')->nullable();
            $table->text('two_factor_secret')->nullable();
            $table->text('two_factor_recovery_codes')->nullable();
            $table->timestamp('two_factor_confirmed_at')->nullable();

            // Metadata
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('email');
            $table->index('global_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
