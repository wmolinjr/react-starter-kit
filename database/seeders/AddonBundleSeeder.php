<?php

namespace Database\Seeders;

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
                ],
                'description' => [
                    'en' => 'Everything you need to scale: extra storage, users, and advanced reporting',
                    'pt_BR' => 'Tudo que você precisa para escalar: armazenamento extra, usuários e relatórios avançados',
                ],
                'discount_percent' => 20,
                'badge' => 'most_popular', // BadgePreset value
                'icon' => 'Zap',
                'icon_color' => 'amber',
                'features' => [
                    ['en' => '50GB extra storage', 'pt_BR' => '50GB de armazenamento extra'],
                    ['en' => '5 additional user seats', 'pt_BR' => '5 vagas de usuários adicionais'],
                    ['en' => 'Advanced reporting & analytics', 'pt_BR' => 'Relatórios e análises avançadas'],
                    ['en' => 'Priority email support', 'pt_BR' => 'Suporte prioritário por e-mail'],
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
                ],
                'description' => [
                    'en' => 'Perfect for growing teams: add users and customize their permissions',
                    'pt_BR' => 'Perfeito para equipes em crescimento: adicione usuários e personalize suas permissões',
                ],
                'discount_percent' => 15,
                'badge' => 'best_for_teams', // BadgePreset value
                'icon' => 'Users',
                'icon_color' => 'blue',
                'features' => [
                    ['en' => '5 additional user seats', 'pt_BR' => '5 vagas de usuários adicionais'],
                    ['en' => 'Custom roles & permissions', 'pt_BR' => 'Papéis e permissões personalizados'],
                    ['en' => 'Team activity dashboard', 'pt_BR' => 'Painel de atividades da equipe'],
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
                ],
                'description' => [
                    'en' => 'Maximum storage capacity for data-heavy workloads',
                    'pt_BR' => 'Capacidade máxima de armazenamento para cargas pesadas de dados',
                ],
                'discount_percent' => 25,
                'badge' => 'best_value', // BadgePreset value
                'icon' => 'HardDrive',
                'icon_color' => 'green',
                'features' => [
                    ['en' => '300GB total storage (50GB + 250GB)', 'pt_BR' => '300GB de armazenamento total (50GB + 250GB)'],
                    ['en' => 'High-performance SSD', 'pt_BR' => 'SSD de alta performance'],
                    ['en' => 'Automatic backups', 'pt_BR' => 'Backups automáticos'],
                    ['en' => '99.9% uptime SLA', 'pt_BR' => 'SLA de 99.9% de uptime'],
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
                ],
                'description' => [
                    'en' => 'Complete package for enterprise needs: storage, team, and all features',
                    'pt_BR' => 'Pacote completo para necessidades enterprise: armazenamento, equipe e todos os recursos',
                ],
                'discount_percent' => 30,
                'badge' => 'enterprise', // BadgePreset value
                'icon' => 'Building2',
                'icon_color' => 'purple',
                'features' => [
                    ['en' => '250GB enterprise storage', 'pt_BR' => '250GB de armazenamento enterprise'],
                    ['en' => '10 user seats (2x 5-seat packs)', 'pt_BR' => '10 vagas de usuários (2x pacotes de 5)'],
                    ['en' => 'Advanced reports & analytics', 'pt_BR' => 'Relatórios e análises avançadas'],
                    ['en' => 'Dedicated support', 'pt_BR' => 'Suporte dedicado'],
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
