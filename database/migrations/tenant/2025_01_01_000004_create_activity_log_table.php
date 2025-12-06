<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tenant Migration: Activity Log Table (Spatie ActivityLog)
 *
 * MULTI-DATABASE TENANCY:
 * - NO tenant_id column - isolation is at database level
 * - Each tenant has dedicated database
 *
 * TESTING:
 * - In tests (SQLite), central tables already exist, so we skip creation
 */
return new class extends Migration
{
    public function up(): void
    {
        $tableName = config('activitylog.table_name', 'activity_log');

        // Skip if table already exists (in tests, central migration already ran)
        if (Schema::hasTable($tableName)) {
            return;
        }

        Schema::create($tableName, function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('log_name')->nullable()->index();
            $table->text('description');
            $table->nullableUuidMorphs('subject', 'subject'); // UUID for User/Tenant models
            $table->string('event')->nullable();
            $table->nullableUuidMorphs('causer', 'causer'); // UUID for User/Tenant models
            $table->json('properties')->nullable();
            $table->uuid('batch_uuid')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('activitylog.table_name', 'activity_log'));
    }
};
