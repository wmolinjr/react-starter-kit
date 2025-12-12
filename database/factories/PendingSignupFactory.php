<?php

namespace Database\Factories;

use App\Enums\BusinessSector;
use App\Models\Central\PendingSignup;
use App\Models\Central\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * Factory for PendingSignup model.
 *
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Central\PendingSignup>
 */
class PendingSignupFactory extends Factory
{
    protected $model = PendingSignup::class;

    protected static ?string $password;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => static::$password ??= Hash::make('password'),
            'locale' => 'pt_BR',
            'workspace_name' => null,
            'workspace_slug' => null,
            'business_sector' => null,
            'plan_id' => null,
            'billing_period' => 'monthly',
            'payment_method' => null,
            'payment_provider' => 'stripe',
            'provider_session_id' => null,
            'provider_payment_id' => null,
            'status' => PendingSignup::STATUS_PENDING,
            'customer_id' => null,
            'tenant_id' => null,
            'failure_reason' => null,
            'metadata' => null,
            'expires_at' => now()->addHours(24),
            'paid_at' => null,
        ];
    }

    /**
     * Pending signup with workspace data (step 2 completed).
     */
    public function withWorkspace(?Plan $plan = null): static
    {
        return $this->state(function (array $attributes) use ($plan) {
            $plan ??= Plan::factory()->create();

            return [
                'workspace_name' => fake()->company(),
                'workspace_slug' => fake()->unique()->slug(),
                'business_sector' => fake()->randomElement(BusinessSector::cases())->value,
                'plan_id' => $plan->id,
            ];
        });
    }

    /**
     * Pending signup ready for card payment.
     */
    public function readyForCardPayment(?Plan $plan = null): static
    {
        return $this->withWorkspace($plan)->state([
            'payment_method' => 'card',
            'status' => PendingSignup::STATUS_PENDING,
        ]);
    }

    /**
     * Pending signup with Stripe checkout session.
     */
    public function withStripeSession(?Plan $plan = null): static
    {
        return $this->withWorkspace($plan)->state([
            'payment_method' => 'card',
            'payment_provider' => 'stripe',
            'provider_session_id' => 'cs_test_'.fake()->uuid(),
            'status' => PendingSignup::STATUS_PROCESSING,
        ]);
    }

    /**
     * Pending signup with PIX payment.
     */
    public function withPixPayment(?Plan $plan = null): static
    {
        return $this->withWorkspace($plan)->state([
            'payment_method' => 'pix',
            'payment_provider' => 'asaas',
            'provider_payment_id' => 'pay_'.fake()->uuid(),
            'status' => PendingSignup::STATUS_PROCESSING,
        ]);
    }

    /**
     * Pending signup with Boleto payment.
     */
    public function withBoletoPayment(?Plan $plan = null): static
    {
        return $this->withWorkspace($plan)->state([
            'payment_method' => 'boleto',
            'payment_provider' => 'asaas',
            'provider_payment_id' => 'pay_'.fake()->uuid(),
            'status' => PendingSignup::STATUS_PROCESSING,
        ]);
    }

    /**
     * Completed signup.
     */
    public function completed(): static
    {
        return $this->state([
            'status' => PendingSignup::STATUS_COMPLETED,
            'paid_at' => now(),
        ]);
    }

    /**
     * Failed signup.
     */
    public function failed(string $reason = 'Payment declined'): static
    {
        return $this->state([
            'status' => PendingSignup::STATUS_FAILED,
            'failure_reason' => $reason,
        ]);
    }

    /**
     * Expired signup.
     */
    public function expired(): static
    {
        return $this->state([
            'status' => PendingSignup::STATUS_EXPIRED,
            'expires_at' => now()->subHour(),
        ]);
    }

    /**
     * Processing signup (payment initiated).
     */
    public function processing(): static
    {
        return $this->state([
            'status' => PendingSignup::STATUS_PROCESSING,
        ]);
    }
}
