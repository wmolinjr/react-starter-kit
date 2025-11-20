<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

class TenantSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Criar super admin
        $superAdmin = User::create([
            'name' => 'Super Admin',
            'email' => 'admin@setor3.app',
            'password' => bcrypt('password'),
            'email_verified_at' => now(),
        ]);

        // Assign Super Admin role (globally, without tenant_id)
        setPermissionsTeamId(null);
        $superAdmin->assignRole('Super Admin');
        setPermissionsTeamId(null); // Keep null for next operations

        // Tenant 1
        $tenant1Id = DB::table('tenants')->insertGetId([
            'name' => 'Acme Corporation',
            'slug' => 'acme',
            'settings' => json_encode([
                'branding' => [
                    'primary_color' => '#3b82f6',
                ],
                'limits' => [
                    'max_users' => 50,
                    'max_projects' => 100,
                ],
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('domains')->insert([
            'tenant_id' => $tenant1Id,
            'domain' => 'tenant1.localhost',
            'is_primary' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $tenant1Owner = User::create([
            'name' => 'John Doe',
            'email' => 'john@acme.com',
            'password' => bcrypt('password'),
            'email_verified_at' => now(),
        ]);

        DB::table('tenant_user')->insert([
            'tenant_id' => $tenant1Id,
            'user_id' => $tenant1Owner->id,
            'joined_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Assign owner role
        $tenant1 = Tenant::find($tenant1Id);
        tenancy()->initialize($tenant1);
        setPermissionsTeamId($tenant1->id); // Set team ID for Spatie Permission

        // Sync permissions and roles for this tenant
        Artisan::call('permissions:sync');

        $tenant1Owner->assignRole('owner');
        tenancy()->end();

        // Tenant 2
        $tenant2Id = DB::table('tenants')->insertGetId([
            'name' => 'Startup Inc',
            'slug' => 'startup',
            'settings' => json_encode([
                'branding' => [
                    'primary_color' => '#10b981',
                ],
                'limits' => [
                    'max_users' => 10,
                    'max_projects' => 25,
                ],
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('domains')->insert([
            'tenant_id' => $tenant2Id,
            'domain' => 'tenant2.localhost',
            'is_primary' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $tenant2Owner = User::create([
            'name' => 'Jane Smith',
            'email' => 'jane@startup.com',
            'password' => bcrypt('password'),
            'email_verified_at' => now(),
        ]);

        DB::table('tenant_user')->insert([
            'tenant_id' => $tenant2Id,
            'user_id' => $tenant2Owner->id,
            'joined_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Assign owner role
        $tenant2 = Tenant::find($tenant2Id);
        tenancy()->initialize($tenant2);
        setPermissionsTeamId($tenant2->id); // Set team ID for Spatie Permission

        // Sync permissions and roles for this tenant
        Artisan::call('permissions:sync');

        $tenant2Owner->assignRole('owner');
        tenancy()->end();

        $this->command->info('✅ Tenants created successfully!');
        $this->command->info('  - tenant1.localhost (john@acme.com / password)');
        $this->command->info('  - tenant2.localhost (jane@startup.com / password)');
        $this->command->info('  - Super admin: admin@setor3.app / password');
    }
}
