<?php

namespace Tests\Feature\Auth;

use App\Models\Tenant\User;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\WithTenant;
use Tests\TestCase;

class PasswordConfirmationTest extends TestCase
{
    use WithTenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->initializeTenant();
    }

    public function test_confirm_password_screen_can_be_rendered()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('password.confirm'));

        $response->assertStatus(200);

        $response->assertInertia(fn (Assert $page) => $page
            ->component('tenant/auth/confirm-password')
        );
    }

    public function test_password_confirmation_requires_authentication()
    {
        $response = $this->get(route('password.confirm'));

        $response->assertRedirect(route('login'));
    }
}
