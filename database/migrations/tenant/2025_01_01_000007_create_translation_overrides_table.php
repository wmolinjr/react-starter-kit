<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tenant Migration: Translation Overrides Table
 *
 * MULTI-DATABASE TENANCY:
 * - NO tenant_id column - isolation is at database level
 * - Each tenant has dedicated database with own translation overrides
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_translation_overrides', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuidMorphs('translatable'); // UUID for models
            $table->string('field', 50);
            $table->json('translations');
            $table->timestamps();

            // Unique: one override per model + field
            $table->unique(
                ['translatable_type', 'translatable_id', 'field'],
                'translation_override_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_translation_overrides');
    }
};
