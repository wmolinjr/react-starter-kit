<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tenant Migration: Add global_id to Users Table
 *
 * Adds global_id for Resource Syncing with Central\Customer.
 * Only set for owners (users who are also Customers in central DB).
 *
 * Note: This is DIFFERENT from federated_user_id:
 * - global_id: Links to Central\Customer for BILLING sync
 * - federated_user_id: Links to FederatedUser for MULTI-BRANCH sync
 *
 * A user can have both (billing customer who is also part of federation).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Link to Central\Customer for Resource Syncing
            // Only set for owners (users who are also Customers)
            $table->string('global_id')
                ->nullable()
                ->unique()
                ->after('id');

            $table->index('global_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['global_id']);
            $table->dropColumn('global_id');
        });
    }
};
