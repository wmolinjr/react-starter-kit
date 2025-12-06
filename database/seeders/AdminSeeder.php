<?php

namespace Database\Seeders;

use App\Models\Central\User as Admin;
use Illuminate\Database\Seeder;

/**
 * AdminSeeder
 *
 * OPTION C: TENANT-ONLY USERS
 * - Creates administrative users in the CENTRAL database
 * - Uses Admin model (not User)
 * - These accounts can access central admin panel
 * - Can impersonate into tenant domains
 *
 * Test accounts:
 * - admin@setor3.app / password (super admin)
 * - support@setor3.app / password (support)
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
                'is_super_admin' => true,
                'locale' => 'pt_BR',
            ]
        );

        $this->command->info("  - Super Admin: {$superAdmin->email} (is_super_admin: true)");

        // Support Admin (can impersonate but not super admin)
        $supportAdmin = Admin::firstOrCreate(
            ['email' => 'support@setor3.app'],
            [
                'name' => 'Support Team',
                'password' => bcrypt('password'),
                'email_verified_at' => now(),
                'is_super_admin' => true, // Allow impersonation for support
                'locale' => 'pt_BR',
            ]
        );

        $this->command->info("  - Support Admin: {$supportAdmin->email}");

        $this->command->info('Admin users seeded successfully!');
    }
}
