<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Central Migration: Add customer_id to Subscriptions
 *
 * Subscriptions now belong to Customer (the billable entity),
 * not just Tenant. This enables:
 * - Unified billing across multiple tenants
 * - Customer portal showing all subscriptions
 * - Proper Cashier integration with Customer model
 *
 * tenant_id is kept for backward compatibility and to identify
 * which tenant the subscription is for.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            // Add customer_id (subscriptions now belong to Customer)
            $table->foreignUuid('customer_id')
                ->nullable()
                ->after('id')
                ->constrained('customers')
                ->cascadeOnDelete();

            $table->index('customer_id');
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropForeign(['customer_id']);
            $table->dropColumn('customer_id');
        });
    }
};
