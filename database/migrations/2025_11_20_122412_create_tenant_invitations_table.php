<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Central Migration: Tenant Invitations Table
 *
 * OPTION C ARCHITECTURE:
 * - Invitations use email (not user_id) since users don't exist until they accept
 * - invited_by_user_id is UUID reference only (NO FK - users in tenant DB)
 * - When accepted, user is created in tenant database
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_invitations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            // Option C: Use email instead of user_id (user doesn't exist until accepted)
            $table->string('email');
            // Option C: No FK constraint - invited_by user is in tenant database
            $table->uuid('invited_by_user_id')->nullable()->index();
            $table->string('role'); // Role to assign when accepted (owner, admin, member)
            $table->string('invitation_token', 64)->unique();
            $table->timestamp('invited_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('expires_at');
            $table->timestamps();

            // Ensure one active invitation per email per tenant
            $table->unique(['tenant_id', 'email', 'invitation_token']);
            $table->index(['invitation_token', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_invitations');
    }
};
