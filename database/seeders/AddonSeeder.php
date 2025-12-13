<?php

namespace Database\Seeders;

use App\Enums\AddonType;
use App\Enums\BadgePreset;
use App\Enums\PlanLimit;
use App\Models\Central\Addon;
use App\Models\Central\Plan;
use Illuminate\Database\Seeder;

/**
 * Addon Seeder - Single Source of Truth for Addon Catalog
 *
 * All addon definitions are maintained here. The database is the runtime source of truth.
 * To add/modify addons: update this seeder and run `sail artisan db:seed --class=AddonSeeder`
 *
 * Provider IDs (Stripe, Asaas, etc.) are NOT set here - use provider sync commands
 * to create products/prices and automatically store the IDs in the database.
 * Example: `sail artisan stripe:sync --addons`
 *
 * AddonTypes:
 * - QUOTA: Increases plan limits (storage, users, projects)
 * - FEATURE: Unlocks features (advanced_reports, custom_roles)
 * - METERED: Usage-based billing (bandwidth overage, storage overage)
 * - CREDIT: One-time purchase with validity (storage credits)
 */
class AddonSeeder extends Seeder
{
    /**
     * Seed the addon catalog.
     */
    public function run(): void
    {
        foreach ($this->getAddons() as $slug => $data) {
            $addon = $this->createAddon($slug, $data);
            $this->attachToPlans($addon, $data['available_for_plans'] ?? []);
        }
    }

