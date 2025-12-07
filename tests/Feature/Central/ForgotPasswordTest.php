<?php

namespace Tests\Feature\Central;

use App\Models\Central\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Auth\Notifications\ResetPassword;
use Tests\TestCase;

class ForgotPasswordTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Clear rate limiter before each test
        RateLimiter::clear('admin@example.com');

        // Run central migrations
        $this->artisan('migrate', [
            '--path' => 'database/migrations',
            '--database' => 'central',
        ]);
    }

    public function test_forgot_password_page_can_be_rendered(): void
    {
        $response = $this->get('/admin/forgot-password');

        $response->assertStatus(200);
    }

    public function test_password_reset_link_can_be_requested(): void
    {
        Notification::fake();

        // Use unique email to avoid throttling
        $uniqueEmail = 'reset-test-'.uniqid().'@example.com';
        $admin = User::factory()->create([
            'email' => $uniqueEmail,
        ]);

        $response = $this->post('/admin/forgot-password', [
            'email' => $admin->email,
        ]);

        // Response should redirect back with status
        $response->assertSessionHas('status');

        // Verify notification was sent
        Notification::assertSentTo($admin, ResetPassword::class);
    }

    public function test_password_reset_link_is_not_sent_with_invalid_email(): void
    {
        Notification::fake();

        $response = $this->post('/admin/forgot-password', [
            'email' => 'nonexistent@example.com',
        ]);

        Notification::assertNothingSent();
    }

    public function test_forgot_password_requires_email(): void
    {
        $response = $this->post('/admin/forgot-password', []);

        $response->assertSessionHasErrors('email');
    }

    public function test_forgot_password_requires_valid_email(): void
    {
        $response = $this->post('/admin/forgot-password', [
            'email' => 'not-an-email',
        ]);

        $response->assertSessionHasErrors('email');
    }
}
