<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('domains', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('domain')->unique()->comment('Custom domain (e.g., "www.cliente.com")');
            $table->boolean('is_primary')->default(false)->comment('Primary domain for canonical URLs');
            $table->enum('verification_status', ['pending', 'verified', 'failed'])->default('pending');
            $table->string('verification_token')->nullable()->comment('Token for DNS TXT record verification');
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'is_primary']);
            $table->index('verification_status');
        });

        // Migrate existing domains from tenants table to domains table
        DB::statement("
            INSERT INTO domains (tenant_id, domain, is_primary, verification_status, verified_at, created_at, updated_at)
            SELECT id, domain, true, 'verified', NOW(), created_at, updated_at
            FROM tenants
            WHERE domain IS NOT NULL AND domain != ''
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('domains');
    }
};
