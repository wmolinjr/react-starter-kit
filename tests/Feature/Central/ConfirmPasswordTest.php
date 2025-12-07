<?php

namespace Tests\Feature\Central;

use App\Models\Central\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConfirmPasswordTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create();
    }

    public function test_confirm_password_page_can_be_rendered(): void
    {
        $response = $this->actingAs($this->admin, 'central')
            ->get(route('central.admin.auth.confirm-password'));

        $response->assertOk();
    }

    public function test_password_can_be_confirmed(): void
    {
        $response = $this->actingAs($this->admin, 'central')
            ->post(route('central.admin.auth.confirm-password.store'), [
                'password' => 'password',
            ]);

        $response->assertRedirect();
        $this->assertNotNull(session('auth.password_confirmed_at'));
    }

    public function test_password_is_not_confirmed_with_invalid_password(): void
    {
        $response = $this->actingAs($this->admin, 'central')
            ->post(route('central.admin.auth.confirm-password.store'), [
                'password' => 'wrong-password',
            ]);

        $response->assertSessionHasErrors('password');
        $this->assertNull(session('auth.password_confirmed_at'));
    }

    public function test_confirm_password_routes_require_authentication(): void
    {
        $this->get(route('central.admin.auth.confirm-password'))
            ->assertRedirect(route('central.admin.auth.login'));

        $this->post(route('central.admin.auth.confirm-password.store'), [
            'password' => 'password',
        ])->assertRedirect(route('central.admin.auth.login'));
    }

    public function test_two_factor_enable_requires_password_confirmation(): void
    {
        // Without password confirmation, should redirect to confirm password page
        $response = $this->actingAs($this->admin, 'central')
            ->post(route('central.admin.settings.two-factor.enable'));

        $response->assertRedirect(route('central.admin.auth.confirm-password'));
    }

    public function test_two_factor_enable_works_after_password_confirmation(): void
    {
        // First confirm password
        $this->actingAs($this->admin, 'central')
            ->post(route('central.admin.auth.confirm-password.store'), [
                'password' => 'password',
            ]);

        // Now enable 2FA should work
        $response = $this->actingAs($this->admin, 'central')
            ->post(route('central.admin.settings.two-factor.enable'));

        $response->assertRedirect();
        $response->assertSessionHas('status', 'two-factor-authentication-enabled');
    }

    public function test_two_factor_disable_requires_password_confirmation(): void
    {
        // Enable 2FA first
        $this->admin->forceFill([
            'two_factor_secret' => encrypt('test-secret'),
            'two_factor_recovery_codes' => encrypt(json_encode(['code1', 'code2'])),
            'two_factor_confirmed_at' => now(),
        ])->save();

        // Without password confirmation, should redirect to confirm password page
        $response = $this->actingAs($this->admin, 'central')
            ->delete(route('central.admin.settings.two-factor.disable'));

        $response->assertRedirect(route('central.admin.auth.confirm-password'));
    }

    public function test_two_factor_show_page_requires_password_confirmation(): void
    {
        // Show page should require password confirmation (same as Tenant behavior)
        $response = $this->actingAs($this->admin, 'central')
            ->get(route('central.admin.settings.two-factor.show'));

        $response->assertRedirect(route('central.admin.auth.confirm-password'));
    }

    public function test_two_factor_show_page_works_after_password_confirmation(): void
    {
        // First confirm password
        $this->actingAs($this->admin, 'central')
            ->post(route('central.admin.auth.confirm-password.store'), [
                'password' => 'password',
            ]);

        // Now show page should work
        $response = $this->actingAs($this->admin, 'central')
            ->get(route('central.admin.settings.two-factor.show'));

        $response->assertOk();
    }

    public function test_password_confirmation_expires_after_timeout(): void
    {
        // Confirm password
        $this->actingAs($this->admin, 'central')
            ->post(route('central.admin.auth.confirm-password.store'), [
                'password' => 'password',
            ]);

        // Travel forward in time past the timeout (3 hours)
        $this->travel(4)->hours();

        // Should require password confirmation again
        $response = $this->actingAs($this->admin, 'central')
            ->post(route('central.admin.settings.two-factor.enable'));

        $response->assertRedirect(route('central.admin.auth.confirm-password'));
    }
}
