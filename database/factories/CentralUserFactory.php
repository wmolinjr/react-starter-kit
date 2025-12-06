<?php

namespace Database\Factories;

use App\Models\Central\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Factory for Central User model (central database users).
 *
 * Uses Spatie Permission with guard 'central'.
 * Roles: super-admin, central-admin, support-admin
 *
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Central\User>
 */
class CentralUserFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<\Illuminate\Database\Eloquent\Model>
     */
    protected $model = User::class;

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
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= 'password',
            'locale' => 'pt_BR',
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the user has the super-admin role.
     */
    public function superAdmin(): static
    {
        return $this->afterCreating(function (User $user) {
            $this->ensureRoleExists('super-admin');
            $user->assignRole('super-admin');
        });
    }

    /**
     * Indicate that the user has the central-admin role.
     */
    public function centralAdmin(): static
    {
        return $this->afterCreating(function (User $user) {
            $this->ensureRoleExists('central-admin');
            $user->assignRole('central-admin');
        });
    }

    /**
     * Indicate that the user has the support-admin role.
     */
    public function supportAdmin(): static
    {
        return $this->afterCreating(function (User $user) {
            $this->ensureRoleExists('support-admin');
            $user->assignRole('support-admin');
        });
    }

    /**
     * Ensure a role exists in the database (for tests with fresh database).
     * Also ensures the role has all central permissions.
     */
    protected function ensureRoleExists(string $roleName): void
    {
        $role = \App\Models\Shared\Role::firstOrCreate(
            ['name' => $roleName, 'guard_name' => 'central'],
            ['display_name' => ['en' => $roleName], 'description' => ['en' => $roleName]]
        );

        // Sync all central permissions for super-admin and central-admin roles
        if (in_array($roleName, ['super-admin', 'central-admin']) && $role->permissions->isEmpty()) {
            $this->ensureCentralPermissionsExist();
            $permissions = \App\Models\Shared\Permission::where('guard_name', 'central')->get();
            $role->syncPermissions($permissions);
        }
    }

    /**
     * Ensure central permissions exist in the database.
     */
    protected function ensureCentralPermissionsExist(): void
    {
        foreach (\App\Enums\CentralPermission::values() as $permName) {
            \App\Models\Shared\Permission::firstOrCreate(
                ['name' => $permName, 'guard_name' => 'central']
            );
        }
    }

    /**
     * Indicate that the user's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Indicate that the user has two-factor authentication configured.
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
     * Indicate that the user does not have two-factor authentication configured.
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
     * Set a specific locale for the user.
     */
    public function withLocale(string $locale): static
    {
        return $this->state(fn (array $attributes) => [
            'locale' => $locale,
        ]);
    }
}
