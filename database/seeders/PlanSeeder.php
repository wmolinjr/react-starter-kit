<?php

namespace Database\Seeders;

use App\Console\Commands\SyncPlanPermissions;
use App\Enums\BadgePreset;
use App\Enums\BillingPeriod;
use App\Enums\PlanFeature;
use App\Enums\PlanLimit;
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

            $this->command->info("  ✓ {$plan->name} - {$plan->formatted_price}");
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
                'es' => 'Inicial',
            ],
            'slug' => 'starter',
            'description' => [
                'en' => 'Perfect for individuals and small projects. Get started with essential features.',
                'pt_BR' => 'Perfeito para indivíduos e pequenos projetos. Comece com recursos essenciais.',
                'es' => 'Perfecto para individuos y pequeños proyectos. Comienza con funciones esenciales.',
            ],
            'price' => 2900, // 29.00/month in configured currency
            'currency' => stripe_currency(),
            'billing_period' => BillingPeriod::MONTHLY->value,
            'features' => $this->features([
                PlanFeature::BASE,
                PlanFeature::PROJECTS,
            ]),
            'limits' => $this->limits([
                PlanLimit::USERS->value => 1,
                PlanLimit::PROJECTS->value => 50,
                PlanLimit::STORAGE->value => 1024, // 1GB in MB
                PlanLimit::LOG_RETENTION->value => 30, // days
                PlanLimit::API_CALLS->value => 0, // no API access
                PlanLimit::FILE_UPLOAD_SIZE->value => 10, // 10MB
                PlanLimit::CUSTOM_ROLES->value => 0, // no custom roles
                PlanLimit::LOCALES->value => 1, // single language
            ]),
            'is_active' => true,
            'is_featured' => false,
            'badge' => null,
            'icon' => BadgePreset::STARTER->icon(), // Uses Rocket icon from enum
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
                'es' => 'Profesional',
            ],
            'slug' => 'professional',
            'description' => [
                'en' => 'For growing teams and businesses. Unlock powerful collaboration tools.',
                'pt_BR' => 'Para equipes e empresas em crescimento. Desbloqueie ferramentas poderosas de colaboração.',
                'es' => 'Para equipos y negocios en crecimiento. Desbloquea potentes herramientas de colaboración.',
            ],
            'price' => 9900, // 99.00/month in configured currency
            'currency' => stripe_currency(),
            'billing_period' => BillingPeriod::MONTHLY->value,
            'features' => $this->features([
                PlanFeature::BASE,
                PlanFeature::PROJECTS,
                PlanFeature::CUSTOM_ROLES,    // ⭐ Pro+
                PlanFeature::API_ACCESS,       // ⭐ Pro+
                PlanFeature::MULTI_LANGUAGE,   // ⭐ Pro+
            ]),
            'limits' => $this->limits([
                PlanLimit::USERS->value => 50,
                PlanLimit::PROJECTS->value => -1, // unlimited
                PlanLimit::STORAGE->value => 10240, // 10GB
                PlanLimit::LOG_RETENTION->value => 90,
                PlanLimit::API_CALLS->value => 10000, // 10k/month
                PlanLimit::FILE_UPLOAD_SIZE->value => 50, // 50MB
                PlanLimit::CUSTOM_ROLES->value => 5, // up to 5 custom roles
                PlanLimit::LOCALES->value => 3, // up to 3 languages
            ]),
            'is_active' => true,
            'is_featured' => true, // Most popular
            'badge' => BadgePreset::MOST_POPULAR->value,
            'icon' => BadgePreset::PRO->icon(), // Uses Crown icon from enum
            'icon_color' => BadgePreset::MOST_POPULAR->color(), // amber
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
                'es' => 'Empresarial',
            ],
            'slug' => 'enterprise',
            'description' => [
                'en' => 'For large organizations with advanced security and compliance needs.',
                'pt_BR' => 'Para grandes organizações com necessidades avançadas de segurança e conformidade.',
                'es' => 'Para grandes organizaciones con necesidades avanzadas de seguridad y cumplimiento.',
            ],
            'price' => 0, // Custom pricing
            'currency' => stripe_currency(),
            'billing_period' => BillingPeriod::MONTHLY->value,
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
                PlanLimit::USERS->value => -1, // unlimited
                PlanLimit::PROJECTS->value => -1,
                PlanLimit::STORAGE->value => 102400, // 100GB
                PlanLimit::LOG_RETENTION->value => 365, // 1 year
                PlanLimit::API_CALLS->value => -1, // unlimited
                PlanLimit::FILE_UPLOAD_SIZE->value => 100, // 100MB
                PlanLimit::CUSTOM_ROLES->value => -1, // unlimited
                PlanLimit::LOCALES->value => -1, // unlimited languages
            ]),
            'is_active' => true,
            'is_featured' => false,
            'badge' => BadgePreset::ENTERPRISE->value,
            'icon' => BadgePreset::ENTERPRISE->icon(), // Building2 from enum
            'icon_color' => BadgePreset::ENTERPRISE->color(), // purple
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
                $plan->name,
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
