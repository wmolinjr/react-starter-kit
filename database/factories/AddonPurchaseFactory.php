<?php

namespace Database\Factories;

use App\Enums\AddonType;
use App\Models\Central\AddonPurchase;
use App\Models\Central\AddonSubscription;
use App\Models\Central\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * AddonPurchase Factory
 *
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Central\AddonPurchase>
 */
class AddonPurchaseFactory extends Factory
{
    protected $model = AddonPurchase::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'addon_subscription_id' => null,
            'addon_slug' => $this->faker->slug(2),
            'addon_type' => $this->faker->randomElement([
                AddonType::QUOTA->value,
                AddonType::FEATURE->value,
                AddonType::CREDIT->value,
            ]),
            'quantity' => $this->faker->numberBetween(1, 5),
            'amount_paid' => $this->faker->randomElement([1000, 2900, 4900, 9900]),
            'currency' => stripe_currency(),
            'payment_method' => 'stripe_checkout',
            'stripe_checkout_session_id' => null,
            'stripe_payment_intent_id' => null,
            'stripe_invoice_id' => null,
            'status' => 'completed',
            'purchased_at' => now(),
            'refunded_at' => null,
            'valid_from' => now(),
            'valid_until' => now()->addYear(),
            'is_consumed' => false,
            'metadata' => null,
            'failure_reason' => null,
        ];
    }

    /**
     * Assign purchase to a specific tenant
     */
    public function forTenant(Tenant $tenant): static
    {
        return $this->state(fn (array $attributes) => [
            'tenant_id' => $tenant->id,
        ]);
    }

    /**
     * Link to an existing subscription (also sets tenant_id from subscription)
     */
    public function forSubscription(AddonSubscription $subscription): static
    {
        return $this->state(fn (array $attributes) => [
            'tenant_id' => $subscription->tenant_id,
            'addon_subscription_id' => $subscription->id,
            'addon_slug' => $subscription->addon_slug,
            'addon_type' => $subscription->addon_type->value,
        ]);
    }

    /**
     * Set as pending status
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
            'purchased_at' => null,
        ]);
    }

    /**
     * Set as completed status
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'completed',
            'purchased_at' => now(),
        ]);
    }

    /**
     * Set as failed status
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'failed',
            'failure_reason' => 'Payment declined',
            'purchased_at' => null,
        ]);
    }

    /**
     * Set as refunded status
     */
    public function refunded(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'refunded',
            'refunded_at' => now(),
        ]);
    }

    /**
     * Set with Stripe checkout session
     */
    public function withStripeCheckout(): static
    {
        return $this->state(fn (array $attributes) => [
            'payment_method' => 'stripe_checkout',
            'stripe_checkout_session_id' => 'cs_test_'.fake()->regexify('[A-Za-z0-9]{24}'),
        ]);
    }

    /**
     * Set with Stripe payment intent
     */
    public function withStripePaymentIntent(): static
    {
        return $this->state(fn (array $attributes) => [
            'stripe_payment_intent_id' => 'pi_'.fake()->regexify('[A-Za-z0-9]{24}'),
        ]);
    }

    /**
     * Set as consumed (for one-time credits)
     */
    public function consumed(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_consumed' => true,
        ]);
    }

    /**
     * Set validity period
     */
    public function validFor(int $months): static
    {
        return $this->state(fn (array $attributes) => [
            'valid_from' => now(),
            'valid_until' => now()->addMonths($months),
        ]);
    }

    /**
     * Set as expired
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'valid_from' => now()->subYear(),
            'valid_until' => now()->subDay(),
        ]);
    }
}
