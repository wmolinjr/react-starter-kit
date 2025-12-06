<?php

namespace Database\Seeders;

use App\Models\Central\Plan;
use App\Models\Central\Tenant;
use Illuminate\Database\Seeder;

/**
 * TenantSeeder
 *
 * OPTION C: TENANT-ONLY USERS
 * - Creates tenants in the CENTRAL database
 * - Tenant creation triggers automatic:
 *   1. Database creation (Jobs\CreateDatabase)
 *   2. Migrations (Jobs\MigrateDatabase)
 *   3. Seeding (Jobs\SeedTenantDatabase)
 * - Each tenant gets their own PostgreSQL database
 *
 * IMPORTANT: Users are now created in TENANT databases only.
 * Owner data is passed to tenant and SeedTenantDatabase creates the user.
 */
class TenantSeeder extends Seeder
{
    public function run(): void
    {
        // Get plans (created by PlanSeeder which runs before TenantSeeder)
        $starterPlan = Plan::where('slug', 'starter')->first();
        $professionalPlan = Plan::where('slug', 'professional')->first();
        $enterprisePlan = Plan::where('slug', 'enterprise')->first();

        if (! $professionalPlan || ! $starterPlan || ! $enterprisePlan) {
            $this->command->error('Plans not found! Run PlanSeeder first.');

            return;
        }

        // Tenant 1 - Professional Plan
        $this->createTenant(
            name: 'Acme Corporation',
            slug: 'acme',
            domain: 'tenant1.localhost',
            ownerName: 'John Doe',
            ownerEmail: 'john@acme.com',
            plan: $professionalPlan,
            settings: ['branding' => ['primary_color' => '#3b82f6']]
        );

        // Tenant 2 - Starter Plan
        $this->createTenant(
            name: 'Startup Inc',
            slug: 'startup',
            domain: 'tenant2.localhost',
            ownerName: 'Jane Smith',
            ownerEmail: 'jane@startup.com',
            plan: $starterPlan,
            settings: ['branding' => ['primary_color' => '#10b981']]
        );

        // Tenant 3 - Enterprise Plan
        $this->createTenant(
            name: 'Enterprise Corp',
            slug: 'enterprise',
            domain: 'tenant3.localhost',
            ownerName: 'Mike Johnson',
            ownerEmail: 'mike@enterprise.com',
            plan: $enterprisePlan,
            settings: ['branding' => ['primary_color' => '#8b5cf6']]
        );

        $this->command->info('');
        $this->command->info('Tenants created successfully!');
        $this->command->info('  - tenant1.localhost (john@acme.com / password) - Professional Plan');
        $this->command->info('  - tenant2.localhost (jane@startup.com / password) - Starter Plan');
        $this->command->info('  - tenant3.localhost (mike@enterprise.com / password) - Enterprise Plan');
    }

    /**
     * Create a tenant with owner user.
     *
     * OPTION C (TENANT-ONLY USERS):
     * - Owner data is stored in tenant settings temporarily
     * - SeedTenantDatabase job reads this data and creates the user
     * - User is created ONLY in tenant database
     * - No User model in central database for tenant users
     */
    private function createTenant(
        string $name,
        string $slug,
        string $domain,
        string $ownerName,
        string $ownerEmail,
        Plan $plan,
        array $settings = []
    ): Tenant {
        // Store owner data in settings for SeedTenantDatabase to consume
        $settings['_seed_owner'] = [
            'name' => $ownerName,
            'email' => $ownerEmail,
            'password' => 'password', // Will be hashed by SeedTenantDatabase
        ];

        // Create tenant (triggers database creation, migration, seeding)
        $tenant = Tenant::create([
            'name' => $name,
            'slug' => $slug,
            'plan_id' => $plan->id,
            'settings' => $settings,
        ]);

        // Create domain
        $tenant->domains()->create([
            'domain' => $domain,
            'is_primary' => true,
        ]);

        // Remove temporary seed data from settings
        $cleanSettings = $tenant->settings;
        unset($cleanSettings['_seed_owner']);
        $tenant->update(['settings' => $cleanSettings]);

        // Regenerate plan permissions
        $tenant->regeneratePlanPermissions();

        $this->command->info("Created tenant: {$name} ({$domain})");

        return $tenant;
    }
}
