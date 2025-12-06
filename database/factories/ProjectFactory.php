<?php

namespace Database\Factories;

use App\Models\Tenant\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Project Factory
 *
 * MULTI-DATABASE TENANCY:
 * - Project lives in tenant database (no tenant_id column)
 * - Isolation is at database level
 * - Must be created within tenant context (tenancy initialized)
 *
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Tenant\Project>
 */
class ProjectFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<\App\Models\Tenant\Project>
     */
    protected $model = \App\Models\Tenant\Project::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->sentence(3),
            'description' => fake()->paragraph(),
            'status' => 'active',
        ];
    }

    /**
     * Set specific user as owner for this project.
     */
    public function ownedBy(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $user->id,
        ]);
    }
}
