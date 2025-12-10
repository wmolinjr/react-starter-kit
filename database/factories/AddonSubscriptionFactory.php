<?php

namespace Database\Factories;

use App\Enums\AddonStatus;
use App\Enums\AddonType;
use App\Enums\BillingPeriod;
use App\Models\Central\AddonSubscription;
use App\Models\Central\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * AddonSubscription Factory
 *
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Central\AddonSubscription>
 */
class AddonSubscriptionFactory extends Factory
{
    protected $model = AddonSubscription::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        $type = $this->faker->randomElement(AddonType::cases());
        $billingPeriod = match ($type) {
            AddonType::QUOTA, AddonType::FEATURE => $this->faker->randomElement([
                BillingPeriod::MONTHLY,
                BillingPeriod::YEARLY,
            ]),
            AddonType::METERED => BillingPeriod::METERED,
            AddonType::CREDIT => BillingPeriod::ONE_TIME,
        };

        return [
            'tenant_id' => Tenant::factory(),
            'addon_slug' => $this->faker->slug(2),
            'addon_type' => $type,
            'name' => $this->faker->words(3, true),
            'description' => $this->faker->sentence(),
            'quantity' => $type->isStackable() ? $this->faker->numberBetween(1, 10) : 1,
            'price' => $this->faker->randomElement([1000, 2900, 4900, 9900, 19900]),
            'currency' => stripe_currency(),
            'billing_period' => $billingPeriod,
            'status' => AddonStatus::ACTIVE,
            'started_at' => now(),
            'expires_at' => $type === AddonType::CREDIT ? now()->addYear() : null,
            'canceled_at' => null,
            'provider' => null,
            'provider_item_id' => null,
            'provider_price_id' => null,
            'metered_usage' => 0,
            'metadata' => null,
            'notes' => null,
        ];
    }

    /**
     * Set as QUOTA type (increases plan limits)
     */
    public function quota(): static
    {
        return $this->state(fn (array $attributes) => [
            'addon_type' => AddonType::QUOTA,
            'addon_slug' => 'storage_50gb',
            'name' => 'Storage 50GB',
        ]);
    }

    /**
     * Alias for quota() - storage addon
     */
    public function storage(): static
    {
        return $this->quota();
    }

    /**
     * Set as users quota addon
     */
    public function users(): static
    {
        return $this->state(fn (array $attributes) => [
            'addon_type' => AddonType::QUOTA,
            'addon_slug' => 'extra_users_5',
            'name' => 'Extra Users (5 seats)',
        ]);
    }

    /**
     * Set as FEATURE type (unlocks features)
     */
    public function feature(): static
    {
        return $this->state(fn (array $attributes) => [
            'addon_type' => AddonType::FEATURE,
            'addon_slug' => 'advanced_reports',
            'name' => 'Advanced Reports',
            'quantity' => 1,
        ]);
    }

    /**
     * Set as CREDIT type (one-time purchase with validity)
     */
    public function credit(): static
    {
        return $this->state(fn (array $attributes) => [
            'addon_type' => AddonType::CREDIT,
            'addon_slug' => 'storage_credit_100gb',
            'name' => 'Storage Credit 100GB',
            'billing_period' => BillingPeriod::ONE_TIME,
            'expires_at' => now()->addYear(),
        ]);
    }

    /**
     * Set as active status
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => AddonStatus::ACTIVE,
            'started_at' => now(),
            'expires_at' => null,
            'canceled_at' => null,
        ]);
    }

    /**
     * Set as canceled status
     */
    public function canceled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => AddonStatus::CANCELED,
            'canceled_at' => now(),
        ]);
    }

    /**
     * Set as expired
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => AddonStatus::ACTIVE,
            'expires_at' => now()->subDay(),
        ]);
    }

    /**
     * Set with future expiration
     */
    public function expiresIn(int $days): static
    {
        return $this->state(fn (array $attributes) => [
            'expires_at' => now()->addDays($days),
        ]);
    }

    /**
     * Set as monthly billing
     */
    public function monthly(): static
    {
        return $this->state(fn (array $attributes) => [
            'billing_period' => BillingPeriod::MONTHLY,
        ]);
    }

    /**
     * Set as yearly billing
     */
    public function yearly(): static
    {
        return $this->state(fn (array $attributes) => [
            'billing_period' => BillingPeriod::YEARLY,
        ]);
    }

    /**
     * Set as one-time purchase
     */
    public function oneTime(): static
    {
        return $this->state(fn (array $attributes) => [
            'billing_period' => BillingPeriod::ONE_TIME,
        ]);
    }

    /**
     * Set as METERED type (usage-based billing)
     */
    public function metered(): static
    {
        return $this->state(fn (array $attributes) => [
            'addon_type' => AddonType::METERED,
            'addon_slug' => 'storage_overage',
            'name' => 'Storage Overage',
            'billing_period' => BillingPeriod::METERED,
            'metered_usage' => 0,
        ]);
    }

    /**
     * Set as manual (admin-created)
     */
    public function manual(): static
    {
        return $this->state(fn (array $attributes) => [
            'billing_period' => BillingPeriod::MANUAL,
            'price' => 0,
        ]);
    }

    /**
     * Set with provider subscription item (Stripe by default)
     */
    public function withProvider(string $provider = 'stripe'): static
    {
        return $this->state(fn (array $attributes) => [
            'provider' => $provider,
            'provider_item_id' => 'si_'.fake()->regexify('[A-Za-z0-9]{14}'),
            'provider_price_id' => 'price_'.fake()->regexify('[A-Za-z0-9]{14}'),
        ]);
    }

    /**
     * @deprecated Use withProvider() instead
     */
    public function withStripe(): static
    {
        return $this->withProvider('stripe');
    }

    /**
     * Assign addon to a specific tenant
     */
    public function forTenant(Tenant $tenant): static
    {
        return $this->state(fn (array $attributes) => [
            'tenant_id' => $tenant->id,
        ]);
    }
}
