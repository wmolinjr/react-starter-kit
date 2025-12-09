<?php

namespace Database\Seeders;

use App\Enums\FederatedUserStatus;
use App\Models\Central\FederatedUser;
use App\Models\Central\FederatedUserLink;
use App\Models\Central\FederationGroup;
use App\Models\Central\FederationGroupTenant;
use App\Models\Central\Tenant;
use App\Models\Tenant\User as TenantUser;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * FederationSeeder
 *
 * Creates test data for User Sync Federation feature.
 *
 * Test Scenario:
 * - Federation Group "ACME Corporation" with 2 tenants (tenant1, tenant2)
 * - tenant1 (Acme Corporation) is the master
 * - tenant2 (Startup Inc) is a member
 * - Shared user: shared@acme.com exists in both tenants
 *
 * Test Users:
 * - shared@acme.com: Federated user (owner in tenant1, member in tenant2)
 * - john@acme.com: Local-only owner in tenant1 (not federated)
 * - jane@startup.com: Local-only owner in tenant2 (not federated)
 *
 * NOTE: This seeder runs AFTER TenantSeeder because it needs existing tenants.
 */
class FederationSeeder extends Seeder
{
    public function run(): void
    {
        $tenant1 = Tenant::where('slug', 'acme')->first();
        $tenant2 = Tenant::where('slug', 'startup')->first();

        if (!$tenant1 || !$tenant2) {
            $this->command->warn('Tenants not found. Skipping FederationSeeder.');
            $this->command->warn('Run TenantSeeder first to create tenants.');
            return;
        }

        $this->command->info('Creating Federation Group "ACME Corporation"...');

        // Create Federation Group with tenant1 as master
        $group = FederationGroup::create([
            'name' => 'ACME Corporation',
            'description' => 'Federation group for ACME subsidiaries',
            'master_tenant_id' => $tenant1->id,
            'sync_strategy' => FederationGroup::STRATEGY_MASTER_WINS,
            'settings' => [
                'sync_fields' => FederationGroup::DEFAULT_SYNC_FIELDS,
                'auto_create_on_login' => true,
                'require_email_verification' => false,
                'notification_email' => 'admin@acme.com',
            ],
            'is_active' => true,
        ]);

        // Add tenant1 (master) to the group
        FederationGroupTenant::create([
            'federation_group_id' => $group->id,
            'tenant_id' => $tenant1->id,
            'sync_enabled' => true,
            'joined_at' => now(),
            'settings' => [
                'default_role' => 'member',
                'auto_accept_users' => true,
            ],
        ]);

        // Add tenant2 (member) to the group
        FederationGroupTenant::create([
            'federation_group_id' => $group->id,
            'tenant_id' => $tenant2->id,
            'sync_enabled' => true,
            'joined_at' => now(),
            'settings' => [
                'default_role' => 'member',
                'auto_accept_users' => true,
            ],
        ]);

        $this->command->info('  - Created group with tenant1 (master) and tenant2 (member)');

        // Create shared user in tenant1 (master)
        $sharedUserTenant1 = null;
        $tenant1->run(function () use (&$sharedUserTenant1) {
            $sharedUserTenant1 = TenantUser::create([
                'name' => 'Shared User',
                'email' => 'shared@acme.com',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'locale' => 'pt_BR',
            ]);
            $sharedUserTenant1->assignRole('admin');
        });

        // Create FederatedUser record in central DB
        $federatedUser = FederatedUser::create([
            'federation_group_id' => $group->id,
            'global_email' => 'shared@acme.com',
            'synced_data' => [
                'name' => 'Shared User',
                'password_hash' => Hash::make('password'),
                'locale' => 'pt_BR',
                'two_factor_enabled' => false,
            ],
            'master_tenant_id' => $tenant1->id,
            'master_tenant_user_id' => $sharedUserTenant1->id,
            'last_synced_at' => now(),
            'last_sync_source' => $tenant1->id,
            'sync_version' => 1,
            'status' => FederatedUserStatus::ACTIVE,
        ]);

        // Create link for tenant1
        FederatedUserLink::create([
            'federated_user_id' => $federatedUser->id,
            'tenant_id' => $tenant1->id,
            'tenant_user_id' => $sharedUserTenant1->id,
            'sync_status' => FederatedUserLink::STATUS_SYNCED,
            'last_synced_at' => now(),
            'metadata' => [
                'created_via' => FederatedUserLink::CREATED_VIA_AUTO_SYNC,
                'original_role' => 'admin',
            ],
        ]);

        // Update tenant1 user with federated_user_id
        $tenant1->run(function () use ($sharedUserTenant1, $federatedUser) {
            $sharedUserTenant1->federated_user_id = $federatedUser->id;
            $sharedUserTenant1->save();
        });

        $this->command->info('  - Created shared@acme.com as admin in tenant1 (master)');

        // Create same user in tenant2 (member) - synced from master
        $sharedUserTenant2 = null;
        $tenant2->run(function () use (&$sharedUserTenant2, $federatedUser) {
            $sharedUserTenant2 = TenantUser::create([
                'name' => 'Shared User',
                'email' => 'shared@acme.com',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'locale' => 'pt_BR',
                'federated_user_id' => $federatedUser->id,
            ]);
            $sharedUserTenant2->assignRole('member');
        });

        // Create link for tenant2
        FederatedUserLink::create([
            'federated_user_id' => $federatedUser->id,
            'tenant_id' => $tenant2->id,
            'tenant_user_id' => $sharedUserTenant2->id,
            'sync_status' => FederatedUserLink::STATUS_SYNCED,
            'last_synced_at' => now(),
            'metadata' => [
                'created_via' => FederatedUserLink::CREATED_VIA_AUTO_SYNC,
                'original_role' => 'member',
            ],
        ]);

        $this->command->info('  - Created shared@acme.com as member in tenant2 (synced)');

        $this->command->newLine();
        $this->command->info('Federation seeded successfully!');
        $this->command->info('');
        $this->command->info('Test accounts:');
        $this->command->info('  Federated user (same password in both tenants):');
        $this->command->info('    - shared@acme.com / password');
        $this->command->info('    - tenant1.test: admin role');
        $this->command->info('    - tenant2.test: member role');
        $this->command->info('');
        $this->command->info('  Local-only users (not federated):');
        $this->command->info('    - john@acme.com / password (tenant1 owner)');
        $this->command->info('    - jane@startup.com / password (tenant2 owner)');
    }
}
