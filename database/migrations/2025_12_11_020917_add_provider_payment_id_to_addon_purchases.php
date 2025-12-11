<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds provider_payment_id for multi-gateway support.
     * Allows tracking PIX/Boleto payments from Asaas, PagSeguro, MercadoPago etc.
     */
    public function up(): void
    {
        Schema::table('addon_purchases', function (Blueprint $table) {
            $table->string('provider_payment_id')->nullable()->after('stripe_invoice_id');
            $table->index('provider_payment_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('addon_purchases', function (Blueprint $table) {
            $table->dropIndex(['provider_payment_id']);
            $table->dropColumn('provider_payment_id');
        });
    }
};
