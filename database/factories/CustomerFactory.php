<?php

namespace Database\Factories;

use App\Models\Central\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Factory for Customer model (billing entity).
 *
 * Customers are billing entities that own tenants (workspaces).
 * Uses 'customer' guard for authentication at /account/*.
 *
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Central\Customer>
 */
class CustomerFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<\Illuminate\Database\Eloquent\Model>
     */
    protected $model = Customer::class;

    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'global_id' => 'cust_' . Str::orderedUuid()->toString(),
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= 'password',
            'locale' => 'pt_BR',
            'currency' => 'brl',
            'phone' => fake()->phoneNumber(),
            'billing_address' => null,
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the customer's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Set the customer's locale.
     */
    public function withLocale(string $locale): static
    {
        return $this->state(fn (array $attributes) => [
            'locale' => $locale,
        ]);
    }

    /**
     * Set the customer's currency.
     */
    public function withCurrency(string $currency): static
    {
        return $this->state(fn (array $attributes) => [
            'currency' => $currency,
        ]);
    }

    /**
     * Set a billing address for the customer.
     */
    public function withBillingAddress(?array $address = null): static
    {
        return $this->state(fn (array $attributes) => [
            'billing_address' => $address ?? [
                'line1' => fake()->streetAddress(),
                'line2' => fake()->secondaryAddress(),
                'city' => fake()->city(),
                'state' => fake()->stateAbbr(),
                'postal_code' => fake()->postcode(),
                'country' => fake()->countryCode(),
            ],
        ]);
    }

    /**
     * Indicate that the customer has two-factor authentication configured.
     */
    public function withTwoFactor(): static
    {
        return $this->state(fn (array $attributes) => [
            'two_factor_secret' => Str::random(40),
            'two_factor_recovery_codes' => json_encode([
                Str::random(10),
                Str::random(10),
                Str::random(10),
                Str::random(10),
                Str::random(10),
                Str::random(10),
                Str::random(10),
                Str::random(10),
            ]),
            'two_factor_confirmed_at' => now(),
        ]);
    }

    /**
     * Indicate that the customer does not have two-factor authentication configured.
     */
    public function withoutTwoFactor(): static
    {
        return $this->state(fn (array $attributes) => [
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ]);
    }

    /**
     * Create a customer with a Stripe customer ID (for testing billing).
     */
    public function withStripeCustomer(): static
    {
        return $this->state(fn (array $attributes) => [
            'stripe_id' => 'cus_' . Str::random(14),
            'pm_type' => 'card',
            'pm_last_four' => '4242',
        ]);
    }
}
