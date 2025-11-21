# Plans & Features - Hybrid Architecture (Database + Pennant + Permissions)

## Visão Geral

Documentação técnica da **Arquitetura Híbrida** para gerenciamento de planos SaaS com integração profunda entre **Database-Driven Plans**, **Laravel Pennant (Feature Flags)** e **Spatie Permission**.

**Diferencial**: Features dos planos controlam automaticamente as permissions disponíveis para os tenants.

---

## 📋 Índice

1. [Por Que Hybrid?](#por-que-hybrid)
2. [Arquitetura em 3 Camadas](#arquitetura-em-3-camadas)
3. [Database Schema](#database-schema)
4. [Models](#models)
5. [Laravel Pennant Integration](#laravel-pennant-integration)
6. [Permission Sync System](#permission-sync-system)
7. [Middleware & Gates](#middleware--gates)
8. [Usage Tracking](#usage-tracking)
9. [Frontend Integration](#frontend-integration)
10. [Cashier Integration](#cashier-integration)
11. [Exemplos Práticos](#exemplos-práticos)
12. [Testes](#testes)
13. [Migration Path](#migration-path)
14. [Troubleshooting](#troubleshooting)

---

## Por Que Hybrid?

### 🎯 Best of Both Worlds

#### Database-Driven (Plans)
✅ **Billing-ready**: Stripe/Paddle integration
✅ **Flexible**: Admin gerencia planos
✅ **Auditable**: Mudanças rastreadas
✅ **Overrides**: Custom deals por tenant

#### Laravel Pennant (Features)
✅ **Elegant syntax**: `Feature::active('customRoles')`
✅ **Rich values**: Retorna números, arrays, objetos
✅ **Scoped**: Works with Tenant scope nativamente
✅ **Cached**: Performance otimizada
✅ **Class-based**: Features como classes organizadas

#### Spatie Permission (Authorization)
✅ **Fine-grained**: Controle granular de ações
✅ **Role-based**: Owner, Admin, Member
✅ **Tenant-isolated**: Permissions isoladas por tenant
✅ **Gate integration**: `$user->can('tenant.projects:create')`

### 🔗 Como Elas se Integram

```
┌─────────────────────────────────────────────────────────┐
│                    USER REQUEST                          │
└─────────────────────────────────────────────────────────┘
                            │
                            ▼
         ┌──────────────────────────────────────┐
         │  1. PLAN (Database)                  │
         │  - Tenant tem plan_id                │
         │  - Plan define features disponíveis  │
         │  - Plan define limits (quotas)       │
         └──────────────────────────────────────┘
                            │
                            ▼
         ┌──────────────────────────────────────┐
         │  2. PENNANT (Feature Flags)          │
         │  - Feature::active('customRoles')    │
         │  - Feature::value('maxUsers') -> 50  │
         │  - Scoped to Tenant                  │
         └──────────────────────────────────────┘
                            │
                            ▼
         ┌──────────────────────────────────────┐
         │  3. PERMISSIONS (Authorization)      │
         │  - Feature "customRoles" habilitada? │
         │    → Libera permissions de roles     │
         │  - Feature "apiAccess" habilitada?   │
         │    → Libera permissions de API       │
         └──────────────────────────────────────┘
                            │
                            ▼
                    ✅ ACCESS GRANTED
```

### 🎨 Exemplo Concreto

**Scenario**: Tenant upgrade de Starter → Professional

```php
// 1. Update plan (Database)
$tenant->changePlan($professionalPlan);

// 2. Pennant automaticamente resolve novas features
Feature::for($tenant)->active('customRoles'); // true agora!
Feature::for($tenant)->value('maxUsers'); // 50 agora!

// 3. Permission Sync automático (via Observer)
// Libera permissions:
// - tenant.roles:create
// - tenant.roles:edit
// - tenant.apiTokens:view
// - tenant.apiTokens:create

// 4. User pode agora acessar
$user->can('tenant.roles:create'); // true
```

---

## Arquitetura em 3 Camadas

### Layer 1: Plans (Database) - Source of Truth

**Responsabilidade**: Definir o que cada plano oferece

```php
// Plan::find('professional')
[
    'features' => [
        'customRoles' => true,
        'apiAccess' => true,
        'advancedReports' => false,
    ],
    'limits' => [
        'users' => 50,
        'projects' => -1, // unlimited
        'storage' => 10240, // 10GB
    ],
]
```

### Layer 2: Pennant (Feature Flags) - Elegant API

**Responsabilidade**: Resolver features baseado no plano

```php
// app/Features/CustomRoles.php
class CustomRoles
{
    public function resolve(Tenant $tenant): bool
    {
        return $tenant->hasFeature('customRoles');
    }
}

// Usage
if (Feature::active('customRoles')) {
    // Show custom roles UI
}
```

### Layer 3: Permissions (Authorization) - Granular Control

**Responsabilidade**: Controlar ações específicas

```php
// Mapping: Feature → Permissions
'customRoles' => [
    'tenant.roles:view',
    'tenant.roles:create',
    'tenant.roles:edit',
    'tenant.roles:delete',
],

'apiAccess' => [
    'tenant.apiTokens:view',
    'tenant.apiTokens:create',
    'tenant.apiTokens:delete',
],
```

---

## Database Schema

### Migration: `create_plans_table`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();

            // Pricing
            $table->integer('price')->default(0);
            $table->string('currency', 3)->default('USD');
            $table->enum('billing_period', ['monthly', 'yearly'])->default('monthly');

            // Stripe/Paddle Integration
            $table->string('stripe_price_id')->nullable()->unique();
            $table->string('paddle_price_id')->nullable()->unique();

            // Features (JSON)
            // {
            //   "customRoles": true,
            //   "apiAccess": true,
            //   "advancedReports": false,
            //   "sso": false,
            //   "whiteLabel": false
            // }
            $table->json('features')->nullable();

            // Limits (JSON)
            // {
            //   "users": 50,
            //   "projects": -1,
            //   "storage": 10240,
            //   "apiCalls": 10000,
            //   "logRetention": 90
            // }
            $table->json('limits')->nullable();

            // ⭐ NEW: Permission Mapping (JSON)
            // Maps features to permissions that should be enabled
            // {
            //   "customRoles": ["tenant.roles:*"],
            //   "apiAccess": ["tenant.apiTokens:*"],
            //   "advancedReports": ["tenant.reports:export", "tenant.reports:schedule"]
            // }
            $table->json('permission_map')->nullable();

            // Meta
            $table->boolean('is_active')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->integer('sort_order')->default(0);

            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
```

### Migration: `add_plan_to_tenants_table`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->foreignId('plan_id')
                ->nullable()
                ->after('id')
                ->constrained('plans')
                ->nullOnDelete();

            // Custom overrides
            $table->json('plan_features_override')->nullable();
            $table->json('plan_limits_override')->nullable();

            // Trial
            $table->timestamp('trial_ends_at')->nullable();

            // Usage tracking
            $table->json('current_usage')->nullable();

            // ⭐ NEW: Cache de permissions habilitadas pelo plano
            // Regenerado quando plano muda
            $table->json('plan_enabled_permissions')->nullable();

            $table->index('plan_id');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropForeign(['plan_id']);
            $table->dropColumn([
                'plan_id',
                'plan_features_override',
                'plan_limits_override',
                'trial_ends_at',
                'current_usage',
                'plan_enabled_permissions',
            ]);
        });
    }
};
```

---

## Models

### Model: `Plan`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'description',
        'price',
        'currency',
        'billing_period',
        'stripe_price_id',
        'paddle_price_id',
        'features',
        'limits',
        'permission_map',
        'is_active',
        'is_featured',
        'sort_order',
    ];

    protected $casts = [
        'price' => 'integer',
        'features' => 'array',
        'limits' => 'array',
        'permission_map' => 'array',
        'is_active' => 'boolean',
        'is_featured' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function tenants(): HasMany
    {
        return $this->hasMany(Tenant::class);
    }

    /**
     * Check if plan has a specific feature
     */
    public function hasFeature(string $feature): bool
    {
        return $this->features[$feature] ?? false;
    }

    /**
     * Get limit for a resource (-1 = unlimited)
     */
    public function getLimit(string $resource): int
    {
        return $this->limits[$resource] ?? 0;
    }

    /**
     * Check if limit is unlimited
     */
    public function isUnlimited(string $resource): bool
    {
        return $this->getLimit($resource) === -1;
    }

    /**
     * ⭐ NEW: Get permissions that should be enabled for a feature
     */
    public function getPermissionsForFeature(string $feature): array
    {
        return $this->permission_map[$feature] ?? [];
    }

    /**
     * ⭐ NEW: Get all permissions enabled by this plan
     */
    public function getAllEnabledPermissions(): array
    {
        $permissions = [];

        foreach ($this->features ?? [] as $feature => $enabled) {
            if ($enabled) {
                $featurePermissions = $this->getPermissionsForFeature($feature);
                $permissions = array_merge($permissions, $featurePermissions);
            }
        }

        return array_unique($permissions);
    }

    /**
     * ⭐ NEW: Expand wildcard permissions
     * "tenant.roles:*" → all roles permissions
     */
    public function expandPermissions(array $permissions): array
    {
        $expanded = [];

        foreach ($permissions as $permission) {
            if (str_ends_with($permission, ':*')) {
                // Wildcard: get all permissions for this category
                $category = str_replace(':*', '', $permission);
                $categoryPermissions = \App\Models\Permission::where('name', 'like', "{$category}:%")->pluck('name')->toArray();
                $expanded = array_merge($expanded, $categoryPermissions);
            } else {
                $expanded[] = $permission;
            }
        }

        return array_unique($expanded);
    }

    /**
     * Get formatted price
     */
    public function getFormattedPriceAttribute(): string
    {
        if ($this->price === 0) {
            return 'Custom';
        }

        return '$' . number_format($this->price / 100, 2);
    }

    /**
     * Scopes
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order');
    }
}
```

### Model: `Tenant` (Updated)

```php
<?php

namespace App\Models;

use Laravel\Pennant\Concerns\HasFeatures;
use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;
use Stancl\Tenancy\Database\Concerns\HasDatabase;
use Stancl\Tenancy\Database\Concerns\HasDomains;
use Stancl\Tenancy\Database\Contracts\TenantWithDatabase;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Tenant extends BaseTenant implements TenantWithDatabase
{
    use HasDatabase, HasDomains;
    use HasFeatures; // ⭐ Laravel Pennant trait

    protected $casts = [
        'plan_features_override' => 'array',
        'plan_limits_override' => 'array',
        'current_usage' => 'array',
        'plan_enabled_permissions' => 'array',
        'trial_ends_at' => 'datetime',
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /**
     * Check if tenant has a specific feature
     * Used by Pennant features
     */
    public function hasFeature(string $feature): bool
    {
        // Override primeiro
        if (isset($this->plan_features_override[$feature])) {
            return $this->plan_features_override[$feature];
        }

        // Trial gets all features
        if ($this->isOnTrial()) {
            return true;
        }

        // Fallback para plan default
        return $this->plan?->hasFeature($feature) ?? false;
    }

    /**
     * Get limit for a resource
     */
    public function getLimit(string $resource): int
    {
        // Override primeiro
        if (isset($this->plan_limits_override[$resource])) {
            return $this->plan_limits_override[$resource];
        }

        // Trial gets higher limits
        if ($this->isOnTrial()) {
            return $this->plan?->getLimit($resource) ?? -1;
        }

        // Fallback para plan default
        return $this->plan?->getLimit($resource) ?? 0;
    }

    /**
     * Check if unlimited
     */
    public function isUnlimited(string $resource): bool
    {
        return $this->getLimit($resource) === -1;
    }

    /**
     * Get current usage
     */
    public function getCurrentUsage(string $resource): int
    {
        return $this->current_usage[$resource] ?? 0;
    }

    /**
     * Check if limit reached
     */
    public function hasReachedLimit(string $resource): bool
    {
        $limit = $this->getLimit($resource);

        if ($limit === -1) {
            return false;
        }

        $usage = $this->getCurrentUsage($resource);

        return $usage >= $limit;
    }

    /**
     * Increment usage
     */
    public function incrementUsage(string $resource, int $amount = 1): void
    {
        $currentUsage = $this->current_usage ?? [];
        $currentUsage[$resource] = ($currentUsage[$resource] ?? 0) + $amount;

        $this->update(['current_usage' => $currentUsage]);
    }

    /**
     * Decrement usage
     */
    public function decrementUsage(string $resource, int $amount = 1): void
    {
        $currentUsage = $this->current_usage ?? [];
        $currentUsage[$resource] = max(0, ($currentUsage[$resource] ?? 0) - $amount);

        $this->update(['current_usage' => $currentUsage]);
    }

    /**
     * ⭐ NEW: Get permissions enabled by current plan
     */
    public function getPlanEnabledPermissions(): array
    {
        // Return cached if available
        if ($this->plan_enabled_permissions) {
            return $this->plan_enabled_permissions;
        }

        // Regenerate and cache
        return $this->regeneratePlanPermissions();
    }

    /**
     * ⭐ NEW: Regenerate permissions based on current plan
     */
    public function regeneratePlanPermissions(): array
    {
        if (!$this->plan) {
            return [];
        }

        $permissions = $this->plan->getAllEnabledPermissions();
        $expanded = $this->plan->expandPermissions($permissions);

        // Cache it
        $this->update(['plan_enabled_permissions' => $expanded]);

        return $expanded;
    }

    /**
     * ⭐ NEW: Check if permission is enabled by plan
     */
    public function isPlanPermissionEnabled(string $permission): bool
    {
        return in_array($permission, $this->getPlanEnabledPermissions());
    }

    /**
     * Trial methods
     */
    public function isOnTrial(): bool
    {
        return $this->trial_ends_at && $this->trial_ends_at->isFuture();
    }

    public function hasTrialEnded(): bool
    {
        return $this->trial_ends_at && $this->trial_ends_at->isPast();
    }
}
```

---

## Laravel Pennant Integration

### Installation

```bash
composer require laravel/pennant
sail artisan vendor:publish --provider="Laravel\Pennant\PennantServiceProvider"
sail artisan migrate
```

### Configuration: `config/pennant.php`

```php
<?php

return [
    'default' => 'database',

    'stores' => [
        'database' => [
            'driver' => 'database',
            'connection' => null,
        ],
    ],

    // ⭐ Define default scope (Tenant)
    'scope' => \App\Models\Tenant::class,
];
```

### Class-Based Features

#### Feature: `CustomRoles`

```php
<?php

namespace App\Features;

use App\Models\Tenant;
use Illuminate\Support\Lottery;

class CustomRoles
{
    /**
     * Resolve the feature's initial value.
     */
    public function resolve(Tenant $tenant): bool
    {
        // Check if tenant's plan has this feature
        return $tenant->hasFeature('customRoles');
    }
}
```

#### Feature: `ApiAccess`

```php
<?php

namespace App\Features;

use App\Models\Tenant;

class ApiAccess
{
    public function resolve(Tenant $tenant): bool
    {
        return $tenant->hasFeature('apiAccess');
    }
}
```

#### Feature: `MaxUsers` (Rich Value)

```php
<?php

namespace App\Features;

use App\Models\Tenant;

class MaxUsers
{
    /**
     * Resolve the feature's initial value.
     *
     * Returns the user limit as an integer (rich value)
     */
    public function resolve(Tenant $tenant): int
    {
        return $tenant->getLimit('users');
    }
}
```

#### Feature: `StorageLimit` (Rich Value)

```php
<?php

namespace App\Features;

use App\Models\Tenant;

class StorageLimit
{
    /**
     * Returns storage limit in MB
     */
    public function resolve(Tenant $tenant): int
    {
        return $tenant->getLimit('storage');
    }
}
```

### Registering Features: `AppServiceProvider`

```php
<?php

namespace App\Providers;

use App\Features;
use Illuminate\Support\ServiceProvider;
use Laravel\Pennant\Feature;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Register class-based features
        Feature::discover();

        // Or register manually
        Feature::define('customRoles', Features\CustomRoles::class);
        Feature::define('apiAccess', Features\ApiAccess::class);
        Feature::define('maxUsers', Features\MaxUsers::class);
        Feature::define('storageLimit', Features\StorageLimit::class);
    }
}
```

### Using Pennant Features

```php
use Laravel\Pennant\Feature;

// In controllers/middleware
$tenant = tenant();

// Boolean checks
if (Feature::for($tenant)->active('customRoles')) {
    // Show custom roles UI
}

// Rich values
$maxUsers = Feature::for($tenant)->value('maxUsers'); // 50
$storageLimit = Feature::for($tenant)->value('storageLimit'); // 10240 MB

// Multiple checks
if (Feature::for($tenant)->allAreActive(['customRoles', 'apiAccess'])) {
    // Both features enabled
}

// Get all values
$features = Feature::for($tenant)->values([
    'customRoles',
    'apiAccess',
    'maxUsers',
    'storageLimit',
]);

// Conditional execution
Feature::for($tenant)->when('customRoles',
    fn() => $this->showCustomRolesUI(),
    fn() => $this->showUpgradePrompt()
);
```

---

## Permission Sync System

### Concept: Feature → Permissions Mapping

Quando um tenant muda de plano, as permissions devem ser sincronizadas automaticamente baseado nas features habilitadas.

**Exemplo**:

```php
// Plan: Professional
'features' => [
    'customRoles' => true,
    'apiAccess' => true,
],

'permission_map' => [
    'customRoles' => [
        'tenant.roles:view',
        'tenant.roles:create',
        'tenant.roles:edit',
        'tenant.roles:delete',
    ],
    'apiAccess' => [
        'tenant.apiTokens:view',
        'tenant.apiTokens:create',
        'tenant.apiTokens:delete',
    ],
]
```

### Command: `SyncPlanPermissions`

```php
<?php

namespace App\Console\Commands;

use App\Models\Plan;
use App\Models\Permission;
use Illuminate\Console\Command;

class SyncPlanPermissions extends Command
{
    protected $signature = 'plans:sync-permissions {--fresh}';
    protected $description = 'Sync permissions enabled by each plan';

    /**
     * Permission definitions grouped by feature
     */
    protected array $featurePermissions = [
        // Base features (all plans)
        'projects' => [
            'tenant.projects:view',
            'tenant.projects:create',
            'tenant.projects:editOwn',
            'tenant.projects:download',
        ],

        // Professional+
        'customRoles' => [
            'tenant.roles:view',
            'tenant.roles:create',
            'tenant.roles:edit',
            'tenant.roles:delete',
        ],

        'apiAccess' => [
            'tenant.apiTokens:view',
            'tenant.apiTokens:create',
            'tenant.apiTokens:delete',
        ],

        // Enterprise only
        'advancedReports' => [
            'tenant.reports:view',
            'tenant.reports:export',
            'tenant.reports:schedule',
            'tenant.reports:customize',
        ],

        'sso' => [
            'tenant.sso:configure',
            'tenant.sso:manage',
        ],

        'whiteLabel' => [
            'tenant.branding:edit',
            'tenant.branding:preview',
        ],
    ];

    public function handle(): int
    {
        $this->info('🔄 Syncing Plan Permissions...');

        if ($this->option('fresh')) {
            $this->warn('⚠️  Fresh mode: Regenerating all permission maps...');
        }

        $plans = Plan::all();

        foreach ($plans as $plan) {
            $this->syncPlan($plan);
        }

        $this->info('✅ Plan permissions synced successfully!');

        return self::SUCCESS;
    }

    protected function syncPlan(Plan $plan): void
    {
        $this->line("Processing plan: {$plan->name}");

        $permissionMap = [];

        // Build permission map based on plan features
        foreach ($plan->features ?? [] as $feature => $enabled) {
            if ($enabled && isset($this->featurePermissions[$feature])) {
                $permissionMap[$feature] = $this->featurePermissions[$feature];
            }
        }

        // Update plan
        $plan->update(['permission_map' => $permissionMap]);

        $totalPermissions = count(array_merge(...array_values($permissionMap)));
        $this->info("  ✓ {$plan->name}: {$totalPermissions} permissions mapped");
    }
}
```

### Observer: `TenantObserver`

Automaticamente sincroniza permissions quando o plano muda.

```php
<?php

namespace App\Observers;

use App\Models\Tenant;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Support\Facades\Log;

class TenantObserver
{
    /**
     * Handle the Tenant "updated" event.
     */
    public function updated(Tenant $tenant): void
    {
        // Se o plano mudou, sincronizar permissions
        if ($tenant->wasChanged('plan_id')) {
            $this->syncPermissionsForPlanChange($tenant);
        }
    }

    /**
     * ⭐ Sync permissions when plan changes
     */
    protected function syncPermissionsForPlanChange(Tenant $tenant): void
    {
        Log::info("Syncing permissions for tenant {$tenant->id} after plan change");

        // Initialize tenancy context
        tenancy()->initialize($tenant);
        setPermissionsTeamId($tenant->id);

        try {
            // 1. Regenerate plan permissions cache
            $enabledPermissions = $tenant->regeneratePlanPermissions();

            // 2. For each role in this tenant, sync their permissions
            // based on what's enabled by the plan
            $this->syncRolePermissions($tenant, $enabledPermissions);

            // 3. Log activity
            activity()
                ->performedOn($tenant)
                ->withProperties([
                    'plan' => $tenant->plan->name,
                    'enabled_permissions' => $enabledPermissions,
                ])
                ->log('Plan permissions synchronized');

        } catch (\Exception $e) {
            Log::error("Failed to sync permissions for tenant {$tenant->id}: {$e->getMessage()}");
        } finally {
            tenancy()->end();
        }
    }

    /**
     * Sync role permissions based on plan
     */
    protected function syncRolePermissions(Tenant $tenant, array $enabledPermissions): void
    {
        $roles = Role::where('tenant_id', $tenant->id)->get();

        foreach ($roles as $role) {
            // Get current role permissions
            $currentPermissions = $role->permissions->pluck('name')->toArray();

            // Filter: only keep permissions that are enabled by plan
            $allowedPermissions = array_intersect($currentPermissions, $enabledPermissions);

            // Get removed permissions
            $removedPermissions = array_diff($currentPermissions, $allowedPermissions);

            if (!empty($removedPermissions)) {
                Log::info("Removing {count($removedPermissions)} permissions from role {$role->name}");

                // Sync to allowed only
                $role->syncPermissions(
                    Permission::whereIn('name', $allowedPermissions)->get()
                );
            }
        }
    }
}
```

Registrar no `AppServiceProvider`:

```php
public function boot(): void
{
    Tenant::observe(TenantObserver::class);
}
```

### Gate: Check Plan Permission

Adicionar check no Gate para garantir que mesmo com permission, user precisa ter feature habilitada.

```php
// app/Providers/AppServiceProvider.php
use Illuminate\Support\Facades\Gate;

public function boot(): void
{
    // ⭐ Check plan permissions BEFORE checking user permissions
    Gate::before(function ($user, $ability) {
        // Super Admin bypass
        $currentTeamId = getPermissionsTeamId();
        setPermissionsTeamId(null);
        $isSuperAdmin = $user->hasRole('Super Admin');
        setPermissionsTeamId($currentTeamId);

        if ($isSuperAdmin) {
            return true;
        }

        // Check if permission is plan-restricted
        $tenant = tenant();
        if ($tenant && str_starts_with($ability, 'tenant.')) {
            // If permission not enabled by plan, deny
            if (!$tenant->isPlanPermissionEnabled($ability)) {
                return false;
            }
        }

        // Continue to normal permission check
        return null;
    });
}
```

---

## Middleware & Gates

### Middleware: `CheckFeature`

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Pennant\Feature;
use Symfony\Component\HttpFoundation\Response;

class CheckFeature
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        $tenant = tenant();

        if (!$tenant) {
            abort(403, 'Tenant not found.');
        }

        // Use Pennant to check feature
        if (!Feature::for($tenant)->active($feature)) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'This feature is not available on your current plan.',
                    'feature' => $feature,
                    'upgrade_url' => route('tenant.billing.index'),
                ], 403);
            }

            return redirect()
                ->route('tenant.billing.index')
                ->with('error', 'This feature is not available on your current plan. Please upgrade.');
        }

        return $next($request);
    }
}
```

### Middleware: `CheckLimit`

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Pennant\Feature;
use Symfony\Component\HttpFoundation\Response;

class CheckLimit
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, string $resource): Response
    {
        $tenant = tenant();

        if (!$tenant) {
            abort(403, 'Tenant not found.');
        }

        // Get limit from Pennant (rich value)
        $limitFeature = 'max' . ucfirst($resource);
        $limit = Feature::for($tenant)->value($limitFeature);
        $usage = $tenant->getCurrentUsage($resource);

        // Check if reached
        if ($limit !== -1 && $usage >= $limit) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => "You have reached the limit for {$resource}.",
                    'limit' => $limit,
                    'current' => $usage,
                    'upgrade_url' => route('tenant.billing.index'),
                ], 403);
            }

            return redirect()
                ->route('tenant.billing.index')
                ->with('error', "You have reached the limit for {$resource}. Please upgrade your plan.");
        }

        return $next($request);
    }
}
```

Registrar em `bootstrap/app.php`:

```php
$middleware->alias([
    'feature' => \App\Http\Middleware\CheckFeature::class,
    'limit' => \App\Http\Middleware\CheckLimit::class,
]);
```

### Using in Controllers

```php
namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class RoleController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            // Check permission first
            new Middleware('permission:tenant.roles:view', only: ['index']),
            new Middleware('permission:tenant.roles:create', only: ['store']),

            // Then check feature (Pennant)
            new Middleware('feature:customRoles', except: ['index']),
        ];
    }

    public function index()
    {
        // User can view roles (basic permission)
        return view('tenant.roles.index');
    }

    public function store()
    {
        // User can create custom roles (feature enabled)
        return redirect()->back();
    }
}
```

---

## Usage Tracking

Same as Database-Driven architecture, but using Pennant for limits.

### Observer: `UserObserver`

```php
<?php

namespace App\Observers;

use App\Models\User;

class UserObserver
{
    public function created(User $user): void
    {
        if ($tenant = tenant()) {
            $tenant->incrementUsage('users');
        }
    }

    public function deleted(User $user): void
    {
        if ($tenant = tenant()) {
            $tenant->decrementUsage('users');
        }
    }
}
```

---

## Frontend Integration

### Sharing Pennant Features with Inertia

Update `HandleInertiaRequests` middleware:

```php
<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;
use Laravel\Pennant\Feature;

class HandleInertiaRequests extends Middleware
{
    public function share(Request $request): array
    {
        $tenant = tenant();
        $user = $request->user();

        return [
            ...parent::share($request),

            'auth' => [
                'user' => $user,
                'permissions' => $user ? $user->getAllPermissions()->pluck('name') : [],
                'role' => [
                    'name' => $user?->roles?->first()?->name,
                    'isOwner' => $user?->hasRole('owner'),
                    'isAdmin' => $user?->hasRole('admin'),
                    'isAdminOrOwner' => $user?->hasAnyRole(['owner', 'admin']),
                    'isSuperAdmin' => $user?->hasRole('Super Admin'),
                ],
            ],

            // ⭐ Share plan info and features via Pennant
            'plan' => $tenant ? [
                'name' => $tenant->plan->name,
                'slug' => $tenant->plan->slug,

                // Features (boolean)
                'features' => Feature::for($tenant)->values([
                    'customRoles',
                    'apiAccess',
                    'advancedReports',
                    'sso',
                    'whiteLabel',
                ]),

                // Limits (rich values)
                'limits' => [
                    'users' => Feature::for($tenant)->value('maxUsers'),
                    'projects' => Feature::for($tenant)->value('maxProjects'),
                    'storage' => Feature::for($tenant)->value('storageLimit'),
                    'apiCalls' => Feature::for($tenant)->value('apiCallsLimit'),
                ],

                // Current usage
                'usage' => $tenant->current_usage ?? [],

                // Trial info
                'trial' => [
                    'active' => $tenant->isOnTrial(),
                    'endsAt' => $tenant->trial_ends_at?->toISOString(),
                ],
            ] : null,
        ];
    }
}
```

### TypeScript Types

Update `resources/js/types/index.d.ts`:

```typescript
export interface Plan {
    name: string;
    slug: string;
    features: {
        customRoles: boolean;
        apiAccess: boolean;
        advancedReports: boolean;
        sso: boolean;
        whiteLabel: boolean;
    };
    limits: {
        users: number;
        projects: number;
        storage: number;
        apiCalls: number;
    };
    usage: {
        users?: number;
        projects?: number;
        storage?: number;
        apiCalls?: number;
    };
    trial: {
        active: boolean;
        endsAt: string | null;
    };
}

export interface PageProps {
    auth: {
        user: User | null;
        permissions: Permission[];
        role: Role | null;
    };
    plan: Plan | null;
}
```

### React Hook: `usePlan`

```typescript
// resources/js/hooks/use-plan.ts
import { usePage } from '@inertiajs/react';
import { PageProps } from '@/types';

export function usePlan() {
    const { plan } = usePage<PageProps>().props;

    if (!plan) {
        return {
            hasFeature: () => false,
            getLimit: () => 0,
            getUsage: () => 0,
            hasReachedLimit: () => true,
            canAdd: () => false,
            isOnTrial: false,
        };
    }

    const hasFeature = (feature: keyof typeof plan.features): boolean => {
        return plan.features[feature] ?? false;
    };

    const getLimit = (resource: keyof typeof plan.limits): number => {
        return plan.limits[resource] ?? 0;
    };

    const getUsage = (resource: keyof typeof plan.usage): number => {
        return plan.usage[resource] ?? 0;
    };

    const hasReachedLimit = (resource: keyof typeof plan.limits): boolean => {
        const limit = getLimit(resource);
        if (limit === -1) return false; // unlimited

        const usage = getUsage(resource);
        return usage >= limit;
    };

    const canAdd = (resource: keyof typeof plan.limits): boolean => {
        return !hasReachedLimit(resource);
    };

    const getUsagePercentage = (resource: keyof typeof plan.limits): number => {
        const limit = getLimit(resource);
        if (limit === -1) return 0; // unlimited

        const usage = getUsage(resource);
        return Math.round((usage / limit) * 100);
    };

    return {
        plan,
        hasFeature,
        getLimit,
        getUsage,
        hasReachedLimit,
        canAdd,
        getUsagePercentage,
        isOnTrial: plan.trial.active,
        trialEndsAt: plan.trial.endsAt,
    };
}
```

### React Usage Example

```typescript
// resources/js/pages/tenant/team/index.tsx
import { usePlan } from '@/hooks/use-plan';
import { usePermissions } from '@/hooks/use-permissions';
import { Button } from '@/components/ui/button';
import { Progress } from '@/components/ui/progress';

export default function TeamIndex() {
    const { has } = usePermissions();
    const { getLimit, getUsage, canAdd, getUsagePercentage, isOnTrial } = usePlan();

    const usersLimit = getLimit('users');
    const usersCount = getUsage('users');
    const usagePercent = getUsagePercentage('users');

    return (
        <div>
            <h1>Team Members</h1>

            {/* Trial banner */}
            {isOnTrial && (
                <div className="mb-4 p-4 bg-blue-50 rounded">
                    <p>🎉 You're on a trial period with full access!</p>
                </div>
            )}

            {/* Usage indicator */}
            <div className="mb-4">
                <div className="flex justify-between mb-2">
                    <span>Team Members</span>
                    <span>
                        {usersCount} / {usersLimit === -1 ? 'Unlimited' : usersLimit}
                    </span>
                </div>
                <Progress value={usagePercent} className="h-2" />

                {!canAdd('users') && (
                    <p className="text-red-600 mt-2">
                        You've reached your plan limit.
                        <a href="/billing" className="underline ml-1">
                            Upgrade your plan
                        </a>
                    </p>
                )}
            </div>

            {/* Invite button */}
            {has('tenant.team:invite') && canAdd('users') && (
                <Button>Invite Member</Button>
            )}
        </div>
    );
}
```

---

## Cashier Integration

Same as Database-Driven, but with Pennant features synced automatically.

```php
// Tenant model
use Laravel\Cashier\Billable;

class Tenant extends BaseTenant implements TenantWithDatabase
{
    use HasDatabase, HasDomains, Billable, HasFeatures;

    public function subscribeToPlan(Plan $plan, string $paymentMethod = null): void
    {
        // Create Stripe subscription
        $subscription = $this->newSubscription('default', $plan->stripe_price_id)
            ->create($paymentMethod);

        // Update tenant plan (triggers observer → permission sync)
        $this->update(['plan_id' => $plan->id]);

        // Clear Pennant cache for this tenant
        Feature::for($this)->flushCache();

        // Log activity
        activity()
            ->performedOn($this)
            ->withProperties([
                'plan' => $plan->name,
                'price' => $plan->price,
            ])
            ->log('Subscribed to plan');
    }

    public function changePlan(Plan $newPlan): void
    {
        $oldPlan = $this->plan;

        // Swap Stripe subscription
        $this->subscription('default')->swap($newPlan->stripe_price_id);

        // Update tenant plan (triggers observer → permission sync)
        $this->update(['plan_id' => $newPlan->id]);

        // Clear Pennant cache
        Feature::for($this)->flushCache();

        // Log activity
        activity()
            ->performedOn($this)
            ->withProperties([
                'old_plan' => $oldPlan->name,
                'new_plan' => $newPlan->name,
            ])
            ->log('Changed plan');
    }
}
```

---

## Exemplos Práticos

### Exemplo 1: Custom Roles (Feature-Gated)

```php
// Controller
namespace App\Http\Controllers\Tenant;

class RoleController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:tenant.roles:view', only: ['index']),
            new Middleware('permission:tenant.roles:create', only: ['store']),
            new Middleware('feature:customRoles', only: ['store', 'update', 'destroy']),
        ];
    }

    public function index()
    {
        // All plans can VIEW roles
        $roles = Role::where('tenant_id', tenant()->id)->get();

        return Inertia::render('tenant/roles/index', [
            'roles' => $roles,
            'canCreateCustomRoles' => Feature::active('customRoles'),
        ]);
    }

    public function store(Request $request)
    {
        // Only Professional+ can CREATE custom roles
        $validated = $request->validate([
            'name' => 'required|string',
            'permissions' => 'required|array',
        ]);

        $role = Role::create([
            'name' => $validated['name'],
            'tenant_id' => tenant()->id,
        ]);

        $role->syncPermissions($validated['permissions']);

        return redirect()->back()->with('success', 'Custom role created!');
    }
}
```

Frontend:

```typescript
// resources/js/pages/tenant/roles/index.tsx
import { usePlan } from '@/hooks/use-plan';

