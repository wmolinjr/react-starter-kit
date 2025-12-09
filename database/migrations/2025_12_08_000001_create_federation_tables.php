<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Central Migration: Federation Tables
 *
 * USER SYNC FEDERATION:
 * - Allows groups of tenants to synchronize user data
 * - Master tenant is the "source of truth"
 * - Synced data: email, password, name, avatar, 2FA settings
 * - NOT synced: roles, permissions (remain local to each tenant)
 *
 * Tables:
 * - federation_groups: Groups of tenants that sync users
 * - federation_group_tenants: Pivot for group membership
 * - federated_users: Central record of synced users
 * - federated_user_links: Links between central and tenant users
 * - federation_conflicts: Tracks unresolved data conflicts
 *
 * Note: Audit trail is handled by Spatie Activity Log (LogsActivity trait on models)
 */
return new class extends Migration
{
    public function up(): void
    {
        // Federation Groups
        Schema::create('federation_groups', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->text('description')->nullable();

            $table->foreignUuid('master_tenant_id')
                ->constrained('tenants')
                ->cascadeOnDelete();

            // Sync strategy for conflict resolution
            $table->enum('sync_strategy', [
                'master_wins',      // Master always prevails
                'last_write_wins',  // Last update prevails
                'manual_review',    // Conflicts go to review queue
            ])->default('master_wins');

            // Group settings
            $table->json('settings')->nullable();
            // settings = {
            //   "sync_fields": ["name", "email", "password", "avatar", "two_factor"],
            //   "auto_create_on_login": true,
            //   "require_email_verification": false,
            //   "notification_email": "admin@acme.com"
            // }

            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('master_tenant_id');
            $table->index('is_active');
        });

        // Federation Group Tenants (Pivot)
        Schema::create('federation_group_tenants', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('federation_group_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignUuid('tenant_id')
                ->constrained()
                ->cascadeOnDelete();

            // Participation status
            $table->boolean('sync_enabled')->default(true);
            $table->timestamp('joined_at');
            $table->timestamp('left_at')->nullable();

            // Tenant-specific settings within the group
            $table->json('settings')->nullable();
            // settings = {
            //   "default_role": "member",
            //   "auto_accept_users": true,
            //   "require_approval": false
            // }

            $table->timestamps();

            // Constraints
            $table->unique(['federation_group_id', 'tenant_id']);
            $table->unique('tenant_id'); // A tenant can only be in one group

            $table->index('sync_enabled');
        });

        // Federated Users (Central record)
        Schema::create('federated_users', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('federation_group_id')
                ->constrained()
                ->cascadeOnDelete();

            // Canonical email (source of truth)
            $table->string('global_email');

            // Synced data cache (for performance)
            $table->json('synced_data');
            // synced_data = {
            //   "name": "John Doe",
            //   "avatar_url": "https://...",
            //   "locale": "pt_BR",
            //   "two_factor_enabled": true,
            //   "two_factor_secret": "encrypted...",
            //   "two_factor_recovery_codes": "encrypted...",
            //   "password_hash": "$2y$...",
            //   "password_changed_at": "2024-01-15T10:30:00Z"
            // }

            // Reference to master tenant user
            $table->foreignUuid('master_tenant_id')
                ->constrained('tenants')
                ->cascadeOnDelete();
            $table->uuid('master_tenant_user_id'); // User ID in master tenant's database

            // Sync control
            $table->timestamp('last_synced_at')->nullable();
            $table->string('last_sync_source')->nullable(); // tenant_id that originated last sync
            $table->integer('sync_version')->default(1);

            // Status
            $table->enum('status', ['active', 'suspended', 'pending_review'])->default('active');

            $table->timestamps();
            $table->softDeletes();

            // Constraints
            $table->unique(['federation_group_id', 'global_email']);

            // Indexes
            $table->index('global_email');
            $table->index('master_tenant_user_id');
            $table->index('status');
        });

        // Federated User Links (Links central to tenant users)
        Schema::create('federated_user_links', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('federated_user_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignUuid('tenant_id')
                ->constrained()
                ->cascadeOnDelete();

            // User ID in the tenant's database
            $table->uuid('tenant_user_id');

            // Link status
            $table->enum('sync_status', [
                'synced',           // Synchronized
                'pending_sync',     // Awaiting sync
                'sync_failed',      // Failed (retry pending)
                'conflict',         // Conflict detected
                'disabled',         // Sync disabled manually
            ])->default('synced');

            // Sync control
            $table->timestamp('last_synced_at')->nullable();
            $table->integer('sync_attempts')->default(0);
            $table->text('last_sync_error')->nullable();

            // Metadata
            $table->json('metadata')->nullable();
            // metadata = {
            //   "created_via": "auto_sync",  // auto_sync, manual_link, import
            //   "original_role": "member",
            //   "notes": "..."
            // }

            $table->timestamps();

            // Constraints
            $table->unique(['federated_user_id', 'tenant_id']);
            $table->unique(['tenant_id', 'tenant_user_id']);

            // Indexes
            $table->index('sync_status');
            $table->index('tenant_user_id');
        });

        // Federation Conflicts (Unresolved conflicts)
        Schema::create('federation_conflicts', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('federated_user_id')
                ->constrained()
                ->cascadeOnDelete();

            // Conflicting field
            $table->string('field'); // e.g., 'name', 'password'

            // Conflicting values
            $table->json('values');
            // values = {
            //   "tenant_xxx": {"value": "John", "updated_at": "..."},
            //   "tenant_yyy": {"value": "Johnny", "updated_at": "..."}
            // }

            // Resolution status
            $table->enum('status', ['pending', 'resolved', 'dismissed'])->default('pending');

            // Resolution details
            $table->foreignUuid('resolved_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('resolution')->nullable(); // 'master_value', 'manual', 'dismissed'
            $table->text('resolution_notes')->nullable();
            $table->timestamp('resolved_at')->nullable();

            $table->timestamps();

            // Indexes
            $table->index('status');
            $table->index('field');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('federation_conflicts');
        Schema::dropIfExists('federated_user_links');
        Schema::dropIfExists('federated_users');
        Schema::dropIfExists('federation_group_tenants');
        Schema::dropIfExists('federation_groups');
    }
};
