<?php

namespace Tests\Feature\Tenant;

use App\Enums\TenantPermission;
use App\Models\Central\FederatedUser;
use App\Models\Central\FederatedUserLink;
use App\Models\Central\FederationGroup;
use App\Models\Central\Tenant;
use App\Models\Shared\Role;
use App\Models\Tenant\User;
use App\Services\Central\FederationService as CentralFederationService;
use App\Services\Tenant\FederationService as TenantFederationService;
use Illuminate\Support\Facades\Hash;
use Tests\TenantTestCase;

class TenantFederationTest extends TenantTestCase
{
    protected CentralFederationService $centralFederationService;

    protected TenantFederationService $tenantFederationService;

    protected FederationGroup $group;

    protected Tenant $branchTenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->centralFederationService = app(CentralFederationService::class);
        $this->tenantFederationService = app(TenantFederationService::class);

        // Give owner federation permissions
        $this->user->givePermissionTo([
            TenantPermission::FEDERATION_VIEW->value,
            TenantPermission::FEDERATION_MANAGE->value,
        ]);

        // Create federation group with current tenant as master
        $this->group = $this->centralFederationService->createGroup(
            name: 'Test Federation',
            masterTenant: $this->tenant,
            syncStrategy: FederationGroup::STRATEGY_MASTER_WINS
        );

        // Create branch tenant and add to group
        $professionalPlan = \App\Models\Central\Plan::where('slug', 'professional')->first();
        $this->branchTenant = Tenant::factory()->create([
            'name' => 'Branch Tenant',
            'slug' => 'branch-tenant-' . uniqid(),
            'plan_id' => $professionalPlan?->id,
        ]);
        $this->branchTenant->domains()->create([
            'domain' => $this->branchTenant->slug . '.test',
            'is_primary' => true,
        ]);

