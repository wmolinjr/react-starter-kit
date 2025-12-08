<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;

/**
 * DatabaseSeeder
 *
 * OPTION C: TENANT-ONLY USERS
 * - This seeds the CENTRAL database
 * - Tenant databases are seeded automatically via SeedTenantDatabase job
 *
 * Execution order:
 * 1. sail artisan migrate:fresh
 * 2. sail artisan db:seed
 *
 * Seeders run:
 * 1. PlanSeeder - Plans (Starter, Professional, Enterprise)
 * 2. AddonSeeder - Addon catalog
 * 3. AddonBundleSeeder - Addon bundles (packages)
 * 4. Permissions sync - Central roles (Super Admin, etc.)
 * 5. CentralUserSeeder - Central users (Central\User model)
 * 6. TenantSeeder - Create tenants (triggers tenant DB creation & seeding)
 *
 * NOTE: Feature and Limit definitions are now in enums (PlanFeature, PlanLimit)
 *       and do not require database seeding.
 *
 * NOTE: Tenant\User is for tenant databases. Central\User is for central database.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('Seeding central database...');
        $this->command->newLine();

        // Seed plans (features/limits come from enums, not database)
        $this->call([PlanSeeder::class]);
        $this->command->newLine();

        // Seed addon catalog
        $this->command->info('Seeding addon catalog...');
        $this->call([AddonSeeder::class]);
        $this->command->info('Addon catalog seeded!');
        $this->command->newLine();

        // Seed addon bundles
        $this->command->info('Seeding addon bundles...');
        $this->call([AddonBundleSeeder::class]);
        $this->command->info('Addon bundles seeded!');
        $this->command->newLine();

        // Sync permissions and roles (creates Super Admin role in central DB)
        $this->command->info('Syncing permissions and roles...');
        Artisan::call('permissions:sync', [], $this->command->getOutput());
        $this->command->newLine();

        // Seed central users (Central\User model)
        $this->call([CentralUserSeeder::class]);
        $this->command->newLine();

        // Seed tenants (triggers automatic database creation, migration, seeding)
        // Owner users are created in TENANT databases by SeedTenantDatabase job
        $this->call([TenantSeeder::class]);
        $this->command->newLine();

        // Seed federation groups and federated users
        $this->command->info('Seeding federation data...');
        $this->call([FederationSeeder::class]);
    }
}
