<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TenantTestCase;

class TeamTest extends TenantTestCase
{
    /** @test */
    public function owner_can_invite_members_to_team()
    {
        $response = $this->post('/team/invite', [
            'email' => 'newmember@example.com',
            'role' => 'member',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
    }

    /** @test */
    public function member_cannot_invite_others()
    {
        $member = $this->createTenantUser('member');
        $this->actingAs($member);

        $response = $this->post('/team/invite', [
            'email' => 'newmember@example.com',
            'role' => 'member',
        ]);

        $response->assertForbidden();
    }

    /** @test */
    public function owner_can_change_user_roles()
    {
        $member = $this->createTenantUser('member');

        $response = $this->patch("/team/{$member->id}/role", [
            'role' => 'admin',
        ]);

        $response->assertRedirect();

        // Verify role changed in pivot table
        $this->assertDatabaseHas('tenant_user', [
            'tenant_id' => $this->tenant->id,
            'user_id' => $member->id,
            'role' => 'admin',
        ]);
    }

    /** @test */
    public function admin_can_change_member_roles_but_not_owner()
    {
        $admin = $this->createTenantUser('admin');
        $member = $this->createTenantUser('member');

        $this->actingAs($admin);

        // Admin can change member role
        $response = $this->patch("/team/{$member->id}/role", [
            'role' => 'viewer',
        ]);

        $response->assertRedirect();

        // Admin cannot change owner role
        $response = $this->patch("/team/{$this->user->id}/role", [
            'role' => 'member',
        ]);

        $response->assertForbidden();
    }

    /** @test */
    public function owner_can_remove_team_members()
    {
        $member = $this->createTenantUser('member');

        $response = $this->delete("/team/{$member->id}");

        $response->assertRedirect();

        $this->assertDatabaseMissing('tenant_user', [
            'tenant_id' => $this->tenant->id,
            'user_id' => $member->id,
        ]);
    }

    /** @test */
    public function member_cannot_remove_team_members()
    {
        $member = $this->createTenantUser('member');
        $otherMember = $this->createTenantUser('member');

        $this->actingAs($member);

        $response = $this->delete("/team/{$otherMember->id}");

        $response->assertForbidden();
    }

    /** @test */
    public function cannot_remove_owner_from_team()
    {
        $response = $this->delete("/team/{$this->user->id}");

        $response->assertForbidden();
    }

    /** @test */
    public function user_can_only_see_team_members_of_current_tenant()
    {
        // Add members to current tenant
        $member1 = $this->createTenantUser('member');
        $member2 = $this->createTenantUser('admin');

        // Create another tenant with members
        $otherTenant = $this->createOtherTenant();
        $otherUser = User::factory()->create();
        $otherTenant->users()->attach($otherUser->id, [
            'role' => 'member',
            'joined_at' => now(),
        ]);

        $response = $this->get('/team');

        $response->assertOk();

        // Should see current tenant members
        $response->assertSee($member1->name);
        $response->assertSee($member2->name);
        $response->assertSee($this->user->name);

        // Should NOT see other tenant members
        $response->assertDontSee($otherUser->name);
    }
}
