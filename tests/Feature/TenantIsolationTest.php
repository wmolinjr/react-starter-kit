<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TenantTestCase;

/**
 * Comprehensive Tenant Isolation Security Test Suite
 *
 * Verifies that tenant data is completely isolated and cannot be
 * accessed across tenant boundaries through various attack vectors.
 */
class TenantIsolationTest extends TenantTestCase
{
    use RefreshDatabase;

    /**
     * Test 1: Projects - Cannot access other tenant's projects via direct access
     */
    public function test_cannot_access_other_tenant_projects_via_direct_url(): void
    {
        // Create project in another tenant
        $otherTenant = $this->createOtherTenant();
        $otherProject = Project::factory()->create([
            'tenant_id' => $otherTenant->id,
            'name' => 'Secret Project',
        ]);

        // Try to access other tenant's project directly
        $response = $this->get("/projects/{$otherProject->id}");

        $response->assertNotFound();
    }

    /**
     * Test 2: Projects - Cannot see other tenant's projects in listings
     */
    public function test_cannot_see_other_tenant_projects_in_listing(): void
    {
        // Create projects in current tenant
        Project::factory()->count(3)->create([
            'tenant_id' => $this->tenant->id,
        ]);

        // Create projects in another tenant
        $otherTenant = $this->createOtherTenant();
        $otherProjects = Project::factory()->count(5)->create([
            'tenant_id' => $otherTenant->id,
        ]);

        // Get projects listing
        $response = $this->get('/projects');

        $response->assertOk();

        // Verify other tenant's projects are not visible
        foreach ($otherProjects as $otherProject) {
            $response->assertDontSee($otherProject->name);
        }
    }

    /**
     * Test 3: Projects - Cannot update other tenant's projects
     */
    public function test_cannot_update_other_tenant_projects(): void
    {
        $otherTenant = $this->createOtherTenant();
        $otherProject = Project::factory()->create([
            'tenant_id' => $otherTenant->id,
            'name' => 'Original Name',
        ]);

        // Try to update other tenant's project
        $response = $this->patch("/projects/{$otherProject->id}", [
            'name' => 'Hacked Name',
            'description' => 'Should not work',
            'status' => 'active',
        ]);

        $response->assertNotFound();

        // Verify project was not updated
        $this->assertDatabaseHas('projects', [
            'id' => $otherProject->id,
            'name' => 'Original Name',
        ]);
    }

    /**
     * Test 4: Projects - Cannot delete other tenant's projects
     */
    public function test_cannot_delete_other_tenant_projects(): void
    {
        $otherTenant = $this->createOtherTenant();
        $otherProject = Project::factory()->create([
            'tenant_id' => $otherTenant->id,
        ]);

        // Try to delete other tenant's project
        $response = $this->delete("/projects/{$otherProject->id}");

        $response->assertNotFound();

        // Verify project still exists
        $this->assertDatabaseHas('projects', [
            'id' => $otherProject->id,
        ]);
    }

    /**
     * Test 5: Team Members - Cannot access other tenant's team members
     */
    public function test_cannot_see_other_tenant_team_members(): void
    {
        // Create users in another tenant
        $otherTenant = $this->createOtherTenant();
        $otherUser = User::factory()->create();
        $otherTenant->users()->attach($otherUser->id, [
            'joined_at' => now(),
        ]);

        // Assign role using Spatie Permission
        tenancy()->initialize($otherTenant);
        setPermissionsTeamId($otherTenant->id);
        $memberRole = \App\Models\Role::findOrCreate('member', 'web');
        $otherUser->assignRole($memberRole);
        tenancy()->end();

        // Reinitialize test tenant before HTTP request
        tenancy()->initialize($this->tenant);
        setPermissionsTeamId($this->tenant->id);

        // Get team listing
        $response = $this->get('/team');

        $response->assertOk();
        $response->assertDontSee($otherUser->name);
        $response->assertDontSee($otherUser->email);
    }

    /**
     * Test 6: Direct Eloquent queries are automatically scoped
     */
    public function test_eloquent_queries_are_automatically_scoped_to_tenant(): void
    {
        // Create data in current tenant
        Project::factory()->count(3)->create([
            'tenant_id' => $this->tenant->id,
        ]);

        // Create data in another tenant
        $otherTenant = $this->createOtherTenant();
        Project::factory()->count(5)->create([
            'tenant_id' => $otherTenant->id,
        ]);

        // Query without explicit tenant_id filter
        $projects = Project::all();

        // Should only return current tenant's projects
        $this->assertCount(3, $projects);
        $this->assertTrue(
            $projects->every(fn ($project) => $project->tenant_id === $this->tenant->id),
            'All projects should belong to current tenant'
        );
    }

