<?php

namespace Tests\Feature;

use App\Models\Project;
use Tests\TenantTestCase;

class ProjectTest extends TenantTestCase
{
    /** @test */
    public function user_can_create_project_in_their_tenant()
    {
        $response = $this->post('/projects', [
            'name' => 'Test Project',
            'description' => 'Test description',
            'status' => 'active',
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('projects', [
            'name' => 'Test Project',
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
        ]);
    }

    /** @test */
    public function user_cannot_see_projects_from_other_tenants()
    {
        // Create another tenant with project
        $otherTenant = $this->createOtherTenant();
        $otherProject = Project::factory()->create([
            'tenant_id' => $otherTenant->id,
        ]);

        // Verify it doesn't appear in listing
        $response = $this->get('/projects');

        $response->assertDontSee($otherProject->name);

        // Verify cannot access directly
        $response = $this->get("/projects/{$otherProject->id}");

        $response->assertNotFound();
    }

    /** @test */
    public function member_can_only_edit_own_projects()
    {
        // Create member (not owner)
        $member = $this->createTenantUser('member');

        // Create project owned by owner user
        $otherProject = Project::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
        ]);

        // Member tries to edit owner's project
        $this->actingAs($member);

        $response = $this->patch("/projects/{$otherProject->id}", [
            'name' => 'Updated Name',
        ]);

        $response->assertForbidden();
    }

    /** @test */
    public function owner_can_delete_any_project_in_tenant()
    {
        // Create project owned by member
        $member = $this->createTenantUser('member');
        $memberProject = Project::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $member->id,
        ]);

        // Owner deletes member's project (should be allowed)
        $response = $this->delete("/projects/{$memberProject->id}");

        $response->assertRedirect();

        $this->assertDatabaseMissing('projects', [
            'id' => $memberProject->id,
        ]);
    }

    /** @test */
    public function projects_are_automatically_scoped_to_current_tenant()
    {
        // Create projects in current tenant
        Project::factory()->count(3)->create([
            'tenant_id' => $this->tenant->id,
        ]);

        // Create projects in another tenant
        $otherTenant = $this->createOtherTenant();
        Project::factory()->count(2)->create([
            'tenant_id' => $otherTenant->id,
        ]);

        // Verify only current tenant's projects are visible
        $projects = Project::all();

        $this->assertCount(3, $projects);
        $this->assertTrue($projects->every(fn ($project) => $project->tenant_id === $this->tenant->id));
    }
}
