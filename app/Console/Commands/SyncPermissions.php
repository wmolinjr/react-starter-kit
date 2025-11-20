<?php

namespace App\Console\Commands;

use App\Models\Permission;
use App\Models\Role;
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
     * All permissions structure
     */
    protected array $permissions = [
        // Projects (8 permissions)
        ['name' => 'tenant.projects:view', 'description' => 'View all projects', 'category' => 'projects'],
        ['name' => 'tenant.projects:create', 'description' => 'Create new projects', 'category' => 'projects'],
        ['name' => 'tenant.projects:edit', 'description' => 'Edit any project', 'category' => 'projects'],
        ['name' => 'tenant.projects:edit-own', 'description' => 'Edit own projects only', 'category' => 'projects'],
        ['name' => 'tenant.projects:delete', 'description' => 'Delete projects', 'category' => 'projects'],
        ['name' => 'tenant.projects:upload', 'description' => 'Upload files', 'category' => 'projects'],
        ['name' => 'tenant.projects:download', 'description' => 'Download files', 'category' => 'projects'],
        ['name' => 'tenant.projects:archive', 'description' => 'Archive projects', 'category' => 'projects'],

        // Team (5 permissions)
        ['name' => 'tenant.team:view', 'description' => 'View team members', 'category' => 'team'],
        ['name' => 'tenant.team:invite', 'description' => 'Invite members', 'category' => 'team'],
        ['name' => 'tenant.team:remove', 'description' => 'Remove members', 'category' => 'team'],
        ['name' => 'tenant.team:manage-roles', 'description' => 'Manage roles', 'category' => 'team'],
        ['name' => 'tenant.team:activity', 'description' => 'View activity logs', 'category' => 'team'],

        // Settings (3 permissions)
        ['name' => 'tenant.settings:view', 'description' => 'View settings', 'category' => 'settings'],
        ['name' => 'tenant.settings:edit', 'description' => 'Edit settings', 'category' => 'settings'],
        ['name' => 'tenant.settings:danger', 'description' => 'Danger zone access', 'category' => 'settings'],

        // Billing (3 permissions)
        ['name' => 'tenant.billing:view', 'description' => 'View billing', 'category' => 'billing'],
        ['name' => 'tenant.billing:manage', 'description' => 'Manage subscriptions', 'category' => 'billing'],
        ['name' => 'tenant.billing:invoices', 'description' => 'Download invoices', 'category' => 'billing'],
    ];

    /**
     * Roles structure with their permissions
     */
    protected array $roles = [
        'owner' => [
            'display_name' => 'Proprietário',
            'description' => 'Acesso total incluindo billing',
            'permissions' => [
                // All permissions (19 total)
                'tenant.projects:view',
                'tenant.projects:create',
                'tenant.projects:edit',
                'tenant.projects:edit-own',
                'tenant.projects:delete',
                'tenant.projects:upload',
                'tenant.projects:download',
                'tenant.projects:archive',
                'tenant.team:view',
                'tenant.team:invite',
                'tenant.team:remove',
                'tenant.team:manage-roles',
                'tenant.team:activity',
                'tenant.settings:view',
                'tenant.settings:edit',
                'tenant.settings:danger',
                'tenant.billing:view',
                'tenant.billing:manage',
                'tenant.billing:invoices',
            ],
        ],
        'admin' => [
            'display_name' => 'Administrador',
            'description' => 'Gerencia projetos e equipe',
            'permissions' => [
                // Projects (all except delete)
                'tenant.projects:view',
                'tenant.projects:create',
                'tenant.projects:edit',
                'tenant.projects:upload',
                'tenant.projects:download',
                'tenant.projects:archive',
                // Team (all)
                'tenant.team:view',
                'tenant.team:invite',
                'tenant.team:remove',
                'tenant.team:manage-roles',
                'tenant.team:activity',
                // Settings (view and edit, no danger)
                'tenant.settings:view',
                'tenant.settings:edit',
                // No billing access
            ],
        ],
        'member' => [
            'display_name' => 'Membro',
            'description' => 'Cria e edita próprios projetos',
            'permissions' => [
                // Projects (view, create, edit-own, download)
                'tenant.projects:view',
                'tenant.projects:create',
                'tenant.projects:edit-own',
                'tenant.projects:download',
                // Team (view only)
                'tenant.team:view',
                // Settings (view only)
                'tenant.settings:view',
                // No billing access
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

            \DB::statement('SET FOREIGN_KEY_CHECKS=0;');
            \DB::table('model_has_permissions')->truncate();
            \DB::table('model_has_roles')->truncate();
            \DB::table('role_has_permissions')->truncate();
            \DB::table('permissions')->truncate();
            \DB::table('roles')->truncate();
            \DB::statement('SET FOREIGN_KEY_CHECKS=1;');

            $this->info('✅ Tables truncated successfully.');
            $this->newLine();
        }

        // Sync Permissions
        $this->syncPermissions();

        // Sync Roles
        $this->syncRoles();

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
     * Sync all permissions (insert or update)
     */
    protected function syncPermissions(): void
    {
        $this->info('📝 Syncing Permissions...');

        $created = 0;
        $updated = 0;

        foreach ($this->permissions as $permData) {
            $permission = Permission::updateOrCreate(
                ['name' => $permData['name'], 'guard_name' => 'web'],
                [
                    'description' => $permData['description'],
                    'category' => $permData['category'],
                ]
            );

            if ($permission->wasRecentlyCreated) {
                $created++;
                $this->line("  ✓ Created: {$permission->name}");
            } else {
                $updated++;
                $this->line("  ↻ Updated: {$permission->name}");
            }
        }

        $this->info("  ✅ {$created} permissions created, {$updated} updated.");
        $this->newLine();
    }

    /**
     * Sync all roles (insert or update)
     */
    protected function syncRoles(): void
    {
        $this->info('👥 Syncing Roles...');

        $created = 0;
        $updated = 0;

        foreach ($this->roles as $roleName => $roleData) {
            $role = Role::updateOrCreate(
                ['name' => $roleName, 'guard_name' => 'web'],
                [
                    'display_name' => $roleData['display_name'],
                    'description' => $roleData['description'],
                ]
            );

            if ($role->wasRecentlyCreated) {
                $created++;
                $this->line("  ✓ Created role: {$role->name} ({$role->display_name})");
            } else {
                $updated++;
                $this->line("  ↻ Updated role: {$role->name} ({$role->display_name})");
            }

            // Sync permissions (replaces all, doesn't duplicate)
            $role->syncPermissions($roleData['permissions']);
            $permCount = count($roleData['permissions']);
            $this->line("    → Synced {$permCount} permissions");
        }

        $this->info("  ✅ {$created} roles created, {$updated} updated.");
        $this->newLine();
    }

    /**
     * Display summary of current state
     */
    protected function displaySummary(): void
    {
        $this->info('📊 Summary:');
        $this->newLine();

        // Permissions by category
        $this->table(
            ['Category', 'Count'],
            [
                ['projects', Permission::where('category', 'projects')->count()],
                ['team', Permission::where('category', 'team')->count()],
                ['settings', Permission::where('category', 'settings')->count()],
                ['billing', Permission::where('category', 'billing')->count()],
                ['TOTAL', Permission::count()],
            ]
        );

        $this->newLine();

        // Roles with permission count
        $rolesData = [];
        foreach (Role::all() as $role) {
            $rolesData[] = [
                $role->name,
                $role->display_name ?? '-',
                $role->permissions()->count(),
            ];
        }

        $this->table(
            ['Role', 'Display Name', 'Permissions'],
            $rolesData
        );
    }
}
