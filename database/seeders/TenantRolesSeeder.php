<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

class TenantRolesSeeder extends Seeder
{
    /**
     * Seed default roles and permissions for all tenants.
     *
     * Creates 3 roles per tenant: owner, admin, member
     * Plus basic permissions for resource management
     */
    public function run(): void
    {
        // Para cada tenant existente, criar roles e permissions
        Tenant::all()->each(function (Tenant $tenant) {
            // Inicializar contexto do tenant
            tenancy()->initialize($tenant);

            $this->createRolesAndPermissions();

            // Limpar contexto
            tenancy()->end();
        });
    }

    /**
     * Create roles and permissions for current tenant
     */
    protected function createRolesAndPermissions(): void
    {
        // Criar permissions básicas
        $permissions = [
            // Project permissions
            'view projects',
            'create projects',
            'edit projects',
            'delete projects',

            // Team permissions
            'view team',
            'invite members',
            'remove members',
            'manage roles',

            // Settings permissions
            'view settings',
            'edit settings',
            'manage billing',
        ];

        foreach ($permissions as $permissionName) {
            Permission::findOrCreate($permissionName, 'web');
        }

        // Criar role "owner" com todas as permissões
        $ownerRole = Role::findOrCreate('owner', 'web');
        $ownerRole->givePermissionTo(Permission::all());

        // Criar role "admin" com permissões de gerenciamento (exceto billing)
        $adminRole = Role::findOrCreate('admin', 'web');
        $adminRole->givePermissionTo([
            'view projects',
            'create projects',
            'edit projects',
            'delete projects',
            'view team',
            'invite members',
            'remove members',
            'view settings',
            'edit settings',
        ]);

        // Criar role "member" com permissões básicas
        $memberRole = Role::findOrCreate('member', 'web');
        $memberRole->givePermissionTo([
            'view projects',
            'create projects',
            'edit projects',
            'view team',
            'view settings',
        ]);

        $this->command->info('  ✓ Created roles and permissions for tenant: '.tenant('name'));
    }
}
