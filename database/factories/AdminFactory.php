<?php

namespace Database\Factories;

use App\Models\Central\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Factory for Central User model (central database administrators).
 *
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Central\User>
 */
class AdminFactory extends Factory
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
            'is_super_admin' => false,
            'locale' => 'pt_BR',
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the admin is a super admin.
     */
    public function superAdmin(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_super_admin' => true,
        ]);
    }

    /**
     * Indicate that the admin's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Indicate that the admin has two-factor authentication configured.
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
     * Indicate that the admin does not have two-factor authentication configured.
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
     * Set a specific locale for the admin.
     */
    public function withLocale(string $locale): static
    {
        return $this->state(fn (array $attributes) => [
            'locale' => $locale,
        ]);
    }
}
