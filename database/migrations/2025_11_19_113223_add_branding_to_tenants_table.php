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
        Schema::table('tenants', function (Blueprint $table) {
            $table->text('description')->nullable()->after('settings');
            $table->string('logo')->nullable()->after('description');
            $table->string('favicon')->nullable()->after('logo');
            $table->string('primary_color', 7)->nullable()->after('favicon');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['description', 'logo', 'favicon', 'primary_color']);
        });
    }
};
