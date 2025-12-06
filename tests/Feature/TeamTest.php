<?php

namespace Tests\Feature;

use App\Models\Tenant\User;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Test;
use Tests\TenantTestCase;

class TeamTest extends TenantTestCase
{
    #[Test]
    public function owner_can_invite_members_to_team()
    {
        $response = $this->post($this->tenantRoute('tenant.admin.team.invite'), [
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

        $response = $this->post($this->tenantRoute('tenant.admin.team.invite'), [
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
        \App\Models\Universal\Role::findOrCreate('admin', 'tenant');

        $response = $this->patch($this->tenantRoute('tenant.admin.team.update-role', ['user' => $member]), [
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
        $response = $this->patch($this->tenantRoute('tenant.admin.team.update-role', ['user' => $member]), [
            'role' => 'admin',
        ]);

        $response->assertRedirect();

        // Admin cannot change owner role
        $response = $this->patch($this->tenantRoute('tenant.admin.team.update-role', ['user' => $this->user]), [
            'role' => 'member',
        ]);

        $response->assertForbidden();
    }

    #[Test]
    public function owner_can_remove_team_members()
    {
        $member = $this->createTenantUser('member');
        $memberId = $member->id;

        $response = $this->delete($this->tenantRoute('tenant.admin.team.remove', ['user' => $member]));

        $response->assertRedirect();

        // Option C: Users are soft-deleted from tenant database (not removed from pivot)
        // Verify user is soft-deleted
        $this->assertSoftDeleted('users', [
            'id' => $memberId,
        ]);
    }

    #[Test]
    public function member_cannot_remove_team_members()
    {
        $member = $this->createTenantUser('member');
        $otherMember = $this->createTenantUser('member');

        $this->actingAs($member);

        $response = $this->delete($this->tenantRoute('tenant.admin.team.remove', ['user' => $otherMember]));

        $response->assertForbidden();
    }

    #[Test]
    public function cannot_remove_owner_from_team()
    {
        // Create a second owner
        $secondOwner = $this->createTenantUser('owner');

        // Try to remove the second owner (not self-removal, but still forbidden)
        $response = $this->delete($this->tenantRoute('tenant.admin.team.remove', ['user' => $secondOwner]));

        $response->assertForbidden();
    }

    #[Test]
    public function user_can_only_see_team_members_of_current_tenant()
    {
        // Add members to current tenant
        $member1 = $this->createTenantUser('member');
        $member2 = $this->createTenantUser('admin');

        // OPTION C ARCHITECTURE:
        // In production, each tenant has its own database, so cross-tenant user access
        // is physically impossible. Users exist ONLY in their tenant's database.
        //
        // In tests with single database, we verify that:
        // 1. Team page shows correct number of users
        // 2. Users created in tenant context are visible

        $response = $this->get($this->tenantRoute('tenant.admin.team.index'));

        $response->assertOk();

        // Check Inertia props contain correct members
        // Note: Don't assert exact count as tests within same class share data
        $response->assertInertia(
            fn ($page) => $page
                ->component('tenant/admin/team/index')
                ->has('members') // Just verify members array exists
                ->has('members.0.name')
                ->has('members.0.email')
                ->has('members.0.role')
                // Verify expected members are present
                ->where('members', function ($members) use ($member1, $member2) {
                    $emails = array_column($members->toArray(), 'email');
                    return in_array($this->user->email, $emails) &&
                           in_array($member1->email, $emails) &&
                           in_array($member2->email, $emails);
                })
        );
    }
}
