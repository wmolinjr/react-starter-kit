<?php

namespace Database\Seeders;

use App\Models\Page;
use App\Models\PageBlock;
use App\Models\Tenant;
use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->command->info('🌱 Seeding database...');

        // Create test user
        $this->command->info('Creating test user...');
        $user = User::firstOrCreate(
            ['email' => 'test@example.com'],
            [
                'name' => 'Test User',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );

        // Create Tenant 1: Cliente
        $this->command->info('Creating tenant: Cliente...');
        $cliente = Tenant::firstOrCreate(
            ['subdomain' => 'cliente'],
            [
                'name' => 'Cliente Corp',
                'slug' => 'cliente',
                'subdomain' => 'cliente',
                'status' => 'active',
                'settings' => [
                    'primary_color' => '#3B82F6',
                    'logo' => null,
                ],
            ]
        );

        // Attach user to Cliente tenant
        if (!$cliente->users->contains($user)) {
            $cliente->users()->attach($user->id, ['role' => 'owner']);
        }

        // Create Tenant 2: Acme
        $this->command->info('Creating tenant: Acme...');
        $acme = Tenant::firstOrCreate(
            ['subdomain' => 'acme'],
            [
                'name' => 'Acme Corporation',
                'slug' => 'acme',
                'subdomain' => 'acme',
                'status' => 'active',
                'settings' => [
                    'primary_color' => '#10B981',
                    'logo' => null,
                ],
            ]
        );

        // Attach user to Acme tenant
        if (!$acme->users->contains($user)) {
            $acme->users()->attach($user->id, ['role' => 'owner']);
        }

        // Set current tenant for user
        $user->update(['current_tenant_id' => $cliente->id]);

        // Create pages for Cliente
        $this->command->info('Creating pages for Cliente...');
        $this->createPagesForTenant($cliente, $user);

        // Create pages for Acme
        $this->command->info('Creating pages for Acme...');
        $this->createPagesForTenant($acme, $user);

        $this->command->info('✅ Seeding completed successfully!');
        $this->command->newLine();
        $this->command->info('🔑 Login credentials:');
        $this->command->info('   Email: test@example.com');
        $this->command->info('   Password: password');
        $this->command->newLine();
        $this->command->info('🌐 Access tenants via:');
        $this->command->info('   http://cliente.localhost');
        $this->command->info('   http://acme.localhost');
    }

    private function createPagesForTenant(Tenant $tenant, User $user): void
    {
        // Home Page
        $home = Page::firstOrCreate(
            [
                'tenant_id' => $tenant->id,
                'slug' => 'home',
            ],
            [
                'title' => 'Home',
                'meta_title' => 'Welcome to ' . $tenant->name,
                'meta_description' => 'Official homepage of ' . $tenant->name,
                'status' => 'published',
                'published_at' => now(),
                'created_by' => $user->id,
            ]
        );

        // Create blocks for home page
        if ($home->blocks()->count() === 0) {
            PageBlock::create([
                'page_id' => $home->id,
                'block_type' => 'hero',
                'content' => [
                    'title' => 'Welcome to ' . $tenant->name,
                    'subtitle' => 'Building amazing things together',
                    'button_text' => 'Get Started',
                ],
                'order' => 0,
            ]);

            PageBlock::create([
                'page_id' => $home->id,
                'block_type' => 'features',
                'content' => [
                    'title' => 'Our Features',
                    'features' => [
                        [
                            'title' => 'Fast & Reliable',
                            'description' => 'Lightning-fast performance and 99.9% uptime',
                        ],
                        [
                            'title' => 'Secure',
                            'description' => 'Enterprise-grade security for your data',
                        ],
                        [
                            'title' => 'Scalable',
                            'description' => 'Grows with your business needs',
                        ],
                    ],
                ],
                'order' => 1,
            ]);

            PageBlock::create([
                'page_id' => $home->id,
                'block_type' => 'cta',
                'content' => [
                    'title' => 'Ready to get started?',
                    'description' => 'Join thousands of satisfied customers',
                    'button_text' => 'Sign Up Now',
                ],
                'order' => 2,
            ]);
        }

        // About Page
        $about = Page::firstOrCreate(
            [
                'tenant_id' => $tenant->id,
                'slug' => 'about',
            ],
            [
                'title' => 'About Us',
                'meta_title' => 'About ' . $tenant->name,
                'meta_description' => 'Learn more about ' . $tenant->name . ' and our mission',
                'status' => 'published',
                'published_at' => now(),
                'created_by' => $user->id,
            ]
        );

        if ($about->blocks()->count() === 0) {
            PageBlock::create([
                'page_id' => $about->id,
                'block_type' => 'text',
                'content' => [
                    'heading' => 'Our Story',
                    'content' => 'Founded in 2025, ' . $tenant->name . ' has been at the forefront of innovation in our industry. We are committed to delivering excellence and exceeding expectations.',
                ],
                'order' => 0,
            ]);

            PageBlock::create([
                'page_id' => $about->id,
                'block_type' => 'text',
                'content' => [
                    'heading' => 'Our Mission',
                    'content' => 'To empower businesses with cutting-edge technology solutions that drive growth and success.',
                ],
                'order' => 1,
            ]);
        }
    }
}
