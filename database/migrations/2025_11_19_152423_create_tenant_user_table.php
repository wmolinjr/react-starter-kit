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
        Schema::create('tenant_user', function (Blueprint $table) {
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // ROLES & PERMISSIONS: Gerenciados via Spatie laravel-permission
            // Removido: enum('role') e json('permissions')
            // Agora usa: model_has_roles e model_has_permissions tables

            $table->timestamp('invited_at')->nullable();
            $table->string('invitation_token')->nullable()->unique();
            $table->timestamp('joined_at')->nullable();
            $table->timestamps();

            $table->primary(['tenant_id', 'user_id']);
            $table->index('user_id');
            $table->index('invitation_token');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenant_user');
    }
};
