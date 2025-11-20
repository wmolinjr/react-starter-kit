<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * IMPORTANTE: Este seeder cria tenants e sincroniza permissões automaticamente!
     *
     * Ordem de execução:
     * 1. sail artisan migrate:fresh
     * 2. sail artisan db:seed
     *
     * Ou use o comando setup que faz tudo:
     * - composer setup
     *
     * Ordem interna:
     * 1. Sync Super Admin role (globally)
     * 2. Criar tenants
     * 3. Chamar permissions:sync para cada tenant
     * 4. Atribuir roles aos usuários
     */
    public function run(): void
    {
        // Sync Super Admin role first (needed for TenantSeeder)
        $this->command->info('🔄 Syncing Super Admin role...');
        Artisan::call('permissions:sync');
        $this->command->info('✅ Super Admin role synced!');
        $this->command->newLine();

        // Seed tenants and assign roles
        // (permissions:sync is called within each tenant context for tenant roles)
        $this->call([
            TenantSeeder::class,
        ]);
    }
}
