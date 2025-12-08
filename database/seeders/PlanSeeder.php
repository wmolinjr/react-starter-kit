<?php

namespace Database\Seeders;

use App\Console\Commands\SyncPlanPermissions;
use App\Enums\PlanFeature;
use App\Models\Central\Plan;
use Illuminate\Database\Seeder;

/**
 * Plan Seeder
 *
 * Seeds initial Plans (Starter, Professional, Enterprise).
 * Run once during initial setup. After that, manage via Admin Panel.
 *
 * Usage:
 * - sail artisan db:seed --class=PlanSeeder
 *
 * Features and Limits are validated against PlanFeature and PlanLimit enums.
 */
class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('🔄 Seeding Plans...');

        foreach ($this->getPlans() as $planData) {
            $plan = Plan::updateOrCreate(
                ['slug' => $planData['slug']],
                $planData
            );

            // Generate permission_map from FeatureDefinition
            $plan->update([
                'permission_map' => SyncPlanPermissions::generatePermissionMap($plan),
            ]);

            $this->command->info("  ✓ {$plan->trans('name')} - {$plan->formatted_price}");
            $this->showPlanDetails($plan);
        }

        $this->command->info('✅ Plans seeded successfully!');
        $this->showSummary();
    }

    /**
     * Get plan definitions.
     * Uses enums to ensure type-safety and consistency.
     */
    protected function getPlans(): array
    {
        return [
            $this->starterPlan(),
            $this->professionalPlan(),
            $this->enterprisePlan(),
        ];
    }

    /**
     * Starter Plan - For individuals and small projects.
     */
    protected function starterPlan(): array
    {
        return [
            'name' => [
                'en' => 'Starter',
                'pt_BR' => 'Iniciante',
            ],
            'slug' => 'starter',
            'description' => [
                'en' => 'Perfect for individuals and small projects. Get started with essential features.',
                'pt_BR' => 'Perfeito para indivíduos e pequenos projetos. Comece com recursos essenciais.',
            ],
            'price' => 2900, // 29.00/month in configured currency
            'currency' => stripe_currency(),
            'billing_period' => 'monthly',
            'features' => $this->features([
                PlanFeature::BASE,
                PlanFeature::PROJECTS,
            ]),
            'limits' => $this->limits([
                'users' => 1,
                'projects' => 50,
                'storage' => 1024, // 1GB in MB
                'logRetention' => 30, // days
                'apiCalls' => 0, // no API access
                'fileUploadSize' => 10, // 10MB
                'customRoles' => 0, // no custom roles
                'locales' => 1, // single language
            ]),
            'is_active' => true,
            'is_featured' => false,
            'badge' => null,
            'icon' => 'Rocket',
            'icon_color' => 'slate',
            'sort_order' => 1,
        ];
    }

    /**
     * Professional Plan - For growing teams and businesses.
     */
    protected function professionalPlan(): array
    {
        return [
            'name' => [
                'en' => 'Professional',
                'pt_BR' => 'Profissional',
            ],
            'slug' => 'professional',
            'description' => [
                'en' => 'For growing teams and businesses. Unlock powerful collaboration tools.',
                'pt_BR' => 'Para equipes e empresas em crescimento. Desbloqueie ferramentas poderosas de colaboração.',
            ],
            'price' => 9900, // 99.00/month in configured currency
            'currency' => stripe_currency(),
            'billing_period' => 'monthly',
            'features' => $this->features([
                PlanFeature::BASE,
                PlanFeature::PROJECTS,
                PlanFeature::CUSTOM_ROLES,    // ⭐ Pro+
                PlanFeature::API_ACCESS,       // ⭐ Pro+
                PlanFeature::MULTI_LANGUAGE,   // ⭐ Pro+
            ]),
            'limits' => $this->limits([
                'users' => 50,
                'projects' => -1, // unlimited
                'storage' => 10240, // 10GB
                'logRetention' => 90,
                'apiCalls' => 10000, // 10k/month
                'fileUploadSize' => 50, // 50MB
                'customRoles' => 5, // up to 5 custom roles
                'locales' => 3, // up to 3 languages
            ]),
            'is_active' => true,
            'is_featured' => true, // Most popular
            'badge' => 'Most Popular',
            'icon' => 'Zap',
            'icon_color' => 'blue',
            'sort_order' => 2,
        ];
    }

    /**
     * Enterprise Plan - For large organizations.
     */
    protected function enterprisePlan(): array
    {
        return [
            'name' => [
                'en' => 'Enterprise',
                'pt_BR' => 'Empresarial',
            ],
            'slug' => 'enterprise',
            'description' => [
                'en' => 'For large organizations with advanced security and compliance needs.',
                'pt_BR' => 'Para grandes organizações com necessidades avançadas de segurança e conformidade.',
            ],
            'price' => 0, // Custom pricing
            'currency' => stripe_currency(),
            'billing_period' => 'monthly',
            'features' => $this->features([
                PlanFeature::BASE,
                PlanFeature::PROJECTS,
                PlanFeature::CUSTOM_ROLES,
                PlanFeature::API_ACCESS,
                PlanFeature::ADVANCED_REPORTS,  // ⭐ Enterprise
                PlanFeature::SSO,               // ⭐ Enterprise
                PlanFeature::WHITE_LABEL,       // ⭐ Enterprise
                PlanFeature::AUDIT_LOG,         // ⭐ Enterprise
                PlanFeature::PRIORITY_SUPPORT,  // ⭐ Enterprise
                PlanFeature::MULTI_LANGUAGE,
                PlanFeature::FEDERATION,        // ⭐ Enterprise
            ]),
            'limits' => $this->limits([
                'users' => -1, // unlimited
                'projects' => -1,
                'storage' => 102400, // 100GB
                'logRetention' => 365, // 1 year
                'apiCalls' => -1, // unlimited
                'fileUploadSize' => 100, // 100MB
                'customRoles' => -1, // unlimited
                'locales' => -1, // unlimited languages
            ]),
            'is_active' => true,
            'is_featured' => false,
            'badge' => 'Enterprise',
            'icon' => 'Building2',
            'icon_color' => 'purple',
            'sort_order' => 3,
        ];
    }

    /**
     * Build features array from enabled PlanFeature enums.
     * All features default to false, only passed features are true.
     *
     * @param  PlanFeature[]  $enabled
     */
    protected function features(array $enabled): array
    {
        $features = [];

        foreach (PlanFeature::cases() as $feature) {
            $features[$feature->value] = in_array($feature, $enabled, true);
        }

        return $features;
    }

    /**
     * Build limits array.
     * Simply passes through the values array.
     *
     * @param  array<string, int>  $values  Keys are limit names (users, projects, etc.)
     */
    protected function limits(array $values): array
    {
        return $values;
    }

    protected function showPlanDetails(Plan $plan): void
    {
        $featuresCount = count(array_filter($plan->features ?? []));
        $permissionsCount = count($plan->getAllEnabledPermissions());

        $this->command->line("    Features: {$featuresCount}");
        $this->command->line("    Permissions: {$permissionsCount}");
        $this->command->line('');
    }

    protected function showSummary(): void
    {
        $this->command->newLine();
        $this->command->table(
            ['Plan', 'Price', 'Features', 'Permissions', 'Users', 'Projects', 'Storage'],
            Plan::ordered()->get()->map(fn ($plan) => [
                $plan->trans('name'),
                $plan->formatted_price,
                count(array_filter($plan->features ?? [])),
                count($plan->getAllEnabledPermissions()),
                $plan->limits['users'] ?? 0,
                $plan->limits['projects'] ?? 0,
                ($plan->limits['storage'] ?? 0).'MB',
            ])
        );
    }
}
