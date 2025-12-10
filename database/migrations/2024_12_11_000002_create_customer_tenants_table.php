<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Central Migration: Customer-Tenant Pivot Table
 *
 * This is the pivot table for Stancl Resource Syncing.
 * Maps Customer.global_id to Tenant.id for automatic sync:
 * - When Customer is attached to Tenant, Tenant\User is created
 * - When Customer is detached from Tenant, Tenant\User is deleted
 *
 * Note: This is DIFFERENT from customer_id on tenants table:
 * - customer_id: Who PAYS for the tenant (ownership)
 * - customer_tenants: Which tenants a Customer has ACCESS to (Resource Syncing)
 *
 * A Customer can have access to tenants they don't own (e.g., invited as admin).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_tenants', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('global_id');  // Customer's global_id
            $table->uuid('tenant_id');    // Tenant's id (UUID type)
            $table->timestamps();

            $table->unique(['global_id', 'tenant_id']);
            $table->index('global_id');
            $table->index('tenant_id');

            // Foreign keys
            $table->foreign('global_id')
                ->references('global_id')
                ->on('customers')
                ->cascadeOnDelete();

            $table->foreign('tenant_id')
                ->references('id')
                ->on('tenants')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_tenants');
    }
};
