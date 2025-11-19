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
        Schema::create('tenant_invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('email')->comment('Email of the invited user');
            $table->string('role')->default('member')->comment('Role: owner, admin, member');
            $table->string('token')->unique()->comment('Unique token for accepting invitation');
            $table->foreignId('invited_by')->nullable()->constrained('users')->nullOnDelete()->comment('User who sent the invitation');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('expires_at')->nullable()->comment('Invitation expiration time');
            $table->timestamps();

            $table->index(['tenant_id', 'email']);
            $table->index('token');
            $table->index('accepted_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenant_invitations');
    }
};
