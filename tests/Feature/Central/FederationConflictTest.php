<?php

namespace Tests\Feature\Central;

use App\Enums\CentralPermission;
use App\Enums\FederatedUserStatus;
use App\Enums\FederationConflictStatus;
use App\Enums\FederationSyncStrategy;
use App\Models\Central\FederatedUser;
use App\Models\Central\FederationConflict;
use App\Models\Central\FederationGroup;
use App\Models\Central\Tenant;
use App\Models\Central\User;
use App\Models\Shared\Role;
use App\Services\Central\FederationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FederationConflictTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected Tenant $masterTenant;

    protected Tenant $branchTenant1;

    protected Tenant $branchTenant2;

    protected FederationGroup $group;

    protected FederatedUser $federatedUser;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed plans
        \Artisan::call('db:seed', ['--class' => 'PlanSeeder']);

        // Sync permissions
        \Artisan::call('permissions:sync');

        // Create admin with federation permissions
        $this->admin = User::factory()->create();
        $adminRole = Role::findByName('central-admin', 'central');
        $this->admin->assignRole($adminRole);

        $this->admin->givePermissionTo([
            CentralPermission::FEDERATION_VIEW->value,
            CentralPermission::FEDERATION_MANAGE_CONFLICTS->value,
        ]);

        // Create test tenants
        $professionalPlan = \App\Models\Central\Plan::where('slug', 'professional')->first();

        $this->masterTenant = Tenant::factory()->create([
            'name' => 'Master Corp',
            'slug' => 'master-corp',
            'plan_id' => $professionalPlan?->id,
        ]);

        $this->branchTenant1 = Tenant::factory()->create([
            'name' => 'Branch One',
            'slug' => 'branch-one',
            'plan_id' => $professionalPlan?->id,
        ]);

        $this->branchTenant2 = Tenant::factory()->create([
            'name' => 'Branch Two',
            'slug' => 'branch-two',
            'plan_id' => $professionalPlan?->id,
        ]);

        // Create federation group with manual_review strategy
        $this->group = FederationGroup::create([
            'name' => 'Manual Review Group',
            'master_tenant_id' => $this->masterTenant->id,
            'sync_strategy' => FederationSyncStrategy::MANUAL_REVIEW->value,
            'is_active' => true,
        ]);

        // Add tenants to group
        $this->group->tenants()->attach($this->masterTenant->id, [
            'sync_enabled' => true,
            'joined_at' => now(),
        ]);

        $this->group->tenants()->attach($this->branchTenant1->id, [
            'sync_enabled' => true,
            'joined_at' => now(),
        ]);

        $this->group->tenants()->attach($this->branchTenant2->id, [
            'sync_enabled' => true,
            'joined_at' => now(),
        ]);

        // Create federated user
        $this->federatedUser = FederatedUser::create([
            'federation_group_id' => $this->group->id,
            'global_email' => 'john@example.com',
            'synced_data' => ['name' => 'John Doe'],
            'master_tenant_id' => $this->masterTenant->id,
            'master_tenant_user_id' => \Illuminate\Support\Str::uuid()->toString(),
            'status' => FederatedUserStatus::ACTIVE,
            'sync_version' => 1,
        ]);
    }

    // =========================================================================
    // Conflict Model Tests
    // =========================================================================

    public function test_conflict_can_be_created(): void
    {
        $conflict = FederationConflict::create([
            'federated_user_id' => $this->federatedUser->id,
            'field' => 'name',
            'values' => [],
            'status' => FederationConflictStatus::PENDING->value,
        ]);

        $this->assertDatabaseHas('federation_conflicts', [
            'id' => $conflict->id,
            'federated_user_id' => $this->federatedUser->id,
            'field' => 'name',
            'status' => FederationConflictStatus::PENDING->value,
        ]);
    }

    public function test_conflict_can_add_conflicting_values(): void
    {
        $conflict = FederationConflict::create([
            'federated_user_id' => $this->federatedUser->id,
            'field' => 'name',
            'values' => [],
            'status' => FederationConflictStatus::PENDING->value,
        ]);

        $conflict->addConflictingValue($this->masterTenant->id, 'John Doe');
        $conflict->addConflictingValue($this->branchTenant1->id, 'John Smith');

        $conflict->refresh();

        $this->assertEquals('John Doe', $conflict->getValueForTenant($this->masterTenant->id));
        $this->assertEquals('John Smith', $conflict->getValueForTenant($this->branchTenant1->id));
    }

    public function test_conflict_tracks_involved_tenants(): void
    {
        $conflict = FederationConflict::create([
            'federated_user_id' => $this->federatedUser->id,
            'field' => 'name',
            'values' => [],
            'status' => FederationConflictStatus::PENDING->value,
        ]);

        $conflict->addConflictingValue($this->masterTenant->id, 'Value 1');
        $conflict->addConflictingValue($this->branchTenant1->id, 'Value 2');
        $conflict->addConflictingValue($this->branchTenant2->id, 'Value 3');

        $involvedTenants = $conflict->getInvolvedTenantIds();

        $this->assertCount(3, $involvedTenants);
        $this->assertContains($this->masterTenant->id, $involvedTenants);
        $this->assertContains($this->branchTenant1->id, $involvedTenants);
        $this->assertContains($this->branchTenant2->id, $involvedTenants);
    }

    public function test_conflict_can_be_resolved(): void
    {
        $conflict = FederationConflict::create([
            'federated_user_id' => $this->federatedUser->id,
            'field' => 'name',
            'values' => [
                $this->masterTenant->id => ['value' => 'John Doe', 'updated_at' => now()->toIso8601String()],
                $this->branchTenant1->id => ['value' => 'John Smith', 'updated_at' => now()->toIso8601String()],
            ],
            'status' => FederationConflictStatus::PENDING->value,
        ]);

        $conflict->resolve(
            resolvedValue: 'John Doe',
            resolverId: $this->admin->id,
            resolution: FederationConflict::RESOLUTION_MASTER_VALUE,
            notes: 'Used master tenant value'
        );

        $conflict->refresh();

        $this->assertEquals(FederationConflictStatus::RESOLVED, $conflict->status);
        $this->assertEquals($this->admin->id, $conflict->resolved_by);
        $this->assertEquals(FederationConflict::RESOLUTION_MASTER_VALUE, $conflict->resolution);
        $this->assertEquals('Used master tenant value', $conflict->resolution_notes);
        $this->assertNotNull($conflict->resolved_at);
    }

    public function test_conflict_can_be_dismissed(): void
    {
        $conflict = FederationConflict::create([
            'federated_user_id' => $this->federatedUser->id,
            'field' => 'name',
            'values' => [],
            'status' => FederationConflictStatus::PENDING->value,
        ]);

        $conflict->dismiss(
            resolverId: $this->admin->id,
            notes: 'Not important'
        );

        $conflict->refresh();

        $this->assertEquals(FederationConflictStatus::DISMISSED, $conflict->status);
        $this->assertEquals(FederationConflict::RESOLUTION_DISMISSED, $conflict->resolution);
    }

    public function test_is_pending_returns_correct_value(): void
    {
        $pendingConflict = FederationConflict::create([
            'federated_user_id' => $this->federatedUser->id,
            'field' => 'name',
            'values' => [],
            'status' => FederationConflictStatus::PENDING->value,
        ]);

        $resolvedConflict = FederationConflict::create([
            'federated_user_id' => $this->federatedUser->id,
            'field' => 'email',
            'values' => [],
            'status' => FederationConflictStatus::RESOLVED->value,
        ]);

        $this->assertTrue($pendingConflict->isPending());
        $this->assertFalse($resolvedConflict->isPending());
    }

    public function test_is_resolved_returns_correct_value(): void
    {
        $pendingConflict = FederationConflict::create([
            'federated_user_id' => $this->federatedUser->id,
            'field' => 'name',
            'values' => [],
            'status' => FederationConflictStatus::PENDING->value,
        ]);

        $resolvedConflict = FederationConflict::create([
            'federated_user_id' => $this->federatedUser->id,
            'field' => 'email',
            'values' => [],
            'status' => FederationConflictStatus::RESOLVED->value,
        ]);

        $this->assertFalse($pendingConflict->isResolved());
        $this->assertTrue($resolvedConflict->isResolved());
    }

    // =========================================================================
    // Scope Tests
    // =========================================================================

    public function test_pending_scope_filters_correctly(): void
    {
        FederationConflict::create([
            'federated_user_id' => $this->federatedUser->id,
            'field' => 'name',
            'values' => [],
            'status' => FederationConflictStatus::PENDING->value,
        ]);

        FederationConflict::create([
            'federated_user_id' => $this->federatedUser->id,
            'field' => 'email',
            'values' => [],
            'status' => FederationConflictStatus::RESOLVED->value,
        ]);

        $pendingConflicts = FederationConflict::pending()->get();

        $this->assertCount(1, $pendingConflicts);
        $this->assertEquals('name', $pendingConflicts->first()->field);
    }

    public function test_resolved_scope_filters_correctly(): void
    {
        FederationConflict::create([
            'federated_user_id' => $this->federatedUser->id,
            'field' => 'name',
            'values' => [],
            'status' => FederationConflictStatus::PENDING->value,
        ]);

        FederationConflict::create([
            'federated_user_id' => $this->federatedUser->id,
            'field' => 'email',
            'values' => [],
            'status' => FederationConflictStatus::RESOLVED->value,
        ]);

        $resolvedConflicts = FederationConflict::resolved()->get();

        $this->assertCount(1, $resolvedConflicts);
        $this->assertEquals('email', $resolvedConflicts->first()->field);
    }

    public function test_for_field_scope(): void
    {
        FederationConflict::create([
            'federated_user_id' => $this->federatedUser->id,
            'field' => 'name',
            'values' => [],
            'status' => FederationConflictStatus::PENDING->value,
        ]);

        FederationConflict::create([
            'federated_user_id' => $this->federatedUser->id,
            'field' => 'email',
            'values' => [],
            'status' => FederationConflictStatus::PENDING->value,
        ]);

        $nameConflicts = FederationConflict::forField('name')->get();

        $this->assertCount(1, $nameConflicts);
    }

    public function test_for_user_scope(): void
    {
        $otherUser = FederatedUser::create([
            'federation_group_id' => $this->group->id,
            'global_email' => 'other@example.com',
            'synced_data' => ['name' => 'Other User'],
            'master_tenant_id' => $this->masterTenant->id,
            'master_tenant_user_id' => \Illuminate\Support\Str::uuid()->toString(),
            'status' => FederatedUserStatus::ACTIVE,
            'sync_version' => 1,
        ]);

        FederationConflict::create([
            'federated_user_id' => $this->federatedUser->id,
            'field' => 'name',
            'values' => [],
            'status' => FederationConflictStatus::PENDING->value,
        ]);

        FederationConflict::create([
            'federated_user_id' => $otherUser->id,
            'field' => 'name',
            'values' => [],
            'status' => FederationConflictStatus::PENDING->value,
        ]);

        $userConflicts = FederationConflict::forUser($this->federatedUser->id)->get();

        $this->assertCount(1, $userConflicts);
    }

    // =========================================================================
    // Find or Create Tests
    // =========================================================================

    public function test_find_or_create_pending_creates_new(): void
    {
        $conflict = FederationConflict::findOrCreatePending($this->federatedUser->id, 'locale');

        $this->assertNotNull($conflict);
        $this->assertEquals('locale', $conflict->field);
        $this->assertEquals(FederationConflictStatus::PENDING, $conflict->status);
    }

    public function test_find_or_create_pending_returns_existing(): void
    {
        $existing = FederationConflict::create([
            'federated_user_id' => $this->federatedUser->id,
            'field' => 'locale',
            'values' => [
                $this->masterTenant->id => ['value' => 'en', 'updated_at' => now()->toIso8601String()],
            ],
            'status' => FederationConflictStatus::PENDING->value,
        ]);

        $found = FederationConflict::findOrCreatePending($this->federatedUser->id, 'locale');

        $this->assertEquals($existing->id, $found->id);
        $this->assertNotEmpty($found->values);
    }

    public function test_find_or_create_creates_new_if_existing_is_resolved(): void
    {
        FederationConflict::create([
            'federated_user_id' => $this->federatedUser->id,
            'field' => 'locale',
            'values' => [],
            'status' => FederationConflictStatus::RESOLVED->value,
        ]);

        $newConflict = FederationConflict::findOrCreatePending($this->federatedUser->id, 'locale');

        $this->assertEquals(FederationConflictStatus::PENDING, $newConflict->status);
    }

    // =========================================================================
    // Controller Tests - Index
    // =========================================================================

    public function test_conflicts_index_page_can_be_rendered(): void
    {
        FederationConflict::create([
            'federated_user_id' => $this->federatedUser->id,
            'field' => 'name',
            'values' => [],
            'status' => FederationConflictStatus::PENDING->value,
        ]);

        $response = $this->actingAs($this->admin, 'central')
            ->get(route('central.admin.federation.conflicts.index', $this->group));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('central/admin/federation/conflicts')
            ->has('group')
            ->has('conflicts', 1)
        );
    }

    public function test_conflicts_index_requires_authentication(): void
    {
        $response = $this->get(route('central.admin.federation.conflicts.index', $this->group));

        $response->assertRedirect(route('central.admin.auth.login'));
    }

    public function test_conflicts_index_requires_federation_view_permission(): void
    {
        $adminWithoutPermission = User::factory()->create();

        $response = $this->actingAs($adminWithoutPermission, 'central')
            ->get(route('central.admin.federation.conflicts.index', $this->group));

        $response->assertForbidden();
    }

    // =========================================================================
    // Controller Tests - Show
    // =========================================================================

    public function test_conflict_show_page_can_be_rendered(): void
    {
        $conflict = FederationConflict::create([
            'federated_user_id' => $this->federatedUser->id,
            'field' => 'name',
            'values' => [
                $this->masterTenant->id => ['value' => 'John Doe', 'updated_at' => now()->toIso8601String()],
                $this->branchTenant1->id => ['value' => 'John Smith', 'updated_at' => now()->toIso8601String()],
            ],
            'status' => FederationConflictStatus::PENDING->value,
        ]);

        $response = $this->actingAs($this->admin, 'central')
            ->get(route('central.admin.federation.conflicts.show', [$this->group, $conflict]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('central/admin/federation/conflict')
            ->has('group')
            ->has('conflict')
        );
    }

    // =========================================================================
    // Controller Tests - Resolve
    // =========================================================================

    public function test_conflict_can_be_resolved_via_controller(): void
    {
        $conflict = FederationConflict::create([
            'federated_user_id' => $this->federatedUser->id,
            'field' => 'name',
            'values' => [
                $this->masterTenant->id => ['value' => 'John Doe', 'updated_at' => now()->toIso8601String()],
                $this->branchTenant1->id => ['value' => 'John Smith', 'updated_at' => now()->toIso8601String()],
            ],
            'status' => FederationConflictStatus::PENDING->value,
        ]);

        $response = $this->actingAs($this->admin, 'central')
            ->post(route('central.admin.federation.conflicts.resolve', [$this->group, $conflict]), [
                'resolved_value' => 'John Doe',
                'resolution' => FederationConflict::RESOLUTION_MASTER_VALUE,
                'notes' => 'Using master value',
            ]);

        $response->assertRedirect(route('central.admin.federation.conflicts.index', $this->group));
        $response->assertSessionHas('success');

        $conflict->refresh();
        $this->assertEquals(FederationConflictStatus::RESOLVED, $conflict->status);
    }

    public function test_resolve_requires_manage_conflicts_permission(): void
    {
        $viewOnlyAdmin = User::factory()->create();
        $viewOnlyAdmin->givePermissionTo(CentralPermission::FEDERATION_VIEW->value);

        $conflict = FederationConflict::create([
            'federated_user_id' => $this->federatedUser->id,
            'field' => 'name',
            'values' => [],
            'status' => FederationConflictStatus::PENDING->value,
        ]);

        $response = $this->actingAs($viewOnlyAdmin, 'central')
            ->post(route('central.admin.federation.conflicts.resolve', [$this->group, $conflict]), [
                'resolved_value' => 'John Doe',
                'resolution' => FederationConflict::RESOLUTION_MANUAL,
            ]);

        $response->assertForbidden();
    }

    // =========================================================================
    // Controller Tests - Dismiss
    // =========================================================================

    public function test_conflict_can_be_dismissed_via_controller(): void
    {
        $conflict = FederationConflict::create([
            'federated_user_id' => $this->federatedUser->id,
            'field' => 'name',
            'values' => [],
            'status' => FederationConflictStatus::PENDING->value,
        ]);

        $response = $this->actingAs($this->admin, 'central')
            ->post(route('central.admin.federation.conflicts.dismiss', [$this->group, $conflict]));

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $conflict->refresh();
        $this->assertEquals(FederationConflictStatus::DISMISSED, $conflict->status);
    }

    public function test_dismiss_requires_manage_conflicts_permission(): void
    {
        $viewOnlyAdmin = User::factory()->create();
        $viewOnlyAdmin->givePermissionTo(CentralPermission::FEDERATION_VIEW->value);

        $conflict = FederationConflict::create([
            'federated_user_id' => $this->federatedUser->id,
            'field' => 'name',
            'values' => [],
            'status' => FederationConflictStatus::PENDING->value,
        ]);

        $response = $this->actingAs($viewOnlyAdmin, 'central')
            ->post(route('central.admin.federation.conflicts.dismiss', [$this->group, $conflict]));

        $response->assertForbidden();
    }

    // =========================================================================
    // FederationService Conflict Tests
    // =========================================================================

    public function test_service_creates_conflict_for_manual_review(): void
    {
        $service = app(FederationService::class);

        $service->createConflict(
            $this->federatedUser,
            ['name' => 'Conflicting Name'],
            $this->branchTenant1
        );

        $this->assertDatabaseHas('federation_conflicts', [
            'federated_user_id' => $this->federatedUser->id,
            'field' => 'name',
            'status' => FederationConflictStatus::PENDING->value,
        ]);
    }

    public function test_service_gets_pending_conflicts_for_group(): void
    {
        FederationConflict::create([
            'federated_user_id' => $this->federatedUser->id,
            'field' => 'name',
            'values' => [],
            'status' => FederationConflictStatus::PENDING->value,
        ]);

        FederationConflict::create([
            'federated_user_id' => $this->federatedUser->id,
            'field' => 'locale',
            'values' => [],
            'status' => FederationConflictStatus::RESOLVED->value,
        ]);

        $service = app(FederationService::class);
        $pendingConflicts = $service->getPendingConflicts($this->group);

        $this->assertCount(1, $pendingConflicts);
    }

    public function test_service_resolves_conflict_and_updates_user(): void
    {
        $conflict = FederationConflict::create([
            'federated_user_id' => $this->federatedUser->id,
            'field' => 'name',
            'values' => [
                $this->masterTenant->id => ['value' => 'John Doe', 'updated_at' => now()->toIso8601String()],
                $this->branchTenant1->id => ['value' => 'John Smith', 'updated_at' => now()->toIso8601String()],
            ],
            'status' => FederationConflictStatus::PENDING->value,
        ]);

        $service = app(FederationService::class);
        $service->resolveConflict(
            conflict: $conflict,
            resolvedValue: 'John Smith',
            resolverId: $this->admin->id,
            resolution: FederationConflict::RESOLUTION_MANUAL,
            notes: 'Branch value was correct'
        );

        $conflict->refresh();
        $this->federatedUser->refresh();

        $this->assertEquals(FederationConflictStatus::RESOLVED, $conflict->status);
        $this->assertEquals('John Smith', $this->federatedUser->getSyncedField('name'));
    }

    // =========================================================================
    // Relationship Tests
    // =========================================================================

    public function test_conflict_belongs_to_federated_user(): void
    {
        $conflict = FederationConflict::create([
            'federated_user_id' => $this->federatedUser->id,
            'field' => 'name',
            'values' => [],
            'status' => FederationConflictStatus::PENDING->value,
        ]);

        $this->assertNotNull($conflict->federatedUser);
        $this->assertEquals($this->federatedUser->id, $conflict->federatedUser->id);
    }

    public function test_conflict_belongs_to_resolver(): void
    {
        $conflict = FederationConflict::create([
            'federated_user_id' => $this->federatedUser->id,
            'field' => 'name',
            'values' => [],
            'status' => FederationConflictStatus::PENDING->value,
        ]);

        $conflict->resolve('John Doe', $this->admin->id);

        $conflict->refresh();

        $this->assertNotNull($conflict->resolver);
        $this->assertEquals($this->admin->id, $conflict->resolver->id);
    }
}