    /**
     * Addon catalog definitions.
     *
     * @return array<string, array>
     */
    protected function getAddons(): array
    {
        return [
            /*
            |------------------------------------------------------------------
            | QUOTA Add-ons (Increase plan limits)
            |------------------------------------------------------------------
            */
            'storage_50gb' => [
                'name' => [
                    'en' => 'Storage 50GB',
                    'pt_BR' => 'Armazenamento 50GB',
                    'es' => 'Almacenamiento 50GB',
                ],
                'description' => [
                    'en' => 'Add 50GB of additional storage to your plan',
                    'pt_BR' => 'Adicione 50GB de armazenamento extra ao seu plano',
                    'es' => 'Añade 50GB de almacenamiento adicional a tu plan',
                ],
                'type' => AddonType::QUOTA,
                'limit_key' => PlanLimit::STORAGE->value, // Which plan limit to increase
                'unit_value' => 50000, // 50GB in MB
                'unit_label' => ['en' => 'GB', 'pt_BR' => 'GB', 'es' => 'GB'],
                'price_monthly' => 4900, // $49/month
                'price_yearly' => 49000, // $490/year
                'available_for_plans' => ['starter', 'professional'],
                'min_quantity' => 1,
                'max_quantity' => 20,
                'icon' => PlanLimit::STORAGE->icon(), // HardDrive from enum
                'icon_color' => null, // System color
                'badge' => BadgePreset::MOST_POPULAR->value,
                'sort_order' => 1,
                'features' => [
                    'High-performance SSD storage',
                    'Automatic backups included',
                    '99.9% uptime SLA',
                ],
            ],

            'storage_250gb' => [
                'name' => [
                    'en' => 'Storage 250GB',
                    'pt_BR' => 'Armazenamento 250GB',
                    'es' => 'Almacenamiento 250GB',
                ],
                'description' => [
                    'en' => 'Add 250GB of additional storage (best value)',
                    'pt_BR' => 'Adicione 250GB de armazenamento extra (melhor custo-benefício)',
                    'es' => 'Añade 250GB de almacenamiento adicional (mejor valor)',
                ],
                'type' => AddonType::QUOTA,
                'limit_key' => PlanLimit::STORAGE->value,
                'unit_value' => 250000, // 250GB in MB
                'unit_label' => ['en' => 'GB', 'pt_BR' => 'GB', 'es' => 'GB'],
                'price_monthly' => 19900, // $199/month
                'price_yearly' => 199000, // $1990/year
                'available_for_plans' => ['professional', 'enterprise'],
                'min_quantity' => 1,
                'max_quantity' => 10,
                'icon' => 'Database',
                'icon_color' => PlanLimit::STORAGE->color(), // purple from enum
                'badge' => BadgePreset::BEST_VALUE->value,
                'sort_order' => 2,
            ],

            'extra_users_5' => [
                'name' => [
                    'en' => 'Extra Users (5 seats)',
                    'pt_BR' => 'Usuários Extras (5 vagas)',
                    'es' => 'Usuarios Extra (5 puestos)',
                ],
                'description' => [
                    'en' => 'Add 5 additional user seats to your team',
                    'pt_BR' => 'Adicione 5 vagas de usuários extras à sua equipe',
                    'es' => 'Añade 5 puestos de usuario adicionales a tu equipo',
                ],
                'type' => AddonType::QUOTA,
                'limit_key' => PlanLimit::USERS->value,
                'unit_value' => 5, // 5 users per unit
                'unit_label' => PlanLimit::USERS->unitLabel(), // ['en' => 'users', 'pt_BR' => 'usuários', 'es' => 'usuarios']
                'price_monthly' => 4900, // $49/month for 5 users
                'price_yearly' => 49000, // $490/year
                'available_for_plans' => ['starter', 'professional'],
                'min_quantity' => 1,
                'max_quantity' => 100,
                'icon' => PlanLimit::USERS->icon(), // Users from enum
                'icon_color' => PlanLimit::USERS->color(), // blue from enum
                'sort_order' => 3,
            ],

            /*
            |------------------------------------------------------------------
            | FEATURE Add-ons (Unlock features)
            |------------------------------------------------------------------
            */
            'advanced_reports' => [
                'name' => [
                    'en' => 'Advanced Reports',
                    'pt_BR' => 'Relatórios Avançados',
                    'es' => 'Informes Avanzados',
                ],
                'description' => [
                    'en' => 'Unlock advanced reporting and analytics features',
                    'pt_BR' => 'Desbloqueie recursos avançados de relatórios e análises',
                    'es' => 'Desbloquea funciones avanzadas de informes y análisis',
                ],
                'type' => AddonType::FEATURE,
                'price_monthly' => 2900, // $29/month
                'price_yearly' => 29000, // $290/year
                'available_for_plans' => ['professional'],
                'min_quantity' => 1,
                'max_quantity' => 1,
                'icon' => 'BarChart3',
                'icon_color' => 'emerald',
                'badge' => BadgePreset::PRO->value,
                'sort_order' => 10,
                'features' => [
                    'Custom report builder',
                    'Scheduled reports',
                    'Export to PDF/Excel',
                    'API access',
                ],
                'metadata' => [
                    'feature_key' => 'advancedReports',
                ],
            ],

            'custom_roles' => [
                'name' => [
                    'en' => 'Custom Roles',
                    'pt_BR' => 'Papéis Personalizados',
                    'es' => 'Roles Personalizados',
                ],
                'description' => [
                    'en' => 'Create custom roles and permissions',
                    'pt_BR' => 'Crie papéis e permissões personalizados',
                    'es' => 'Crea roles y permisos personalizados',
                ],
                'type' => AddonType::FEATURE,
                'price_monthly' => 1900, // $19/month
                'available_for_plans' => ['starter'],
                'min_quantity' => 1,
                'max_quantity' => 1,
                'icon' => PlanLimit::CUSTOM_ROLES->icon(), // Shield from enum
                'icon_color' => PlanLimit::CUSTOM_ROLES->color(), // red from enum
                'sort_order' => 11,
                'metadata' => [
                    'feature_key' => 'customRoles',
                ],
            ],

            /*
            |------------------------------------------------------------------
            | CREDIT Add-ons (One-time purchase with validity)
            |------------------------------------------------------------------
            */
            'storage_credit_100gb' => [
                'name' => [
                    'en' => 'Storage Credit 100GB',
                    'pt_BR' => 'Crédito de Armazenamento 100GB',
                    'es' => 'Crédito de Almacenamiento 100GB',
                ],
                'description' => [
                    'en' => 'One-time purchase of 100GB storage (valid for 1 year)',
                    'pt_BR' => 'Compra única de 100GB de armazenamento (válido por 1 ano)',
                    'es' => 'Compra única de 100GB de almacenamiento (válido por 1 año)',
                ],
                'type' => AddonType::CREDIT,
                'limit_key' => PlanLimit::STORAGE->value,
                'unit_value' => 100000, // 100GB in MB
                'unit_label' => ['en' => 'GB', 'pt_BR' => 'GB', 'es' => 'GB'],
                'price_one_time' => 7900, // $79 one-time
                'validity_months' => 12,
                'available_for_plans' => ['starter', 'professional', 'enterprise'],
                'min_quantity' => 1,
                'max_quantity' => 50,
                'icon' => AddonType::CREDIT->icon(), // CreditCard from enum
                'icon_color' => AddonType::CREDIT->color(), // green from enum
                'badge' => BadgePreset::ONE_TIME->value,
                'sort_order' => 20,
            ],

            /*
            |------------------------------------------------------------------
            | METERED Add-ons (Usage-based billing)
            |------------------------------------------------------------------
            */
            'storage_overage' => [
                'name' => [
                    'en' => 'Storage Overage',
                    'pt_BR' => 'Excedente de Armazenamento',
                    'es' => 'Excedente de Almacenamiento',
                ],
                'description' => [
                    'en' => 'Pay-as-you-go for storage exceeding plan limits',
                    'pt_BR' => 'Pague conforme o uso para armazenamento além dos limites do plano',
                    'es' => 'Paga por uso para almacenamiento que exceda los límites del plan',
                ],
                'type' => AddonType::METERED,
                'unit_label' => ['en' => 'GB', 'pt_BR' => 'GB', 'es' => 'GB'],
                'price_metered' => 10, // $0.10/GB
                'free_tier' => 0, // No free tier (already in plan)
                'available_for_plans' => ['starter', 'professional', 'enterprise'],
                'icon' => AddonType::METERED->icon(), // Activity from enum
                'icon_color' => AddonType::METERED->color(), // orange from enum
                'sort_order' => 30,
                'metadata' => [
                    'unit_price_display' => '$0.10 per GB',
                    'auto_enabled' => true,
                ],
            ],

            'bandwidth_overage' => [
                'name' => [
                    'en' => 'Bandwidth Overage',
                    'pt_BR' => 'Excedente de Banda',
                    'es' => 'Excedente de Ancho de Banda',
                ],
                'description' => [
                    'en' => 'Pay-as-you-go for bandwidth exceeding plan limits',
                    'pt_BR' => 'Pague conforme o uso para banda além dos limites do plano',
                    'es' => 'Paga por uso para ancho de banda que exceda los límites del plan',
                ],
                'type' => AddonType::METERED,
                'unit_label' => ['en' => 'GB', 'pt_BR' => 'GB', 'es' => 'GB'],
                'price_metered' => 5, // $0.05/GB
                'free_tier' => 100000, // First 100GB free (in MB)
                'available_for_plans' => ['starter', 'professional', 'enterprise'],
                'icon' => 'Wifi',
                'icon_color' => 'cyan',
                'sort_order' => 31,
                'metadata' => [
                    'unit_price_display' => '$0.05 per GB',
                    'auto_enabled' => true,
                ],
            ],
        ];
    }

