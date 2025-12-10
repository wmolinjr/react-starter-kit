<?php

namespace Tests\Feature\Settings;

use Illuminate\Support\Facades\Hash;
use Tests\TenantTestCase;

class PasswordUpdateTest extends TenantTestCase
{
    public function test_password_update_page_is_displayed()
    {
        $response = $this
            ->actingAs($this->user, 'tenant')
            ->get($this->tenantRoute('tenant.admin.user-settings.password.edit'));

        $response->assertStatus(200);
    }

    public function test_password_can_be_updated()
    {
        $response = $this
            ->actingAs($this->user, 'tenant')
            ->from($this->tenantRoute('tenant.admin.user-settings.password.edit'))
            ->put($this->tenantRoute('tenant.admin.user-settings.password.update'), [
                'current_password' => 'password',
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect($this->tenantRoute('tenant.admin.user-settings.password.edit'));

        $this->assertTrue(Hash::check('new-password', $this->user->refresh()->password));
    }

    public function test_correct_password_must_be_provided_to_update_password()
    {
        $response = $this
            ->actingAs($this->user, 'tenant')
            ->from($this->tenantRoute('tenant.admin.user-settings.password.edit'))
            ->put($this->tenantRoute('tenant.admin.user-settings.password.update'), [
                'current_password' => 'wrong-password',
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ]);

        $response
            ->assertSessionHasErrors('current_password')
            ->assertRedirect($this->tenantRoute('tenant.admin.user-settings.password.edit'));
    }
}
