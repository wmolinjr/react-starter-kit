<?php

namespace Database\Factories;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Project>
 */
class ProjectFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * Automatically uses current tenant context if initialized,
     * otherwise creates a new tenant.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => tenancy()->initialized
                ? tenant('id')
                : Tenant::factory(),
            'user_id' => User::factory(),
            'name' => fake()->sentence(3),
            'description' => fake()->paragraph(),
            'status' => 'active',
        ];
    }

    /**
     * Set specific tenant for this project.
     */
    public function forTenant(Tenant $tenant): static
    {
        return $this->state(fn (array $attributes) => [
            'tenant_id' => $tenant->id,
        ]);
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
