<?php

namespace Tests\Feature\Customer\Auth;

use App\Models\Central\Customer;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class CustomerEmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_email_verification_screen_can_be_rendered(): void
    {
        $customer = Customer::factory()->unverified()->create();

        $response = $this->actingAs($customer, 'customer')
            ->get(route('central.account.verification.notice'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('customer/auth/verify-email'));
    }

    public function test_customer_email_can_be_verified(): void
    {
        Event::fake();

        $customer = Customer::factory()->unverified()->create();

        $verificationUrl = URL::temporarySignedRoute(
            'central.account.verification.verify',
            now()->addMinutes(60),
            ['id' => $customer->id, 'hash' => sha1($customer->email)]
        );

        $response = $this->actingAs($customer, 'customer')
            ->get($verificationUrl);

        Event::assertDispatched(Verified::class);

        $this->assertTrue($customer->fresh()->hasVerifiedEmail());
        $response->assertRedirect(route('central.account.dashboard', ['verified' => 1], absolute: false));
    }

    public function test_customer_email_is_not_verified_with_invalid_hash(): void
    {
        $customer = Customer::factory()->unverified()->create();

        $verificationUrl = URL::temporarySignedRoute(
            'central.account.verification.verify',
            now()->addMinutes(60),
            ['id' => $customer->id, 'hash' => sha1('wrong-email')]
        );

        $response = $this->actingAs($customer, 'customer')
            ->get($verificationUrl);

        $this->assertFalse($customer->fresh()->hasVerifiedEmail());
    }

    public function test_verified_customer_is_redirected_from_verification_screen(): void
    {
        $customer = Customer::factory()->create([
            'email_verified_at' => now(),
        ]);

        $response = $this->actingAs($customer, 'customer')
            ->get(route('central.account.verification.notice'));

        $response->assertRedirect(route('central.account.dashboard', absolute: false));
    }
}
