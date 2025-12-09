<?php

namespace Tests\Feature\Central;

use App\Enums\FederatedUserStatus;
use App\Models\Central\FederatedUser;
use App\Models\Central\FederatedUserLink;
use App\Models\Central\FederationGroup;
use App\Models\Central\FederationGroupTenant;
use App\Models\Central\Tenant;
use App\Services\Central\FederationService;
use App\Services\Central\FederationSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class FederatedUserSyncTest extends TestCase
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
    // Federated User Creation Tests
    // =========================================================================

    public function test_federated_user_can_be_created(): void
    {
        $federatedUser = $this->federationService->createFederatedUser(
            group: $this->group,
            email: 'john@example.com',
            syncedData: [
                'name' => 'John Doe',
                'password_hash' => Hash::make('password'),
                'locale' => 'en',
            ],
            masterTenant: $this->masterTenant,
            masterTenantUserId: 'user-123'
        );

        $this->assertDatabaseHas('federated_users', [
            'id' => $federatedUser->id,
            'global_email' => 'john@example.com',
            'federation_group_id' => $this->group->id,
            'master_tenant_id' => $this->masterTenant->id,
            'status' => FederatedUserStatus::ACTIVE,
        ]);

        // Check link was created for master tenant
        $this->assertDatabaseHas('federated_user_links', [
            'federated_user_id' => $federatedUser->id,
            'tenant_id' => $this->masterTenant->id,
            'tenant_user_id' => 'user-123',
            'sync_status' => FederatedUserLink::STATUS_SYNCED,
        ]);
    }

    public function test_federated_user_stores_synced_data(): void
    {
        $syncedData = [
            'name' => 'John Doe',
            'password_hash' => Hash::make('password'),
            'locale' => 'pt_BR',
            'two_factor_enabled' => false,
        ];

        $federatedUser = $this->federationService->createFederatedUser(
            group: $this->group,
            email: 'john@example.com',
            syncedData: $syncedData,
            masterTenant: $this->masterTenant,
            masterTenantUserId: 'user-123'
        );

        $this->assertEquals('John Doe', $federatedUser->getName());
        $this->assertEquals('pt_BR', $federatedUser->getSyncedField('locale'));
        $this->assertFalse($federatedUser->hasTwoFactorEnabled());
    }

    public function test_federated_user_email_is_normalized_to_lowercase(): void
    {
        $federatedUser = $this->federationService->createFederatedUser(
            group: $this->group,
            email: 'John.Doe@Example.COM',
            syncedData: ['name' => 'John Doe'],
            masterTenant: $this->masterTenant,
            masterTenantUserId: 'user-123'
        );

        $this->assertEquals('john.doe@example.com', $federatedUser->global_email);
    }

    // =========================================================================
    // User Link Tests
    // =========================================================================

    public function test_user_link_can_be_created(): void
    {
        $federatedUser = $this->federationService->createFederatedUser(
            group: $this->group,
            email: 'john@example.com',
            syncedData: ['name' => 'John Doe'],
            masterTenant: $this->masterTenant,
            masterTenantUserId: 'user-123'
        );

        // Create link to branch tenant
        $link = $this->federationService->createUserLink(
            federatedUser: $federatedUser,
            tenant: $this->branchTenant1,
            tenantUserId: 'branch-user-456'
        );

        $this->assertDatabaseHas('federated_user_links', [
            'id' => $link->id,
            'federated_user_id' => $federatedUser->id,
            'tenant_id' => $this->branchTenant1->id,
            'tenant_user_id' => 'branch-user-456',
            'sync_status' => FederatedUserLink::STATUS_SYNCED,
        ]);

        $this->assertTrue($federatedUser->hasLinkToTenant($this->branchTenant1));
    }

    public function test_user_can_have_links_to_multiple_tenants(): void
    {
        $federatedUser = $this->federationService->createFederatedUser(
            group: $this->group,
            email: 'john@example.com',
            syncedData: ['name' => 'John Doe'],
            masterTenant: $this->masterTenant,
            masterTenantUserId: 'user-123'
        );

        // Create links to branch tenants
        $this->federationService->createUserLink($federatedUser, $this->branchTenant1, 'branch1-user-456');
        $this->federationService->createUserLink($federatedUser, $this->branchTenant2, 'branch2-user-789');

        $linkedTenantIds = $federatedUser->getLinkedTenantIds();

        $this->assertCount(3, $linkedTenantIds); // master + 2 branches
        $this->assertContains($this->masterTenant->id, $linkedTenantIds);
        $this->assertContains($this->branchTenant1->id, $linkedTenantIds);
        $this->assertContains($this->branchTenant2->id, $linkedTenantIds);
    }

    public function test_get_link_for_tenant(): void
    {
        $federatedUser = $this->federationService->createFederatedUser(
            group: $this->group,
            email: 'john@example.com',
            syncedData: ['name' => 'John Doe'],
            masterTenant: $this->masterTenant,
            masterTenantUserId: 'user-123'
        );

        $this->federationService->createUserLink($federatedUser, $this->branchTenant1, 'branch-user-456');

        $link = $federatedUser->getLinkForTenant($this->branchTenant1);

        $this->assertNotNull($link);
        $this->assertEquals('branch-user-456', $link->tenant_user_id);
        $this->assertEquals($this->branchTenant1->id, $link->tenant_id);
    }

    // =========================================================================
    // Synced Data Update Tests
    // =========================================================================

    public function test_synced_data_can_be_updated(): void
    {
        $federatedUser = $this->federationService->createFederatedUser(
            group: $this->group,
            email: 'john@example.com',
            syncedData: ['name' => 'John Doe', 'locale' => 'en'],
            masterTenant: $this->masterTenant,
            masterTenantUserId: 'user-123'
        );

        $initialVersion = $federatedUser->sync_version;

        $federatedUser->updateSyncedField('name', 'John Smith');

        $federatedUser->refresh();

        $this->assertEquals('John Smith', $federatedUser->getName());
        $this->assertEquals($initialVersion + 1, $federatedUser->sync_version);
    }

    public function test_multiple_synced_fields_can_be_updated(): void
    {
        $federatedUser = $this->federationService->createFederatedUser(
            group: $this->group,
            email: 'john@example.com',
            syncedData: ['name' => 'John Doe', 'locale' => 'en'],
            masterTenant: $this->masterTenant,
            masterTenantUserId: 'user-123'
        );

        $federatedUser->updateSyncedData([
            'name' => 'John Smith',
            'locale' => 'pt_BR',
            'two_factor_enabled' => true,
        ], $this->masterTenant->id);

        $federatedUser->refresh();

        $this->assertEquals('John Smith', $federatedUser->getName());
        $this->assertEquals('pt_BR', $federatedUser->getSyncedField('locale'));
        $this->assertTrue($federatedUser->hasTwoFactorEnabled());
        $this->assertEquals($this->masterTenant->id, $federatedUser->last_sync_source);
    }

    // =========================================================================
    // Link Status Tests
    // =========================================================================

    public function test_link_can_be_marked_as_synced(): void
    {
        $federatedUser = $this->federationService->createFederatedUser(
            group: $this->group,
            email: 'john@example.com',
            syncedData: ['name' => 'John Doe'],
            masterTenant: $this->masterTenant,
            masterTenantUserId: 'user-123'
        );

        $link = FederatedUserLink::create([
            'federated_user_id' => $federatedUser->id,
            'tenant_id' => $this->branchTenant1->id,
            'tenant_user_id' => 'branch-user-456',
            'sync_status' => FederatedUserLink::STATUS_PENDING_SYNC,
        ]);

        $link->markAsSynced();

        $link->refresh();

        $this->assertEquals(FederatedUserLink::STATUS_SYNCED, $link->sync_status);
        $this->assertNotNull($link->last_synced_at);
        $this->assertEquals(0, $link->sync_attempts);
        $this->assertNull($link->last_sync_error);
    }

    public function test_link_can_be_marked_as_failed(): void
    {
        $federatedUser = $this->federationService->createFederatedUser(
            group: $this->group,
            email: 'john@example.com',
            syncedData: ['name' => 'John Doe'],
            masterTenant: $this->masterTenant,
            masterTenantUserId: 'user-123'
        );

        $link = FederatedUserLink::create([
            'federated_user_id' => $federatedUser->id,
            'tenant_id' => $this->branchTenant1->id,
            'tenant_user_id' => 'branch-user-456',
            'sync_status' => FederatedUserLink::STATUS_SYNCED,
            'sync_attempts' => 0,
        ]);

        $link->markAsFailed('Connection timeout');

        $link->refresh();

        $this->assertEquals(FederatedUserLink::STATUS_SYNC_FAILED, $link->sync_status);
        $this->assertEquals(1, $link->sync_attempts);
        $this->assertEquals('Connection timeout', $link->last_sync_error);
    }

    public function test_link_should_retry_returns_correct_value(): void
    {
        $federatedUser = $this->federationService->createFederatedUser(
            group: $this->group,
            email: 'john@example.com',
            syncedData: ['name' => 'John Doe'],
            masterTenant: $this->masterTenant,
            masterTenantUserId: 'user-123'
        );

        $link = FederatedUserLink::create([
            'federated_user_id' => $federatedUser->id,
            'tenant_id' => $this->branchTenant1->id,
            'tenant_user_id' => 'branch-user-456',
            'sync_status' => FederatedUserLink::STATUS_SYNC_FAILED,
            'sync_attempts' => 1,
        ]);

        $this->assertTrue($link->shouldRetry(3));

        $link->update(['sync_attempts' => 3]);
        $this->assertFalse($link->shouldRetry(3));

        $link->update(['sync_status' => FederatedUserLink::STATUS_SYNCED]);
        $this->assertFalse($link->shouldRetry(3));
    }

    public function test_link_can_be_disabled(): void
    {
        $federatedUser = $this->federationService->createFederatedUser(
            group: $this->group,
            email: 'john@example.com',
            syncedData: ['name' => 'John Doe'],
            masterTenant: $this->masterTenant,
            masterTenantUserId: 'user-123'
        );

        $link = $federatedUser->getLinkForTenant($this->masterTenant);

        $link->disable();

        $link->refresh();

        $this->assertTrue($link->isDisabled());
        $this->assertEquals(FederatedUserLink::STATUS_DISABLED, $link->sync_status);
    }

    // =========================================================================
    // Find User Tests
    // =========================================================================

    public function test_find_federated_user_by_email(): void
    {
        $federatedUser = $this->federationService->createFederatedUser(
            group: $this->group,
            email: 'john@example.com',
            syncedData: ['name' => 'John Doe'],
            masterTenant: $this->masterTenant,
            masterTenantUserId: 'user-123'
        );

        $found = $this->federationService->findFederatedUserByEmail($this->group, 'john@example.com');

        $this->assertNotNull($found);
        $this->assertEquals($federatedUser->id, $found->id);
    }

    public function test_find_federated_user_by_email_is_case_insensitive(): void
    {
        $federatedUser = $this->federationService->createFederatedUser(
            group: $this->group,
            email: 'john@example.com',
            syncedData: ['name' => 'John Doe'],
            masterTenant: $this->masterTenant,
            masterTenantUserId: 'user-123'
        );

        $found = $this->federationService->findFederatedUserByEmail($this->group, 'JOHN@EXAMPLE.COM');

        $this->assertNotNull($found);
        $this->assertEquals($federatedUser->id, $found->id);
    }

    public function test_find_federated_user_by_tenant_user(): void
    {
        $federatedUser = $this->federationService->createFederatedUser(
            group: $this->group,
            email: 'john@example.com',
            syncedData: ['name' => 'John Doe'],
            masterTenant: $this->masterTenant,
            masterTenantUserId: 'user-123'
        );

        $found = $this->federationService->findFederatedUserByTenantUser(
            $this->masterTenant->id,
            'user-123'
        );

        $this->assertNotNull($found);
        $this->assertEquals($federatedUser->id, $found->id);
    }

    // =========================================================================
    // Active/Status Scope Tests
    // =========================================================================

    public function test_active_scope_filters_users(): void
    {
        $activeUser = $this->federationService->createFederatedUser(
            group: $this->group,
            email: 'active@example.com',
            syncedData: ['name' => 'Active User'],
            masterTenant: $this->masterTenant,
            masterTenantUserId: 'user-1'
        );

        $suspendedUser = $this->federationService->createFederatedUser(
            group: $this->group,
            email: 'suspended@example.com',
            syncedData: ['name' => 'Suspended User'],
            masterTenant: $this->masterTenant,
            masterTenantUserId: 'user-2'
        );
        $suspendedUser->update(['status' => FederatedUserStatus::SUSPENDED]);

        $activeUsers = FederatedUser::active()->get();

        $this->assertCount(1, $activeUsers);
        $this->assertEquals($activeUser->id, $activeUsers->first()->id);
    }

    public function test_in_group_scope(): void
    {
        // Create another group
        $otherGroup = FederationGroup::create([
            'name' => 'Other Group',
            'master_tenant_id' => $this->branchTenant1->id,
            'sync_strategy' => FederationGroup::STRATEGY_MASTER_WINS,
            'is_active' => true,
        ]);

        $userInGroup = $this->federationService->createFederatedUser(
            group: $this->group,
            email: 'ingroup@example.com',
            syncedData: ['name' => 'In Group'],
            masterTenant: $this->masterTenant,
            masterTenantUserId: 'user-1'
        );

        FederatedUser::create([
            'federation_group_id' => $otherGroup->id,
            'global_email' => 'other@example.com',
            'synced_data' => ['name' => 'Other User'],
            'master_tenant_id' => $this->branchTenant1->id,
            'master_tenant_user_id' => 'user-2',
            'status' => FederatedUserStatus::ACTIVE,
        ]);

        $usersInGroup = FederatedUser::inGroup($this->group->id)->get();

        $this->assertCount(1, $usersInGroup);
        $this->assertEquals($userInGroup->id, $usersInGroup->first()->id);
    }

    // =========================================================================
    // Link Scope Tests
    // =========================================================================

    public function test_synced_links_scope(): void
    {
        $federatedUser = $this->federationService->createFederatedUser(
            group: $this->group,
            email: 'john@example.com',
            syncedData: ['name' => 'John Doe'],
            masterTenant: $this->masterTenant,
            masterTenantUserId: 'user-123'
        );

        FederatedUserLink::create([
            'federated_user_id' => $federatedUser->id,
            'tenant_id' => $this->branchTenant1->id,
            'tenant_user_id' => 'branch-user-1',
            'sync_status' => FederatedUserLink::STATUS_SYNCED,
        ]);

        FederatedUserLink::create([
            'federated_user_id' => $federatedUser->id,
            'tenant_id' => $this->branchTenant2->id,
            'tenant_user_id' => 'branch-user-2',
            'sync_status' => FederatedUserLink::STATUS_PENDING_SYNC,
        ]);

        $syncedLinks = $federatedUser->syncedLinks()->get();

        // Master link (created automatically) + branch1
        $this->assertCount(2, $syncedLinks);
    }

    public function test_pending_links_scope(): void
    {
        $federatedUser = $this->federationService->createFederatedUser(
            group: $this->group,
            email: 'john@example.com',
            syncedData: ['name' => 'John Doe'],
            masterTenant: $this->masterTenant,
            masterTenantUserId: 'user-123'
        );

        FederatedUserLink::create([
            'federated_user_id' => $federatedUser->id,
            'tenant_id' => $this->branchTenant1->id,
            'tenant_user_id' => 'branch-user-1',
            'sync_status' => FederatedUserLink::STATUS_PENDING_SYNC,
        ]);

        $pendingLinks = $federatedUser->pendingLinks()->get();

        $this->assertCount(1, $pendingLinks);
        $this->assertEquals($this->branchTenant1->id, $pendingLinks->first()->tenant_id);
    }

    public function test_active_links_excludes_disabled(): void
    {
        $federatedUser = $this->federationService->createFederatedUser(
            group: $this->group,
            email: 'john@example.com',
            syncedData: ['name' => 'John Doe'],
            masterTenant: $this->masterTenant,
            masterTenantUserId: 'user-123'
        );

        $link = FederatedUserLink::create([
            'federated_user_id' => $federatedUser->id,
            'tenant_id' => $this->branchTenant1->id,
            'tenant_user_id' => 'branch-user-1',
            'sync_status' => FederatedUserLink::STATUS_SYNCED,
        ]);

        // 2 active links (master + branch1)
        $this->assertCount(2, $federatedUser->activeLinks()->get());

        $link->disable();

        // 1 active link (master only)
        $this->assertCount(1, $federatedUser->fresh()->activeLinks()->get());
    }

    // =========================================================================
    // Static Finder Tests
    // =========================================================================

    public function test_find_by_email_in_group(): void
    {
        $federatedUser = $this->federationService->createFederatedUser(
            group: $this->group,
            email: 'john@example.com',
            syncedData: ['name' => 'John Doe'],
            masterTenant: $this->masterTenant,
            masterTenantUserId: 'user-123'
        );

        $found = FederatedUser::findByEmailInGroup('john@example.com', $this->group->id);

        $this->assertNotNull($found);
        $this->assertEquals($federatedUser->id, $found->id);
    }

    public function test_find_by_tenant_user(): void
    {
        $federatedUser = $this->federationService->createFederatedUser(
            group: $this->group,
            email: 'john@example.com',
            syncedData: ['name' => 'John Doe'],
            masterTenant: $this->masterTenant,
            masterTenantUserId: 'user-123'
        );

        $link = FederatedUserLink::findByTenantUser($this->masterTenant->id, 'user-123');

        $this->assertNotNull($link);
        $this->assertEquals($federatedUser->id, $link->federated_user_id);
    }
}