interface RolesPageProps {
    roles: Role[];
    canCreateCustomRoles: boolean;
}

export default function RolesIndex({ roles, canCreateCustomRoles }: RolesPageProps) {
    const { hasFeature } = usePlan();

    return (
        <div>
            <h1>Roles</h1>

            {/* Show all roles */}
            {roles.map(role => (
                <div key={role.id}>{role.name}</div>
            ))}

            {/* Create button - gated by feature */}
            {canCreateCustomRoles ? (
                <Button>Create Custom Role</Button>
            ) : (
                <div className="p-4 bg-gray-100 rounded">
                    <p>Custom roles are available on Professional and Enterprise plans</p>
                    <a href="/billing" className="text-blue-600 underline">
                        Upgrade to unlock
                    </a>
                </div>
            )}
        </div>
    );
}
```

### Exemplo 2: API Tokens (Feature + Limit)

```php
// Controller
namespace App\Http\Controllers\Tenant;

class ApiTokenController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:tenant.apiTokens:view', only: ['index']),
            new Middleware('permission:tenant.apiTokens:create', only: ['store']),
            new Middleware('feature:apiAccess'), // All methods require feature
            new Middleware('limit:apiCalls', only: ['store']), // Check API calls limit
        ];
    }

    public function index()
    {
        $tokens = auth()->user()->tokens;

        return Inertia::render('tenant/api-tokens/index', [
            'tokens' => $tokens,
            'apiCallsLimit' => Feature::for(tenant())->value('apiCallsLimit'),
            'apiCallsUsed' => tenant()->getCurrentUsage('apiCalls'),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $token = auth()->user()->createToken($validated['name']);

        return redirect()->back()->with('success', 'API token created!');
    }
}
```

Frontend:

```typescript
// resources/js/pages/tenant/api-tokens/index.tsx
import { usePlan } from '@/hooks/use-plan';
import { Progress } from '@/components/ui/progress';

interface ApiTokensProps {
    tokens: Token[];
    apiCallsLimit: number;
    apiCallsUsed: number;
}

export default function ApiTokensIndex({ tokens, apiCallsLimit, apiCallsUsed }: ApiTokensProps) {
    const { hasFeature } = usePlan();

    if (!hasFeature('apiAccess')) {
        return (
            <div className="p-4 bg-gray-100 rounded">
                <h2>API Access</h2>
                <p>Available on Professional and Enterprise plans</p>
                <a href="/billing" className="text-blue-600 underline">
                    Upgrade to unlock
                </a>
            </div>
        );
    }

    const usagePercent = apiCallsLimit === -1 ? 0 : (apiCallsUsed / apiCallsLimit) * 100;

    return (
        <div>
            <h1>API Tokens</h1>

            {/* Usage */}
            <div className="mb-4">
                <p>API Calls this month</p>
                <Progress value={usagePercent} />
                <span>
                    {apiCallsUsed} / {apiCallsLimit === -1 ? 'Unlimited' : apiCallsLimit}
                </span>
            </div>

            {/* Tokens list */}
            {tokens.map(token => (
                <div key={token.id}>{token.name}</div>
            ))}

            <Button>Create Token</Button>
        </div>
    );
}
```

### Exemplo 3: Plan Change Flow

```php
// Controller
namespace App\Http\Controllers\Tenant;

use App\Models\Plan;
use Laravel\Pennant\Feature;

class BillingController extends Controller
{
    public function index()
    {
        $tenant = tenant();
        $currentPlan = $tenant->plan;
        $availablePlans = Plan::active()->ordered()->get();

        return Inertia::render('tenant/billing/index', [
            'currentPlan' => $currentPlan,
            'availablePlans' => $availablePlans,
            'hasPaymentMethod' => $tenant->hasPaymentMethod(),
        ]);
    }

    public function changePlan(Request $request, Plan $plan)
    {
        $tenant = tenant();
        $oldPlan = $tenant->plan;

        // Validate downgrade
        if ($plan->price < $oldPlan->price) {
            $this->validateDowngrade($tenant, $plan);
        }

        try {
            // Change plan (triggers observer)
            $tenant->changePlan($plan);

            // Flash success with features info
            $newFeatures = Feature::for($tenant)->values([
                'customRoles',
                'apiAccess',
                'advancedReports',
            ]);

            return redirect()
                ->route('tenant.billing.index')
                ->with('success', "Successfully upgraded to {$plan->name}!")
                ->with('newFeatures', $newFeatures);

        } catch (\Exception $e) {
            return redirect()
                ->back()
                ->with('error', "Failed to change plan: {$e->getMessage()}");
        }
    }

    protected function validateDowngrade(Tenant $tenant, Plan $newPlan): void
    {
        // Check if current usage exceeds new plan limits
        $limits = [
            'users' => Feature::for($tenant)->value('maxUsers'),
            'projects' => Feature::for($tenant)->value('maxProjects'),
            'storage' => Feature::for($tenant)->value('storageLimit'),
        ];

        foreach ($limits as $resource => $newLimit) {
            $currentUsage = $tenant->getCurrentUsage($resource);

            if ($newLimit !== -1 && $currentUsage > $newLimit) {
                throw new \Exception("Cannot downgrade: You have {$currentUsage} {$resource}, but the new plan allows only {$newLimit}.");
            }
        }
    }
}
```

Frontend:

```typescript
// resources/js/pages/tenant/billing/index.tsx
import { usePlan } from '@/hooks/use-plan';

interface BillingProps {
    currentPlan: Plan;
    availablePlans: Plan[];
    hasPaymentMethod: boolean;
}

export default function Billing({ currentPlan, availablePlans, hasPaymentMethod }: BillingProps) {
    const { hasFeature } = usePlan();

    return (
        <div>
            <h1>Billing & Plans</h1>

            {/* Current plan */}
            <div className="mb-8">
                <h2>Current Plan: {currentPlan.name}</h2>
                <p>Price: {currentPlan.formatted_price}/month</p>

                {/* Features */}
                <div className="mt-4">
                    <h3>Features</h3>
                    <ul>
                        {hasFeature('customRoles') && <li>✅ Custom Roles</li>}
                        {hasFeature('apiAccess') && <li>✅ API Access</li>}
                        {hasFeature('advancedReports') && <li>✅ Advanced Reports</li>}
                        {hasFeature('sso') && <li>✅ SSO</li>}
                        {hasFeature('whiteLabel') && <li>✅ White Label</li>}
                    </ul>
                </div>
            </div>

            {/* Available plans */}
            <div className="grid grid-cols-3 gap-4">
                {availablePlans.map(plan => (
                    <PlanCard
                        key={plan.id}
                        plan={plan}
                        current={plan.id === currentPlan.id}
                        hasPaymentMethod={hasPaymentMethod}
                    />
                ))}
            </div>
        </div>
    );
}
```

---

## Testes

### Feature Test: Pennant Integration

```php
<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\Tenant;
use Laravel\Pennant\Feature;
use Tests\TenantTestCase;
use PHPUnit\Framework\Attributes\Test;

class PennantIntegrationTest extends TenantTestCase
{
    #[Test]
    public function starter_plan_has_no_custom_roles_feature()
    {
        $starterPlan = Plan::factory()->create([
            'slug' => 'starter',
            'features' => ['customRoles' => false],
        ]);

        $this->tenant->update(['plan_id' => $starterPlan->id]);

        // Check via Pennant
        $this->assertFalse(Feature::for($this->tenant)->active('customRoles'));
    }

    #[Test]
    public function professional_plan_has_custom_roles_feature()
    {
        $proPlan = Plan::factory()->create([
            'slug' => 'professional',
            'features' => ['customRoles' => true],
        ]);

        $this->tenant->update(['plan_id' => $proPlan->id]);

        // Check via Pennant
        $this->assertTrue(Feature::for($this->tenant)->active('customRoles'));
    }

    #[Test]
    public function max_users_feature_returns_correct_limit()
    {
        $proPlan = Plan::factory()->create([
            'slug' => 'professional',
            'limits' => ['users' => 50],
        ]);

        $this->tenant->update(['plan_id' => $proPlan->id]);

        // Check rich value via Pennant
        $maxUsers = Feature::for($this->tenant)->value('maxUsers');
        $this->assertEquals(50, $maxUsers);
    }

    #[Test]
    public function plan_override_takes_precedence()
    {
        $starterPlan = Plan::factory()->create([
            'slug' => 'starter',
            'features' => ['apiAccess' => false],
        ]);

        $this->tenant->update([
            'plan_id' => $starterPlan->id,
            'plan_features_override' => ['apiAccess' => true], // Override!
        ]);

        // Should use override
        $this->assertTrue(Feature::for($this->tenant)->active('apiAccess'));
    }
}
```

### Feature Test: Permission Sync on Plan Change

```php
<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\Role;
use App\Models\Permission;
use Tests\TenantTestCase;
use PHPUnit\Framework\Attributes\Test;

class PermissionSyncTest extends TenantTestCase
{
    #[Test]
    public function upgrading_to_professional_enables_api_permissions()
    {
        // Start with Starter plan (no API)
        $starterPlan = Plan::factory()->create([
            'slug' => 'starter',
            'features' => ['apiAccess' => false],
            'permission_map' => [],
        ]);

        $this->tenant->update(['plan_id' => $starterPlan->id]);

        // Owner should not have API permissions
        $this->actingAs($this->user);
        $this->assertFalse($this->user->can('tenant.apiTokens:view'));

        // Upgrade to Professional
        $proPlan = Plan::factory()->create([
            'slug' => 'professional',
            'features' => ['apiAccess' => true],
            'permission_map' => [
                'apiAccess' => [
                    'tenant.apiTokens:view',
                    'tenant.apiTokens:create',
                    'tenant.apiTokens:delete',
                ],
            ],
        ]);

        $this->tenant->update(['plan_id' => $proPlan->id]);

        // Permissions should be synced (via observer)
        // Refresh user
        $this->user->refresh()->unsetRelation('roles')->unsetRelation('permissions');

        // Now should have API permissions (if added to owner role)
        $ownerRole = Role::findByName('owner');
        $ownerRole->givePermissionTo(['tenant.apiTokens:view', 'tenant.apiTokens:create', 'tenant.apiTokens:delete']);

        $this->user->refresh()->unsetRelation('roles')->unsetRelation('permissions');
        $this->assertTrue($this->user->can('tenant.apiTokens:view'));
    }

    #[Test]
    public function downgrading_removes_plan_restricted_permissions()
    {
        // Start with Professional
        $proPlan = Plan::factory()->create([
            'slug' => 'professional',
            'features' => ['apiAccess' => true],
            'permission_map' => [
                'apiAccess' => [
                    'tenant.apiTokens:view',
                    'tenant.apiTokens:create',
                ],
            ],
        ]);

        $this->tenant->update(['plan_id' => $proPlan->id]);

        // Give user API permissions
        $this->user->givePermissionTo(['tenant.apiTokens:view', 'tenant.apiTokens:create']);
        $this->assertTrue($this->user->can('tenant.apiTokens:view'));

        // Downgrade to Starter (no API)
        $starterPlan = Plan::factory()->create([
            'slug' => 'starter',
            'features' => ['apiAccess' => false],
            'permission_map' => [],
        ]);

        $this->tenant->update(['plan_id' => $starterPlan->id]);

        // Permissions should be removed (via observer)
        $this->user->refresh()->unsetRelation('roles')->unsetRelation('permissions');

        // Should no longer have API permissions
        $this->assertFalse($this->user->can('tenant.apiTokens:view'));
    }
}
```

---

## Migration Path

### Phase 1: Setup (Week 1)

**Goal**: Install Pennant, create database schema, seeders

```bash
# Install Pennant
composer require laravel/pennant
sail artisan vendor:publish --provider="Laravel\Pennant\PennantServiceProvider"

# Run migrations
sail artisan migrate

# Create plans seeder
sail artisan make:seeder PlanSeeder

# Seed plans
sail artisan db:seed --class=PlanSeeder

# Sync plan permissions
sail artisan plans:sync-permissions
```

### Phase 2: Features (Week 2)

**Goal**: Create class-based features, integrate with Pennant

1. Create feature classes in `app/Features/`
2. Register features in `AppServiceProvider`
3. Test feature resolution
4. Update middleware to use features

### Phase 3: Permission Sync (Week 2-3)

**Goal**: Integrate permission sync system

1. Create `TenantObserver` for plan changes
2. Implement permission sync logic
3. Update Gate to check plan permissions
4. Test permission sync

### Phase 4: Frontend (Week 3)

**Goal**: Integrate Pennant features in frontend

1. Share features via Inertia
2. Create `usePlan()` hook
3. Update UI components
4. Test feature gates in UI

### Phase 5: Cashier (Week 4)

**Goal**: Integrate Laravel Cashier for billing

1. Install Cashier
2. Add Stripe integration
3. Create billing UI
4. Test subscription flow

**Total**: ~1 month

---

## Troubleshooting

### Pennant Cache Not Updating

**Problem**: Feature still returns old value after plan change

**Solution**:
```php
// Clear Pennant cache after plan change
Feature::for($tenant)->flushCache();

// Or clear specific feature
Feature::forget('customRoles');
```

### Permissions Not Syncing

**Problem**: User still has old permissions after downgrade

**Solution**:
```bash
# Check observer is registered
sail artisan tinker
>>> Tenant::observe(\App\Observers\TenantObserver::class);

# Manual sync
>>> $tenant = Tenant::find(1);
>>> $tenant->regeneratePlanPermissions();

# Clear permission cache
sail artisan permission:cache-reset
```

### Feature Not Resolving

**Problem**: `Feature::active('customRoles')` returns false unexpectedly

**Solution**:
```php
// Check plan has feature
$tenant = tenant();
dump($tenant->plan->features);

// Check Pennant definition
Feature::define('customRoles', function (Tenant $tenant) {
    dd($tenant->hasFeature('customRoles'));
});

// Clear Pennant storage
sail artisan pennant:purge customRoles
```

### Gate Always Denying

**Problem**: Gate denies access even though user has permission

**Solution**:
```php
// Check plan enables this permission
$tenant = tenant();
dump($tenant->getPlanEnabledPermissions());

// Check Gate before hook
Gate::before(function ($user, $ability) {
    Log::info("Gate check: {$ability}", [
        'plan_enabled' => tenant()->isPlanPermissionEnabled($ability),
    ]);
});
```

---

## Conclusão

### 🎯 Vantagens da Arquitetura Híbrida

1. **Elegant Code**: `Feature::active('customRoles')` vs `tenant()->hasFeature('customRoles')`
2. **Rich Values**: `Feature::value('maxUsers')` retorna int diretamente
3. **Type-Safe**: Class-based features com IDE support
4. **Automatic Permission Sync**: Plan change → permissions updated automatically
5. **Cashier-Ready**: Full billing integration
6. **Flexible**: Overrides, trials, custom deals
7. **Performant**: Pennant cache + plan cache
8. **Testable**: Mock features facilmente
9. **Scalable**: Suporta feature flags A/B testing no futuro
10. **Industry Standard**: Laravel first-party package

### 📊 Comparação Final

| Aspecto | Database-Driven | Hybrid (DB + Pennant) |
|---------|----------------|----------------------|
| Code Elegance | ⭐⭐⭐ | ⭐⭐⭐⭐⭐ |
| Feature Checks | `$tenant->hasFeature()` | `Feature::active()` |
| Rich Values | Manual JSON decode | Native support |
| Permission Sync | Manual | Automatic (observer) |
| Cashier Integration | ⭐⭐⭐⭐⭐ | ⭐⭐⭐⭐⭐ |
| Complexity | Medium | Medium-High |
| Learning Curve | Low | Medium |
| Future-Proof | ⭐⭐⭐⭐ | ⭐⭐⭐⭐⭐ |
| A/B Testing | ❌ | ✅ (Pennant) |
| Type Safety | ⭐⭐⭐ | ⭐⭐⭐⭐⭐ |

### 🚀 Recomendação

Use **Hybrid Architecture** se:
- ✅ Time confortável com Laravel (experiência média/alta)
- ✅ Quer código elegante e maintainable
- ✅ Planeja usar A/B testing ou soft launches
- ✅ Valoriza DX (Developer Experience)
- ✅ Aplicação de longo prazo (5+ anos)

Use **Database-Driven** se:
- ✅ Time pequeno ou júnior
- ✅ MVP rápido é prioridade
- ✅ Simplicidade > Elegância
- ✅ Não planeja feature flags avançados

---

## Próximos Passos

1. ✅ **Revisar documentação** com time
2. ✅ **Decidir arquitetura** (Hybrid recomendada)
3. ⏳ **Criar branch** `feature/plans-hybrid`
4. ⏳ **Implementar migrations**
5. ⏳ **Criar seeders** com 3 planos
6. ⏳ **Implementar Pennant features**
7. ⏳ **Criar permission sync system**
8. ⏳ **Atualizar frontend**
9. ⏳ **Testes completos**
10. ⏳ **Code review & merge**

**Estimated Time**: 3-4 semanas para MVP completo

---

## Referências

- **Laravel Pennant**: https://laravel.com/docs/12.x/pennant
- **Spatie Permission**: https://spatie.be/docs/laravel-permission
- **Stancl Tenancy**: https://tenancyforlaravel.com/
- **Laravel Cashier**: https://laravel.com/docs/12.x/billing
- **Database-Driven Architecture**: `docs/PLANS-ARCHITECTURE.md`
