<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Central Migration: Add customer_id to Tenants
 *
 * Links Tenant to Customer (who pays for this tenant).
 * Different from customer_tenants pivot which handles Resource Syncing access.
 *
 * Also adds payment_method_id for tenant-specific payment method override.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            // Who pays for this tenant
            $table->foreignUuid('customer_id')
                ->nullable()
                ->after('id')
                ->constrained('customers')
                ->nullOnDelete();

            // Optional: Override payment method for this tenant
            // Allows different payment methods per tenant for same customer
            $table->string('payment_method_id')->nullable()->after('customer_id');

            $table->index('customer_id');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropForeign(['customer_id']);
            $table->dropColumn(['customer_id', 'payment_method_id']);
        });
    }
};