    /**
     * Test 7: Cannot manually bypass tenant scope with direct queries
     */
    public function test_cannot_bypass_tenant_scope_with_where_clause(): void
    {
        $otherTenant = $this->createOtherTenant();
        $otherProject = Project::factory()->create([
            'tenant_id' => $otherTenant->id,
        ]);

        // Try to query other tenant's project with explicit where
        $project = Project::where('id', $otherProject->id)->first();

        // Should return null because of automatic scoping
        $this->assertNull($project, 'Should not be able to access other tenant data even with explicit where clause');
    }

    /**
     * Test 8: Switching tenants properly isolates data
     */
    public function test_switching_tenants_properly_isolates_data(): void
    {
        // Create projects in first tenant
        $firstTenantProjects = Project::factory()->count(2)->create([
            'tenant_id' => $this->tenant->id,
        ]);

        // Verify first tenant can see their projects
        $this->assertCount(2, Project::all());

        // Switch to second tenant
        $secondTenant = $this->createOtherTenant();
        tenancy()->end();
        tenancy()->initialize($secondTenant);

        // Create projects in second tenant
        $secondTenantProjects = Project::factory()->count(3)->create([
            'tenant_id' => $secondTenant->id,
        ]);

        // Verify second tenant only sees their projects
        $this->assertCount(3, Project::all());
        $this->assertTrue(
            Project::all()->every(fn ($p) => $p->tenant_id === $secondTenant->id),
            'All projects should belong to second tenant'
        );

        // Switch back to first tenant
        tenancy()->end();
        tenancy()->initialize($this->tenant);

        // Verify first tenant still only sees their projects
        $this->assertCount(2, Project::all());
        $this->assertTrue(
            Project::all()->every(fn ($p) => $p->tenant_id === $this->tenant->id),
            'All projects should belong to first tenant'
        );
    }

    /**
     * Test 9: User cannot perform actions on behalf of another tenant
     */
    public function test_user_cannot_create_resources_for_other_tenant(): void
    {
        $otherTenant = $this->createOtherTenant();

        // Try to create project with manipulated tenant_id
        $response = $this->post('/projects', [
            'name' => 'Malicious Project',
            'description' => 'Trying to create in another tenant',
            'status' => 'active',
            'tenant_id' => $otherTenant->id, // Try to inject other tenant ID
        ]);

        // Should either be ignored or fail
        $response->assertRedirect();

        // Verify project was created in CURRENT tenant, not the injected one
        $this->assertDatabaseMissing('projects', [
            'name' => 'Malicious Project',
            'tenant_id' => $otherTenant->id,
        ]);

        // If created, should be in current tenant
        $project = Project::where('name', 'Malicious Project')->first();
        if ($project) {
            $this->assertEquals(
                $this->tenant->id,
                $project->tenant_id,
                'Project should be created in current tenant regardless of injected tenant_id'
            );
        }
    }

    /**
     * Test 10: Global search cannot leak data across tenants
     */
    public function test_global_search_respects_tenant_boundaries(): void
    {
        // Create project in current tenant
        Project::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Current Tenant Secret Project',
        ]);

        // Create project with same name in another tenant
        $otherTenant = $this->createOtherTenant();
        Project::factory()->create([
            'tenant_id' => $otherTenant->id,
            'name' => 'Other Tenant Secret Project',
        ]);

        // Search for "Secret"
        $results = Project::where('name', 'like', '%Secret%')->get();

        // Should only find current tenant's project
        $this->assertCount(1, $results);
        $this->assertEquals($this->tenant->id, $results->first()->tenant_id);
        $this->assertEquals('Current Tenant Secret Project', $results->first()->name);
    }

    /**
     * Test 11: File/Media isolation (if using media library)
     */
    public function test_cannot_access_other_tenant_media_files(): void
    {
        $otherTenant = $this->createOtherTenant();
        $otherProject = Project::factory()->create([
            'tenant_id' => $otherTenant->id,
        ]);

        // Simulate media upload
        $mediaId = 999; // Simulated media ID from other tenant

        // Try to download media from other tenant's project
        $response = $this->get("/projects/{$otherProject->id}/media/{$mediaId}/download");

        $response->assertNotFound();
    }

    /**
     * Test 12: Tenant context is maintained throughout request lifecycle
     */
    public function test_tenant_context_persists_throughout_request(): void
    {
        $initialTenantId = current_tenant_id();

        // Create project (which internally queries database)
        $response = $this->post('/projects', [
            'name' => 'Test Project',
            'description' => 'Testing tenant persistence',
            'status' => 'active',
        ]);

        $response->assertRedirect();

        // Verify tenant context didn't change
        $this->assertEquals($initialTenantId, current_tenant_id());

        // Verify project was created in correct tenant
        $this->assertDatabaseHas('projects', [
            'name' => 'Test Project',
            'tenant_id' => $this->tenant->id,
        ]);
    }
}
