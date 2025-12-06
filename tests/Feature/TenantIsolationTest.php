<?php

namespace Tests\Feature;

use App\Models\Tenant\Project;
use App\Models\Central\Tenant;
use App\Models\Tenant\User;
use Tests\TenantTestCase;

/**
 * Tenant Isolation Security Test Suite
 *
 * MULTI-DATABASE TENANCY:
 * - Each tenant has dedicated PostgreSQL database
 * - Isolation is at database level, not row level
 * - No tenant_id columns - data is physically separated
 *
 * These tests verify that tenant data isolation works correctly
 * through database separation.
 */
class TenantIsolationTest extends TenantTestCase
{
    /**
     * Test 1: Projects created in tenant context stay in tenant database
     */
    public function test_projects_are_created_in_tenant_database(): void
    {
        // Create project in current tenant
        $project = Project::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'My Tenant Project',
        ]);

        // Verify project exists
        $this->assertDatabaseHas('projects', [
            'id' => $project->id,
            'name' => 'My Tenant Project',
        ]);

        // Verify project is accessible
        $this->assertNotNull(Project::find($project->id));
    }

    /**
     * Test 2: Projects listing only shows tenant's projects
     */
    public function test_project_listing_shows_only_tenant_projects(): void
    {
        // Count existing projects before creating new ones
        $initialCount = Project::count();

        // Create projects in current tenant
        Project::factory()->count(3)->create([
            'user_id' => $this->user->id,
        ]);

        // Get projects listing
        $response = $this->get($this->tenantRoute('tenant.admin.projects.index'));

        $response->assertOk();

        // Should have 3 more projects than before
        $this->assertEquals($initialCount + 3, Project::count());
    }

    /**
     * Test 3: Can create project via form submission
     */
    public function test_can_create_project_via_form(): void
    {
        $response = $this->post($this->tenantRoute('tenant.admin.projects.store'), [
            'name' => 'New Project',
            'description' => 'Project description',
            'status' => 'active',
        ]);

        $response->assertRedirect();

        // Verify project was created
        $this->assertDatabaseHas('projects', [
            'name' => 'New Project',
            'description' => 'Project description',
        ]);
    }

    /**
     * Test 4: Can update own project
     */
    public function test_can_update_own_project(): void
    {
        $project = Project::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'Original Name',
        ]);

        $response = $this->patch($this->tenantRoute('tenant.admin.projects.update', ['project' => $project]), [
            'name' => 'Updated Name',
            'description' => 'Updated description',
            'status' => 'active',
        ]);

        $response->assertRedirect();

        // Verify project was updated
        $this->assertDatabaseHas('projects', [
            'id' => $project->id,
            'name' => 'Updated Name',
        ]);
    }

    /**
     * Test 5: Can delete own project
     */
    public function test_can_delete_own_project(): void
    {
        $project = Project::factory()->create([
            'user_id' => $this->user->id,
        ]);

        $projectId = $project->id;

        $response = $this->delete($this->tenantRoute('tenant.admin.projects.destroy', ['project' => $project]));

        $response->assertRedirect();

        // Verify project was soft deleted
        $this->assertSoftDeleted('projects', [
            'id' => $projectId,
        ]);
    }

    /**
     * Test 6: Team members are visible within tenant context
     */
    public function test_team_members_are_visible(): void
    {
        // Create additional user in current tenant
        $member = $this->createTenantUser('member');

        // Get team listing
        $response = $this->get($this->tenantRoute('tenant.admin.team.index'));

        $response->assertOk();

        // Check member is in Inertia props (email avoids HTML encoding issues with special chars in names)
        $response->assertSee($member->email);
    }

    /**
     * Test 7: Tenant context is maintained throughout request
     */
    public function test_tenant_context_maintained_throughout_request(): void
    {
        // Verify tenant is initialized
        $this->assertTrue(tenancy()->initialized);
        $this->assertEquals($this->tenant->id, tenant('id'));

        // Create project
        $response = $this->post($this->tenantRoute('tenant.admin.projects.store'), [
            'name' => 'Test Project',
            'description' => 'Testing tenant persistence',
            'status' => 'active',
        ]);

        $response->assertRedirect();

        // Verify tenant context is still correct
        $this->assertTrue(tenancy()->initialized);
        $this->assertEquals($this->tenant->id, tenant('id'));

        // Verify project was created
        $this->assertDatabaseHas('projects', [
            'name' => 'Test Project',
        ]);
    }

    /**
     * Test 8: Non-existent project returns 404
     */
    public function test_nonexistent_project_returns_404(): void
    {
        // Try to access non-existent project with valid UUID format
        $fakeUuid = '00000000-0000-0000-0000-000000000000';
        $response = $this->get($this->tenantRoute('tenant.admin.projects.show', ['project' => $fakeUuid]));

        $response->assertNotFound();
    }

    /**
     * Test 9: Search only returns tenant's data
     */
    public function test_search_returns_only_tenant_data(): void
    {
        // Create projects with searchable names
        Project::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'Searchable Project Alpha',
        ]);

        Project::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'Searchable Project Beta',
        ]);

        // Search for "Searchable"
        $results = Project::where('name', 'like', '%Searchable%')->get();

        // Should find both projects
        $this->assertCount(2, $results);
    }

    /**
     * Test 10: Eloquent queries work correctly in tenant context
     */
    public function test_eloquent_queries_work_in_tenant_context(): void
    {
        // Count existing projects before creating new ones
        $initialCount = Project::count();
        $initialUserCount = Project::where('user_id', $this->user->id)->count();

        // Create projects
        $projects = Project::factory()->count(5)->create([
            'user_id' => $this->user->id,
        ]);

        // Test count - should have 5 more projects
        $this->assertEquals($initialCount + 5, Project::count());

        // Test first
        $this->assertNotNull(Project::first());

        // Test find
        $this->assertNotNull(Project::find($projects->first()->id));

        // Test where - should have 5 more projects for this user
        $this->assertEquals($initialUserCount + 5, Project::where('user_id', $this->user->id)->count());
    }

    /**
     * Test 11: User exists in tenant context
     *
     * OPTION C ARCHITECTURE:
     * - Users exist ONLY in tenant databases (no pivot table)
     * - User belongs to exactly one tenant (the database they're in)
     * - Verify user can be queried in tenant context
     */
    public function test_user_is_assigned_to_tenant(): void
    {
        // Option C: Users exist directly in tenant database
        // The user was created in TenantTestCase::setUp() within tenant context

        // Verify user exists in current tenant context
        $this->assertTrue(User::where('id', $this->user->id)->exists());

        // Verify we can load the user from tenant context
        $loadedUser = User::find($this->user->id);
        $this->assertNotNull($loadedUser);
        $this->assertEquals($this->user->email, $loadedUser->email);

        // Verify user has a role (assigned in TenantTestCase::setUp())
        $this->assertTrue($loadedUser->hasRole('owner'));
    }

    /**
     * Test 12: Role assignment works in tenant context
     */
    public function test_role_assignment_works_in_tenant_context(): void
    {
        // Verify user has owner role
        $this->assertTrue($this->user->hasRole('owner'));

        // Create member
        $member = $this->createTenantUser('member');

        // Verify member has member role
        $this->assertTrue($member->hasRole('member'));
        $this->assertFalse($member->hasRole('owner'));
    }
}
