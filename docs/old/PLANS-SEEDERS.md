# Plans Seeders - Complete Implementation

## Overview

Seeders completos para os 3 planos (Starter, Professional, Enterprise) com mapeamento de features → permissions.

---

## Seeder: `PlanSeeder`

```php
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
```

---

## Seeder: `EnterprisePermissionsSeeder`

Cria as permissions extras necessárias para Enterprise (Reports, SSO, Branding).

```php
<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;

class EnterprisePermissionsSeeder extends Seeder
{
    /**
     * Additional permissions for Enterprise features
     */
    protected array $permissions = [
        // Advanced Reports
        [
            'name' => 'tenant.reports:view',
            'description' => 'View reports',
            'category' => 'reports',
        ],
        [
            'name' => 'tenant.reports:export',
            'description' => 'Export reports to PDF/Excel',
            'category' => 'reports',
        ],
        [
            'name' => 'tenant.reports:schedule',
            'description' => 'Schedule automated reports',
            'category' => 'reports',
        ],
        [
            'name' => 'tenant.reports:customize',
            'description' => 'Create custom report templates',
            'category' => 'reports',
        ],

        // SSO
        [
            'name' => 'tenant.sso:configure',
            'description' => 'Configure SSO settings',
            'category' => 'sso',
        ],
        [
            'name' => 'tenant.sso:manage',
            'description' => 'Manage SSO providers',
            'category' => 'sso',
        ],
        [
            'name' => 'tenant.sso:testConnection',
            'description' => 'Test SSO connection',
            'category' => 'sso',
        ],

        // White Label / Branding
        [
            'name' => 'tenant.branding:view',
            'description' => 'View branding settings',
            'category' => 'branding',
        ],
        [
            'name' => 'tenant.branding:edit',
            'description' => 'Edit branding (logo, colors, etc)',
            'category' => 'branding',
        ],
        [
            'name' => 'tenant.branding:preview',
            'description' => 'Preview branding changes',
            'category' => 'branding',
        ],
        [
            'name' => 'tenant.branding:publish',
            'description' => 'Publish branding to production',
            'category' => 'branding',
        ],

        // Custom Roles
        [
            'name' => 'tenant.roles:view',
            'description' => 'View custom roles',
            'category' => 'roles',
        ],
        [
            'name' => 'tenant.roles:create',
            'description' => 'Create custom roles',
            'category' => 'roles',
        ],
        [
            'name' => 'tenant.roles:edit',
            'description' => 'Edit custom roles',
            'category' => 'roles',
        ],
        [
            'name' => 'tenant.roles:delete',
            'description' => 'Delete custom roles',
            'category' => 'roles',
        ],
    ];

    public function run(): void
    {
        $this->command->info('🔄 Seeding Enterprise Permissions...');

        // This should run BEFORE permissions:sync
        // These permissions are referenced in PlanSeeder

        foreach ($this->permissions as $permissionData) {
            $permission = Permission::updateOrCreate(
                ['name' => $permissionData['name']],
                $permissionData
            );

            $this->command->info("  ✓ {$permission->name}");
        }

        $this->command->info('✅ Enterprise permissions created!');
        $this->command->info('💡 Run "permissions:sync" to sync these with roles.');
    }
}
```

---

## DatabaseSeeder Update

```php
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            // 1. Base permissions (from existing SyncPermissions command)
            // Run: php artisan permissions:sync

            // 2. Enterprise permissions (new categories)
            EnterprisePermissionsSeeder::class,

            // 3. Plans with permission mappings
            PlanSeeder::class,

            // 4. Demo tenants (optional)
            // TenantSeeder::class,
        ]);

        $this->command->info('');
        $this->command->info('🎉 Database seeded successfully!');
        $this->command->newLine();
        $this->command->info('Next steps:');
        $this->command->line('  1. Run: php artisan permissions:sync (sync permissions to roles)');
        $this->command->line('  2. Run: php artisan plans:sync-permissions (map features to permissions)');
        $this->command->line('  3. Assign plans to tenants via UI or tinker');
    }
}
```

