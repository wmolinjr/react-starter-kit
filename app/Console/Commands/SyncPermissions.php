<?php

namespace App\Console\Commands;

use App\Models\Universal\Permission;
use App\Models\Universal\Role;
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
     * Central Admin role (global, stored in central database)
     * Has all central category permissions for admin panel access.
     * display_name and description use multi-language format.
     *
     * MULTI-DATABASE TENANCY: No tenant_id column - isolation at database level.
     */
    protected array $centralRoles = [
        'Central Admin' => [
            'display_name' => ['en' => 'Central Administrator', 'pt_BR' => 'Administrador Central'],
            'description' => ['en' => 'Access to central admin panel', 'pt_BR' => 'Acesso ao painel administrativo central'],
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
        $this->syncPermissions(CentralPermission::toSeederArray(), 'Central Admin');

        // Step 2: Sync Roles (after permissions exist)
        $this->syncSuperAdminRole();
        $this->syncCentralAdminRole();

        // Clear cache
        $this->info('🧹 Clearing permission cache...');
        Artisan::call('permission:cache-reset');
        $this->info('✅ Cache cleared.');
        $this->newLine();

        // Generate TypeScript types
        $this->info('📝 Generating TypeScript types...');
        Artisan::call('permissions:generate-types');
        $this->info('✅ TypeScript types generated.');
        $this->newLine();

        $this->info('🎉 Roles & Permissions synced successfully!');
        $this->newLine();

        // Summary
        $this->displaySummary();

        return self::SUCCESS;
    }

    /**
     * Sync Super Admin role globally.
     * This role bypasses all permission checks via Gate::before()
     *
     * MULTI-DATABASE TENANCY: No tenant_id column needed.
     * Stored in central database's roles table.
     */
    protected function syncSuperAdminRole(): void
    {
        $this->info('👑 Syncing Global Super Admin Role...');

        $role = Role::updateOrCreate(
            ['name' => 'Super Admin', 'guard_name' => 'tenant'],
            [
                'display_name' => ['en' => 'Super Administrator', 'pt_BR' => 'Super Administrador'],
                'description' => ['en' => 'Full platform access (global)', 'pt_BR' => 'Acesso total à plataforma (global)'],
            ]
        );

        if ($role->wasRecentlyCreated) {
            $this->line("  ✓ Created global role: {$role->name}");
        } else {
            $this->line("  ↻ Updated global role: {$role->name}");
        }

        // Note: Super Admin doesn't need explicit permissions
        // Gate::before() in AppServiceProvider grants all permissions automatically
        $this->line("    → Bypasses all permission checks via Gate::before()");

        $this->info('  ✅ Super Admin role synced.');
        $this->newLine();
    }

    /**
     * Sync Central Admin role globally.
     * This role has all central category permissions for admin panel access.
     *
     * MULTI-DATABASE TENANCY: No tenant_id column needed.
     * Stored in central database's roles table.
     */
    protected function syncCentralAdminRole(): void
    {
        $this->info('🏛️  Syncing Global Central Admin Role...');

        // Get all central permissions from registry
        $centralPermissions = CentralPermission::values();

        foreach ($this->centralRoles as $roleName => $roleData) {
            $role = Role::updateOrCreate(
                ['name' => $roleName, 'guard_name' => 'tenant'],
                [
                    'display_name' => $roleData['display_name'],
                    'description' => $roleData['description'],
                ]
            );

            if ($role->wasRecentlyCreated) {
                $this->line("  ✓ Created global role: {$role->name}");
            } else {
                $this->line("  ↻ Updated global role: {$role->name}");
            }

            // Sync central permissions
            $role->syncPermissions($centralPermissions);
            $permCount = count($centralPermissions);
            $this->line("    → Synced {$permCount} central permissions");
        }

        $this->info('  ✅ Central Admin role synced.');
        $this->newLine();
    }

    /**
     * Sync permissions (insert or update)
     * Category and description are derived from enums via model accessors.
     *
     * @param array $permissions Array of permission definitions from enum
     * @param string $type Label for display (Tenant/Central Admin)
     */
    protected function syncPermissions(array $permissions, string $type): void
    {
        $this->info("📝 Syncing {$type} Permissions...");

        $created = 0;
        $updated = 0;

        foreach ($permissions as $permData) {
            $permission = Permission::firstOrCreate(
                ['name' => $permData['name'], 'guard_name' => 'tenant']
            );

            if ($permission->wasRecentlyCreated) {
                $created++;
                $this->line("  ✓ Created: {$permission->name}");
            } else {
                $updated++;
            }
        }

        $this->info("  ✅ {$created} {$type} permissions created, {$updated} existing.");
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

        $this->info('🏛️  Central Admin Permissions (in central database):');
        $centralData = [];
        foreach ($centralCategories as $category => $count) {
            $centralData[] = [$category, $count];
        }
        $centralData[] = ['TOTAL', count($centralPermissions)];
        $this->table(['Category', 'Count'], $centralData);
        $this->newLine();

        // Show tenant permission count (for reference - seeded per tenant database)
        $tenantPermissions = TenantPermission::toSeederArray();
        $this->info("📋 Tenant Permissions (seeded per tenant database): " . count($tenantPermissions) . " permissions");
        $this->newLine();

        // Roles with permission count (central database roles)
        $this->info('👥 Roles (Central Database):');
        $rolesData = [];
        foreach (Role::all() as $role) {
            $rolesData[] = [
                $role->name,
                $role->trans('display_name') ?: '-',
                $role->permissions()->count(),
            ];
        }

        $this->table(
            ['Role', 'Display Name', 'Permissions'],
            $rolesData
        );
    }
}
