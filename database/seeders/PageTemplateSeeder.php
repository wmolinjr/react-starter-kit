<?php

namespace Database\Seeders;

use App\Models\PageTemplate;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

class PageTemplateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $tenants = Tenant::all();

        if ($tenants->isEmpty()) {
            $this->command->warn('No tenants found. Please seed tenants first.');
            return;
        }

        foreach ($tenants as $tenant) {
            // Landing Page Template
            PageTemplate::create([
                'tenant_id' => $tenant->id,
                'name' => 'Landing Page',
                'description' => 'Perfect for product launches and marketing campaigns',
                'category' => 'marketing',
                'thumbnail' => 'https://picsum.photos/400/300?random=1',
                'blocks' => [
                    [
                        'block_type' => 'hero',
                        'content' => [
                            'title' => 'Your Product Name',
                            'subtitle' => 'Solve your customers\' biggest problem',
                            'button_text' => 'Get Started',
                            'button_url' => '#',
                            'image_url' => 'https://picsum.photos/1920/1080',
                        ],
                        'config' => [
                            'background_color' => '#1e40af',
                            'text_color' => '#ffffff',
                            'padding' => 'large',
                        ],
                    ],
                    [
                        'block_type' => 'features',
                        'content' => [
                            'title' => 'Amazing Features',
                            'features' => [
                                [
                                    'icon' => 'check',
                                    'title' => 'Feature One',
                                    'description' => 'Description of your amazing feature',
                                ],
                                [
                                    'icon' => 'star',
                                    'title' => 'Feature Two',
                                    'description' => 'Another great feature',
                                ],
                                [
                                    'icon' => 'heart',
                                    'title' => 'Feature Three',
                                    'description' => 'Yet another awesome feature',
                                ],
                            ],
                        ],
                        'config' => [
                            'background_color' => '#ffffff',
                            'text_color' => '#000000',
                            'padding' => 'medium',
                        ],
                    ],
                    [
                        'block_type' => 'cta',
                        'content' => [
                            'title' => 'Ready to get started?',
                            'description' => 'Sign up today and see the difference',
                            'button_text' => 'Start Free Trial',
                            'button_url' => '#',
                        ],
                        'config' => [
                            'background_color' => '#10b981',
                            'text_color' => '#ffffff',
                            'padding' => 'large',
                        ],
                    ],
                ],
            ]);

            // About Page Template
            PageTemplate::create([
                'tenant_id' => $tenant->id,
                'name' => 'About Page',
                'description' => 'Tell your company story and showcase your team',
                'category' => 'corporate',
                'thumbnail' => 'https://picsum.photos/400/300?random=2',
                'blocks' => [
                    [
                        'block_type' => 'text',
                        'content' => [
                            'heading' => 'Our Story',
                            'content' => 'Tell your company\'s unique story here. Explain your mission, vision, and values.',
                        ],
                        'config' => [
                            'background_color' => '#ffffff',
                            'text_color' => '#000000',
                            'padding' => 'medium',
                        ],
                    ],
                    [
                        'block_type' => 'image',
                        'content' => [
                            'image_url' => 'https://picsum.photos/1200/800',
                            'alt_text' => 'Our team',
                            'caption' => 'The team that makes it all happen',
                        ],
                        'config' => [
                            'background_color' => '#f3f4f6',
                            'padding' => 'medium',
                        ],
                    ],
                    [
                        'block_type' => 'testimonials',
                        'content' => [
                            'title' => 'What People Say',
                            'testimonials' => [
                                [
                                    'quote' => 'Amazing experience working with this team!',
                                    'author' => 'Customer Name',
                                    'role' => 'Job Title',
                                    'company' => 'Company Name',
                                    'avatar' => 'https://picsum.photos/200',
                                ],
                            ],
                        ],
                        'config' => [
                            'background_color' => '#ffffff',
                            'padding' => 'medium',
                        ],
                    ],
                ],
            ]);

            // Services Page Template
            PageTemplate::create([
                'tenant_id' => $tenant->id,
                'name' => 'Services Page',
                'description' => 'Showcase your services and offerings',
                'category' => 'business',
                'thumbnail' => 'https://picsum.photos/400/300?random=3',
                'blocks' => [
                    [
                        'block_type' => 'text',
                        'content' => [
                            'heading' => 'Our Services',
                            'content' => 'We offer a wide range of professional services to help you succeed.',
                        ],
                        'config' => [
                            'background_color' => '#ffffff',
                            'text_color' => '#000000',
                            'padding' => 'medium',
                        ],
                    ],
                    [
                        'block_type' => 'features',
                        'content' => [
                            'title' => 'What We Offer',
                            'features' => [
                                [
                                    'icon' => 'check',
                                    'title' => 'Service 1',
                                    'description' => 'Description of your first service',
                                ],
                                [
                                    'icon' => 'check',
                                    'title' => 'Service 2',
                                    'description' => 'Description of your second service',
                                ],
                                [
                                    'icon' => 'check',
                                    'title' => 'Service 3',
                                    'description' => 'Description of your third service',
                                ],
                            ],
                        ],
                        'config' => [
                            'background_color' => '#f3f4f6',
                            'padding' => 'large',
                        ],
                    ],
                    [
                        'block_type' => 'cta',
                        'content' => [
                            'title' => 'Need our services?',
                            'description' => 'Get in touch today for a free consultation',
                            'button_text' => 'Contact Us',
                            'button_url' => '#',
                        ],
                        'config' => [
                            'background_color' => '#3b82f6',
                            'text_color' => '#ffffff',
                            'padding' => 'large',
                        ],
                    ],
                ],
            ]);

            // Portfolio Template
            PageTemplate::create([
                'tenant_id' => $tenant->id,
                'name' => 'Portfolio',
                'description' => 'Showcase your work and projects',
                'category' => 'creative',
                'thumbnail' => 'https://picsum.photos/400/300?random=4',
                'blocks' => [
                    [
                        'block_type' => 'text',
                        'content' => [
                            'heading' => 'Our Work',
                            'content' => 'Check out some of our recent projects and success stories.',
                        ],
                        'config' => [
                            'background_color' => '#ffffff',
                            'text_color' => '#000000',
                            'padding' => 'small',
                        ],
                    ],
                    [
                        'block_type' => 'gallery',
                        'content' => [
                            'images' => [
                                [
                                    'url' => 'https://picsum.photos/800/600?random=10',
                                    'alt' => 'Project 1',
                                    'caption' => 'Amazing Project One',
                                ],
                                [
                                    'url' => 'https://picsum.photos/800/600?random=11',
                                    'alt' => 'Project 2',
                                    'caption' => 'Beautiful Project Two',
                                ],
                                [
                                    'url' => 'https://picsum.photos/800/600?random=12',
                                    'alt' => 'Project 3',
                                    'caption' => 'Stunning Project Three',
                                ],
                            ],
                        ],
                        'config' => [
                            'background_color' => '#f9fafb',
                            'padding' => 'large',
                        ],
                    ],
                ],
            ]);
        }

        $this->command->info('Page templates seeded successfully!');
    }
}
