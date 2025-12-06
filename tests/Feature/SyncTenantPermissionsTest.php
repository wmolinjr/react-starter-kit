<?php

namespace Tests\Feature;

use App\Jobs\SyncTenantPermissions;
use App\Models\Shared\Permission;
use App\Models\Central\Plan;
use App\Models\Shared\Role;
use App\Models\Central\Tenant;
use App\Services\Central\PlanPermissionResolver;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SyncTenantPermissionsTest extends TestCase
{

    protected function setUp(): void
    {
        parent::setUp();

        // Seed plans
        \Artisan::call('db:seed', ['--class' => 'PlanSeeder']);

        // Sync permissions
        \Artisan::call('permissions:sync');
    }

    #[Test]
    public function creates_missing_permissions_in_tenant_database(): void
    {
        $proPlan = Plan::where('slug', 'professional')->first();
        $tenant = Tenant::factory()->create(['plan_id' => $proPlan->id]);

        // Create domain for tenant
        $tenant->domains()->create([
            'domain' => $tenant->slug . '.test',
            'is_primary' => true,
        ]);

        // Initialize tenant context
        tenancy()->initialize($tenant);

        // Delete all permissions to start fresh
        Permission::truncate();

        // Run the job
        $job = new SyncTenantPermissions($tenant);
        $job->handle(new PlanPermissionResolver());

        // Permissions should have been created
        $this->assertDatabaseHas('permissions', [
            'name' => 'projects:view',
            'guard_name' => 'tenant',
        ]);

        $this->assertDatabaseHas('permissions', [
            'name' => 'roles:view',
            'guard_name' => 'tenant',
        ]);

        tenancy()->end();
    }

    #[Test]
    public function updates_plan_enabled_permissions_cache(): void
    {
        $proPlan = Plan::where('slug', 'professional')->first();
        $tenant = Tenant::factory()->create([
            'plan_id' => $proPlan->id,
            'plan_enabled_permissions' => [], // Start empty
        ]);

        $tenant->domains()->create([
            'domain' => $tenant->slug . '.test',
            'is_primary' => true,
        ]);

        tenancy()->initialize($tenant);

        // Seed basic permissions
        $this->seedTenantPermissions();

        // Run the job
        $job = new SyncTenantPermissions($tenant);
        $job->handle(new PlanPermissionResolver());

        tenancy()->end();

        // Refresh tenant from database
        $tenant->refresh();

        // Cache should be populated
        $this->assertNotEmpty($tenant->plan_enabled_permissions);
        $this->assertContains('roles:view', $tenant->plan_enabled_permissions);
    }

    #[Test]
    public function removes_unauthorized_permissions_on_downgrade(): void
    {
        $proPlan = Plan::where('slug', 'professional')->first();
        $starterPlan = Plan::where('slug', 'starter')->first();

        $tenant = Tenant::factory()->create(['plan_id' => $proPlan->id]);
        $tenant->domains()->create([
            'domain' => $tenant->slug . '.test',
            'is_primary' => true,
        ]);

        tenancy()->initialize($tenant);

        // Seed permissions and create a custom role with pro permissions
        $this->seedTenantPermissions();

        $customRole = Role::create([
            'name' => 'custom-manager',
            'guard_name' => 'tenant',
            'is_protected' => false,
        ]);

        // Give it permissions including custom roles (pro feature)
        $customRole->givePermissionTo([
            'projects:view',
            'roles:view',
            'roles:create',
        ]);

        // Verify role has the permissions
        $this->assertTrue($customRole->hasPermissionTo('roles:view'));

        tenancy()->end();

        // Downgrade to Starter
        $tenant->update(['plan_id' => $starterPlan->id]);
        $tenant->refresh();

        tenancy()->initialize($tenant);

        // Run job with isDowngrade=true
        $job = new SyncTenantPermissions($tenant, isDowngrade: true);
        $job->handle(new PlanPermissionResolver());

        // Refresh role
        $customRole->refresh();

        // Role should still have base permissions
        $this->assertTrue($customRole->hasPermissionTo('projects:view'));

        // Role should NOT have custom roles permissions anymore
        $this->assertFalse($customRole->hasPermissionTo('roles:view'));
        $this->assertFalse($customRole->hasPermissionTo('roles:create'));

        tenancy()->end();
    }

    #[Test]
    public function syncs_default_roles_permissions(): void
    {
        $proPlan = Plan::where('slug', 'professional')->first();
        $tenant = Tenant::factory()->create(['plan_id' => $proPlan->id]);
        $tenant->domains()->create([
            'domain' => $tenant->slug . '.test',
            'is_primary' => true,
        ]);

        tenancy()->initialize($tenant);

        // Seed permissions
        $this->seedTenantPermissions();

        // Create default roles with minimal permissions
        $ownerRole = Role::create([
            'name' => 'owner',
            'guard_name' => 'tenant',
            'is_protected' => true,
        ]);
        $ownerRole->givePermissionTo('projects:view');

        $adminRole = Role::create([
            'name' => 'admin',
            'guard_name' => 'tenant',
            'is_protected' => true,
        ]);
        $adminRole->givePermissionTo('projects:view');

        $memberRole = Role::create([
            'name' => 'member',
            'guard_name' => 'tenant',
            'is_protected' => true,
        ]);
        $memberRole->givePermissionTo('projects:view');

        // Run the job
        $job = new SyncTenantPermissions($tenant);
        $job->handle(new PlanPermissionResolver());

        // Refresh roles
        $ownerRole->refresh();
        $adminRole->refresh();
        $memberRole->refresh();

        // Owner should have all plan permissions (Pro plan)
        $this->assertTrue($ownerRole->hasPermissionTo('roles:view'));
        $this->assertTrue($ownerRole->hasPermissionTo('apiTokens:view'));
        $this->assertTrue($ownerRole->hasPermissionTo('projects:view'));

        // Admin should have most permissions but NOT apiTokens
        $this->assertTrue($adminRole->hasPermissionTo('projects:view'));
        $this->assertFalse($adminRole->hasPermissionTo('apiTokens:view'));

        // Member should have only view permissions
        $this->assertTrue($memberRole->hasPermissionTo('projects:view'));
        $this->assertFalse($memberRole->hasPermissionTo('projects:delete'));
        $this->assertFalse($memberRole->hasPermissionTo('roles:view'));

        tenancy()->end();
    }

    #[Test]
    public function job_is_queueable(): void
    {
        Queue::fake();

        $tenant = Tenant::factory()->create();

        SyncTenantPermissions::dispatch($tenant);

        Queue::assertPushed(SyncTenantPermissions::class, function ($job) use ($tenant) {
            return $job->tenant->id === $tenant->id;
        });
    }

    #[Test]
    public function job_has_correct_tags(): void
    {
        $tenant = Tenant::factory()->create();

        $job = new SyncTenantPermissions($tenant);
        $tags = $job->tags();

        $this->assertContains('tenant:' . $tenant->id, $tags);
        $this->assertContains('sync-permissions', $tags);
    }

    #[Test]
    public function member_role_gets_only_view_and_editown_permissions(): void
    {
        $proPlan = Plan::where('slug', 'professional')->first();
        $tenant = Tenant::factory()->create(['plan_id' => $proPlan->id]);
        $tenant->domains()->create([
            'domain' => $tenant->slug . '.test',
            'is_primary' => true,
        ]);

        tenancy()->initialize($tenant);

        $this->seedTenantPermissions();

        $memberRole = Role::firstOrCreate(
            ['name' => 'member', 'guard_name' => 'tenant'],
            ['is_protected' => true]
        );

        // Clear any existing permissions to test fresh
        $memberRole->syncPermissions([]);

        $job = new SyncTenantPermissions($tenant);
        $job->handle(new PlanPermissionResolver());

        $memberRole->refresh();
        $memberPermissions = $memberRole->permissions->pluck('name')->toArray();

        // Should have view permissions
        $this->assertContains('projects:view', $memberPermissions);
        $this->assertContains('team:view', $memberPermissions);
        $this->assertContains('settings:view', $memberPermissions);

        // Should have editOwn
        $this->assertContains('projects:editOwn', $memberPermissions);

        // Should have download
        $this->assertContains('projects:download', $memberPermissions);

        // Should NOT have edit/delete/create (except create is not blocked)
        $this->assertNotContains('projects:delete', $memberPermissions);
        $this->assertNotContains('team:invite', $memberPermissions);
        $this->assertNotContains('roles:create', $memberPermissions);

        tenancy()->end();
    }

    /**
     * Seed tenant permissions for testing.
     */
    protected function seedTenantPermissions(): void
    {
        $permissions = [
            ['name' => 'projects:view', 'category' => 'projects'],
            ['name' => 'projects:create', 'category' => 'projects'],
            ['name' => 'projects:edit', 'category' => 'projects'],
            ['name' => 'projects:editOwn', 'category' => 'projects'],
            ['name' => 'projects:delete', 'category' => 'projects'],
            ['name' => 'projects:upload', 'category' => 'projects'],
            ['name' => 'projects:download', 'category' => 'projects'],
            ['name' => 'projects:archive', 'category' => 'projects'],
            ['name' => 'team:view', 'category' => 'team'],
            ['name' => 'team:invite', 'category' => 'team'],
            ['name' => 'team:remove', 'category' => 'team'],
            ['name' => 'team:manageRoles', 'category' => 'team'],
            ['name' => 'team:activity', 'category' => 'team'],
            ['name' => 'settings:view', 'category' => 'settings'],
            ['name' => 'settings:edit', 'category' => 'settings'],
            ['name' => 'settings:danger', 'category' => 'settings'],
            ['name' => 'billing:view', 'category' => 'billing'],
            ['name' => 'billing:manage', 'category' => 'billing'],
            ['name' => 'billing:invoices', 'category' => 'billing'],
            ['name' => 'apiTokens:view', 'category' => 'apiTokens'],
            ['name' => 'apiTokens:create', 'category' => 'apiTokens'],
            ['name' => 'apiTokens:delete', 'category' => 'apiTokens'],
            ['name' => 'roles:view', 'category' => 'roles'],
            ['name' => 'roles:create', 'category' => 'roles'],
            ['name' => 'roles:edit', 'category' => 'roles'],
            ['name' => 'roles:delete', 'category' => 'roles'],
            ['name' => 'locales:view', 'category' => 'locales'],
            ['name' => 'locales:manage', 'category' => 'locales'],
        ];

        foreach ($permissions as $permData) {
            Permission::firstOrCreate([
                'name' => $permData['name'],
                'guard_name' => 'tenant',
            ], [
                'category' => $permData['category'],
            ]);
        }
    }
}