    /**
     * Create or update an addon in the database.
     *
     * Note: Provider IDs (provider_product_ids, provider_price_ids, provider_meter_ids)
     * are NOT set here. They are populated by provider sync commands after creating
     * products/prices in the payment provider (e.g., Stripe, Asaas).
     */
    protected function createAddon(string $slug, array $data): Addon
    {
        $addonType = $data['type'];

        return Addon::updateOrCreate(
            ['slug' => $slug],
            [
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'type' => $addonType->value,
                'active' => true,
                'sort_order' => $data['sort_order'] ?? 0,
                'limit_key' => $data['limit_key'] ?? null,
                'unit_value' => $data['unit_value'] ?? null,
                'unit_label' => $data['unit_label'] ?? $addonType->unitLabel(),
                'min_quantity' => $data['min_quantity'] ?? 1,
                'max_quantity' => $data['max_quantity'] ?? null,
                'stackable' => $addonType->isStackable(),
                'price_monthly' => $data['price_monthly'] ?? null,
                'price_yearly' => $data['price_yearly'] ?? null,
                'price_one_time' => $data['price_one_time'] ?? null,
                'price_metered' => $data['price_metered'] ?? null,
                'currency' => stripe_currency(),
                'free_tier' => $data['free_tier'] ?? null,
                'validity_months' => $data['validity_months'] ?? null,
                // Provider IDs are NOT set here - use provider sync commands
                'features' => $data['features'] ?? null,
                'icon' => $data['icon'] ?? null,
                'icon_color' => $data['icon_color'] ?? null,
                'badge' => $data['badge'] ?? null,
                'metadata' => $data['metadata'] ?? null,
            ]
        );
    }

    /**
     * Attach addon to plans.
     */
    protected function attachToPlans(Addon $addon, array $planSlugs): void
    {
        if (empty($planSlugs)) {
            return;
        }

        $plans = Plan::whereIn('slug', $planSlugs)->get();

        foreach ($plans as $plan) {
            $addon->plans()->syncWithoutDetaching([
                $plan->id => ['active' => true],
            ]);
        }
    }
}
