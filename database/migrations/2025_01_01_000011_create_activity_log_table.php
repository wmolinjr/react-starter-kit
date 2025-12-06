<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Central Migration: Activity Log Table (Spatie ActivityLog)
 *
 * MULTI-DATABASE TENANCY:
 * - This migration runs on the CENTRAL database
 * - For central/admin activity logs
 * - NO tenant_id - this is the central context only
 * - Each tenant has its own activity_log table in their database
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create(config('activitylog.table_name', 'activity_log'), function (Blueprint $table) {
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
