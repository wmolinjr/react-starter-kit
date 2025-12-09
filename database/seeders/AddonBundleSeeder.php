<?php

namespace Database\Seeders;

use App\Enums\BadgePreset;
use App\Models\Central\Addon;
use App\Models\Central\AddonBundle;
use App\Models\Central\Plan;
use Illuminate\Database\Seeder;

/**
 * Addon Bundle Seeder
 *
 * Defines bundles (packages) of addons that can be purchased together
 * with a discount. Bundles are created from existing addons defined
 * in AddonSeeder.
 *
 * Run after AddonSeeder: sail artisan db:seed --class=AddonBundleSeeder
 */
class AddonBundleSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->getBundles() as $slug => $data) {
            $bundle = $this->createBundle($slug, $data);
            $this->attachAddons($bundle, $data['addons'] ?? []);
            $this->attachPlans($bundle, $data['available_for_plans'] ?? []);
        }
    }

    /**
     * Bundle definitions
     */
    protected function getBundles(): array
    {
        return [
            /*
            |------------------------------------------------------------------
            | Power Pack - Storage + Users + Reports
            |------------------------------------------------------------------
            */
            'power_pack' => [
                'name' => [
                    'en' => 'Power Pack',
                    'pt_BR' => 'Pacote Power',
                    'es' => 'Paquete Power',
                ],
                'description' => [
                    'en' => 'Everything you need to scale: extra storage, users, and advanced reporting',
                    'pt_BR' => 'Tudo que você precisa para escalar: armazenamento extra, usuários e relatórios avançados',
                    'es' => 'Todo lo que necesitas para escalar: almacenamiento extra, usuarios e informes avanzados',
                ],
                'discount_percent' => 20,
                'badge' => BadgePreset::MOST_POPULAR->value,
                'icon' => BadgePreset::MOST_POPULAR->icon(), // Star from enum
                'icon_color' => BadgePreset::MOST_POPULAR->color(), // amber from enum
                'features' => [
                    ['en' => '50GB extra storage', 'pt_BR' => '50GB de armazenamento extra', 'es' => '50GB de almacenamiento extra'],
                    ['en' => '5 additional user seats', 'pt_BR' => '5 vagas de usuários adicionais', 'es' => '5 puestos de usuario adicionales'],
                    ['en' => 'Advanced reporting & analytics', 'pt_BR' => 'Relatórios e análises avançadas', 'es' => 'Informes y análisis avanzados'],
                    ['en' => 'Priority email support', 'pt_BR' => 'Suporte prioritário por e-mail', 'es' => 'Soporte prioritario por correo'],
                ],
                'addons' => [
                    ['slug' => 'storage_50gb', 'quantity' => 1],
                    ['slug' => 'extra_users_5', 'quantity' => 1],
                    ['slug' => 'advanced_reports', 'quantity' => 1],
                ],
                'available_for_plans' => ['professional'],
            ],

            /*
            |------------------------------------------------------------------
            | Team Starter - Users + Custom Roles
            |------------------------------------------------------------------
            */
            'team_starter' => [
                'name' => [
                    'en' => 'Team Starter',
                    'pt_BR' => 'Equipe Iniciante',
                    'es' => 'Equipo Inicial',
                ],
                'description' => [
                    'en' => 'Perfect for growing teams: add users and customize their permissions',
                    'pt_BR' => 'Perfeito para equipes em crescimento: adicione usuários e personalize suas permissões',
                    'es' => 'Perfecto para equipos en crecimiento: añade usuarios y personaliza sus permisos',
                ],
                'discount_percent' => 15,
                'badge' => BadgePreset::BEST_FOR_TEAMS->value,
                'icon' => BadgePreset::BEST_FOR_TEAMS->icon(), // Users from enum
                'icon_color' => BadgePreset::BEST_FOR_TEAMS->color(), // blue from enum
                'features' => [
                    ['en' => '5 additional user seats', 'pt_BR' => '5 vagas de usuários adicionais', 'es' => '5 puestos de usuario adicionales'],
                    ['en' => 'Custom roles & permissions', 'pt_BR' => 'Papéis e permissões personalizados', 'es' => 'Roles y permisos personalizados'],
                    ['en' => 'Team activity dashboard', 'pt_BR' => 'Painel de atividades da equipe', 'es' => 'Panel de actividad del equipo'],
                ],
                'addons' => [
                    ['slug' => 'extra_users_5', 'quantity' => 1],
                    ['slug' => 'custom_roles', 'quantity' => 1],
                ],
                'available_for_plans' => ['starter'],
            ],

            /*
            |------------------------------------------------------------------
            | Storage Pro - Large storage bundle
            |------------------------------------------------------------------
            */
            'storage_pro' => [
                'name' => [
                    'en' => 'Storage Pro',
                    'pt_BR' => 'Armazenamento Pro',
                    'es' => 'Almacenamiento Pro',
                ],
                'description' => [
                    'en' => 'Maximum storage capacity for data-heavy workloads',
                    'pt_BR' => 'Capacidade máxima de armazenamento para cargas pesadas de dados',
                    'es' => 'Capacidad máxima de almacenamiento para cargas de datos pesadas',
                ],
                'discount_percent' => 25,
                'badge' => BadgePreset::BEST_VALUE->value,
                'icon' => BadgePreset::BEST_VALUE->icon(), // Trophy from enum
                'icon_color' => BadgePreset::BEST_VALUE->color(), // green from enum
                'features' => [
                    ['en' => '300GB total storage (50GB + 250GB)', 'pt_BR' => '300GB de armazenamento total (50GB + 250GB)', 'es' => '300GB de almacenamiento total (50GB + 250GB)'],
                    ['en' => 'High-performance SSD', 'pt_BR' => 'SSD de alta performance', 'es' => 'SSD de alto rendimiento'],
                    ['en' => 'Automatic backups', 'pt_BR' => 'Backups automáticos', 'es' => 'Copias de seguridad automáticas'],
                    ['en' => '99.9% uptime SLA', 'pt_BR' => 'SLA de 99.9% de uptime', 'es' => 'SLA de 99.9% de disponibilidad'],
                ],
                'addons' => [
                    ['slug' => 'storage_50gb', 'quantity' => 1],
                    ['slug' => 'storage_250gb', 'quantity' => 1],
                ],
                'available_for_plans' => ['professional', 'enterprise'],
            ],

            /*
            |------------------------------------------------------------------
            | Enterprise Essentials - Everything
            |------------------------------------------------------------------
            */
            'enterprise_essentials' => [
                'name' => [
                    'en' => 'Enterprise Essentials',
                    'pt_BR' => 'Essenciais Enterprise',
                    'es' => 'Esenciales Empresarial',
                ],
                'description' => [
                    'en' => 'Complete package for enterprise needs: storage, team, and all features',
                    'pt_BR' => 'Pacote completo para necessidades enterprise: armazenamento, equipe e todos os recursos',
                    'es' => 'Paquete completo para necesidades empresariales: almacenamiento, equipo y todas las funciones',
                ],
                'discount_percent' => 30,
                'badge' => BadgePreset::ENTERPRISE->value,
                'icon' => BadgePreset::ENTERPRISE->icon(), // Building2 from enum
                'icon_color' => BadgePreset::ENTERPRISE->color(), // purple from enum
                'features' => [
                    ['en' => '250GB enterprise storage', 'pt_BR' => '250GB de armazenamento enterprise', 'es' => '250GB de almacenamiento empresarial'],
                    ['en' => '10 user seats (2x 5-seat packs)', 'pt_BR' => '10 vagas de usuários (2x pacotes de 5)', 'es' => '10 puestos de usuario (2x paquetes de 5)'],
                    ['en' => 'Advanced reports & analytics', 'pt_BR' => 'Relatórios e análises avançadas', 'es' => 'Informes y análisis avanzados'],
                    ['en' => 'Dedicated support', 'pt_BR' => 'Suporte dedicado', 'es' => 'Soporte dedicado'],
                ],
                'addons' => [
                    ['slug' => 'storage_250gb', 'quantity' => 1],
                    ['slug' => 'extra_users_5', 'quantity' => 2], // 10 users total
                    ['slug' => 'advanced_reports', 'quantity' => 1],
                ],
                'available_for_plans' => ['professional', 'enterprise'],
            ],
        ];
    }

    /**
     * Create or update a bundle
     */
    protected function createBundle(string $slug, array $data): AddonBundle
    {
        return AddonBundle::updateOrCreate(
            ['slug' => $slug],
            [
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'active' => true,
                'discount_percent' => $data['discount_percent'] ?? 0,
                'price_monthly' => $data['price_monthly'] ?? null,
                'price_yearly' => $data['price_yearly'] ?? null,
                'currency' => stripe_currency(),
                'badge' => $data['badge'] ?? null,
                'icon' => $data['icon'] ?? 'Package',
                'icon_color' => $data['icon_color'] ?? 'slate',
                'features' => $data['features'] ?? null,
                'sort_order' => $data['sort_order'] ?? 0,
                'metadata' => $data['metadata'] ?? null,
            ]
        );
    }

    /**
     * Attach addons to bundle
     */
    protected function attachAddons(AddonBundle $bundle, array $addonConfigs): void
    {
        $attachments = [];
        $sortOrder = 0;

        foreach ($addonConfigs as $config) {
            $addon = Addon::where('slug', $config['slug'])->first();

            if (! $addon) {
                if ($this->command) {
                    $this->command->warn("Addon not found: {$config['slug']}");
                }

                continue;
            }

            $attachments[$addon->id] = [
                'quantity' => $config['quantity'] ?? 1,
                'billing_period' => $config['billing_period'] ?? null,
                'sort_order' => $sortOrder++,
            ];
        }

        $bundle->addons()->sync($attachments);
    }

    /**
     * Attach plans to bundle
     */
    protected function attachPlans(AddonBundle $bundle, array $planSlugs): void
    {
        if (empty($planSlugs)) {
            return;
        }

        $plans = Plan::whereIn('slug', $planSlugs)->get();

        foreach ($plans as $plan) {
            $bundle->plans()->syncWithoutDetaching([
                $plan->id => ['active' => true],
            ]);
        }
    }
}
