<?php

namespace Tests\Feature\Central;

use App\Models\Central\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class ResetPasswordTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Run central migrations
        $this->artisan('migrate', [
            '--path' => 'database/migrations',
            '--database' => 'central',
        ]);
    }

    public function test_reset_password_page_can_be_rendered(): void
    {
        $admin = User::factory()->create();

        $token = Password::broker('central_users')->createToken($admin);

        $response = $this->get('/admin/reset-password/'.$token.'?email='.$admin->email);

        $response->assertStatus(200);
    }

    public function test_password_can_be_reset_with_valid_token(): void
    {
        $admin = User::factory()->create([
            'password' => Hash::make('old-password'),
        ]);

        $token = Password::broker('central_users')->createToken($admin);

        $response = $this->post('/admin/reset-password', [
            'token' => $token,
            'email' => $admin->email,
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);

        $response->assertRedirect('/admin/login');

        $admin->refresh();
        $this->assertTrue(Hash::check('new-password', $admin->password));
    }

    public function test_password_cannot_be_reset_with_invalid_token(): void
    {
        $admin = User::factory()->create([
            'password' => Hash::make('old-password'),
        ]);

        $response = $this->post('/admin/reset-password', [
            'token' => 'invalid-token',
            'email' => $admin->email,
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);

        $response->assertSessionHasErrors('email');

        $admin->refresh();
        $this->assertTrue(Hash::check('old-password', $admin->password));
    }

    public function test_password_reset_requires_valid_password(): void
    {
        $admin = User::factory()->create();

        $token = Password::broker('central_users')->createToken($admin);

        $response = $this->post('/admin/reset-password', [
            'token' => $token,
            'email' => $admin->email,
            'password' => 'short',
            'password_confirmation' => 'short',
        ]);

        $response->assertSessionHasErrors('password');
    }

    public function test_password_reset_requires_matching_confirmation(): void
    {
        $admin = User::factory()->create();

        $token = Password::broker('central_users')->createToken($admin);

        $response = $this->post('/admin/reset-password', [
            'token' => $token,
            'email' => $admin->email,
            'password' => 'new-password',
            'password_confirmation' => 'different-password',
        ]);

        $response->assertSessionHasErrors('password');
    }
}
