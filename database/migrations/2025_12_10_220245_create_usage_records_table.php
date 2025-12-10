<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('usage_records', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Tenant reference
            $table->foreignUuid('tenant_id')
                ->constrained('tenants')
                ->cascadeOnDelete();

            // Addon reference (optional - for addon-specific usage)
            $table->foreignUuid('addon_id')
                ->nullable()
                ->constrained('addons')
                ->nullOnDelete();

            // Usage type (storage, bandwidth, api_calls, etc.)
            $table->string('usage_type', 50);

            // Quantity used
            $table->bigInteger('quantity');

            // Unit of measurement (MB, GB, requests, etc.)
            $table->string('unit', 20)->default('units');

            // Plan limit at time of recording (for historical context)
            $table->bigInteger('plan_limit')->nullable();

            // Overage amount (if any)
            $table->bigInteger('overage')->default(0);

            // Price per unit at time of recording (in cents)
            $table->integer('unit_price')->default(0);

            // Total cost calculated (in cents)
            $table->integer('total_cost')->default(0);

            // Whether this record was reported to billing provider
            $table->boolean('reported_to_provider')->default(false);
            $table->timestamp('reported_at')->nullable();
            $table->string('provider_reference_id')->nullable(); // Stripe meter event ID

            // Billing period info
            $table->date('billing_period_start');
            $table->date('billing_period_end');

            // Additional metadata
            $table->json('metadata')->nullable();

            $table->timestamps();

            // Indexes for efficient queries
            $table->index(['tenant_id', 'usage_type', 'billing_period_start']);
            $table->index(['tenant_id', 'reported_to_provider']);
            $table->index(['billing_period_start', 'billing_period_end']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('usage_records');
    }
};
