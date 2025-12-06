<?php

namespace Database\Factories;

use App\Models\Central\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;

class PlanFactory extends Factory
{
    protected $model = Plan::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->words(2, true),
            'slug' => $this->faker->unique()->slug,
            'description' => $this->faker->sentence,
            'price' => $this->faker->numberBetween(1000, 50000),
            'currency' => stripe_currency(),
            'billing_period' => 'monthly',
            'features' => [
                'projects' => true,
                'customRoles' => false,
                'apiAccess' => false,
            ],
            'limits' => [
                'users' => 10,
                'projects' => 50,
                'storage' => 1024,
            ],
            'permission_map' => [],
            'is_active' => true,
            'is_featured' => false,
            'sort_order' => 0,
        ];
    }

    /**
     * Starter plan state
     */
    public function starter(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Starter',
            'slug' => 'starter',
            'price' => 2900,
            'features' => [
                'projects' => true,
                'customRoles' => false,
                'apiAccess' => false,
            ],
            'limits' => [
                'users' => 1,
                'projects' => 50,
                'storage' => 1024,
            ],
        ]);
    }

    /**
     * Professional plan state
     */
    public function professional(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Professional',
            'slug' => 'professional',
            'price' => 9900,
            'features' => [
                'projects' => true,
                'customRoles' => true,
                'apiAccess' => true,
                'advancedReports' => false,
            ],
            'limits' => [
                'users' => 50,
                'projects' => -1,
                'storage' => 10240,
                'apiCalls' => 10000,
            ],
        ]);
    }

    /**
     * Enterprise plan state
     */
    public function enterprise(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Enterprise',
            'slug' => 'enterprise',
            'price' => 0,
            'features' => [
                'projects' => true,
                'customRoles' => true,
                'apiAccess' => true,
                'advancedReports' => true,
                'sso' => true,
                'whiteLabel' => true,
            ],
            'limits' => [
                'users' => -1,
                'projects' => -1,
                'storage' => 102400,
                'apiCalls' => -1,
            ],
        ]);
    }
}
