<?php

namespace Database\Factories;

use App\Enums\AddonType;
use App\Models\Central\Addon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Central\Addon>
 */
class AddonFactory extends Factory
{
    protected $model = Addon::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $type = fake()->randomElement(AddonType::cases());

        return [
            'slug' => fake()->unique()->slug(2),
            'name' => ['en' => fake()->words(3, true), 'pt_BR' => fake()->words(3, true)],
            'description' => ['en' => fake()->sentence(), 'pt_BR' => fake()->sentence()],
            'type' => $type,
            'active' => true,
            'sort_order' => fake()->numberBetween(0, 100),
            'limit_key' => $type === AddonType::QUOTA ? fake()->randomElement(['storage', 'users', 'projects']) : null,
            'unit_value' => $type->isStackable() ? fake()->numberBetween(1, 100) : null,
            'unit_label' => $type->unitLabel(),
            'min_quantity' => 1,
            'max_quantity' => $type->isStackable() ? 100 : 1,
            'stackable' => $type->isStackable(),
            'price_monthly' => $type->isRecurring() ? fake()->randomElement([500, 1000, 1500, 2000, 2500]) : null,
            'price_yearly' => $type->isRecurring() ? fake()->randomElement([5000, 10000, 15000, 20000, 25000]) : null,
            'price_one_time' => $type->isOneTime() ? fake()->randomElement([5000, 10000, 15000]) : null,
            'price_metered' => $type->isMetered() ? fake()->randomElement([5, 10, 15, 20]) : null,
            'currency' => stripe_currency(),
            'validity_months' => $type->hasValidity() ? 12 : null,
        ];
    }

    /**
     * Configure as QUOTA type (storage)
     */
    public function quota(string $limitKey = 'storage', int $unitValue = 50000): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => AddonType::QUOTA,
            'limit_key' => $limitKey,
            'unit_value' => $unitValue,
            'unit_label' => ['en' => 'GB', 'pt_BR' => 'GB'],
            'stackable' => true,
            'price_monthly' => 4900,
            'price_yearly' => 49000,
            'price_one_time' => null,
            'price_metered' => null,
        ]);
    }

    /**
     * Alias for quota() with storage
     */
    public function storage(int $unitValue = 50000): static
    {
        return $this->quota('storage', $unitValue);
    }

    /**
     * Configure as QUOTA type for users
     */
    public function users(int $unitValue = 5): static
    {
        return $this->quota('users', $unitValue)->state(fn (array $attributes) => [
            'unit_label' => ['en' => 'seats', 'pt_BR' => 'vagas'],
        ]);
    }

    /**
     * Configure as FEATURE type
     */
    public function feature(array $features = []): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => AddonType::FEATURE,
            'limit_key' => null,
            'unit_value' => null,
            'features' => $features,
            'stackable' => false,
            'max_quantity' => 1,
            'price_monthly' => 1900,
            'price_yearly' => 19000,
            'price_one_time' => null,
            'price_metered' => null,
        ]);
    }

    /**
     * Configure as CREDIT type (one-time purchase with validity)
     */
    public function credit(int $price = 7900, int $validityMonths = 12): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => AddonType::CREDIT,
            'limit_key' => 'storage',
            'unit_value' => 100000,
            'unit_label' => ['en' => 'GB', 'pt_BR' => 'GB'],
            'stackable' => true,
            'price_monthly' => null,
            'price_yearly' => null,
            'price_one_time' => $price,
            'price_metered' => null,
            'validity_months' => $validityMonths,
        ]);
    }

    /**
     * Alias for credit()
     */
    public function oneTime(int $price = 7900, int $validityMonths = 12): static
    {
        return $this->credit($price, $validityMonths);
    }

    /**
     * Configure as METERED type (usage-based billing)
     */
    public function metered(int $pricePerUnit = 10, int $freeTier = 0): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => AddonType::METERED,
            'limit_key' => null,
            'unit_label' => ['en' => 'GB', 'pt_BR' => 'GB'],
            'stackable' => true,
            'price_monthly' => null,
            'price_yearly' => null,
            'price_one_time' => null,
            'price_metered' => $pricePerUnit,
            'free_tier' => $freeTier,
        ]);
    }

    /**
     * Configure as inactive
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'active' => false,
        ]);
    }
}
