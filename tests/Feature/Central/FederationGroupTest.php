<?php

namespace Tests\Feature\Central;

use App\Enums\CentralPermission;
use App\Models\Central\FederatedUser;
use App\Models\Central\FederationGroup;
use App\Models\Central\Tenant;
use App\Models\Central\User;
use App\Models\Shared\Role;
use App\Services\Central\FederationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FederationGroupTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected Tenant $masterTenant;

    protected Tenant $secondTenant;

    protected Tenant $thirdTenant;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed plans
        \Artisan::call('db:seed', ['--class' => 'PlanSeeder']);

        // Sync permissions
        \Artisan::call('permissions:sync');

        // Create admin with federation permissions
        $this->admin = User::factory()->create();
        $adminRole = Role::findByName('admin', 'central');
        $this->admin->assignRole($adminRole);

        // Grant federation permissions explicitly
        $this->admin->givePermissionTo([
            CentralPermission::FEDERATION_VIEW->value,
            CentralPermission::FEDERATION_CREATE->value,
            CentralPermission::FEDERATION_EDIT->value,
            CentralPermission::FEDERATION_DELETE->value,
            CentralPermission::FEDERATION_MANAGE_CONFLICTS->value,
        ]);

        // Create test tenants
        $professionalPlan = \App\Models\Central\Plan::where('slug', 'professional')->first();

        $this->masterTenant = Tenant::factory()->create([
            'name' => 'Master Corp',
            'slug' => 'master-corp',
            'plan_id' => $professionalPlan?->id,
        ]);

        $this->secondTenant = Tenant::factory()->create([
            'name' => 'Branch One',
            'slug' => 'branch-one',
            'plan_id' => $professionalPlan?->id,
        ]);

        $this->thirdTenant = Tenant::factory()->create([
            'name' => 'Branch Two',
            'slug' => 'branch-two',
            'plan_id' => $professionalPlan?->id,
        ]);
    }

    // =========================================================================
    // Index Tests
    // =========================================================================

    public function test_federation_index_page_can_be_rendered(): void
    {
        $response = $this->actingAs($this->admin, 'central')
            ->get(route('central.admin.federation.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('central/admin/federation/index')
            ->has('groups')
            ->has('stats')
        );
    }

    public function test_federation_index_shows_existing_groups(): void
    {
        // Create a federation group
        $group = FederationGroup::create([
            'name' => 'Test Federation',
            'master_tenant_id' => $this->masterTenant->id,
            'sync_strategy' => FederationGroup::STRATEGY_MASTER_WINS,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin, 'central')
            ->get(route('central.admin.federation.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('groups', 1)
            ->where('groups.0.name', 'Test Federation')
        );
    }

    public function test_federation_index_requires_authentication(): void
    {
        $response = $this->get(route('central.admin.federation.index'));

        $response->assertRedirect(route('central.admin.auth.login'));
    }

    public function test_federation_index_requires_federation_view_permission(): void
    {
        // Create admin without federation permissions
        $adminWithoutPermission = User::factory()->create();

        $response = $this->actingAs($adminWithoutPermission, 'central')
            ->get(route('central.admin.federation.index'));

        $response->assertForbidden();
    }

    // =========================================================================
    // Create Tests
    // =========================================================================

    public function test_federation_create_page_can_be_rendered(): void
    {
        $response = $this->actingAs($this->admin, 'central')
            ->get(route('central.admin.federation.create'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('central/admin/federation/create')
            ->has('availableTenants')
            ->has('syncStrategies')
            ->has('defaultSyncFields')
        );
    }

    public function test_federation_create_page_requires_federation_create_permission(): void
    {
        // Create admin with only view permission
        $viewOnlyAdmin = User::factory()->create();
        $viewOnlyAdmin->givePermissionTo(CentralPermission::FEDERATION_VIEW->value);

        $response = $this->actingAs($viewOnlyAdmin, 'central')
            ->get(route('central.admin.federation.create'));

        $response->assertForbidden();
    }

    public function test_available_tenants_excludes_tenants_already_in_groups(): void
    {
        // Create a federation group with masterTenant
        $group = FederationGroup::create([
            'name' => 'Existing Group',
            'master_tenant_id' => $this->masterTenant->id,
            'sync_strategy' => FederationGroup::STRATEGY_MASTER_WINS,
            'is_active' => true,
        ]);

        // Add master tenant to group
        $group->tenants()->attach($this->masterTenant->id, [
            'sync_enabled' => true,
            'joined_at' => now(),
        ]);

        $response = $this->actingAs($this->admin, 'central')
            ->get(route('central.admin.federation.create'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('availableTenants', 2) // Only secondTenant and thirdTenant
        );
    }

    // =========================================================================
    // Store Tests
    // =========================================================================

    public function test_federation_group_can_be_created(): void
    {
        $response = $this->actingAs($this->admin, 'central')
            ->post(route('central.admin.federation.store'), [
                'name' => 'New Federation Group',
                'description' => 'A test federation group',
                'master_tenant_id' => $this->masterTenant->id,
                'sync_strategy' => FederationGroup::STRATEGY_MASTER_WINS,
                'settings' => [
                    'sync_fields' => FederationGroup::DEFAULT_SYNC_FIELDS,
                    'auto_create_on_login' => true,
                ],
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('federation_groups', [
            'name' => 'New Federation Group',
            'master_tenant_id' => $this->masterTenant->id,
            'sync_strategy' => FederationGroup::STRATEGY_MASTER_WINS,
        ]);

        // Check master tenant was added to group
        $group = FederationGroup::where('name', 'New Federation Group')->first();
        $this->assertTrue($group->hasTenant($this->masterTenant));
    }

    public function test_federation_group_creation_validates_required_fields(): void
    {
        $response = $this->actingAs($this->admin, 'central')
            ->post(route('central.admin.federation.store'), []);

        $response->assertSessionHasErrors(['name', 'master_tenant_id', 'sync_strategy']);
    }

    public function test_federation_group_creation_validates_sync_strategy(): void
    {
        $response = $this->actingAs($this->admin, 'central')
            ->post(route('central.admin.federation.store'), [
                'name' => 'Test Group',
                'master_tenant_id' => $this->masterTenant->id,
                'sync_strategy' => 'invalid_strategy',
            ]);

        $response->assertSessionHasErrors(['sync_strategy']);
    }

    public function test_federation_group_creation_validates_master_tenant_exists(): void
    {
        $response = $this->actingAs($this->admin, 'central')
            ->post(route('central.admin.federation.store'), [
                'name' => 'Test Group',
                'master_tenant_id' => '00000000-0000-0000-0000-000000000000',
                'sync_strategy' => FederationGroup::STRATEGY_MASTER_WINS,
            ]);

        $response->assertSessionHasErrors(['master_tenant_id']);
    }

    public function test_federation_group_creation_fails_if_tenant_already_in_group(): void
    {
        // Create existing group with masterTenant
        $existingGroup = FederationGroup::create([
            'name' => 'Existing Group',
            'master_tenant_id' => $this->masterTenant->id,
            'sync_strategy' => FederationGroup::STRATEGY_MASTER_WINS,
            'is_active' => true,
        ]);

        $existingGroup->tenants()->attach($this->masterTenant->id, [
            'sync_enabled' => true,
            'joined_at' => now(),
        ]);

        // Try to create new group with same tenant
        $response = $this->actingAs($this->admin, 'central')
            ->post(route('central.admin.federation.store'), [
                'name' => 'New Group',
                'master_tenant_id' => $this->masterTenant->id,
                'sync_strategy' => FederationGroup::STRATEGY_MASTER_WINS,
            ]);

        $response->assertSessionHas('error');
    }

    // =========================================================================
    // Show Tests
    // =========================================================================

    public function test_federation_show_page_can_be_rendered(): void
    {
        $group = FederationGroup::create([
            'name' => 'Test Group',
            'master_tenant_id' => $this->masterTenant->id,
            'sync_strategy' => FederationGroup::STRATEGY_MASTER_WINS,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin, 'central')
            ->get(route('central.admin.federation.show', $group));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('central/admin/federation/show')
            ->has('group')
            ->has('stats')
            ->where('group.name', 'Test Group')
        );
    }

    public function test_federation_show_page_returns_404_for_invalid_group(): void
    {
        $response = $this->actingAs($this->admin, 'central')
            ->get(route('central.admin.federation.show', '00000000-0000-0000-0000-000000000000'));

        $response->assertNotFound();
    }

    // =========================================================================
    // Edit Tests
    // =========================================================================

    public function test_federation_edit_page_can_be_rendered(): void
    {
        $group = FederationGroup::create([
            'name' => 'Test Group',
            'master_tenant_id' => $this->masterTenant->id,
            'sync_strategy' => FederationGroup::STRATEGY_MASTER_WINS,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin, 'central')
            ->get(route('central.admin.federation.edit', $group));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('central/admin/federation/edit')
            ->has('group')
            ->has('availableTenants')
            ->where('group.name', 'Test Group')
        );
    }

    public function test_federation_edit_page_requires_federation_edit_permission(): void
    {
        $group = FederationGroup::create([
            'name' => 'Test Group',
            'master_tenant_id' => $this->masterTenant->id,
            'sync_strategy' => FederationGroup::STRATEGY_MASTER_WINS,
            'is_active' => true,
        ]);

        $viewOnlyAdmin = User::factory()->create();
        $viewOnlyAdmin->givePermissionTo(CentralPermission::FEDERATION_VIEW->value);

        $response = $this->actingAs($viewOnlyAdmin, 'central')
            ->get(route('central.admin.federation.edit', $group));

        $response->assertForbidden();
    }

    // =========================================================================
    // Update Tests
    // =========================================================================

    public function test_federation_group_can_be_updated(): void
    {
        $group = FederationGroup::create([
            'name' => 'Original Name',
            'description' => 'Original description',
            'master_tenant_id' => $this->masterTenant->id,
            'sync_strategy' => FederationGroup::STRATEGY_MASTER_WINS,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin, 'central')
            ->put(route('central.admin.federation.update', $group), [
                'name' => 'Updated Name',
                'description' => 'Updated description',
                'sync_strategy' => FederationGroup::STRATEGY_LAST_WRITE_WINS,
                'is_active' => false,
            ]);

        $response->assertRedirect(route('central.admin.federation.show', $group));
        $response->assertSessionHas('success');

        $group->refresh();
        $this->assertEquals('Updated Name', $group->name);
        $this->assertEquals('Updated description', $group->description);
        $this->assertEquals(FederationGroup::STRATEGY_LAST_WRITE_WINS, $group->sync_strategy);
        $this->assertFalse($group->is_active);
    }

    public function test_federation_group_update_requires_federation_edit_permission(): void
    {
        $group = FederationGroup::create([
            'name' => 'Test Group',
            'master_tenant_id' => $this->masterTenant->id,
            'sync_strategy' => FederationGroup::STRATEGY_MASTER_WINS,
            'is_active' => true,
        ]);

        $viewOnlyAdmin = User::factory()->create();
        $viewOnlyAdmin->givePermissionTo(CentralPermission::FEDERATION_VIEW->value);

        $response = $this->actingAs($viewOnlyAdmin, 'central')
            ->put(route('central.admin.federation.update', $group), [
                'name' => 'Updated Name',
            ]);

        $response->assertForbidden();
    }

    // =========================================================================
    // Delete Tests
    // =========================================================================

    public function test_federation_group_can_be_deleted(): void
    {
        $group = FederationGroup::create([
            'name' => 'Test Group',
            'master_tenant_id' => $this->masterTenant->id,
            'sync_strategy' => FederationGroup::STRATEGY_MASTER_WINS,
            'is_active' => true,
        ]);

        $groupId = $group->id;

        $response = $this->actingAs($this->admin, 'central')
            ->delete(route('central.admin.federation.destroy', $group));

        $response->assertRedirect(route('central.admin.federation.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseMissing('federation_groups', ['id' => $groupId]);
    }

    public function test_federation_group_delete_requires_federation_delete_permission(): void
    {
        $group = FederationGroup::create([
            'name' => 'Test Group',
            'master_tenant_id' => $this->masterTenant->id,
            'sync_strategy' => FederationGroup::STRATEGY_MASTER_WINS,
            'is_active' => true,
        ]);

        $editOnlyAdmin = User::factory()->create();
        $editOnlyAdmin->givePermissionTo([
            CentralPermission::FEDERATION_VIEW->value,
            CentralPermission::FEDERATION_EDIT->value,
        ]);

        $response = $this->actingAs($editOnlyAdmin, 'central')
            ->delete(route('central.admin.federation.destroy', $group));

        $response->assertForbidden();
    }

    // =========================================================================
    // Add Tenant Tests
    // =========================================================================

    public function test_tenant_can_be_added_to_federation_group(): void
    {
        $group = FederationGroup::create([
            'name' => 'Test Group',
            'master_tenant_id' => $this->masterTenant->id,
            'sync_strategy' => FederationGroup::STRATEGY_MASTER_WINS,
            'is_active' => true,
        ]);

        // Add master tenant first
        $group->tenants()->attach($this->masterTenant->id, [
            'sync_enabled' => true,
            'joined_at' => now(),
        ]);

        $response = $this->actingAs($this->admin, 'central')
            ->post(route('central.admin.federation.tenants.add', $group), [
                'tenant_id' => $this->secondTenant->id,
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertTrue($group->fresh()->hasTenant($this->secondTenant));
    }

    public function test_tenant_cannot_be_added_if_already_in_another_group(): void
    {
        // Create first group with secondTenant
        $group1 = FederationGroup::create([
            'name' => 'Group 1',
            'master_tenant_id' => $this->masterTenant->id,
            'sync_strategy' => FederationGroup::STRATEGY_MASTER_WINS,
            'is_active' => true,
        ]);

        $group1->tenants()->attach($this->masterTenant->id, [
            'sync_enabled' => true,
            'joined_at' => now(),
        ]);

        $group1->tenants()->attach($this->secondTenant->id, [
            'sync_enabled' => true,
            'joined_at' => now(),
        ]);

        // Create second group
        $group2 = FederationGroup::create([
            'name' => 'Group 2',
            'master_tenant_id' => $this->thirdTenant->id,
            'sync_strategy' => FederationGroup::STRATEGY_MASTER_WINS,
            'is_active' => true,
        ]);

        $group2->tenants()->attach($this->thirdTenant->id, [
            'sync_enabled' => true,
            'joined_at' => now(),
        ]);

        // Try to add secondTenant to group2
        $response = $this->actingAs($this->admin, 'central')
            ->post(route('central.admin.federation.tenants.add', $group2), [
                'tenant_id' => $this->secondTenant->id,
            ]);

        $response->assertSessionHas('error');
    }

    // =========================================================================
    // Remove Tenant Tests
    // =========================================================================

    public function test_tenant_can_be_removed_from_federation_group(): void
    {
        $group = FederationGroup::create([
            'name' => 'Test Group',
            'master_tenant_id' => $this->masterTenant->id,
            'sync_strategy' => FederationGroup::STRATEGY_MASTER_WINS,
            'is_active' => true,
        ]);

        // Add tenants
        $group->tenants()->attach($this->masterTenant->id, [
            'sync_enabled' => true,
            'joined_at' => now(),
        ]);

        $group->tenants()->attach($this->secondTenant->id, [
            'sync_enabled' => true,
            'joined_at' => now(),
        ]);

        $response = $this->actingAs($this->admin, 'central')
            ->delete(route('central.admin.federation.tenants.remove', [$group, $this->secondTenant]));

        $response->assertRedirect();
        $response->assertSessionHas('success');

        // Tenant should be marked as left (soft removal)
        $pivot = $group->tenants()->wherePivot('tenant_id', $this->secondTenant->id)->first();
        $this->assertNotNull($pivot->pivot->left_at);
    }

    public function test_master_tenant_cannot_be_removed_from_group(): void
    {
        $group = FederationGroup::create([
            'name' => 'Test Group',
            'master_tenant_id' => $this->masterTenant->id,
            'sync_strategy' => FederationGroup::STRATEGY_MASTER_WINS,
            'is_active' => true,
        ]);

        $group->tenants()->attach($this->masterTenant->id, [
            'sync_enabled' => true,
            'joined_at' => now(),
        ]);

        $response = $this->actingAs($this->admin, 'central')
            ->delete(route('central.admin.federation.tenants.remove', [$group, $this->masterTenant]));

        $response->assertSessionHas('error');
    }

    // =========================================================================
    // Sync Strategy Tests
    // =========================================================================

    public function test_group_with_master_wins_strategy(): void
    {
        $group = FederationGroup::create([
            'name' => 'Master Wins Group',
            'master_tenant_id' => $this->masterTenant->id,
            'sync_strategy' => FederationGroup::STRATEGY_MASTER_WINS,
            'is_active' => true,
        ]);

        $this->assertEquals(FederationGroup::STRATEGY_MASTER_WINS, $group->sync_strategy);
        $this->assertTrue($group->isMaster($this->masterTenant));
        $this->assertFalse($group->isMaster($this->secondTenant));
    }

    public function test_group_with_last_write_wins_strategy(): void
    {
        $group = FederationGroup::create([
            'name' => 'Last Write Wins Group',
            'master_tenant_id' => $this->masterTenant->id,
            'sync_strategy' => FederationGroup::STRATEGY_LAST_WRITE_WINS,
            'is_active' => true,
        ]);

        $this->assertEquals(FederationGroup::STRATEGY_LAST_WRITE_WINS, $group->sync_strategy);
    }

    public function test_group_with_manual_review_strategy(): void
    {
        $group = FederationGroup::create([
            'name' => 'Manual Review Group',
            'master_tenant_id' => $this->masterTenant->id,
            'sync_strategy' => FederationGroup::STRATEGY_MANUAL_REVIEW,
            'is_active' => true,
        ]);

        $this->assertEquals(FederationGroup::STRATEGY_MANUAL_REVIEW, $group->sync_strategy);
    }

    // =========================================================================
    // Settings Tests
    // =========================================================================

    public function test_group_default_sync_fields(): void
    {
        $group = FederationGroup::create([
            'name' => 'Test Group',
            'master_tenant_id' => $this->masterTenant->id,
            'sync_strategy' => FederationGroup::STRATEGY_MASTER_WINS,
            'is_active' => true,
        ]);

        $syncFields = $group->getSyncFields();

        $this->assertContains('name', $syncFields);
        $this->assertContains('email', $syncFields);
        $this->assertContains('password', $syncFields);
    }

    public function test_group_custom_settings(): void
    {
        $group = FederationGroup::create([
            'name' => 'Test Group',
            'master_tenant_id' => $this->masterTenant->id,
            'sync_strategy' => FederationGroup::STRATEGY_MASTER_WINS,
            'settings' => [
                'sync_fields' => ['name', 'email'],
                'auto_create_on_login' => false,
                'custom_setting' => 'custom_value',
            ],
            'is_active' => true,
        ]);

        $this->assertEquals(['name', 'email'], $group->getSyncFields());
        $this->assertFalse($group->shouldAutoCreateOnLogin());
        $this->assertEquals('custom_value', $group->getSetting('custom_setting'));
    }

    // =========================================================================
    // Active Scope Tests
    // =========================================================================

    public function test_active_scope_filters_inactive_groups(): void
    {
        FederationGroup::create([
            'name' => 'Active Group',
            'master_tenant_id' => $this->masterTenant->id,
            'sync_strategy' => FederationGroup::STRATEGY_MASTER_WINS,
            'is_active' => true,
        ]);

        FederationGroup::create([
            'name' => 'Inactive Group',
            'master_tenant_id' => $this->secondTenant->id,
            'sync_strategy' => FederationGroup::STRATEGY_MASTER_WINS,
            'is_active' => false,
        ]);

        $activeGroups = FederationGroup::active()->get();

        $this->assertCount(1, $activeGroups);
        $this->assertEquals('Active Group', $activeGroups->first()->name);
    }
}