        $this->centralFederationService->addTenantToGroup($this->group, $this->branchTenant);
    }

    // =========================================================================
    // Federation Status Tests
    // =========================================================================

    public function test_federation_page_can_be_rendered(): void
    {
        $response = $this->actingAs($this->user)
            ->get($this->tenantRoute('tenant.admin.settings.federation.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('tenant/admin/settings/federation')
            ->has('stats')
            ->has('group')
            ->has('membership')
            ->has('federatedUsers')
            ->has('localOnlyUsers')
        );
    }

    public function test_federation_page_shows_correct_status(): void
    {
        $response = $this->actingAs($this->user)
            ->get($this->tenantRoute('tenant.admin.settings.federation.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('stats.is_federated', true)
            ->where('stats.is_master', true)
            ->where('stats.group_name', 'Test Federation')
        );
    }

    public function test_federation_page_requires_federation_view_permission(): void
    {
        $memberUser = $this->createTenantUser('member');

        $response = $this->actingAs($memberUser)
            ->get($this->tenantRoute('tenant.admin.settings.federation.index'));

        $response->assertForbidden();
    }

    public function test_federation_page_requires_authentication(): void
    {
        \Auth::logout();

        $response = $this->get($this->tenantRoute('tenant.admin.settings.federation.index'));

        $response->assertRedirect();
    }

    // =========================================================================
    // Tenant Federation Service Tests
    // =========================================================================

    public function test_get_current_group_returns_federation_group(): void
    {
        $group = $this->tenantFederationService->getCurrentGroup();

        $this->assertNotNull($group);
        $this->assertEquals($this->group->id, $group->id);
        $this->assertEquals('Test Federation', $group->name);
    }

    public function test_is_federated_returns_true_when_in_group(): void
    {
        $this->assertTrue($this->tenantFederationService->isFederated());
    }

    public function test_is_master_returns_true_for_master_tenant(): void
    {
        $this->assertTrue($this->tenantFederationService->isMaster());
    }

    public function test_get_membership_returns_correct_data(): void
    {
        $membership = $this->tenantFederationService->getMembership();

        $this->assertNotNull($membership);
        $this->assertTrue($membership->sync_enabled);
        $this->assertNotNull($membership->joined_at);
    }

    // =========================================================================
    // Federate User Tests
    // =========================================================================

    public function test_local_user_can_be_federated(): void
    {
        $localUser = User::factory()->create([
            'name' => 'Local User',
            'email' => 'local@example.com',
        ]);

        $response = $this->actingAs($this->user)
            ->post($this->tenantRoute('tenant.admin.settings.federation.users.federate'), [
                'user_id' => $localUser->id,
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $localUser->refresh();
        $this->assertNotNull($localUser->federated_user_id);

        // Check federated user was created in central
        $this->assertDatabaseHas('federated_users', [
            'global_email' => 'local@example.com',
            'federation_group_id' => $this->group->id,
            'master_tenant_id' => $this->tenant->id,
        ]);
    }

    public function test_federate_user_requires_federation_manage_permission(): void
    {
        $viewOnlyUser = $this->createTenantUser('member');
        $viewOnlyUser->givePermissionTo(TenantPermission::FEDERATION_VIEW->value);

        $localUser = User::factory()->create(['email' => 'local@example.com']);

        $response = $this->actingAs($viewOnlyUser)
            ->post($this->tenantRoute('tenant.admin.settings.federation.users.federate'), [
                'user_id' => $localUser->id,
            ]);

        $response->assertForbidden();
    }

    public function test_already_federated_user_cannot_be_federated_again(): void
    {
        // Create and federate a user
        $localUser = User::factory()->create(['email' => 'already@example.com']);

        // Manually federate
        $federatedUser = FederatedUser::create([
            'federation_group_id' => $this->group->id,
            'global_email' => 'already@example.com',
            'synced_data' => ['name' => 'Test'],
            'master_tenant_id' => $this->tenant->id,
            'master_tenant_user_id' => $localUser->id,
            'status' => FederatedUser::STATUS_ACTIVE,
            'sync_version' => 1,
        ]);

        $localUser->update(['federated_user_id' => $federatedUser->id]);

        $response = $this->actingAs($this->user)
            ->post($this->tenantRoute('tenant.admin.settings.federation.users.federate'), [
                'user_id' => $localUser->id,
            ]);

        $response->assertSessionHas('error');
    }

    // =========================================================================
    // Unfederate User Tests
    // =========================================================================

    public function test_federated_user_can_be_unfederated(): void
    {
        // Create and federate a user (not master)
        $localUser = User::factory()->create(['email' => 'federated@example.com']);

        $federatedUser = FederatedUser::create([
            'federation_group_id' => $this->group->id,
            'global_email' => 'federated@example.com',
            'synced_data' => ['name' => 'Federated User'],
            'master_tenant_id' => $this->branchTenant->id, // Not master in this tenant
            'master_tenant_user_id' => 'other-user-id',
            'status' => FederatedUser::STATUS_ACTIVE,
            'sync_version' => 1,
        ]);

        $localUser->update(['federated_user_id' => $federatedUser->id]);

        // Create link
        FederatedUserLink::create([
            'federated_user_id' => $federatedUser->id,
            'tenant_id' => $this->tenant->id,
            'tenant_user_id' => $localUser->id,
            'sync_status' => FederatedUserLink::STATUS_SYNCED,
        ]);

        $response = $this->actingAs($this->user)
            ->delete($this->tenantRoute('tenant.admin.settings.federation.users.unfederate', ['user' => $localUser->id]));

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $localUser->refresh();
        $this->assertNull($localUser->federated_user_id);
    }

    public function test_master_user_cannot_be_unfederated(): void
    {
        // Create user who is the master user
        $masterUser = User::factory()->create(['email' => 'master@example.com']);

        $federatedUser = FederatedUser::create([
            'federation_group_id' => $this->group->id,
            'global_email' => 'master@example.com',
            'synced_data' => ['name' => 'Master User'],
            'master_tenant_id' => $this->tenant->id, // THIS tenant is master
            'master_tenant_user_id' => $masterUser->id, // THIS user is master
            'status' => FederatedUser::STATUS_ACTIVE,
            'sync_version' => 1,
        ]);

        $masterUser->update(['federated_user_id' => $federatedUser->id]);

        FederatedUserLink::create([
            'federated_user_id' => $federatedUser->id,
            'tenant_id' => $this->tenant->id,
            'tenant_user_id' => $masterUser->id,
            'sync_status' => FederatedUserLink::STATUS_SYNCED,
            'metadata' => ['is_master' => true],
        ]);

        $response = $this->actingAs($this->user)
            ->delete($this->tenantRoute('tenant.admin.settings.federation.users.unfederate', ['user' => $masterUser->id]));

        $response->assertSessionHas('error');
    }

    // =========================================================================
    // Get Users Tests
    // =========================================================================

    public function test_get_federated_users_returns_only_federated(): void
    {
        // Create federated user
        $federatedLocal = User::factory()->create(['email' => 'federated@example.com']);
        $federatedUser = FederatedUser::create([
            'federation_group_id' => $this->group->id,
            'global_email' => 'federated@example.com',
            'synced_data' => ['name' => 'Federated'],
            'master_tenant_id' => $this->tenant->id,
            'master_tenant_user_id' => $federatedLocal->id,
            'status' => FederatedUser::STATUS_ACTIVE,
            'sync_version' => 1,
        ]);
        $federatedLocal->update(['federated_user_id' => $federatedUser->id]);

        // Create local-only user
        User::factory()->create(['email' => 'local@example.com']);

        $federatedUsers = $this->tenantFederationService->getFederatedUsers();

        // Should only have the federated user (excluding the owner from setUp)
        $federatedEmails = $federatedUsers->pluck('email')->toArray();
        $this->assertContains('federated@example.com', $federatedEmails);
        $this->assertNotContains('local@example.com', $federatedEmails);
    }

    public function test_get_local_only_users_excludes_federated(): void
    {
        // Create federated user
        $federatedLocal = User::factory()->create(['email' => 'federated@example.com']);
        $federatedUser = FederatedUser::create([
            'federation_group_id' => $this->group->id,
            'global_email' => 'federated@example.com',
            'synced_data' => ['name' => 'Federated'],
            'master_tenant_id' => $this->tenant->id,
            'master_tenant_user_id' => $federatedLocal->id,
            'status' => FederatedUser::STATUS_ACTIVE,
            'sync_version' => 1,
        ]);
        $federatedLocal->update(['federated_user_id' => $federatedUser->id]);

        // Create local-only user
        $localUser = User::factory()->create(['email' => 'local@example.com']);

        $localOnlyUsers = $this->tenantFederationService->getLocalOnlyUsers();

        $localEmails = $localOnlyUsers->pluck('email')->toArray();
        $this->assertContains('local@example.com', $localEmails);
        $this->assertNotContains('federated@example.com', $localEmails);
    }

    // =========================================================================
    // Sync User Tests
    // =========================================================================

    public function test_sync_user_applies_federation_data(): void
    {
        $localUser = User::factory()->create([
            'name' => 'Old Name',
            'email' => 'sync@example.com',
        ]);

        $federatedUser = FederatedUser::create([
            'federation_group_id' => $this->group->id,
            'global_email' => 'sync@example.com',
            'synced_data' => [
                'name' => 'New Name From Federation',
                'locale' => 'pt_BR',
            ],
            'master_tenant_id' => $this->tenant->id,
            'master_tenant_user_id' => $localUser->id,
            'status' => FederatedUser::STATUS_ACTIVE,
            'sync_version' => 1,
        ]);

        $localUser->update(['federated_user_id' => $federatedUser->id]);

        FederatedUserLink::create([
            'federated_user_id' => $federatedUser->id,
            'tenant_id' => $this->tenant->id,
            'tenant_user_id' => $localUser->id,
            'sync_status' => FederatedUserLink::STATUS_PENDING_SYNC,
        ]);

        $response = $this->actingAs($this->user)
            ->post($this->tenantRoute('tenant.admin.settings.federation.users.sync', ['user' => $localUser->id]));

        $response->assertRedirect();
        $response->assertSessionHas('success');
    }

    public function test_sync_non_federated_user_returns_error(): void
    {
        $localUser = User::factory()->create(['email' => 'nonfederated@example.com']);

        $response = $this->actingAs($this->user)
            ->post($this->tenantRoute('tenant.admin.settings.federation.users.sync', ['user' => $localUser->id]));

        $response->assertSessionHas('error');
    }

    // =========================================================================
    // User Federation Info Tests
    // =========================================================================

    public function test_get_user_federation_info_returns_correct_data(): void
    {
        $localUser = User::factory()->create(['email' => 'info@example.com']);

        $federatedUser = FederatedUser::create([
            'federation_group_id' => $this->group->id,
            'global_email' => 'info@example.com',
            'synced_data' => ['name' => 'Info User'],
            'master_tenant_id' => $this->tenant->id,
            'master_tenant_user_id' => $localUser->id,
            'status' => FederatedUser::STATUS_ACTIVE,
            'sync_version' => 5,
            'last_synced_at' => now(),
        ]);

        $localUser->update(['federated_user_id' => $federatedUser->id]);

        FederatedUserLink::create([
            'federated_user_id' => $federatedUser->id,
            'tenant_id' => $this->tenant->id,
            'tenant_user_id' => $localUser->id,
            'sync_status' => FederatedUserLink::STATUS_SYNCED,
            'metadata' => ['is_master' => true],
        ]);

        $info = $this->tenantFederationService->getUserFederationInfo($localUser);

        $this->assertNotNull($info);
        $this->assertEquals($federatedUser->id, $info['federated_user_id']);
        $this->assertEquals('info@example.com', $info['global_email']);
        $this->assertEquals('Test Federation', $info['group_name']);
        $this->assertEquals(5, $info['sync_version']);
    }

    public function test_get_user_federation_info_returns_null_for_non_federated(): void
    {
        $localUser = User::factory()->create(['email' => 'local@example.com']);

        $info = $this->tenantFederationService->getUserFederationInfo($localUser);

        $this->assertNull($info);
    }

    // =========================================================================
    // Statistics Tests
    // =========================================================================

    public function test_get_stats_returns_correct_data(): void
    {
        // Create some federated and local users
        $federatedLocal = User::factory()->create(['email' => 'fed@example.com']);
        $federatedUser = FederatedUser::create([
            'federation_group_id' => $this->group->id,
            'global_email' => 'fed@example.com',
            'synced_data' => ['name' => 'Fed'],
            'master_tenant_id' => $this->tenant->id,
            'master_tenant_user_id' => $federatedLocal->id,
            'status' => FederatedUser::STATUS_ACTIVE,
            'sync_version' => 1,
        ]);
        $federatedLocal->update(['federated_user_id' => $federatedUser->id]);

        User::factory()->count(2)->create();

        $stats = $this->tenantFederationService->getStats();

        $this->assertTrue($stats['is_federated']);
        $this->assertTrue($stats['is_master']);
        $this->assertEquals('Test Federation', $stats['group_name']);
        $this->assertEquals(FederationGroup::STRATEGY_MASTER_WINS, $stats['sync_strategy']);
        $this->assertGreaterThanOrEqual(1, $stats['federated_users_count']);
        $this->assertGreaterThanOrEqual(2, $stats['local_users_count']);
        $this->assertEquals(2, $stats['total_group_tenants']); // master + branch
    }

    // =========================================================================
    // Auto-Create on Login Tests
    // =========================================================================

    public function test_find_or_create_from_federation_creates_local_user(): void
    {
        // Create federated user in central (without local user)
        $federatedUser = FederatedUser::create([
            'federation_group_id' => $this->group->id,
            'global_email' => 'newuser@example.com',
            'synced_data' => [
                'name' => 'New Federated User',
                'password_hash' => Hash::make('password'),
                'locale' => 'en',
            ],
            'master_tenant_id' => $this->branchTenant->id,
            'master_tenant_user_id' => 'other-user-id',
            'status' => FederatedUser::STATUS_ACTIVE,
            'sync_version' => 1,
        ]);

        // Try to find or create
        $localUser = $this->tenantFederationService->findOrCreateFromFederation('newuser@example.com');

        $this->assertNotNull($localUser);
        $this->assertEquals('newuser@example.com', $localUser->email);
        $this->assertEquals('New Federated User', $localUser->name);
        $this->assertEquals($federatedUser->id, $localUser->federated_user_id);

        // Check link was created
        $this->assertDatabaseHas('federated_user_links', [
            'federated_user_id' => $federatedUser->id,
            'tenant_id' => $this->tenant->id,
            'tenant_user_id' => $localUser->id,
        ]);
    }

    public function test_find_or_create_returns_existing_local_user(): void
    {
        $existingUser = User::factory()->create([
            'email' => 'existing@example.com',
            'name' => 'Existing User',
        ]);

        // Create federated user
        $federatedUser = FederatedUser::create([
            'federation_group_id' => $this->group->id,
            'global_email' => 'existing@example.com',
            'synced_data' => ['name' => 'Federated Name'],
            'master_tenant_id' => $this->branchTenant->id,
            'master_tenant_user_id' => 'other-id',
            'status' => FederatedUser::STATUS_ACTIVE,
            'sync_version' => 1,
        ]);

        $foundUser = $this->tenantFederationService->findOrCreateFromFederation('existing@example.com');

        $this->assertNotNull($foundUser);
        $this->assertEquals($existingUser->id, $foundUser->id);
        // Should be linked now
        $this->assertEquals($federatedUser->id, $foundUser->fresh()->federated_user_id);
    }

    public function test_find_or_create_returns_null_when_not_federated(): void
    {
        // Remove tenant from group first
        tenancy()->end();

        // Create non-federated tenant
        $nonFederatedTenant = Tenant::factory()->create(['slug' => 'non-fed-' . uniqid()]);
        $nonFederatedTenant->domains()->create([
            'domain' => $nonFederatedTenant->slug . '.test',
            'is_primary' => true,
        ]);

        tenancy()->initialize($nonFederatedTenant);

        // Need to recreate the service with new tenant context
        $service = app(TenantFederationService::class);

        $result = $service->findOrCreateFromFederation('anyone@example.com');

        $this->assertNull($result);

        tenancy()->end();
        tenancy()->initialize($this->tenant);
    }

    // =========================================================================
    // Sync Strategy Tests
    // =========================================================================

    public function test_sync_to_federation_works_for_master_tenant(): void
    {
        $localUser = User::factory()->create([
            'name' => 'Updated Name',
            'email' => 'sync@example.com',
        ]);

        $federatedUser = FederatedUser::create([
            'federation_group_id' => $this->group->id,
            'global_email' => 'sync@example.com',
            'synced_data' => ['name' => 'Original Name'],
            'master_tenant_id' => $this->tenant->id, // Current tenant is master
            'master_tenant_user_id' => $localUser->id,
            'status' => FederatedUser::STATUS_ACTIVE,
            'sync_version' => 1,
        ]);

        $localUser->update(['federated_user_id' => $federatedUser->id]);

        // Sync should work because we're the master
        $this->tenantFederationService->syncUserToFederation($localUser);

        $federatedUser->refresh();

        // Name should be updated from local user
        $this->assertEquals('Updated Name', $federatedUser->getSyncedField('name'));
    }
}
