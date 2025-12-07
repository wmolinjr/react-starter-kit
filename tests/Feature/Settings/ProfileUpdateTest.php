<?php

namespace Tests\Feature\Settings;

use App\Models\Tenant\User;
use Tests\TenantTestCase;

class ProfileUpdateTest extends TenantTestCase
{
    public function test_profile_page_is_displayed()
    {
        $response = $this
            ->actingAs($this->user, 'tenant')
            ->get($this->tenantRoute('tenant.admin.user-settings.profile.edit'));

        $response->assertOk();
    }

    public function test_profile_information_can_be_updated()
    {
        $response = $this
            ->actingAs($this->user, 'tenant')
            ->patch($this->tenantRoute('tenant.admin.user-settings.profile.update'), [
                'name' => 'Test User',
                'email' => 'test@example.com',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect($this->tenantRoute('tenant.admin.user-settings.profile.edit'));

        $this->user->refresh();

        $this->assertSame('Test User', $this->user->name);
        $this->assertSame('test@example.com', $this->user->email);
        $this->assertNull($this->user->email_verified_at);
    }

    public function test_email_verification_status_is_unchanged_when_the_email_address_is_unchanged()
    {
        $response = $this
            ->actingAs($this->user, 'tenant')
            ->patch($this->tenantRoute('tenant.admin.user-settings.profile.update'), [
                'name' => 'Test User',
                'email' => $this->user->email,
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect($this->tenantRoute('tenant.admin.user-settings.profile.edit'));

        $this->assertNotNull($this->user->refresh()->email_verified_at);
    }

    public function test_user_can_delete_their_account()
    {
        $userId = $this->user->id;

        $response = $this
            ->actingAs($this->user, 'tenant')
            ->delete($this->tenantRoute('tenant.admin.user-settings.profile.destroy'), [
                'password' => 'password',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/');

        $this->assertGuest('tenant');
        // Option C: User model uses SoftDeletes - verify user is soft deleted
        $this->assertSoftDeleted('users', ['id' => $userId]);
    }

    public function test_correct_password_must_be_provided_to_delete_account()
    {
        $response = $this
            ->actingAs($this->user, 'tenant')
            ->from($this->tenantRoute('tenant.admin.user-settings.profile.edit'))
            ->delete($this->tenantRoute('tenant.admin.user-settings.profile.destroy'), [
                'password' => 'wrong-password',
            ]);

        $response
            ->assertSessionHasErrors('password')
            ->assertRedirect($this->tenantRoute('tenant.admin.user-settings.profile.edit'));

        $this->assertNotNull($this->user->fresh());
    }
}
