<?php

namespace Database\Factories;

use App\Models\Page;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PageBlock>
 */
class PageBlockFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $blockType = fake()->randomElement(['hero', 'text', 'image', 'gallery', 'cta', 'features', 'testimonials']);

        return [
            'page_id' => Page::first()->id ?? 1, // Use existing page
            'block_type' => $blockType,
            'content' => $this->generateBlockContent($blockType),
            'order' => 0,
            'config' => [
                'background_color' => fake()->hexColor(),
                'text_color' => fake()->randomElement(['#000000', '#ffffff']),
                'padding' => fake()->randomElement(['small', 'medium', 'large']),
            ],
        ];
    }

    /**
     * Generate content based on block type.
     */
    private function generateBlockContent(string $blockType): array
    {
        return match ($blockType) {
            'hero' => [
                'title' => fake()->sentence(6),
                'subtitle' => fake()->sentence(10),
                'button_text' => fake()->randomElement(['Get Started', 'Learn More', 'Sign Up']),
                'button_url' => fake()->url(),
                'image_url' => fake()->imageUrl(1920, 1080, 'business'),
            ],
            'text' => [
                'heading' => fake()->sentence(4),
                'content' => fake()->paragraphs(3, true),
            ],
            'image' => [
                'image_url' => fake()->imageUrl(1200, 800, 'business'),
                'alt_text' => fake()->sentence(6),
                'caption' => fake()->sentence(8),
            ],
            'gallery' => [
                'images' => array_map(fn() => [
                    'url' => fake()->imageUrl(800, 600, 'business'),
                    'alt' => fake()->sentence(4),
                    'caption' => fake()->sentence(6),
                ], range(1, fake()->numberBetween(3, 6))),
            ],
            'cta' => [
                'title' => fake()->sentence(5),
                'description' => fake()->paragraph(),
                'button_text' => fake()->randomElement(['Contact Us', 'Get Quote', 'Start Free Trial']),
                'button_url' => fake()->url(),
            ],
            'features' => [
                'title' => fake()->sentence(4),
                'features' => array_map(fn() => [
                    'icon' => fake()->randomElement(['check', 'star', 'heart', 'shield']),
                    'title' => fake()->sentence(3),
                    'description' => fake()->sentence(10),
                ], range(1, 3)),
            ],
            'testimonials' => [
                'title' => fake()->sentence(4),
                'testimonials' => array_map(fn() => [
                    'quote' => fake()->paragraph(),
                    'author' => fake()->name(),
                    'role' => fake()->jobTitle(),
                    'company' => fake()->company(),
                    'avatar' => fake()->imageUrl(200, 200, 'people'),
                ], range(1, 2)),
            ],
            default => [],
        };
    }

    /**
     * Create a hero block.
     */
    public function hero(): static
    {
        return $this->state(fn (array $attributes) => [
            'block_type' => 'hero',
            'content' => $this->generateBlockContent('hero'),
        ]);
    }

    /**
     * Create a text block.
     */
    public function text(): static
    {
        return $this->state(fn (array $attributes) => [
            'block_type' => 'text',
            'content' => $this->generateBlockContent('text'),
        ]);
    }

    /**
     * Create a CTA block.
     */
    public function cta(): static
    {
        return $this->state(fn (array $attributes) => [
            'block_type' => 'cta',
            'content' => $this->generateBlockContent('cta'),
        ]);
    }
}
