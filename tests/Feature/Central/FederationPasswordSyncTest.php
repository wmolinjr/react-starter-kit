<?php

namespace Tests\Feature\Central;

use App\Models\Central\FederatedUser;
use App\Models\Central\FederatedUserLink;
use App\Models\Central\FederationGroup;
use App\Models\Central\Tenant;
use App\Services\Central\FederationService;
use App\Services\Central\FederationSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class FederationPasswordSyncTest extends TestCase
{
    use RefreshDatabase;

    protected FederationService $federationService;

    protected FederationSyncService $syncService;

    protected Tenant $masterTenant;

    protected Tenant $branchTenant1;

    protected Tenant $branchTenant2;

    protected FederationGroup $group;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed plans
        \Artisan::call('db:seed', ['--class' => 'PlanSeeder']);

        // Sync permissions
        \Artisan::call('permissions:sync');

        $this->federationService = app(FederationService::class);
        $this->syncService = app(FederationSyncService::class);

        // Get professional plan for testing
        $professionalPlan = \App\Models\Central\Plan::where('slug', 'professional')->first();

        // Create test tenants
        $this->masterTenant = Tenant::factory()->create([
            'name' => 'Master Corp',
            'slug' => 'master-corp',
            'plan_id' => $professionalPlan?->id,
        ]);
        $this->masterTenant->domains()->create([
            'domain' => 'master-corp.test',
            'is_primary' => true,
        ]);

        $this->branchTenant1 = Tenant::factory()->create([
            'name' => 'Branch One',
            'slug' => 'branch-one',
            'plan_id' => $professionalPlan?->id,
        ]);
        $this->branchTenant1->domains()->create([
            'domain' => 'branch-one.test',
            'is_primary' => true,
        ]);

        $this->branchTenant2 = Tenant::factory()->create([
            'name' => 'Branch Two',
            'slug' => 'branch-two',
            'plan_id' => $professionalPlan?->id,
        ]);
        $this->branchTenant2->domains()->create([
            'domain' => 'branch-two.test',
            'is_primary' => true,
        ]);

        // Create federation group
        $this->group = $this->federationService->createGroup(
            name: 'Test Federation',
            masterTenant: $this->masterTenant,
            syncStrategy: FederationGroup::STRATEGY_MASTER_WINS
        );

        // Add branch tenants to group
        $this->federationService->addTenantToGroup($this->group, $this->branchTenant1);
        $this->federationService->addTenantToGroup($this->group, $this->branchTenant2);
    }

    // =========================================================================
    // Password Storage Tests
    // =========================================================================

    public function test_password_hash_is_stored_in_synced_data(): void
    {
        $hashedPassword = Hash::make('secret-password');

        $federatedUser = $this->federationService->createFederatedUser(
            group: $this->group,
            email: 'john@example.com',
            syncedData: [
                'name' => 'John Doe',
                'password_hash' => $hashedPassword,
            ],
            masterTenant: $this->masterTenant,
            masterTenantUserId: 'user-123'
        );

        $this->assertEquals($hashedPassword, $federatedUser->getPasswordHash());
    }

    public function test_password_hash_can_be_updated(): void
    {
        $initialPassword = Hash::make('initial-password');
        $newPassword = Hash::make('new-password');

        $federatedUser = $this->federationService->createFederatedUser(
            group: $this->group,
            email: 'john@example.com',
            syncedData: [
                'name' => 'John Doe',
                'password_hash' => $initialPassword,
            ],
            masterTenant: $this->masterTenant,
            masterTenantUserId: 'user-123'
        );

        $federatedUser->updateSyncedField('password_hash', $newPassword);

        $federatedUser->refresh();

        $this->assertEquals($newPassword, $federatedUser->getPasswordHash());
        $this->assertNotEquals($initialPassword, $federatedUser->getPasswordHash());
    }

    // =========================================================================
    // Password Sync Service Tests
    // =========================================================================

    public function test_password_sync_updates_federated_user_data(): void
    {
        $federatedUser = $this->federationService->createFederatedUser(
            group: $this->group,
            email: 'john@example.com',
            syncedData: [
                'name' => 'John Doe',
                'password_hash' => Hash::make('old-password'),
            ],
            masterTenant: $this->masterTenant,
            masterTenantUserId: 'user-123'
        );

        $newHashedPassword = Hash::make('new-secure-password');

        $results = $this->syncService->syncPasswordToAllTenants(
            $federatedUser,
            $newHashedPassword,
            $this->masterTenant->id
        );

        $federatedUser->refresh();

        $this->assertEquals($newHashedPassword, $federatedUser->getPasswordHash());
        $this->assertNotNull($federatedUser->getSyncedField('password_changed_at'));
    }

    public function test_password_sync_excludes_source_tenant(): void
    {
        $federatedUser = $this->federationService->createFederatedUser(
            group: $this->group,
            email: 'john@example.com',
            syncedData: [
                'name' => 'John Doe',
                'password_hash' => Hash::make('old-password'),
            ],
            masterTenant: $this->masterTenant,
            masterTenantUserId: 'user-123'
        );

        // Add links to branch tenants
        $this->federationService->createUserLink($federatedUser, $this->branchTenant1, 'branch1-user');
        $this->federationService->createUserLink($federatedUser, $this->branchTenant2, 'branch2-user');

        $results = $this->syncService->syncPasswordToAllTenants(
            $federatedUser,
            Hash::make('new-password'),
            $this->masterTenant->id // Exclude master tenant
        );

        // Master tenant should not be in results (skipped as source)
        $successTenantIds = collect($results['success'])->pluck('tenant_id')->toArray();
        $skippedTenantIds = collect($results['skipped'])->pluck('tenant_id')->toArray();

        $this->assertNotContains($this->masterTenant->id, $successTenantIds);
    }

    // =========================================================================
    // Password Changed Timestamp Tests
    // =========================================================================

    public function test_password_changed_at_is_updated_on_sync(): void
    {
        $federatedUser = $this->federationService->createFederatedUser(
            group: $this->group,
            email: 'john@example.com',
            syncedData: [
                'name' => 'John Doe',
                'password_hash' => Hash::make('old-password'),
            ],
            masterTenant: $this->masterTenant,
            masterTenantUserId: 'user-123'
        );

        $this->assertNull($federatedUser->getSyncedField('password_changed_at'));

        $this->syncService->syncPasswordToAllTenants(
            $federatedUser,
            Hash::make('new-password'),
            $this->masterTenant->id
        );

        $federatedUser->refresh();

        $passwordChangedAt = $federatedUser->getSyncedField('password_changed_at');
        $this->assertNotNull($passwordChangedAt);
    }

    // =========================================================================
    // Sync Version Tests
    // =========================================================================

    public function test_sync_version_increments_on_password_change(): void
    {
        $federatedUser = $this->federationService->createFederatedUser(
            group: $this->group,
            email: 'john@example.com',
            syncedData: [
                'name' => 'John Doe',
                'password_hash' => Hash::make('old-password'),
            ],
            masterTenant: $this->masterTenant,
            masterTenantUserId: 'user-123'
        );

        $initialVersion = $federatedUser->sync_version;

        $this->syncService->syncPasswordToAllTenants(
            $federatedUser,
            Hash::make('new-password'),
            $this->masterTenant->id
        );

        $federatedUser->refresh();

        $this->assertGreaterThan($initialVersion, $federatedUser->sync_version);
    }

    // =========================================================================
    // Sync Strategy Tests (Master Wins)
    // =========================================================================

    public function test_master_wins_allows_master_tenant_password_sync(): void
    {
        // Group is created with MASTER_WINS strategy by default

        $federatedUser = $this->federationService->createFederatedUser(
            group: $this->group,
            email: 'john@example.com',
            syncedData: [
                'name' => 'John Doe',
                'password_hash' => Hash::make('old-password'),
            ],
            masterTenant: $this->masterTenant,
            masterTenantUserId: 'user-123'
        );

        $canSync = $this->syncService->canTenantInitiateSync($this->group, $this->masterTenant);

        $this->assertTrue($canSync);
    }

    public function test_master_wins_blocks_non_master_tenant_password_sync(): void
    {
        // Group is created with MASTER_WINS strategy by default

        $federatedUser = $this->federationService->createFederatedUser(
            group: $this->group,
            email: 'john@example.com',
            syncedData: [
                'name' => 'John Doe',
                'password_hash' => Hash::make('old-password'),
            ],
            masterTenant: $this->masterTenant,
            masterTenantUserId: 'user-123'
        );

        $canSync = $this->syncService->canTenantInitiateSync($this->group, $this->branchTenant1);

        $this->assertFalse($canSync);
    }

    // =========================================================================
    // Sync Strategy Tests (Last Write Wins)
    // =========================================================================

    public function test_last_write_wins_allows_any_tenant_password_sync(): void
    {
        // Create group with LAST_WRITE_WINS strategy
        $lastWriteGroup = FederationGroup::create([
            'name' => 'Last Write Group',
            'master_tenant_id' => $this->masterTenant->id,
            'sync_strategy' => FederationGroup::STRATEGY_LAST_WRITE_WINS,
            'is_active' => true,
        ]);

        $canMasterSync = $this->syncService->canTenantInitiateSync($lastWriteGroup, $this->masterTenant);
        $canBranchSync = $this->syncService->canTenantInitiateSync($lastWriteGroup, $this->branchTenant1);

        $this->assertTrue($canMasterSync);
        $this->assertTrue($canBranchSync);
    }

    // =========================================================================
    // Sync Strategy Tests (Manual Review)
    // =========================================================================

    public function test_manual_review_allows_any_tenant_password_sync(): void
    {
        // Create group with MANUAL_REVIEW strategy
        $manualReviewGroup = FederationGroup::create([
            'name' => 'Manual Review Group',
            'master_tenant_id' => $this->masterTenant->id,
            'sync_strategy' => FederationGroup::STRATEGY_MANUAL_REVIEW,
            'is_active' => true,
        ]);

        $canMasterSync = $this->syncService->canTenantInitiateSync($manualReviewGroup, $this->masterTenant);
        $canBranchSync = $this->syncService->canTenantInitiateSync($manualReviewGroup, $this->branchTenant1);

        $this->assertTrue($canMasterSync);
        $this->assertTrue($canBranchSync);
    }

    // =========================================================================
    // Validation Tests
    // =========================================================================

    public function test_validate_sync_permission_fails_for_inactive_group(): void
    {
        $this->group->update(['is_active' => false]);

        $this->expectException(\App\Exceptions\Central\FederationException::class);

        $this->syncService->validateSyncPermission($this->group, $this->masterTenant);
    }

    public function test_validate_sync_permission_fails_for_non_member_tenant(): void
    {
        $nonMemberTenant = Tenant::factory()->create([
            'name' => 'Non Member',
            'slug' => 'non-member',
        ]);

        $this->expectException(\App\Exceptions\Central\FederationException::class);

        $this->syncService->validateSyncPermission($this->group, $nonMemberTenant);
    }

    public function test_validate_sync_permission_fails_when_sync_disabled(): void
    {
        // Disable sync for branch tenant
        $membership = $this->group->tenants()
            ->wherePivot('tenant_id', $this->branchTenant1->id)
            ->first();

        $membership->pivot->update(['sync_enabled' => false]);

        $this->expectException(\App\Exceptions\Central\FederationException::class);

        $this->syncService->validateSyncPermission($this->group, $this->branchTenant1);
    }

    public function test_validate_sync_permission_succeeds_for_valid_member(): void
    {
        // Should not throw exception
        $this->syncService->validateSyncPermission($this->group, $this->masterTenant);
        $this->syncService->validateSyncPermission($this->group, $this->branchTenant1);

        $this->assertTrue(true); // If we get here, validation passed
    }

    // =========================================================================
    // Link Status After Password Sync Tests
    // =========================================================================

    public function test_links_are_marked_pending_before_password_sync(): void
    {
        $federatedUser = $this->federationService->createFederatedUser(
            group: $this->group,
            email: 'john@example.com',
            syncedData: [
                'name' => 'John Doe',
                'password_hash' => Hash::make('old-password'),
            ],
            masterTenant: $this->masterTenant,
            masterTenantUserId: 'user-123'
        );

        // Create link and mark as pending
        $link = FederatedUserLink::create([
            'federated_user_id' => $federatedUser->id,
            'tenant_id' => $this->branchTenant1->id,
            'tenant_user_id' => 'branch-user-1',
            'sync_status' => FederatedUserLink::STATUS_PENDING_SYNC,
        ]);

        $this->assertEquals(FederatedUserLink::STATUS_PENDING_SYNC, $link->sync_status);
    }

    // =========================================================================
    // 2FA Sync Tests
    // =========================================================================

    public function test_two_factor_data_can_be_synced(): void
    {
        $federatedUser = $this->federationService->createFederatedUser(
            group: $this->group,
            email: 'john@example.com',
            syncedData: [
                'name' => 'John Doe',
                'password_hash' => Hash::make('password'),
            ],
            masterTenant: $this->masterTenant,
            masterTenantUserId: 'user-123'
        );

        $twoFactorData = [
            'two_factor_enabled' => true,
            'two_factor_secret' => 'encrypted-secret',
            'two_factor_recovery_codes' => 'encrypted-codes',
            'two_factor_confirmed_at' => now()->toIso8601String(),
        ];

        $results = $this->syncService->syncTwoFactorToAllTenants(
            $federatedUser,
            $twoFactorData,
            $this->masterTenant->id
        );

        $federatedUser->refresh();

        $this->assertTrue($federatedUser->hasTwoFactorEnabled());
        $this->assertEquals('encrypted-secret', $federatedUser->getSyncedField('two_factor_secret'));
        $this->assertEquals('encrypted-codes', $federatedUser->getSyncedField('two_factor_recovery_codes'));
    }

    public function test_two_factor_can_be_disabled(): void
    {
        $federatedUser = $this->federationService->createFederatedUser(
            group: $this->group,
            email: 'john@example.com',
            syncedData: [
                'name' => 'John Doe',
                'password_hash' => Hash::make('password'),
                'two_factor_enabled' => true,
                'two_factor_secret' => 'old-secret',
            ],
            masterTenant: $this->masterTenant,
            masterTenantUserId: 'user-123'
        );

        $this->assertTrue($federatedUser->hasTwoFactorEnabled());

        $twoFactorData = [
            'two_factor_enabled' => false,
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ];

        $this->syncService->syncTwoFactorToAllTenants(
            $federatedUser,
            $twoFactorData,
            $this->masterTenant->id
        );

        $federatedUser->refresh();

        $this->assertFalse($federatedUser->hasTwoFactorEnabled());
        $this->assertNull($federatedUser->getSyncedField('two_factor_secret'));
    }
}
