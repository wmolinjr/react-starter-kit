<?php

namespace Database\Seeders;

use App\Models\Page;
use App\Models\PageBlock;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;

class PageSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get existing tenants (from previous seed)
        $tenants = Tenant::all();

        if ($tenants->isEmpty()) {
            $this->command->warn('No tenants found. Please seed tenants first.');
            return;
        }

        foreach ($tenants as $tenant) {
            $users = $tenant->users;

            if ($users->isEmpty()) {
                continue;
            }

            $creator = $users->first();

            // Create a published homepage
            $homepage = Page::create([
                'tenant_id' => $tenant->id,
                'title' => 'Home',
                'slug' => 'home',
                'status' => 'published',
                'published_at' => now()->subDays(10),
                'meta_title' => 'Welcome to ' . $tenant->name,
                'meta_description' => 'Official homepage of ' . $tenant->name,
                'created_by' => $creator->id,
            ]);

            // Add blocks to homepage
            PageBlock::create([
                'page_id' => $homepage->id,
                'block_type' => 'hero',
                'order' => 0,
                'content' => [
                    'title' => 'Welcome to ' . $tenant->name,
                    'subtitle' => 'Build amazing pages with our powerful page builder',
                    'button_text' => 'Get Started',
                    'button_url' => '#',
                    'image_url' => 'https://picsum.photos/1920/1080',
                ],
                'config' => [
                    'background_color' => '#1e40af',
                    'text_color' => '#ffffff',
                    'padding' => 'large',
                ],
            ]);

            PageBlock::create([
                'page_id' => $homepage->id,
                'block_type' => 'features',
                'order' => 1,
                'content' => [
                    'title' => 'Key Features',
                    'features' => [
                        [
                            'icon' => 'check',
                            'title' => 'Easy to Use',
                            'description' => 'Intuitive drag-and-drop interface for building pages',
                        ],
                        [
                            'icon' => 'star',
                            'title' => 'Customizable',
                            'description' => 'Fully customizable blocks and layouts',
                        ],
                        [
                            'icon' => 'shield',
                            'title' => 'Secure',
                            'description' => 'Enterprise-grade security and compliance',
                        ],
                    ],
                ],
                'config' => [
                    'background_color' => '#ffffff',
                    'text_color' => '#000000',
                    'padding' => 'medium',
                ],
            ]);

            PageBlock::create([
                'page_id' => $homepage->id,
                'block_type' => 'cta',
                'order' => 2,
                'content' => [
                    'title' => 'Ready to get started?',
                    'description' => 'Join thousands of users building amazing pages',
                    'button_text' => 'Start Free Trial',
                    'button_url' => '#',
                ],
                'config' => [
                    'background_color' => '#10b981',
                    'text_color' => '#ffffff',
                    'padding' => 'large',
                ],
            ]);

            // Create About page
            $aboutPage = Page::create([
                'tenant_id' => $tenant->id,
                'title' => 'About Us',
                'slug' => 'about',
                'status' => 'published',
                'published_at' => now()->subDays(8),
                'meta_title' => 'About ' . $tenant->name,
                'meta_description' => 'Learn more about ' . $tenant->name,
                'created_by' => $creator->id,
            ]);

            PageBlock::create([
                'page_id' => $aboutPage->id,
                'block_type' => 'text',
                'order' => 0,
                'content' => [
                    'heading' => 'Our Story',
                    'content' => 'We are a team of passionate developers building the best page builder platform. Our mission is to empower creators to build beautiful, functional websites without writing code.',
                ],
            ]);

            PageBlock::create([
                'page_id' => $aboutPage->id,
                'block_type' => 'testimonials',
                'order' => 1,
                'content' => [
                    'title' => 'What Our Customers Say',
                    'testimonials' => [
                        [
                            'quote' => 'This page builder has transformed how we create content. The interface is intuitive and powerful.',
                            'author' => 'John Doe',
                            'role' => 'Marketing Director',
                            'company' => 'Tech Corp',
                            'avatar' => 'https://picsum.photos/200',
                        ],
                        [
                            'quote' => 'Best page builder I\'ve ever used. The blocks are flexible and the output is clean.',
                            'author' => 'Jane Smith',
                            'role' => 'Product Manager',
                            'company' => 'Startup Inc',
                            'avatar' => 'https://picsum.photos/201',
                        ],
                    ],
                ],
            ]);

            // Create a draft page
            Page::create([
                'tenant_id' => $tenant->id,
                'title' => 'Coming Soon',
                'slug' => 'coming-soon',
                'status' => 'draft',
                'meta_title' => 'Coming Soon',
                'created_by' => $creator->id,
            ]);

            // Create 2 more random pages
            for ($i = 0; $i < 2; $i++) {
                $page = Page::factory()
                    ->for($tenant)
                    ->published()
                    ->create([
                        'created_by' => $creator->id,
                    ]);

                // Add 2-4 random blocks
                $blockCount = rand(2, 4);
                for ($j = 0; $j < $blockCount; $j++) {
                    PageBlock::factory()
                        ->for($page)
                        ->create(['order' => $j]);
                }
            }
        }

        $this->command->info('Pages seeded successfully!');
    }
}