---

## Plan Factory

```php
<?php

namespace Database\Factories;

use App\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;

class PlanFactory extends Factory
{
    protected $model = Plan::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->words(2, true),
            'slug' => $this->faker->unique()->slug,
            'description' => $this->faker->sentence,
            'price' => $this->faker->numberBetween(1000, 50000),
            'currency' => 'USD',
            'billing_period' => 'monthly',
            'features' => [
                'projects' => true,
                'customRoles' => false,
                'apiAccess' => false,
            ],
            'limits' => [
                'users' => 10,
                'projects' => 50,
                'storage' => 1024,
            ],
            'permission_map' => [],
            'is_active' => true,
            'is_featured' => false,
            'sort_order' => 0,
        ];
    }

    /**
     * Starter plan state
     */
    public function starter(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Starter',
            'slug' => 'starter',
            'price' => 2900,
            'features' => [
                'projects' => true,
                'customRoles' => false,
                'apiAccess' => false,
            ],
            'limits' => [
                'users' => 1,
                'projects' => 50,
                'storage' => 1024,
            ],
        ]);
    }

    /**
     * Professional plan state
     */
    public function professional(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Professional',
            'slug' => 'professional',
            'price' => 9900,
            'features' => [
                'projects' => true,
                'customRoles' => true,
                'apiAccess' => true,
                'advancedReports' => false,
            ],
            'limits' => [
                'users' => 50,
                'projects' => -1,
                'storage' => 10240,
                'apiCalls' => 10000,
            ],
        ]);
    }

    /**
     * Enterprise plan state
     */
    public function enterprise(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Enterprise',
            'slug' => 'enterprise',
            'price' => 0,
            'features' => [
                'projects' => true,
                'customRoles' => true,
                'apiAccess' => true,
                'advancedReports' => true,
                'sso' => true,
                'whiteLabel' => true,
            ],
            'limits' => [
                'users' => -1,
                'projects' => -1,
                'storage' => 102400,
                'apiCalls' => -1,
            ],
        ]);
    }
}
```

---

## Usage

### Seed Database

```bash
# 1. Fresh database
sail artisan migrate:fresh

# 2. Sync base permissions
sail artisan permissions:sync

# 3. Seed enterprise permissions + plans
sail artisan db:seed

# 4. Sync plan permission mappings
sail artisan plans:sync-permissions
```

### Assign Plan to Tenant

```bash
sail artisan tinker

>>> $tenant = Tenant::first();
>>> $starterPlan = Plan::where('slug', 'starter')->first();
>>> $tenant->update(['plan_id' => $starterPlan->id]);

# Permissions automatically synced via observer!
```

### Test in Browser

```bash
# Login to tenant
http://tenant1.localhost

# Check plan features
>>> use Laravel\Pennant\Feature;
>>> Feature::active('customRoles'); // false (Starter)
>>> Feature::value('maxUsers'); // 1

# Upgrade to Professional
>>> $proPlan = Plan::where('slug', 'professional')->first();
>>> tenant()->update(['plan_id' => $proPlan->id]);

# Check again
>>> Feature::active('customRoles'); // true!
>>> Feature::value('maxUsers'); // 50
```

---

## Comparison Table

| Feature | Starter | Professional | Enterprise |
|---------|---------|--------------|------------|
| **Price** | $29/mo | $99/mo | Custom |
| **Users** | 1 | 50 | Unlimited |
| **Projects** | 50 | Unlimited | Unlimited |
| **Storage** | 1GB | 10GB | 100GB |
| **Log Retention** | 30 days | 90 days | 365 days |
| **API Calls** | ❌ | 10k/mo | Unlimited |
| **Custom Roles** | ❌ | ✅ | ✅ |
| **API Access** | ❌ | ✅ | ✅ |
| **Advanced Reports** | ❌ | ❌ | ✅ |
| **SSO** | ❌ | ❌ | ✅ |
| **White Label** | ❌ | ❌ | ✅ |
| **Permissions** | 8 | 27 | 40+ |

