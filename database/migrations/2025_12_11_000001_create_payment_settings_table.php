<?php

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
        Schema::create('payment_settings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('gateway')->unique(); // stripe, asaas, pagseguro, mercadopago
            $table->string('display_name');
            $table->boolean('is_enabled')->default(false);
            $table->boolean('is_sandbox')->default(true);
            $table->boolean('is_default')->default(false);

            // Encrypted credential storage (separate for sandbox/production)
            $table->text('production_credentials')->nullable(); // encrypted JSON
            $table->text('sandbox_credentials')->nullable(); // encrypted JSON

            // Configuration
            $table->json('enabled_payment_types')->default('[]'); // ['card', 'pix', 'boleto']
            $table->json('available_countries')->default('[]'); // ['BR', 'US']
            $table->json('webhook_urls')->nullable(); // Auto-generated webhook URLs

            // Connection testing
            $table->timestamp('last_tested_at')->nullable();
            $table->boolean('last_test_success')->nullable();
            $table->text('last_test_error')->nullable();

            $table->timestamps();

            $table->index('is_enabled');
            $table->index('is_default');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_settings');
    }
};
