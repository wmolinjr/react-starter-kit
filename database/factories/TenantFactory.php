<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Central\Tenant>
 */
class TenantFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<\App\Models\Central\Tenant>
     */
    protected $model = \App\Models\Central\Tenant::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // Don't set ID - let Eloquent/DB handle UUID generation
            'name' => fake()->company(),
            'slug' => fake()->unique()->slug(),
            'settings' => [],
        ];
    }
}
