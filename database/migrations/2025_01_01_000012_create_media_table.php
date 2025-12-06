<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Central Migration: Media Table (Spatie MediaLibrary)
 *
 * MULTI-DATABASE TENANCY:
 * - This migration runs on the CENTRAL database
 * - For central/admin media files
 * - NO tenant_id - this is the central context only
 * - Each tenant has its own media table in their database
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuidMorphs('model'); // UUID for User/Tenant models
            $table->uuid('uuid')->nullable()->unique();
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
