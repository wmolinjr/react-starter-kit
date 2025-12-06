<?php

namespace Database\Seeders;

use App\Models\Central\User as Admin;
use Illuminate\Database\Seeder;

/**
 * AdminSeeder
 *
 * OPTION C: TENANT-ONLY USERS
 * - Creates administrative users in the CENTRAL database
 * - Uses Admin model (Central\User)
 * - Assigns roles via Spatie Permission (guard: central)
 *
 * Roles:
 * - super-admin: Full platform access
 * - central-admin: Admin panel access
 * - support-admin: View and impersonate only
 *
 * Test accounts:
 * - admin@setor3.app / password (super-admin)
 * - support@setor3.app / password (support-admin)
 */
class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('Seeding admin users (central database)...');

        // Super Admin
        $superAdmin = Admin::firstOrCreate(
            ['email' => 'admin@setor3.app'],
            [
                'name' => 'Super Admin',
                'password' => bcrypt('password'),
                'email_verified_at' => now(),
                'locale' => 'pt_BR',
            ]
        );

        // Assign super-admin role (has all permissions)
        if (!$superAdmin->hasRole('super-admin')) {
            $superAdmin->assignRole('super-admin');
        }

        $this->command->info("  - Super Admin: {$superAdmin->email} (role: super-admin)");

        // Support Admin (can view and impersonate, but not edit/delete)
        $supportAdmin = Admin::firstOrCreate(
            ['email' => 'support@setor3.app'],
            [
                'name' => 'Support Team',
                'password' => bcrypt('password'),
                'email_verified_at' => now(),
                'locale' => 'pt_BR',
            ]
        );

        // Assign support-admin role (limited permissions)
        if (!$supportAdmin->hasRole('support-admin')) {
            $supportAdmin->assignRole('support-admin');
        }

        $this->command->info("  - Support Admin: {$supportAdmin->email} (role: support-admin)");

        $this->command->info('Admin users seeded successfully!');
    }
}
