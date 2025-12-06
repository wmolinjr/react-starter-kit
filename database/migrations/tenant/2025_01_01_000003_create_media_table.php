<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tenant Migration: Media Table (Spatie MediaLibrary)
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
        // Skip if table already exists (in tests, central migration already ran)
        if (Schema::hasTable('media')) {
            return;
        }

        Schema::create('media', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuidMorphs('model'); // UUID morphs for all tenant models
            $table->uuid()->nullable()->unique();
            $table->string('collection_name');
            $table->string('name');
            $table->string('file_name');
            $table->string('mime_type')->nullable();
            $table->string('disk');
            $table->string('conversions_disk')->nullable();
            $table->unsignedBigInteger('size');
            $table->json('manipulations');
            $table->json('custom_properties');
            $table->json('generated_conversions');
            $table->json('responsive_images');
            $table->unsignedInteger('order_column')->nullable()->index();
            $table->nullableTimestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media');
    }
};
