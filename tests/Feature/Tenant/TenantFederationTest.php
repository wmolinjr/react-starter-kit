<?php

namespace Tests\Feature\Tenant;

use App\Enums\FederatedUserLinkSyncStatus;
use App\Enums\FederatedUserStatus;
use App\Enums\FederationSyncStrategy;
use App\Enums\TenantPermission;
use App\Models\Central\FederatedUser;
use App\Models\Central\FederatedUserLink;
use App\Models\Central\FederationGroup;
use App\Models\Central\Tenant;
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

    /**
     * Generate a unique email for tests to avoid constraint violations.
     */
    protected function uniqueEmail(string $prefix = 'test'): string
    {
        return $prefix.'-'.uniqid().'@example.com';
    }

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
            syncStrategy: FederationSyncStrategy::MASTER_WINS
        );

        // Create branch tenant and add to group
        $professionalPlan = \App\Models\Central\Plan::where('slug', 'professional')->first();
        $this->branchTenant = Tenant::factory()->create([
            'name' => 'Branch Tenant',
            'slug' => 'branch-tenant-'.uniqid(),
            'plan_id' => $professionalPlan?->id,
        ]);
        $this->branchTenant->domains()->create([
            'domain' => $this->branchTenant->slug.'.test',
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
        // @todo: Fix Stancl/Tenancy v4 connection issue with central model queries during tenant HTTP requests.
        // The CentralConnection trait doesn't properly route queries to the central database when
        // the request comes through tenant middleware. This needs investigation at the framework level.
        $this->markTestSkipped('Stancl/Tenancy v4: CentralConnection not respected during tenant HTTP requests');

        $email = $this->uniqueEmail('local');
        $localUser = User::factory()->create([
            'name' => 'Local User',
            'email' => $email,
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
            'global_email' => $email,
            'federation_group_id' => $this->group->id,
            'master_tenant_id' => $this->tenant->id,
        ]);
    }

    public function test_federate_user_requires_federation_manage_permission(): void
    {
        $viewOnlyUser = $this->createTenantUser('member');
        $viewOnlyUser->givePermissionTo(TenantPermission::FEDERATION_VIEW->value);

        $localUser = User::factory()->create(['email' => $this->uniqueEmail('local')]);

        $response = $this->actingAs($viewOnlyUser)
            ->post($this->tenantRoute('tenant.admin.settings.federation.users.federate'), [
                'user_id' => $localUser->id,
            ]);

        $response->assertForbidden();
    }

    public function test_already_federated_user_cannot_be_federated_again(): void
    {
        // @todo: Fix Stancl/Tenancy v4 connection issue with central model queries during tenant HTTP requests.
        $this->markTestSkipped('Stancl/Tenancy v4: CentralConnection not respected during tenant HTTP requests');

        // Create and federate a user
        $email = $this->uniqueEmail('already');
        $localUser = User::factory()->create(['email' => $email]);

        // Manually federate
        $federatedUser = FederatedUser::create([
            'federation_group_id' => $this->group->id,
            'global_email' => $email,
            'synced_data' => ['name' => 'Test'],
            'master_tenant_id' => $this->tenant->id,
            'master_tenant_user_id' => $localUser->id,
            'status' => FederatedUserStatus::ACTIVE,
            'sync_version' => 1,
        ]);

        $localUser->forceFill(['federated_user_id' => $federatedUser->id])->save();

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
        $email = $this->uniqueEmail('federated');
        $localUser = User::factory()->create(['email' => $email]);

        $federatedUser = FederatedUser::create([
            'federation_group_id' => $this->group->id,
            'global_email' => $email,
            'synced_data' => ['name' => 'Federated User'],
            'master_tenant_id' => $this->branchTenant->id, // Not master in this tenant
            'master_tenant_user_id' => \Illuminate\Support\Str::uuid()->toString(),
            'status' => FederatedUserStatus::ACTIVE,
            'sync_version' => 1,
        ]);

        $localUser->forceFill(['federated_user_id' => $federatedUser->id])->save();

        // Create link
        FederatedUserLink::create([
            'federated_user_id' => $federatedUser->id,
            'tenant_id' => $this->tenant->id,
            'tenant_user_id' => $localUser->id,
            'sync_status' => FederatedUserLinkSyncStatus::SYNCED,
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
        $email = $this->uniqueEmail('master');
        $masterUser = User::factory()->create(['email' => $email]);

        $federatedUser = FederatedUser::create([
            'federation_group_id' => $this->group->id,
            'global_email' => $email,
            'synced_data' => ['name' => 'Master User'],
            'master_tenant_id' => $this->tenant->id, // THIS tenant is master
            'master_tenant_user_id' => $masterUser->id, // THIS user is master
            'status' => FederatedUserStatus::ACTIVE,
            'sync_version' => 1,
        ]);

        $masterUser->forceFill(['federated_user_id' => $federatedUser->id])->save();

        FederatedUserLink::create([
            'federated_user_id' => $federatedUser->id,
            'tenant_id' => $this->tenant->id,
            'tenant_user_id' => $masterUser->id,
            'sync_status' => FederatedUserLinkSyncStatus::SYNCED,
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
        $fedEmail = $this->uniqueEmail('federated');
        $localEmail = $this->uniqueEmail('local');
        $federatedLocal = User::factory()->create(['email' => $fedEmail]);
        $federatedUser = FederatedUser::create([
            'federation_group_id' => $this->group->id,
            'global_email' => $fedEmail,
            'synced_data' => ['name' => 'Federated'],
            'master_tenant_id' => $this->tenant->id,
            'master_tenant_user_id' => $federatedLocal->id,
            'status' => FederatedUserStatus::ACTIVE,
            'sync_version' => 1,
        ]);
        $federatedLocal->forceFill(['federated_user_id' => $federatedUser->id])->save();

        // Create local-only user
        User::factory()->create(['email' => $localEmail]);

        $federatedUsers = $this->tenantFederationService->getFederatedUsers();

        // Should only have the federated user (excluding the owner from setUp)
        $federatedEmails = $federatedUsers->pluck('email')->toArray();
        $this->assertContains($fedEmail, $federatedEmails);
        $this->assertNotContains($localEmail, $federatedEmails);
    }

    public function test_get_local_only_users_excludes_federated(): void
    {
        // Create federated user
        $fedEmail = $this->uniqueEmail('federated');
        $localEmail = $this->uniqueEmail('local');
        $federatedLocal = User::factory()->create(['email' => $fedEmail]);
        $federatedUser = FederatedUser::create([
            'federation_group_id' => $this->group->id,
            'global_email' => $fedEmail,
            'synced_data' => ['name' => 'Federated'],
            'master_tenant_id' => $this->tenant->id,
            'master_tenant_user_id' => $federatedLocal->id,
            'status' => FederatedUserStatus::ACTIVE,
            'sync_version' => 1,
        ]);
        $federatedLocal->forceFill(['federated_user_id' => $federatedUser->id])->save();

        // Create local-only user
        $localUser = User::factory()->create(['email' => $localEmail]);

        $localOnlyUsers = $this->tenantFederationService->getLocalOnlyUsers();

        $localEmails = $localOnlyUsers->pluck('email')->toArray();
        $this->assertContains($localEmail, $localEmails);
        $this->assertNotContains($fedEmail, $localEmails);
    }

    // =========================================================================
    // Sync User Tests
    // =========================================================================

    public function test_sync_user_applies_federation_data(): void
    {
        $email = $this->uniqueEmail('sync');
        $localUser = User::factory()->create([
            'name' => 'Old Name',
            'email' => $email,
        ]);

        $federatedUser = FederatedUser::create([
            'federation_group_id' => $this->group->id,
            'global_email' => $email,
            'synced_data' => [
                'name' => 'New Name From Federation',
                'locale' => 'pt_BR',
            ],
            'master_tenant_id' => $this->tenant->id,
            'master_tenant_user_id' => $localUser->id,
            'status' => FederatedUserStatus::ACTIVE,
            'sync_version' => 1,
        ]);

        $localUser->forceFill(['federated_user_id' => $federatedUser->id])->save();

        FederatedUserLink::create([
            'federated_user_id' => $federatedUser->id,
            'tenant_id' => $this->tenant->id,
            'tenant_user_id' => $localUser->id,
            'sync_status' => FederatedUserLinkSyncStatus::PENDING_SYNC,
        ]);

        $response = $this->actingAs($this->user)
            ->post($this->tenantRoute('tenant.admin.settings.federation.users.sync', ['user' => $localUser->id]));

        $response->assertRedirect();
        $response->assertSessionHas('success');
    }

    public function test_sync_non_federated_user_returns_error(): void
    {
        $localUser = User::factory()->create(['email' => $this->uniqueEmail('nonfederated')]);

        $response = $this->actingAs($this->user)
            ->post($this->tenantRoute('tenant.admin.settings.federation.users.sync', ['user' => $localUser->id]));

        $response->assertSessionHas('error');
    }

    // =========================================================================
    // User Federation Info Tests
    // =========================================================================

    public function test_get_user_federation_info_returns_correct_data(): void
    {
        $email = $this->uniqueEmail('info');
        $localUser = User::factory()->create(['email' => $email]);

        $federatedUser = FederatedUser::create([
            'federation_group_id' => $this->group->id,
            'global_email' => $email,
            'synced_data' => ['name' => 'Info User'],
            'master_tenant_id' => $this->tenant->id,
            'master_tenant_user_id' => $localUser->id,
            'status' => FederatedUserStatus::ACTIVE,
            'sync_version' => 5,
            'last_synced_at' => now(),
        ]);

        $localUser->forceFill(['federated_user_id' => $federatedUser->id])->save();

        FederatedUserLink::create([
            'federated_user_id' => $federatedUser->id,
            'tenant_id' => $this->tenant->id,
            'tenant_user_id' => $localUser->id,
            'sync_status' => FederatedUserLinkSyncStatus::SYNCED,
            'metadata' => ['is_master' => true],
        ]);

        $info = $this->tenantFederationService->getUserFederationInfo($localUser);

        $this->assertNotNull($info);
        $this->assertEquals($federatedUser->id, $info['federated_user_id']);
        $this->assertEquals($email, $info['global_email']);
        $this->assertEquals('Test Federation', $info['group_name']);
        $this->assertEquals(5, $info['sync_version']);
    }

    public function test_get_user_federation_info_returns_null_for_non_federated(): void
    {
        $localUser = User::factory()->create(['email' => $this->uniqueEmail('local')]);

        $info = $this->tenantFederationService->getUserFederationInfo($localUser);

        $this->assertNull($info);
    }

    // =========================================================================
    // Statistics Tests
    // =========================================================================

    public function test_get_stats_returns_correct_data(): void
    {
        // Create some federated and local users
        $email = $this->uniqueEmail('fed');
        $federatedLocal = User::factory()->create(['email' => $email]);
        $federatedUser = FederatedUser::create([
            'federation_group_id' => $this->group->id,
            'global_email' => $email,
            'synced_data' => ['name' => 'Fed'],
            'master_tenant_id' => $this->tenant->id,
            'master_tenant_user_id' => $federatedLocal->id,
            'status' => FederatedUserStatus::ACTIVE,
            'sync_version' => 1,
        ]);
        $federatedLocal->forceFill(['federated_user_id' => $federatedUser->id])->save();

        User::factory()->count(2)->create();

        $stats = $this->tenantFederationService->getStats();

        $this->assertTrue($stats['is_federated']);
        $this->assertTrue($stats['is_master']);
        $this->assertEquals('Test Federation', $stats['group_name']);
        $this->assertEquals(FederationSyncStrategy::MASTER_WINS, $stats['sync_strategy']);
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
        $email = $this->uniqueEmail('newuser');
        $federatedUser = FederatedUser::create([
            'federation_group_id' => $this->group->id,
            'global_email' => $email,
            'synced_data' => [
                'name' => 'New Federated User',
                'password_hash' => Hash::make('password'),
                'locale' => 'en',
            ],
            'master_tenant_id' => $this->branchTenant->id,
            'master_tenant_user_id' => \Illuminate\Support\Str::uuid()->toString(),
            'status' => FederatedUserStatus::ACTIVE,
            'sync_version' => 1,
        ]);

        // Try to find or create
        $localUser = $this->tenantFederationService->findOrCreateFromFederation($email);

        $this->assertNotNull($localUser);
        $this->assertEquals($email, $localUser->email);
        $this->assertEquals('New Federated User', $localUser->name);
        $this->assertEquals($federatedUser->id, $localUser->federated_user_id);

        // Check link was created (use central connection since federated_user_links is in central database)
        $this->assertDatabaseHas('federated_user_links', [
            'federated_user_id' => $federatedUser->id,
            'tenant_id' => $this->tenant->id,
            'tenant_user_id' => $localUser->id,
        ], config('tenancy.database.central_connection'));
    }

    public function test_find_or_create_returns_existing_local_user(): void
    {
        $email = $this->uniqueEmail('existing');
        $existingUser = User::factory()->create([
            'email' => $email,
            'name' => 'Existing User',
        ]);

        // Create federated user
        $federatedUser = FederatedUser::create([
            'federation_group_id' => $this->group->id,
            'global_email' => $email,
            'synced_data' => ['name' => 'Federated Name'],
            'master_tenant_id' => $this->branchTenant->id,
            'master_tenant_user_id' => \Illuminate\Support\Str::uuid()->toString(),
            'status' => FederatedUserStatus::ACTIVE,
            'sync_version' => 1,
        ]);

        $foundUser = $this->tenantFederationService->findOrCreateFromFederation($email);

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
        $nonFederatedTenant = Tenant::factory()->create(['slug' => 'non-fed-'.uniqid()]);
        $nonFederatedTenant->domains()->create([
            'domain' => $nonFederatedTenant->slug.'.test',
            'is_primary' => true,
        ]);

        tenancy()->initialize($nonFederatedTenant);

        // Need to recreate the service with new tenant context
        $service = app(TenantFederationService::class);

        $result = $service->findOrCreateFromFederation($this->uniqueEmail('anyone'));

        $this->assertNull($result);

        tenancy()->end();
        tenancy()->initialize($this->tenant);
    }

    // =========================================================================
    // Sync Strategy Tests
    // =========================================================================

    public function test_sync_to_federation_works_for_master_tenant(): void
    {
        $email = $this->uniqueEmail('sync');
        $localUser = User::factory()->create([
            'name' => 'Updated Name',
            'email' => $email,
        ]);

        $federatedUser = FederatedUser::create([
            'federation_group_id' => $this->group->id,
            'global_email' => $email,
            'synced_data' => ['name' => 'Original Name'],
            'master_tenant_id' => $this->tenant->id, // Current tenant is master
            'master_tenant_user_id' => $localUser->id,
            'status' => FederatedUserStatus::ACTIVE,
            'sync_version' => 1,
        ]);

        $localUser->forceFill(['federated_user_id' => $federatedUser->id])->save();

        // Sync should work because we're the master
        $this->tenantFederationService->syncUserToFederation($localUser);

        $federatedUser->refresh();

        // Name should be updated from local user
        $this->assertEquals('Updated Name', $federatedUser->getSyncedField('name'));
    }

    // =========================================================================
    // Bulk Federation Tests
    // =========================================================================

    public function test_federate_users_federates_multiple_users(): void
    {
        // Ensure auto-federate is disabled
        $this->group->settings = array_merge($this->group->settings ?? [], [
            'auto_federate_new_users' => false,
        ]);
        $this->group->save();

        $uniqueId = uniqid();
        $users = collect([
            User::factory()->create(['email' => "bulk1-{$uniqueId}@example.com"]),
            User::factory()->create(['email' => "bulk2-{$uniqueId}@example.com"]),
            User::factory()->create(['email' => "bulk3-{$uniqueId}@example.com"]),
        ]);

        $results = $this->tenantFederationService->federateUsers($users);

        $this->assertEquals(3, $results['success']);
        $this->assertEquals(0, $results['failed']);
        $this->assertEmpty($results['errors']);

        foreach ($users as $user) {
            $user->refresh();
            $this->assertNotNull($user->federated_user_id);
        }
    }

    public function test_federate_users_handles_partial_failures(): void
    {
        // Ensure auto-federate is disabled
        $this->group->settings = array_merge($this->group->settings ?? [], [
            'auto_federate_new_users' => false,
        ]);
        $this->group->save();

        $uniqueId = uniqid();

        // Create one valid user
        $validUser = User::factory()->create(['email' => "valid-{$uniqueId}@example.com"]);

        // Create one user that's already federated
        $alreadyFederatedEmail = "already-{$uniqueId}@example.com";
        $alreadyFederatedUser = User::factory()->create(['email' => $alreadyFederatedEmail]);
        $federatedUser = FederatedUser::create([
            'federation_group_id' => $this->group->id,
            'global_email' => $alreadyFederatedEmail,
            'synced_data' => ['name' => 'Test'],
            'master_tenant_id' => $this->tenant->id,
            'master_tenant_user_id' => $alreadyFederatedUser->id,
            'status' => FederatedUserStatus::ACTIVE,
            'sync_version' => 1,
        ]);
        $alreadyFederatedUser->forceFill(['federated_user_id' => $federatedUser->id])->save();

        $users = collect([$validUser, $alreadyFederatedUser]);

        $results = $this->tenantFederationService->federateUsers($users);

        $this->assertEquals(1, $results['success']);
        $this->assertEquals(1, $results['failed']);
        $this->assertArrayHasKey($alreadyFederatedEmail, $results['errors']);

        // Valid user should be federated
        $validUser->refresh();
        $this->assertNotNull($validUser->federated_user_id);
    }

    public function test_federate_users_returns_zero_for_empty_collection(): void
    {
        $results = $this->tenantFederationService->federateUsers(collect());

        $this->assertEquals(0, $results['success']);
        $this->assertEquals(0, $results['failed']);
        $this->assertEmpty($results['errors']);
    }

    // =========================================================================
    // Federate All Controller Tests
    // =========================================================================

    public function test_federate_all_federates_all_local_users(): void
    {
        $localUsers = User::factory()->count(3)->create();

        $response = $this->actingAs($this->user)
            ->post($this->tenantRoute('tenant.admin.settings.federation.users.federate-all'));

        $response->assertRedirect();
        $response->assertSessionHas('success');

        foreach ($localUsers as $user) {
            $user->refresh();
            $this->assertNotNull($user->federated_user_id);
        }
    }

    public function test_federate_all_returns_info_when_no_local_users(): void
    {
        // Federate all existing local users first
        $localUsers = $this->tenantFederationService->getLocalOnlyUsers();
        $this->tenantFederationService->federateUsers($localUsers);

        $response = $this->actingAs($this->user)
            ->post($this->tenantRoute('tenant.admin.settings.federation.users.federate-all'));

        $response->assertRedirect();
        $response->assertSessionHas('info');
    }

    public function test_federate_all_requires_federation_manage_permission(): void
    {
        $viewOnlyUser = $this->createTenantUser('member');
        $viewOnlyUser->givePermissionTo(TenantPermission::FEDERATION_VIEW->value);

        $response = $this->actingAs($viewOnlyUser)
            ->post($this->tenantRoute('tenant.admin.settings.federation.users.federate-all'));

        $response->assertForbidden();
    }

    // =========================================================================
    // Federate Bulk Controller Tests
    // =========================================================================

    public function test_federate_bulk_federates_selected_users(): void
    {
        $users = User::factory()->count(5)->create();
        $selectedUsers = $users->take(3);

        $response = $this->actingAs($this->user)
            ->post($this->tenantRoute('tenant.admin.settings.federation.users.federate-bulk'), [
                'user_ids' => $selectedUsers->pluck('id')->toArray(),
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        // Selected users should be federated
        foreach ($selectedUsers as $user) {
            $user->refresh();
            $this->assertNotNull($user->federated_user_id);
        }

        // Non-selected users should NOT be federated
        foreach ($users->skip(3) as $user) {
            $user->refresh();
            $this->assertNull($user->federated_user_id);
        }
    }

    public function test_federate_bulk_validates_user_ids_required(): void
    {
        $response = $this->actingAs($this->user)
            ->post($this->tenantRoute('tenant.admin.settings.federation.users.federate-bulk'), []);

        $response->assertSessionHasErrors(['user_ids']);
    }

    public function test_federate_bulk_validates_user_ids_must_be_array(): void
    {
        $response = $this->actingAs($this->user)
            ->post($this->tenantRoute('tenant.admin.settings.federation.users.federate-bulk'), [
                'user_ids' => 'not-an-array',
            ]);

        $response->assertSessionHasErrors(['user_ids']);
    }

    public function test_federate_bulk_validates_user_ids_must_exist(): void
    {
        $response = $this->actingAs($this->user)
            ->post($this->tenantRoute('tenant.admin.settings.federation.users.federate-bulk'), [
                'user_ids' => ['00000000-0000-0000-0000-000000000000'],
            ]);

        $response->assertSessionHasErrors(['user_ids.0']);
    }

    public function test_federate_bulk_skips_already_federated_users(): void
    {
        // Ensure auto-federate is disabled
        $this->group->settings = array_merge($this->group->settings ?? [], [
            'auto_federate_new_users' => false,
        ]);
        $this->group->save();

        $uniqueId = uniqid();

        // Create and federate one user
        $federatedEmail = "federated-skip-{$uniqueId}@example.com";
        $federatedLocal = User::factory()->create(['email' => $federatedEmail]);
        $federatedUser = FederatedUser::create([
            'federation_group_id' => $this->group->id,
            'global_email' => $federatedEmail,
            'synced_data' => ['name' => 'Test'],
            'master_tenant_id' => $this->tenant->id,
            'master_tenant_user_id' => $federatedLocal->id,
            'status' => FederatedUserStatus::ACTIVE,
            'sync_version' => 1,
        ]);
        $federatedLocal->forceFill(['federated_user_id' => $federatedUser->id])->save();

        // Create local-only user
        $localUser = User::factory()->create(['email' => "local-skip-{$uniqueId}@example.com"]);

        $response = $this->actingAs($this->user)
            ->post($this->tenantRoute('tenant.admin.settings.federation.users.federate-bulk'), [
                'user_ids' => [$federatedLocal->id, $localUser->id],
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        // Only local user should have been processed
        $localUser->refresh();
        $this->assertNotNull($localUser->federated_user_id);
    }

    public function test_federate_bulk_returns_error_when_no_valid_users(): void
    {
        $uniqueId = uniqid();

        // Create and federate a user
        $federatedEmail = "federated-novalid-{$uniqueId}@example.com";
        $federatedLocal = User::factory()->create(['email' => $federatedEmail]);
        $federatedUser = FederatedUser::create([
            'federation_group_id' => $this->group->id,
            'global_email' => $federatedEmail,
            'synced_data' => ['name' => 'Test'],
            'master_tenant_id' => $this->tenant->id,
            'master_tenant_user_id' => $federatedLocal->id,
            'status' => FederatedUserStatus::ACTIVE,
            'sync_version' => 1,
        ]);

        // Use forceFill to bypass guarded attributes and ensure the value is set
        $federatedLocal->forceFill(['federated_user_id' => $federatedUser->id])->save();
        $federatedLocal->refresh();

        // Verify user is federated
        $this->assertNotNull($federatedLocal->federated_user_id);

        // Only pass the already federated user
        $response = $this->actingAs($this->user)
            ->post($this->tenantRoute('tenant.admin.settings.federation.users.federate-bulk'), [
                'user_ids' => [$federatedLocal->id],
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
    }

    public function test_federate_bulk_requires_federation_manage_permission(): void
    {
        $viewOnlyUser = $this->createTenantUser('member');
        $viewOnlyUser->givePermissionTo(TenantPermission::FEDERATION_VIEW->value);

        $localUser = User::factory()->create();

        $response = $this->actingAs($viewOnlyUser)
            ->post($this->tenantRoute('tenant.admin.settings.federation.users.federate-bulk'), [
                'user_ids' => [$localUser->id],
            ]);

        $response->assertForbidden();
    }
}
