<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Central Migration: Subscriptions Table
 *
 * OPTION C ARCHITECTURE:
 * - Subscriptions belong to tenants (not users)
 * - user_id is optional reference (NO FK - users are in tenant databases)
 * - Tenant is the billable entity, user just tracks who created it
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            // Option C: user_id is reference only, no FK (users in tenant DB)
            $table->uuid('user_id')->nullable()->index();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->string('stripe_id')->unique();
            $table->string('stripe_status');
            $table->string('stripe_price')->nullable();
            $table->integer('quantity')->nullable();
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'stripe_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
