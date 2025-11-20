<?php

namespace Tests\Feature;

use App\Models\User;
use PHPUnit\Framework\Attributes\Test;
use Tests\TenantTestCase;

class TeamTest extends TenantTestCase
{
    #[Test]
    public function owner_can_invite_members_to_team()
    {
        $response = $this->post('/team/invite', [
            'email' => 'newmember@example.com',
            'role' => 'member',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
    }

    #[Test]
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

    #[Test]
    public function owner_can_change_user_roles()
    {
        $member = $this->createTenantUser('member');

        // Pre-create admin role for this tenant
        \App\Models\Role::findOrCreate('admin', 'web');

        $response = $this->patch("/team/{$member->id}/role", [
            'role' => 'admin',
        ]);

        $response->assertRedirect();

        // Verify role changed using Spatie Permission
        $member->refresh();
        $this->assertTrue($member->hasRole('admin'));
        $this->assertFalse($member->hasRole('member'));
    }

    #[Test]
    public function admin_can_change_member_roles_but_not_owner()
    {
        $admin = $this->createTenantUser('admin');
        $member = $this->createTenantUser('member');

        $this->actingAs($admin);

        // Admin can change member role to admin
        $response = $this->patch("/team/{$member->id}/role", [
            'role' => 'admin',
        ]);

        $response->assertRedirect();

        // Admin cannot change owner role
        $response = $this->patch("/team/{$this->user->id}/role", [
            'role' => 'member',
        ]);

        $response->assertForbidden();
    }

    #[Test]
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

    #[Test]
    public function member_cannot_remove_team_members()
    {
        $member = $this->createTenantUser('member');
        $otherMember = $this->createTenantUser('member');

        $this->actingAs($member);

        $response = $this->delete("/team/{$otherMember->id}");

        $response->assertForbidden();
    }

    #[Test]
    public function cannot_remove_owner_from_team()
    {
        // Create a second owner
        $secondOwner = $this->createTenantUser('owner');

        // Try to remove the second owner (not self-removal, but still forbidden)
        $response = $this->delete("/team/{$secondOwner->id}");

        $response->assertForbidden();
    }

    #[Test]
    public function user_can_only_see_team_members_of_current_tenant()
    {
        // Add members to current tenant
        $member1 = $this->createTenantUser('member');
        $member2 = $this->createTenantUser('admin');

        // Create another tenant with members
        $otherTenant = $this->createOtherTenant();
        $otherUser = User::factory()->create();
        $otherTenant->users()->attach($otherUser->id, [
            'joined_at' => now(),
        ]);

        // Assign role to other tenant's user using Spatie Permission
        tenancy()->end(); // End current tenant
        tenancy()->initialize($otherTenant); // Switch to other tenant
        setPermissionsTeamId($otherTenant->id); // Set Spatie team ID
        $memberRole = \App\Models\Role::findOrCreate('member', 'web');
        $otherUser->assignRole($memberRole);
        tenancy()->end(); // End other tenant
        tenancy()->initialize($this->tenant); // Switch back to test tenant
        setPermissionsTeamId($this->tenant->id);

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
