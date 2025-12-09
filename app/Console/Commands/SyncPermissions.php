<?php

namespace App\Console\Commands;

use App\Models\Shared\Permission;
use App\Models\Shared\Role;
use App\Enums\CentralPermission;
use App\Enums\TenantPermission;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class SyncPermissions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'permissions:sync {--fresh : Truncate all permissions and roles before syncing}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync roles and permissions (insert or update) - MVP Structure';

    /**
     * Central roles (stored in central database with guard 'central')
     * display_name and description use multi-language format.
     *
     * MULTI-DATABASE TENANCY: No tenant_id column - isolation at database level.
     * Guard 'central' uses 'central_users' provider (Central\User model).
     */
    protected array $centralRoles = [
        'super-admin' => [
            'display_name' => ['en' => 'Super Administrator', 'pt_BR' => 'Super Administrador'],
            'description' => ['en' => 'Full platform access', 'pt_BR' => 'Acesso total à plataforma'],
            'all_permissions' => true, // Gets ALL central permissions
        ],
        'central-admin' => [
            'display_name' => ['en' => 'Central Administrator', 'pt_BR' => 'Administrador Central'],
            'description' => ['en' => 'Access to central admin panel', 'pt_BR' => 'Acesso ao painel administrativo central'],
            'all_permissions' => true, // Gets ALL central permissions
        ],
        'support-admin' => [
            'display_name' => ['en' => 'Support Administrator', 'pt_BR' => 'Administrador de Suporte'],
            'description' => ['en' => 'View and impersonate tenants', 'pt_BR' => 'Visualizar e impersonar tenants'],
            'permissions' => [
                'tenants:view',
                'tenants:show',
                'tenants:impersonate',
                'users:view',
                'users:show',
                'addons:view',
            ],
        ],
    ];

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('🔄 Syncing Roles & Permissions...');
        $this->newLine();

        // Fresh flag: truncate all permissions and roles
        if ($this->option('fresh')) {
            $this->warn('⚠️  Fresh mode: Truncating all permissions and roles...');

            if (! $this->confirm('Are you sure? This will delete ALL permissions and roles!', false)) {
                $this->error('❌ Operation cancelled.');
                return self::FAILURE;
            }

            // PostgreSQL: Use TRUNCATE with CASCADE to handle foreign keys
            \DB::statement('TRUNCATE TABLE model_has_permissions, model_has_roles, role_has_permissions, permissions, roles RESTART IDENTITY CASCADE;');

            $this->info('✅ Tables truncated successfully.');
            $this->newLine();
        }

        // Step 1: Sync Central Permissions from enum (single source of truth)
        // Note: Tenant permissions are seeded in tenant databases via SeedTenantDatabase job
        $this->syncCentralPermissions();

        // Step 2: Sync Central Roles (after permissions exist)
        $this->syncCentralRoles();

        // Clear cache
        $this->info('🧹 Clearing permission cache...');
        Artisan::call('permission:cache-reset');
        $this->info('✅ Cache cleared.');
        $this->newLine();

        // Generate TypeScript types
        $this->info('📝 Generating TypeScript types...');
        Artisan::call('types:generate');
        $this->info('✅ TypeScript types generated.');
        $this->newLine();

        $this->info('🎉 Roles & Permissions synced successfully!');
        $this->newLine();

        // Summary
        $this->displaySummary();

        return self::SUCCESS;
    }

    /**
     * Sync Central Permissions from enum (single source of truth).
     * Uses guard 'central' for admin panel access.
     *
     * MULTI-DATABASE TENANCY: Stored in central database's permissions table.
     */
    protected function syncCentralPermissions(): void
    {
        $this->info('📝 Syncing Central Permissions...');

        $permissions = CentralPermission::toSeederArray();
        $created = 0;
        $updated = 0;

        foreach ($permissions as $permData) {
            $permission = Permission::firstOrCreate(
                ['name' => $permData['name'], 'guard_name' => 'central']
            );

            if ($permission->wasRecentlyCreated) {
                $created++;
                $this->line("  ✓ Created: {$permission->name}");
            } else {
                $updated++;
            }
        }

        $this->info("  ✅ {$created} Central permissions created, {$updated} existing.");
        $this->newLine();
    }

    /**
     * Sync Central Roles from $centralRoles array.
     * Uses guard 'central' for admin panel access.
     * NO BYPASS - all roles use explicit permissions.
     *
     * MULTI-DATABASE TENANCY: Stored in central database's roles table.
     */
    protected function syncCentralRoles(): void
    {
        $this->info('👥 Syncing Central Roles...');

        // Get all central permissions
        $allCentralPermissions = CentralPermission::values();

        foreach ($this->centralRoles as $roleName => $roleData) {
            $role = Role::updateOrCreate(
                ['name' => $roleName, 'guard_name' => 'central'],
                [
                    'display_name' => $roleData['display_name'],
                    'description' => $roleData['description'],
                ]
            );

            if ($role->wasRecentlyCreated) {
                $this->line("  ✓ Created role: {$role->name}");
            } else {
                $this->line("  ↻ Updated role: {$role->name}");
            }

            // Determine which permissions to assign
            if ($roleData['all_permissions'] ?? false) {
                // Role gets ALL central permissions
                $permissionsToSync = $allCentralPermissions;
            } else {
                // Role gets specific permissions
                $permissionsToSync = $roleData['permissions'] ?? [];
            }

            // Sync permissions with guard 'central'
            $role->syncPermissions(
                Permission::where('guard_name', 'central')
                    ->whereIn('name', $permissionsToSync)
                    ->get()
            );

            $permCount = count($permissionsToSync);
            $this->line("    → Synced {$permCount} permissions");
        }

        $this->info('  ✅ Central roles synced.');
        $this->newLine();
    }

    /**
     * Display summary of current state
     */
    protected function displaySummary(): void
    {
        $this->info('📊 Summary:');
        $this->newLine();

        // Get central permission categories from enum
        $centralPermissions = CentralPermission::toSeederArray();
        $centralCategories = collect($centralPermissions)
            ->groupBy('category')
            ->map->count()
            ->sortKeys();

        $this->info('🏛️  Central Permissions (guard: central):');
        $centralData = [];
        foreach ($centralCategories as $category => $count) {
            $centralData[] = [$category, $count];
        }
        $centralData[] = ['TOTAL', count($centralPermissions)];
        $this->table(['Category', 'Count'], $centralData);
        $this->newLine();

        // Show tenant permission count (for reference - seeded per tenant database)
        $tenantPermissions = TenantPermission::toSeederArray();
        $this->info("📋 Tenant Permissions (guard: tenant, seeded per tenant database): " . count($tenantPermissions) . " permissions");
        $this->newLine();

        // Central Roles with permission count
        $this->info('👥 Central Roles (guard: central):');
        $rolesData = [];
        foreach (Role::where('guard_name', 'central')->get() as $role) {
            $rolesData[] = [
                $role->name,
                $role->display_name ?: '-',
                $role->permissions()->count(),
            ];
        }

        if (empty($rolesData)) {
            $this->warn('  No central roles found.');
        } else {
            $this->table(
                ['Role', 'Display Name', 'Permissions'],
                $rolesData
            );
        }
    }
}
