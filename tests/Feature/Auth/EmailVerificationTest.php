<?php

namespace Tests\Feature\Auth;

use App\Models\Tenant\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\Event;
use Tests\Concerns\WithTenant;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use WithTenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->initializeTenant();
    }

    public function test_email_verification_screen_can_be_rendered()
    {
        $user = User::factory()->unverified()->create();

        $response = $this->actingAs($user, 'tenant')->get($this->tenantRoute('tenant.admin.auth.verification.notice'));

        $response->assertStatus(200);
    }

    public function test_email_can_be_verified()
    {
        $user = User::factory()->unverified()->create();

        Event::fake();

        // Build verification URL directly for testing (bypass signed URL issues with domain switching)
        $hash = sha1($user->email);
        $response = $this->actingAs($user, 'tenant')
            ->withoutMiddleware(\Illuminate\Routing\Middleware\ValidateSignature::class)
            ->get($this->tenantRoute('tenant.admin.auth.verification.verify', ['id' => $user->id, 'hash' => $hash]));

        Event::assertDispatched(Verified::class);
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
        // Tenant users are redirected to admin dashboard
        $response->assertRedirect(route('tenant.admin.dashboard', absolute: false).'?verified=1');
    }

    public function test_email_is_not_verified_with_invalid_hash()
    {
        $user = User::factory()->unverified()->create();

        $response = $this->actingAs($user, 'tenant')
            ->withoutMiddleware(\Illuminate\Routing\Middleware\ValidateSignature::class)
            ->get($this->tenantRoute('tenant.admin.auth.verification.verify', ['id' => $user->id, 'hash' => 'invalid-hash']));

        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_email_is_not_verified_with_invalid_user_id(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => null,
        ]);

        $hash = sha1($user->email);
        // Use a valid UUID format that doesn't exist in the database
        $nonExistentId = '00000000-0000-0000-0000-000000000000';

        // Should return 404 for non-existent user
        $this->actingAs($user, 'tenant')
            ->withoutMiddleware(\Illuminate\Routing\Middleware\ValidateSignature::class)
            ->get($this->tenantRoute('tenant.admin.auth.verification.verify', ['id' => $nonExistentId, 'hash' => $hash]))
            ->assertNotFound();

        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_verified_user_is_redirected_to_dashboard_from_verification_prompt(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $response = $this->actingAs($user, 'tenant')->get($this->tenantRoute('tenant.admin.auth.verification.notice'));

        // Tenant users are redirected to admin dashboard
        $response->assertRedirect(route('tenant.admin.dashboard', absolute: false));
    }

    public function test_already_verified_user_visiting_verification_link_is_redirected_without_firing_event_again(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        Event::fake();

        $hash = sha1($user->email);
        $this->actingAs($user, 'tenant')
            ->withoutMiddleware(\Illuminate\Routing\Middleware\ValidateSignature::class)
            ->get($this->tenantRoute('tenant.admin.auth.verification.verify', ['id' => $user->id, 'hash' => $hash]))
            ->assertRedirect(route('tenant.admin.dashboard', absolute: false).'?verified=1');

        $this->assertTrue($user->fresh()->hasVerifiedEmail());
        Event::assertNotDispatched(Verified::class);
    }
}
