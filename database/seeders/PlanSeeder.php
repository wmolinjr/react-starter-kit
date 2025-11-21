<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    /**
     * Complete plan definitions with permission mappings
     */
    protected array $plans = [
        [
            'name' => 'Starter',
            'slug' => 'starter',
            'description' => 'Perfect for individuals and small projects. Get started with essential features.',
            'price' => 2900, // $29.00/month
            'currency' => 'USD',
            'billing_period' => 'monthly',

            // Features enabled
            'features' => [
                'projects' => true,
                'customRoles' => false,
                'apiAccess' => false,
                'advancedReports' => false,
                'sso' => false,
                'whiteLabel' => false,
            ],

            // Resource limits
            'limits' => [
                'users' => 1,
                'projects' => 50,
                'storage' => 1024, // 1GB in MB
                'logRetention' => 30, // days
                'apiCalls' => 0, // no API access
            ],

            // Permission mapping
            // Features enable specific permissions
            'permission_map' => [
                // Everyone gets basic project permissions
                'projects' => [
                    'tenant.projects:view',
                    'tenant.projects:create',
                    'tenant.projects:editOwn',
                    'tenant.projects:download',
                    'tenant.team:view',
                    'tenant.settings:view',
                ],
            ],

            'is_active' => true,
            'is_featured' => false,
            'sort_order' => 1,
        ],

        [
            'name' => 'Professional',
            'slug' => 'professional',
            'description' => 'For growing teams and businesses. Unlock powerful collaboration tools.',
            'price' => 9900, // $99.00/month
            'currency' => 'USD',
            'billing_period' => 'monthly',

            'features' => [
                'projects' => true,
                'customRoles' => true,      // ⭐ NEW
                'apiAccess' => true,        // ⭐ NEW
                'advancedReports' => false,
                'sso' => false,
                'whiteLabel' => false,
            ],

            'limits' => [
                'users' => 50,
                'projects' => -1, // unlimited
                'storage' => 10240, // 10GB
                'logRetention' => 90,
                'apiCalls' => 10000, // 10k/month
            ],

            'permission_map' => [
                // Base permissions (inherited)
                'projects' => [
                    'tenant.projects:view',
                    'tenant.projects:create',
                    'tenant.projects:edit',      // ⭐ Full edit (not just own)
                    'tenant.projects:editOwn',
                    'tenant.projects:delete',    // ⭐ NEW
                    'tenant.projects:upload',    // ⭐ NEW
                    'tenant.projects:download',
                    'tenant.projects:archive',   // ⭐ NEW
                    'tenant.team:view',
                    'tenant.team:invite',        // ⭐ NEW
                    'tenant.team:remove',        // ⭐ NEW
                    'tenant.team:manageRoles',   // ⭐ NEW
                    'tenant.team:activity',      // ⭐ NEW
                    'tenant.settings:view',
                    'tenant.settings:edit',      // ⭐ NEW
                ],

                // Custom Roles (Pro+)
                'customRoles' => [
                    'tenant.roles:view',
                    'tenant.roles:create',
                    'tenant.roles:edit',
                    'tenant.roles:delete',
                ],

                // API Access (Pro+)
                'apiAccess' => [
                    'tenant.apiTokens:view',
                    'tenant.apiTokens:create',
                    'tenant.apiTokens:delete',
                ],
            ],

            'is_active' => true,
            'is_featured' => true, // Most popular
            'sort_order' => 2,
        ],

        [
            'name' => 'Enterprise',
            'slug' => 'enterprise',
            'description' => 'For large organizations with advanced security and compliance needs.',
            'price' => 0, // Custom pricing
            'currency' => 'USD',
            'billing_period' => 'monthly',

            'features' => [
                'projects' => true,
                'customRoles' => true,
                'apiAccess' => true,
                'advancedReports' => true,  // ⭐ NEW
                'sso' => true,              // ⭐ NEW
                'whiteLabel' => true,       // ⭐ NEW
            ],

            'limits' => [
                'users' => -1, // unlimited
                'projects' => -1,
                'storage' => 102400, // 100GB
                'logRetention' => 365, // 1 year
                'apiCalls' => -1, // unlimited
            ],

            'permission_map' => [
                // All Professional permissions +
                'projects' => [
                    'tenant.projects:*', // Wildcard: all project permissions
                    'tenant.team:*',
                    'tenant.settings:*',
                ],

                'customRoles' => [
                    'tenant.roles:*',
                ],

                'apiAccess' => [
                    'tenant.apiTokens:*',
                ],

                // Advanced Reports (Enterprise only)
                'advancedReports' => [
                    'tenant.reports:view',
                    'tenant.reports:export',
                    'tenant.reports:schedule',
                    'tenant.reports:customize',
                ],

                // SSO (Enterprise only)
                'sso' => [
                    'tenant.sso:configure',
                    'tenant.sso:manage',
                    'tenant.sso:testConnection',
                ],

                // White Label (Enterprise only)
                'whiteLabel' => [
                    'tenant.branding:view',
                    'tenant.branding:edit',
                    'tenant.branding:preview',
                    'tenant.branding:publish',
                ],

                // Billing (Enterprise gets full control)
                'billing' => [
                    'tenant.billing:view',
                    'tenant.billing:manage',
                    'tenant.billing:invoices',
                ],
            ],

            'is_active' => true,
            'is_featured' => false,
            'sort_order' => 3,
        ],
    ];

    public function run(): void
    {
        $this->command->info('🔄 Seeding Plans...');

        foreach ($this->plans as $planData) {
            $plan = Plan::updateOrCreate(
                ['slug' => $planData['slug']],
                $planData
            );

            $this->command->info("  ✓ {$plan->name} - {$plan->formatted_price}");
            $this->showPlanDetails($plan);
        }

        $this->command->info('✅ Plans seeded successfully!');
        $this->showSummary();
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
            Plan::ordered()->get()->map(fn($plan) => [
                $plan->name,
                $plan->formatted_price,
                count(array_filter($plan->features ?? [])),
                count($plan->getAllEnabledPermissions()),
                $plan->limits['users'] ?? 0,
                $plan->limits['projects'] ?? 0,
                ($plan->limits['storage'] ?? 0) . 'MB',
            ])
        );
    }
}
