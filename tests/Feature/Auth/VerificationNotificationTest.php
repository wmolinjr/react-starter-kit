<?php

namespace Tests\Feature\Auth;

use App\Models\Tenant\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\WithTenant;
use Tests\TestCase;

class VerificationNotificationTest extends TestCase
{
    use WithTenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->initializeTenant();
    }

    public function test_sends_verification_notification(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'email_verified_at' => null,
        ]);

        $this->actingAs($user)
            ->post(route('verification.send'))
            ->assertRedirect(route('central.home'));

        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_does_not_send_verification_notification_if_email_is_verified(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $this->actingAs($user)
            ->post(route('verification.send'))
            // Tenant users are redirected to admin dashboard
            ->assertRedirect(route('tenant.admin.dashboard', absolute: false));

        Notification::assertNothingSent();
    }
}
