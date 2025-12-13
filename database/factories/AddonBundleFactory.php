<?php

namespace Database\Factories;

use App\Models\Central\AddonBundle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Central\AddonBundle>
 */
class AddonBundleFactory extends Factory
{
    protected $model = AddonBundle::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'slug' => fake()->unique()->slug(2),
            'name' => ['en' => fake()->words(2, true).' Bundle', 'pt_BR' => 'Pacote '.fake()->words(2, true)],
            'description' => ['en' => fake()->sentence(), 'pt_BR' => fake()->sentence()],
            'active' => true,
            'discount_percent' => fake()->randomElement([10, 15, 20, 25]),
            'price_monthly' => null,
            'price_yearly' => null,
            'currency' => stripe_currency(),
            'badge' => null,
            'icon' => 'Package',
            'icon_color' => 'slate',
            'features' => [],
            'sort_order' => fake()->numberBetween(0, 100),
        ];
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

    /**
     * Configure with fixed price override
     */
    public function withFixedPrice(int $monthly, int $yearly): static
    {
        return $this->state(fn (array $attributes) => [
            'price_monthly' => $monthly,
            'price_yearly' => $yearly,
        ]);
    }

    /**
     * Configure with discount
     */
    public function withDiscount(int $percent): static
    {
        return $this->state(fn (array $attributes) => [
            'discount_percent' => $percent,
        ]);
    }

    /**
     * Configure with badge
     */
    public function withBadge(string $badge): static
    {
        return $this->state(fn (array $attributes) => [
            'badge' => $badge,
        ]);
    }

    /**
     * Configure with features
     */
    public function withFeatures(array $features): static
    {
        return $this->state(fn (array $attributes) => [
            'features' => $features,
        ]);
    }

    /**
     * Configure as synced with a provider
     */
    public function synced(string $provider = 'stripe'): static
    {
        $productId = match ($provider) {
            'stripe' => 'prod_'.fake()->unique()->regexify('[A-Za-z0-9]{14}'),
            'asaas' => 'sub_'.fake()->unique()->regexify('[A-Za-z0-9]{16}'),
            default => 'prod_'.fake()->unique()->regexify('[A-Za-z0-9]{14}'),
        };

        $priceIdMonthly = match ($provider) {
            'stripe' => 'price_'.fake()->unique()->regexify('[A-Za-z0-9]{24}'),
            'asaas' => 'pln_'.fake()->unique()->regexify('[A-Za-z0-9]{16}'),
            default => 'price_'.fake()->unique()->regexify('[A-Za-z0-9]{24}'),
        };

        $priceIdYearly = match ($provider) {
            'stripe' => 'price_'.fake()->unique()->regexify('[A-Za-z0-9]{24}'),
            'asaas' => 'pln_'.fake()->unique()->regexify('[A-Za-z0-9]{16}'),
            default => 'price_'.fake()->unique()->regexify('[A-Za-z0-9]{24}'),
        };

        return $this->state(fn (array $attributes) => [
            'provider_product_ids' => [$provider => $productId],
            'provider_price_ids' => [
                $provider => [
                    'monthly' => $priceIdMonthly,
                    'yearly' => $priceIdYearly,
                ],
            ],
        ]);
    }
}
