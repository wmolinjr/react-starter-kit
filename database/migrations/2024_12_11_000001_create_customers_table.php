<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Central Migration: Customers Table
 *
 * Customer is the BILLING ENTITY in the new architecture:
 * - One Customer = One Stripe Customer
 * - Customer can own multiple Tenants
 * - Implements SyncMaster for Resource Syncing with Tenant\User
 *
 * This replaces Tenant as the primary Billable entity.
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

            // Stripe Billable (Laravel Cashier)
            $table->string('stripe_id')->nullable()->unique();
            $table->string('pm_type')->nullable();
            $table->string('pm_last_four', 4)->nullable();
            $table->timestamp('trial_ends_at')->nullable();

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
            $table->index('stripe_id');
            $table->index('global_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