---

## Permission Breakdown by Plan

### Starter (8 permissions)
- `tenant.projects:view`
- `tenant.projects:create`
- `tenant.projects:editOwn`
- `tenant.projects:download`
- `tenant.team:view`
- `tenant.settings:view`
- `tenant.billing:view` (own billing only)
- `tenant.billing:invoices` (download own invoices)

### Professional (+19 = 27 total)
**All Starter permissions plus:**
- `tenant.projects:edit` (full edit)
- `tenant.projects:delete`
- `tenant.projects:upload`
- `tenant.projects:archive`
- `tenant.team:invite`
- `tenant.team:remove`
- `tenant.team:manageRoles`
- `tenant.team:activity`
- `tenant.settings:edit`
- `tenant.roles:view`
- `tenant.roles:create`
- `tenant.roles:edit`
- `tenant.roles:delete`
- `tenant.apiTokens:view`
- `tenant.apiTokens:create`
- `tenant.apiTokens:delete`
- `tenant.billing:manage`

### Enterprise (+13 = 40 total)
**All Professional permissions plus:**
- `tenant.reports:view`
- `tenant.reports:export`
- `tenant.reports:schedule`
- `tenant.reports:customize`
- `tenant.sso:configure`
- `tenant.sso:manage`
- `tenant.sso:testConnection`
- `tenant.branding:view`
- `tenant.branding:edit`
- `tenant.branding:preview`
- `tenant.branding:publish`
- `tenant.settings:danger` (danger zone)
- All wildcard expansions

---

## Testing Plan Features

```php
<?php

namespace Tests\Feature;

use App\Models\Plan;
use Laravel\Pennant\Feature;
use Tests\TestCase;

class PlanFeaturesTest extends TestCase
{
    #[Test]
    public function starter_plan_has_correct_features()
    {
        $plan = Plan::where('slug', 'starter')->first();

        $this->assertTrue($plan->hasFeature('projects'));
        $this->assertFalse($plan->hasFeature('customRoles'));
        $this->assertFalse($plan->hasFeature('apiAccess'));

        $this->assertEquals(1, $plan->getLimit('users'));
        $this->assertEquals(50, $plan->getLimit('projects'));
        $this->assertEquals(1024, $plan->getLimit('storage'));
    }

    #[Test]
    public function professional_plan_enables_custom_roles()
    {
        $plan = Plan::where('slug', 'professional')->first();

        $this->assertTrue($plan->hasFeature('customRoles'));
        $this->assertTrue($plan->hasFeature('apiAccess'));

        $permissions = $plan->getAllEnabledPermissions();

        $this->assertContains('tenant.roles:create', $permissions);
        $this->assertContains('tenant.apiTokens:view', $permissions);
    }

    #[Test]
    public function enterprise_plan_has_all_features()
    {
        $plan = Plan::where('slug', 'enterprise')->first();

        $this->assertTrue($plan->hasFeature('advancedReports'));
        $this->assertTrue($plan->hasFeature('sso'));
        $this->assertTrue($plan->hasFeature('whiteLabel'));

        $this->assertEquals(-1, $plan->getLimit('users')); // unlimited
        $this->assertEquals(-1, $plan->getLimit('projects'));
    }
}
```

---

## Next Steps

1. ✅ Run seeders
2. ✅ Assign plans to test tenants
3. ✅ Test Pennant feature resolution
4. ✅ Test permission sync on plan change
5. ⏳ Create billing UI (plan selection)
6. ⏳ Integrate Stripe via Cashier
7. ⏳ Test full upgrade/downgrade flow
