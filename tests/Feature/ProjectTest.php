<?php

namespace Tests\Feature;

use App\Models\Tenant\Project;
use PHPUnit\Framework\Attributes\Test;
use Tests\TenantTestCase;

/**
 * Project Model & Controller Test Suite
 *
 * MULTI-DATABASE TENANCY:
 * - Project lives in tenant database
 * - Tests run within tenant context (via TenantTestCase)
 * - No tenant_id column - isolation is at database level
 * - Cross-tenant access tests are not applicable (data is physically separated)
 */
class ProjectTest extends TenantTestCase
{
    #[Test]
    public function user_can_create_project()
    {
        $response = $this->post($this->tenantRoute('tenant.admin.projects.store'), [
            'name' => 'Test Project',
            'description' => 'Test description',
            'status' => 'active',
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('projects', [
            'name' => 'Test Project',
            'user_id' => $this->user->id,
        ]);
    }

    #[Test]
    public function user_can_view_project_listing()
    {
        // Count existing projects before creating new ones
        $initialCount = Project::count();

        // Create projects in current tenant
        Project::factory()->count(3)->create([
            'user_id' => $this->user->id,
        ]);

        $response = $this->get($this->tenantRoute('tenant.admin.projects.index'));

        $response->assertOk();
        $this->assertEquals($initialCount + 3, Project::count());
    }

    #[Test]
    public function member_can_only_edit_own_projects()
    {
        // Create member (not owner)
        $member = $this->createTenantUser('member');

        // Create project owned by owner user
        $otherProject = Project::factory()->create([
            'user_id' => $this->user->id,
        ]);

        // Member tries to edit owner's project
        $this->actingAs($member);

        $response = $this->patch($this->tenantRoute('tenant.admin.projects.update', ['project' => $otherProject]), [
            'name' => 'Updated Name',
            'description' => 'Updated description',
            'status' => 'active',
        ]);

        $response->assertForbidden();
    }

    #[Test]
    public function owner_can_delete_any_project_in_tenant()
    {
        // Create project owned by member
        $member = $this->createTenantUser('member');
        $memberProject = Project::factory()->create([
            'user_id' => $member->id,
        ]);

        // Owner deletes member's project (should be allowed)
        $response = $this->delete($this->tenantRoute('tenant.admin.projects.destroy', ['project' => $memberProject]));

        $response->assertRedirect();

        $this->assertSoftDeleted('projects', [
            'id' => $memberProject->id,
        ]);
    }

    #[Test]
    public function projects_are_stored_in_tenant_database()
    {
        // Count existing projects before creating new ones
        $initialCount = Project::count();

        // Create projects in current tenant
        Project::factory()->count(3)->create([
            'user_id' => $this->user->id,
        ]);

        // Verify projects were created
        $this->assertEquals($initialCount + 3, Project::count());

        // Verify newly created projects belong to this user
        $newProjects = Project::where('user_id', $this->user->id)
            ->latest()
            ->take(3)
            ->get();
        $this->assertCount(3, $newProjects);
        // In multi-database tenancy, there's no tenant_id column
        // Data isolation is guaranteed at the database level
        $this->assertTrue($newProjects->every(fn ($project) => $project->user_id === $this->user->id));
    }

    #[Test]
    public function nonexistent_project_returns_404()
    {
        // Use valid UUID format that doesn't exist
        $fakeUuid = '00000000-0000-0000-0000-000000000000';
        $response = $this->get($this->tenantRoute('tenant.admin.projects.show', ['project' => $fakeUuid]));

        $response->assertNotFound();
    }

    #[Test]
    public function user_can_update_own_project()
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

        $this->assertDatabaseHas('projects', [
            'id' => $project->id,
            'name' => 'Updated Name',
        ]);
    }
}
