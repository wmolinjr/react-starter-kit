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
     * O seeder TenantSeeder irá:
     * - Criar tenants (acme e startup)
     * - Chamar permissions:sync para cada tenant
     * - Atribuir role owner aos usuários
     */
    public function run(): void
    {
        // Seed tenants and assign roles
        // (permissions:sync is called within each tenant context)
        $this->call([
            TenantSeeder::class,
        ]);
    }
}
