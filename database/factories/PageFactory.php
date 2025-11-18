<?php

namespace Database\Factories;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Page>
 */
class PageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->unique()->sentence(3);
        $slug = Str::slug($title);

        return [
            'tenant_id' => Tenant::first()->id ?? 1, // Use existing tenant
            'title' => $title,
            'slug' => $slug,
            'content' => null,
            'meta_title' => fake()->sentence(5),
            'meta_description' => fake()->paragraph(2),
            'meta_keywords' => implode(', ', fake()->words(5)),
            'og_image' => fake()->imageUrl(1200, 630, 'business'),
            'status' => fake()->randomElement(['draft', 'published', 'archived']),
            'published_at' => fake()->boolean(60) ? fake()->dateTimeBetween('-1 month', 'now') : null,
            'created_by' => User::first()->id ?? 1, // Use existing user
            'updated_by' => null,
        ];
    }

    /**
     * Indicate that the page is published.
     */
    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'published',
            'published_at' => fake()->dateTimeBetween('-1 month', 'now'),
        ]);
    }

    /**
     * Indicate that the page is a draft.
     */
    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'draft',
            'published_at' => null,
        ]);
    }

    /**
     * Indicate that the page is archived.
     */
    public function archived(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'archived',
            'published_at' => fake()->dateTimeBetween('-6 months', '-1 month'),
        ]);
    }
}
