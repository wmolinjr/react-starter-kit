<?php

namespace Tests\Feature\Tenant;

use App\Events\Tenant\UserCreated;
use App\Listeners\Tenant\AutoFederateNewUser;
use App\Enums\FederatedUserStatus;
use App\Models\Central\FederatedUser;
use App\Models\Central\FederationGroup;
use App\Enums\FederationSyncStrategy;
use App\Models\Central\Tenant;
use App\Models\Tenant\User;
use App\Services\Central\FederationService as CentralFederationService;
use App\Services\Tenant\FederationService as TenantFederationService;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Tests\TenantTestCase;

class AutoFederateNewUserTest extends TenantTestCase
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
    // Auto-Federate Setting Tests
    // =========================================================================

    public function test_listener_federates_user_when_auto_federate_enabled(): void
    {
        $uniqueId = uniqid();

        // Enable auto-federate
        $this->group->settings = array_merge($this->group->settings ?? [], [
            'auto_federate_new_users' => true,
        ]);
        $this->group->save();

        // Create user without triggering the event
        Event::fake([UserCreated::class]);
        $email = "newuser-{$uniqueId}@example.com";
        $user = User::factory()->create(['email' => $email]);

        // Manually dispatch event
        $event = new UserCreated($user);
        $listener = app(AutoFederateNewUser::class);
        $listener->handle($event);

        // User should be federated
        $user->refresh();
        $this->assertNotNull($user->federated_user_id);
    }

    public function test_listener_skips_federation_when_auto_federate_disabled(): void
    {
        $uniqueId = uniqid();

        // Disable auto-federate (default)
        $this->group->settings = array_merge($this->group->settings ?? [], [
            'auto_federate_new_users' => false,
        ]);
        $this->group->save();

        // Create user without triggering the event
        Event::fake([UserCreated::class]);
        $email = "localonly-{$uniqueId}@example.com";
        $user = User::factory()->create(['email' => $email]);

        // Manually dispatch event
        $event = new UserCreated($user);
        $listener = app(AutoFederateNewUser::class);
        $listener->handle($event);

        // User should NOT be federated
        $user->refresh();
        $this->assertNull($user->federated_user_id);

        $this->assertDatabaseMissing('federated_users', [
            'global_email' => $email,
        ], 'central');
    }

    public function test_listener_skips_already_federated_users(): void
    {
        $uniqueId = uniqid();

        // Enable auto-federate
        $this->group->settings = array_merge($this->group->settings ?? [], [
            'auto_federate_new_users' => true,
        ]);
        $this->group->save();

        // Create user and manually federate
        Event::fake([UserCreated::class]);
        $email = "federated-skip-{$uniqueId}@example.com";
        $user = User::factory()->create(['email' => $email]);
        $federatedUser = FederatedUser::create([
            'federation_group_id' => $this->group->id,
            'global_email' => $email,
            'synced_data' => ['name' => 'Test'],
            'master_tenant_id' => $this->tenant->id,
            'master_tenant_user_id' => $user->id,
            'status' => FederatedUserStatus::ACTIVE,
            'sync_version' => 1,
        ]);
        $user->update(['federated_user_id' => $federatedUser->id]);

        // Dispatch event
        $event = new UserCreated($user);
        $listener = app(AutoFederateNewUser::class);
        $listener->handle($event);

        // Should only have one federated user record
        $this->assertEquals(1, FederatedUser::where('global_email', $email)->count());
    }

    public function test_listener_handles_already_federated_user(): void
    {
        $uniqueId = uniqid();

        // Enable auto-federate
        $this->group->settings = array_merge($this->group->settings ?? [], [
            'auto_federate_new_users' => true,
        ]);
        $this->group->save();

        // Create user and manually federate
        Event::fake([UserCreated::class]);
        $email = "test-already-fed-{$uniqueId}@example.com";
        $user = User::factory()->create(['email' => $email]);
        $federatedUser = FederatedUser::create([
            'federation_group_id' => $this->group->id,
            'global_email' => $email,
            'synced_data' => ['name' => 'Test'],
            'master_tenant_id' => $this->tenant->id,
            'master_tenant_user_id' => $user->id,
            'status' => FederatedUserStatus::ACTIVE,
            'sync_version' => 1,
        ]);
        $user->forceFill(['federated_user_id' => $federatedUser->id])->save();

        // The listener should skip already federated users and not throw
        $event = new UserCreated($user);
        $listener = app(AutoFederateNewUser::class);
        $listener->handle($event);

        // User should still have same federated_user_id
        $user->refresh();
        $this->assertEquals($federatedUser->id, $user->federated_user_id);
    }

    public function test_listener_logs_success_on_federation(): void
    {
        $uniqueId = uniqid();

        // Enable auto-federate
        $this->group->settings = array_merge($this->group->settings ?? [], [
            'auto_federate_new_users' => true,
        ]);
        $this->group->save();

        Event::fake([UserCreated::class]);
        $email = "logtest-{$uniqueId}@example.com";
        $user = User::factory()->create(['email' => $email]);

        // Mock Log to expect info
        Log::shouldReceive('info')
            ->once()
            ->andReturnUsing(function ($message, $context) use ($user) {
                $this->assertEquals('Auto-federated new user', $message);
                $this->assertEquals($user->id, $context['user_id']);
                $this->assertEquals($user->email, $context['email']);
            });

        // Allow other log calls
        Log::shouldReceive('warning')->andReturnNull();

        $event = new UserCreated($user);
        $listener = app(AutoFederateNewUser::class);
        $listener->handle($event);
    }

    // =========================================================================
    // Non-Federated Tenant Tests
    // =========================================================================

    public function test_listener_does_nothing_for_non_federated_tenant(): void
    {
        $uniqueId = uniqid();

        // End tenancy and create a non-federated tenant
        tenancy()->end();

        $nonFederatedTenant = Tenant::factory()->create(['slug' => 'non-fed-' . $uniqueId]);
        $nonFederatedTenant->domains()->create([
            'domain' => $nonFederatedTenant->slug . '.test',
            'is_primary' => true,
        ]);

        tenancy()->initialize($nonFederatedTenant);

        // Create user
        Event::fake([UserCreated::class]);
        $user = User::factory()->create(['email' => "nonfedtenant-{$uniqueId}@example.com"]);

        // Dispatch event
        $event = new UserCreated($user);
        $listener = app(AutoFederateNewUser::class);
        $listener->handle($event);

        // User should NOT be federated
        $user->refresh();
        $this->assertNull($user->federated_user_id);

        // Cleanup
        tenancy()->end();
        tenancy()->initialize($this->tenant);
    }

    // =========================================================================
    // Event Integration Tests
    // =========================================================================

    public function test_user_created_event_triggers_listener(): void
    {
        $uniqueId = uniqid();

        // Enable auto-federate
        $this->group->settings = array_merge($this->group->settings ?? [], [
            'auto_federate_new_users' => true,
        ]);
        $this->group->save();

        // Create user (event should be dispatched automatically via model boot)
        $user = User::factory()->create(['email' => "eventtest-{$uniqueId}@example.com"]);

        // Give queue time to process (or sync if synchronous)
        // The listener is queued, so we need to process the queue
        $this->artisan('queue:work', ['--once' => true, '--queue' => 'default']);

        // User should be federated after queue processes
        $user->refresh();
        $this->assertNotNull($user->federated_user_id);
    }

    // =========================================================================
    // shouldAutoFederateNewUsers Model Method Tests
    // =========================================================================

    public function test_should_auto_federate_new_users_returns_true_when_enabled(): void
    {
        $this->group->settings = array_merge($this->group->settings ?? [], [
            'auto_federate_new_users' => true,
        ]);
        $this->group->save();

        $this->assertTrue($this->group->fresh()->shouldAutoFederateNewUsers());
    }

    public function test_should_auto_federate_new_users_returns_false_when_disabled(): void
    {
        $this->group->settings = array_merge($this->group->settings ?? [], [
            'auto_federate_new_users' => false,
        ]);
        $this->group->save();

        $this->assertFalse($this->group->fresh()->shouldAutoFederateNewUsers());
    }

    public function test_should_auto_federate_new_users_defaults_to_false(): void
    {
        // Create group with empty settings
        $group = FederationGroup::create([
            'name' => 'Default Settings Group',
            'master_tenant_id' => $this->tenant->id,
            'sync_strategy' => FederationSyncStrategy::MASTER_WINS->value,
            'is_active' => true,
        ]);

        $this->assertFalse($group->shouldAutoFederateNewUsers());
    }
}
