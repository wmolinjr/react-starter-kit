# Plans & Features Architecture

## Visão Geral

Documento técnico para decidir a melhor arquitetura de gerenciamento de planos/pacotes (Starter, Professional, Enterprise) com limitações de recursos e features em uma aplicação SaaS multi-tenant.

**Objetivo**: Integrar **Stancl/Tenancy**, **Spatie Permission**, **Spatie MediaLibrary**, **Laravel ActivityLog** com sistema de planos que prepare o terreno para **Laravel Cashier** (Stripe/Paddle).

---

## 📋 Índice

1. [Contexto do Projeto](#contexto-do-projeto)
2. [Requisitos de Negócio](#requisitos-de-negócio)
3. [Arquiteturas Analisadas](#arquiteturas-analisadas)
4. [Comparação Detalhada](#comparação-detalhada)
5. [Decisão Recomendada](#decisão-recomendada)
6. [Implementação](#implementação)
7. [Integração com Cashier](#integração-com-cashier)
8. [Exemplos Práticos](#exemplos-práticos)
9. [Testes](#testes)
10. [Roadmap](#roadmap)

---

## Contexto do Projeto

### Stack Atual

- **Laravel 12** + **React 19** + **Inertia.js**
- **Stancl/Tenancy**: Single database + `tenant_id` isolation
- **Spatie Permission**: Roles & permissions (já implementado - 22 permissions, 3 roles)
- **Spatie MediaLibrary**: File uploads com tenant isolation
- **Laravel ActivityLog**: Audit logs (mencionado)
- **PostgreSQL 18** + **Redis** (cache, sessions, queues)

### O Que Já Existe

✅ **Multi-tenancy funcional**:
- Tenant model com custom columns support
- `BelongsToTenant` trait para isolamento de dados
- Session isolation por domínio
- Redis prefixing por tenant

✅ **Sistema de Permissions robusto**:
- 22 permissions em 5 categorias (projects, team, settings, billing, apiTokens)
- 3 roles (owner, admin, member)
- TypeScript types auto-gerados
- Frontend hooks (`usePermissions()`)

✅ **MediaLibrary integrado**:
- TenantPathGenerator (`tenants/{id}/media/...`)
- QueueTenancyBootstrapper para conversion jobs

### O Que Falta

❌ **Sistema de Planos**: Não existe ainda
❌ **Limitações de recursos**: Sem controle de quotas
❌ **Feature flags**: Sem controle de features por plano
❌ **Billing integration**: Preparar para Laravel Cashier

---

## Requisitos de Negócio

### Tipos de Limitações

#### 1. **Feature Flags** (Booleano)
Habilitar/desabilitar funcionalidades por plano:

- **Custom Roles**: Criar roles customizadas (Pro+)
- **API Access**: Acessar API (Pro+)
- **Advanced Reports**: Relatórios avançados (Enterprise)
- **SSO**: Single Sign-On (Enterprise)
- **White Label**: Custom branding (Enterprise)

#### 2. **Quotas Numéricas** (Limites)
Limites quantitativos por plano:

- **Users**: Número de usuários (Starter: 1, Pro: 50, Enterprise: unlimited)
- **Projects**: Número de projetos (Starter: 50, Pro: unlimited)
- **Storage**: Storage em GB (Starter: 1GB, Pro: 10GB, Enterprise: 100GB)
- **API Calls**: Chamadas/mês (Pro: 10k, Enterprise: unlimited)
- **Log Retention**: Retenção de logs em dias (Starter: 30, Pro: 90, Enterprise: 365)

#### 3. **Gerenciamento Dinâmico de Roles**
Roles atribuídas/removidas automaticamente baseado no plano:

- **Starter**: Apenas role `owner` (single user)
- **Professional**: Roles `owner`, `admin`, `member` disponíveis
- **Enterprise**: Todas roles + custom roles

### Exemplo de Planos

```yaml
Starter:
  price: $29/mês
  features:
    - Projects access
    - Basic permissions (owner only)
  quotas:
    - users: 1
    - projects: 50
    - storage: 1GB
    - log_retention: 30 days
    - api_calls: 0 (no API)

Professional:
  price: $99/mês
  features:
    - Projects access
    - Custom Roles
    - API Access
    - Team management
  quotas:
    - users: 50
    - projects: unlimited
    - storage: 10GB
    - log_retention: 90 days
    - api_calls: 10,000/mês

Enterprise:
  price: Custom
  features:
    - All Professional features
    - Advanced Reports
    - SSO
    - White Label
    - Priority Support
  quotas:
    - users: unlimited
    - projects: unlimited
    - storage: 100GB
    - log_retention: 365 days
    - api_calls: unlimited
```

---

## Arquiteturas Analisadas

### Arquitetura 1: **Database-Driven Config (Recomendada)**

Planos como dados no banco, configurações em JSON.

**Estrutura**:
```
tenants table:
  - plan_id (FK para plans)
  - plan_features (JSON) - override customizado
  - plan_limits (JSON) - override customizado

plans table:
  - id, name, slug
  - features (JSON) - { "customRoles": true, "apiAccess": true }
  - limits (JSON) - { "users": 50, "projects": -1, "storage": 10 }
  - stripe_price_id (para Cashier)
```

**Prós**:
✅ **Flexível**: Adicionar/editar planos via admin sem deploy
✅ **Cashier-ready**: `stripe_price_id` built-in
✅ **Overrides**: Tenant pode ter custom limits (casos especiais)
✅ **Auditável**: Mudanças de plano via ActivityLog
✅ **Seed-friendly**: Criar planos via seeders
✅ **Multi-currency**: Suporta múltiplos preços por plano

**Contras**:
❌ **Complexidade**: Mais tabelas, migrations, models
❌ **JSON Schema**: Sem validação forte de schema (pode usar casts)
❌ **Performance**: Query extra para buscar plano (mitigável com cache)

---

### Arquitetura 2: **Config File-Based**

Planos em arquivo de configuração PHP.

**Estrutura**:
```php
// config/plans.php
return [
    'starter' => [
        'name' => 'Starter',
        'features' => ['projects' => true],
        'limits' => ['users' => 1, 'projects' => 50],
    ],
    'professional' => [
        'name' => 'Professional',
        'features' => ['projects' => true, 'customRoles' => true],
        'limits' => ['users' => 50, 'projects' => -1],
    ],
];
```

**Prós**:
✅ **Simples**: Apenas 1 coluna no tenant (`plan_slug`)
✅ **Version controlled**: Mudanças rastreadas no Git
✅ **Type-safe**: IDE autocomplete
✅ **Zero queries**: Sem join com `plans` table
✅ **Fast**: Cache de configuração Laravel

**Contras**:
❌ **Deploy obrigatório**: Toda mudança requer deploy
❌ **Sem overrides**: Difícil ter custom limits por tenant
❌ **Não auditável**: Mudanças no config não ficam no log
❌ **Multi-currency complicado**: Hardcoded no config

---

### Arquitetura 3: **Laravel Pennant (Feature Flags)**

Laravel Pennant para feature flags com rich values.

**Estrutura**:
```php
// Feature flags com rich values
Feature::define('maxUsers', fn() => match(tenant()->plan_slug) {
    'starter' => 1,
    'professional' => 50,
    'enterprise' => -1,
});

Feature::define('customRoles', fn() => in_array(tenant()->plan_slug, ['professional', 'enterprise']));
```

**Prós**:
✅ **Built-in Laravel**: Package oficial (Laravel 11+)
✅ **Elegante**: Syntax limpa e expressiva
✅ **Rich values**: Retorna números, strings, arrays
✅ **Cache automático**: Performance otimizada
✅ **Scope-aware**: Funciona com User/Tenant scopes

**Contras**:
❌ **Ainda precisa de `plan_slug`**: Não elimina planos no banco
❌ **Sem Cashier integration**: Sem `stripe_price_id`
❌ **Complexo para billing**: Não gerencia subscriptions
❌ **Feature-specific**: Não substitui sistema de planos completo

---

### Arquitetura 4: **Hybrid (Database + Pennant)**

Combina Database-Driven para planos + Pennant para feature checks.

**Estrutura**:
```
tenants:
  - plan_id (FK)

plans:
  - id, name, features (JSON), limits (JSON)
  - stripe_price_id

Laravel Pennant:
  Feature::define('maxUsers', fn() => tenant()->plan->limits['users']);
  Feature::define('hasCustomRoles', fn() => tenant()->plan->features['customRoles']);
```

**Prós**:
✅ **Best of both worlds**: Flexibilidade do DB + elegância do Pennant
✅ **Cashier-ready**: Planos no DB com Stripe IDs
✅ **Clean code**: `Feature::active('maxUsers')` no código
✅ **Overrides**: Custom limits no DB quando necessário
✅ **Auditável**: Mudanças de plano no ActivityLog

**Contras**:
❌ **Mais complexo**: Duas abstrações para manter
❌ **Learning curve**: Time precisa entender Pennant
❌ **Overhead**: Pennant storage table + features table

---

## Comparação Detalhada

| Critério | Database-Driven | Config File | Pennant Only | Hybrid (DB + Pennant) |
|----------|----------------|-------------|--------------|----------------------|
| **Flexibilidade** | ⭐⭐⭐⭐⭐ | ⭐⭐ | ⭐⭐⭐ | ⭐⭐⭐⭐⭐ |
| **Cashier Integration** | ⭐⭐⭐⭐⭐ | ⭐⭐⭐ | ⭐ | ⭐⭐⭐⭐⭐ |
| **Simplicidade** | ⭐⭐⭐ | ⭐⭐⭐⭐⭐ | ⭐⭐⭐⭐ | ⭐⭐ |
| **Performance** | ⭐⭐⭐⭐ | ⭐⭐⭐⭐⭐ | ⭐⭐⭐⭐⭐ | ⭐⭐⭐⭐ |
| **Developer Experience** | ⭐⭐⭐⭐ | ⭐⭐⭐ | ⭐⭐⭐⭐⭐ | ⭐⭐⭐⭐⭐ |
| **Overrides** | ⭐⭐⭐⭐⭐ | ⭐ | ⭐⭐ | ⭐⭐⭐⭐⭐ |
| **Audit Trail** | ⭐⭐⭐⭐⭐ | ⭐ | ⭐⭐⭐ | ⭐⭐⭐⭐⭐ |
| **Type Safety** | ⭐⭐⭐ | ⭐⭐⭐⭐ | ⭐⭐⭐⭐⭐ | ⭐⭐⭐⭐ |
| **Manutenibilidade** | ⭐⭐⭐⭐ | ⭐⭐⭐ | ⭐⭐⭐⭐ | ⭐⭐⭐ |

### Recomendação por Cenário

#### Use **Database-Driven** se:
- ✅ Precisa de flexibilidade para custom plans
- ✅ Vai usar Laravel Cashier (Stripe/Paddle)
- ✅ Quer overrides personalizados por tenant
- ✅ Admin panel para gerenciar planos
- ✅ Audit trail de mudanças de plano

#### Use **Config File** se:
- ✅ Planos são fixos e raramente mudam
- ✅ Time pequeno, simplicidade é prioridade
- ✅ Não precisa de overrides customizados
- ✅ Startup/MVP simples

#### Use **Pennant Only** se:
- ✅ Apenas feature flags (sem billing)
- ✅ Experimentação/A-B testing
- ✅ Soft launches de features

#### Use **Hybrid** se:
- ✅ Quer o melhor dos dois mundos
- ✅ Time experiente com Laravel
- ✅ Aplicação de longo prazo
- ✅ Developer Experience é prioridade

---

## Decisão Recomendada

### 🏆 **Arquitetura Escolhida: Database-Driven (com Pennant opcional no futuro)**

**Razões**:

1. **Cashier-Ready**: `stripe_price_id` built-in, pronto para billing
2. **Flexibilidade**: Adicionar/editar planos sem deploy
3. **Overrides**: Custom limits para clientes especiais
4. **Auditável**: Mudanças rastreadas no `activity_log`
5. **Escalável**: Suporta multi-currency, trials, addons
6. **Industry Standard**: Mesma abordagem do Laravel Spark, Jetstream

**Path Forward**:
- **MVP**: Implementar Database-Driven puro
- **V2**: Adicionar Pennant para feature checks mais elegantes (opcional)
- **V3**: Integrar Laravel Cashier para pagamentos

---

## Implementação

### Passo 1: Database Schema

#### Migration: `create_plans_table`

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
            $table->string('name'); // "Starter", "Professional", "Enterprise"
            $table->string('slug')->unique(); // "starter", "professional", "enterprise"
            $table->text('description')->nullable();

            // Pricing
            $table->integer('price')->default(0); // Em centavos (2900 = $29.00)
            $table->string('currency', 3)->default('USD');
            $table->enum('billing_period', ['monthly', 'yearly'])->default('monthly');

            // Stripe/Paddle Integration
            $table->string('stripe_price_id')->nullable()->unique();
            $table->string('paddle_price_id')->nullable()->unique();

            // Features (JSON)
            // { "customRoles": true, "apiAccess": true, "advancedReports": false }
            $table->json('features')->nullable();

            // Limits (JSON)
            // { "users": 50, "projects": -1, "storage": 10240, "apiCalls": 10000 }
            // -1 = unlimited
            $table->json('limits')->nullable();

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

#### Migration: `add_plan_to_tenants_table`

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

            // Custom overrides para este tenant (opcional)
            // Sobrescreve values do plan quando não-null
            $table->json('plan_features_override')->nullable();
            $table->json('plan_limits_override')->nullable();

            // Trial
            $table->timestamp('trial_ends_at')->nullable();

            // Usage tracking (para quotas)
            $table->json('current_usage')->nullable();
            // { "users": 5, "projects": 23, "storage": 2048, "apiCalls": 1523 }

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
            ]);
        });
    }
};
```

### Passo 2: Models

#### Model: `Plan`

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
        'is_active',
        'is_featured',
        'sort_order',
    ];

    protected $casts = [
        'price' => 'integer',
        'features' => 'array',
        'limits' => 'array',
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
     * Get formatted price (e.g., "$29.00")
     */
    public function getFormattedPriceAttribute(): string
    {
        return '$' . number_format($this->price / 100, 2);
    }

    /**
     * Scope: Active plans only
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope: Order by sort_order
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order');
    }
}
```

#### Model: `Tenant` (atualizado)

```php
<?php

namespace App\Models;

use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;
use Stancl\Tenancy\Database\Concerns\HasDatabase;
use Stancl\Tenancy\Database\Concerns\HasDomains;
use Stancl\Tenancy\Database\Contracts\TenantWithDatabase;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Tenant extends BaseTenant implements TenantWithDatabase
{
    use HasDatabase, HasDomains;

    protected $casts = [
        'plan_features_override' => 'array',
        'plan_limits_override' => 'array',
        'current_usage' => 'array',
        'trial_ends_at' => 'datetime',
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /**
     * Check if tenant has a specific feature
     * Checks override first, then plan default
     */
    public function hasFeature(string $feature): bool
    {
        // Override primeiro
        if (isset($this->plan_features_override[$feature])) {
            return $this->plan_features_override[$feature];
        }

        // Fallback para plan default
        return $this->plan?->hasFeature($feature) ?? false;
    }

    /**
     * Get limit for a resource
     * Checks override first, then plan default
     */
    public function getLimit(string $resource): int
    {
        // Override primeiro
        if (isset($this->plan_limits_override[$resource])) {
            return $this->plan_limits_override[$resource];
        }

        // Fallback para plan default
        return $this->plan?->getLimit($resource) ?? 0;
    }

    /**
     * Check if resource limit is unlimited
     */
    public function isUnlimited(string $resource): bool
    {
        return $this->getLimit($resource) === -1;
    }

    /**
     * Get current usage for a resource
     */
    public function getCurrentUsage(string $resource): int
    {
        return $this->current_usage[$resource] ?? 0;
    }

    /**
     * Check if tenant has reached limit for a resource
     */
    public function hasReachedLimit(string $resource): bool
    {
        $limit = $this->getLimit($resource);

        // Unlimited
        if ($limit === -1) {
            return false;
        }

        $usage = $this->getCurrentUsage($resource);

        return $usage >= $limit;
    }

    /**
     * Increment usage for a resource
     */
    public function incrementUsage(string $resource, int $amount = 1): void
    {
        $currentUsage = $this->current_usage ?? [];
        $currentUsage[$resource] = ($currentUsage[$resource] ?? 0) + $amount;

        $this->update(['current_usage' => $currentUsage]);
    }

    /**
     * Decrement usage for a resource
     */
    public function decrementUsage(string $resource, int $amount = 1): void
    {
        $currentUsage = $this->current_usage ?? [];
        $currentUsage[$resource] = max(0, ($currentUsage[$resource] ?? 0) - $amount);

        $this->update(['current_usage' => $currentUsage]);
    }

    /**
     * Check if tenant is on trial
     */
    public function isOnTrial(): bool
    {
        return $this->trial_ends_at && $this->trial_ends_at->isFuture();
    }

    /**
     * Check if trial has ended
     */
    public function hasTrialEnded(): bool
    {
        return $this->trial_ends_at && $this->trial_ends_at->isPast();
    }
}
```

### Passo 3: Seeders

#### Seeder: `PlanSeeder`

```php
<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'name' => 'Starter',
                'slug' => 'starter',
                'description' => 'Perfect for individuals and small projects',
                'price' => 2900, // $29.00
                'currency' => 'USD',
                'billing_period' => 'monthly',
                'features' => [
                    'projects' => true,
                    'customRoles' => false,
                    'apiAccess' => false,
                    'advancedReports' => false,
                    'sso' => false,
                    'whiteLabel' => false,
                ],
                'limits' => [
                    'users' => 1,
                    'projects' => 50,
                    'storage' => 1024, // 1GB em MB
                    'logRetention' => 30, // dias
                    'apiCalls' => 0,
                ],
                'is_active' => true,
                'sort_order' => 1,
            ],
            [
                'name' => 'Professional',
                'slug' => 'professional',
                'description' => 'For growing teams and businesses',
                'price' => 9900, // $99.00
                'currency' => 'USD',
                'billing_period' => 'monthly',
                'features' => [
                    'projects' => true,
                    'customRoles' => true,
                    'apiAccess' => true,
                    'advancedReports' => false,
                    'sso' => false,
                    'whiteLabel' => false,
                ],
                'limits' => [
                    'users' => 50,
                    'projects' => -1, // unlimited
                    'storage' => 10240, // 10GB em MB
                    'logRetention' => 90,
                    'apiCalls' => 10000,
                ],
                'is_active' => true,
                'is_featured' => true,
                'sort_order' => 2,
            ],
            [
                'name' => 'Enterprise',
                'slug' => 'enterprise',
                'description' => 'For large organizations with advanced needs',
                'price' => 0, // Custom pricing
                'currency' => 'USD',
                'billing_period' => 'monthly',
                'features' => [
                    'projects' => true,
                    'customRoles' => true,
                    'apiAccess' => true,
                    'advancedReports' => true,
                    'sso' => true,
                    'whiteLabel' => true,
                ],
                'limits' => [
                    'users' => -1, // unlimited
                    'projects' => -1,
                    'storage' => 102400, // 100GB em MB
                    'logRetention' => 365,
                    'apiCalls' => -1, // unlimited
                ],
                'is_active' => true,
                'sort_order' => 3,
            ],
        ];

        foreach ($plans as $planData) {
            Plan::updateOrCreate(
                ['slug' => $planData['slug']],
                $planData
            );
        }

        $this->command->info('✅ Plans seeded successfully!');
    }
}
```

### Passo 4: Helpers & Facades

#### Helper: `app/Helpers/plan_helpers.php`

```php
<?php

use App\Models\Tenant;

if (!function_exists('tenant_has_feature')) {
    /**
     * Check if current tenant has a feature
     */
    function tenant_has_feature(string $feature): bool
    {
        $tenant = tenant();

        if (!$tenant) {
            return false;
        }

        return $tenant->hasFeature($feature);
    }
}

if (!function_exists('tenant_get_limit')) {
    /**
     * Get limit for a resource for current tenant
     */
    function tenant_get_limit(string $resource): int
    {
        $tenant = tenant();

        if (!$tenant) {
            return 0;
        }

        return $tenant->getLimit($resource);
    }
}

if (!function_exists('tenant_has_reached_limit')) {
    /**
     * Check if current tenant has reached limit
     */
    function tenant_has_reached_limit(string $resource): bool
    {
        $tenant = tenant();

        if (!$tenant) {
            return true;
        }

        return $tenant->hasReachedLimit($resource);
    }
}

if (!function_exists('tenant_can_add')) {
    /**
     * Check if tenant can add more of a resource
     */
    function tenant_can_add(string $resource): bool
    {
        return !tenant_has_reached_limit($resource);
    }
}
```

Registrar em `composer.json`:

```json
{
    "autoload": {
        "files": [
            "app/Helpers/plan_helpers.php"
        ]
    }
}
```

### Passo 5: Middleware

#### Middleware: `CheckPlanLimit`

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPlanLimit
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

        // Check if limit reached
        if ($tenant->hasReachedLimit($resource)) {
            // Return JSON for API requests
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => "You have reached the limit for {$resource}.",
                    'limit' => $tenant->getLimit($resource),
                    'current' => $tenant->getCurrentUsage($resource),
                    'upgrade_url' => route('tenant.billing.index'),
                ], 403);
            }

            // Redirect for web requests
            return redirect()
                ->route('tenant.billing.index')
                ->with('error', "You have reached the limit for {$resource}. Please upgrade your plan.");
        }

        return $next($request);
    }
}
```

#### Middleware: `CheckPlanFeature`

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPlanFeature
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

        // Check if tenant has feature
        if (!$tenant->hasFeature($feature)) {
            // Return JSON for API requests
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => "This feature is not available on your current plan.",
                    'feature' => $feature,
                    'upgrade_url' => route('tenant.billing.index'),
                ], 403);
            }

            // Redirect for web requests
            return redirect()
                ->route('tenant.billing.index')
                ->with('error', 'This feature is not available on your current plan. Please upgrade.');
        }

        return $next($request);
    }
}
```

Registrar em `bootstrap/app.php`:

```php
$middleware->alias([
    'plan.limit' => \App\Http\Middleware\CheckPlanLimit::class,
    'plan.feature' => \App\Http\Middleware\CheckPlanFeature::class,
]);
```

---

## Integração com Cashier

### Preparação para Laravel Cashier (Stripe)

O schema já está preparado com `stripe_price_id`.

#### Instalação

```bash
composer require laravel/cashier
sail artisan vendor:publish --tag="cashier-migrations"
sail artisan migrate
```

#### Model: `Tenant` (com Billable trait)

```php
use Laravel\Cashier\Billable;

class Tenant extends BaseTenant implements TenantWithDatabase
{
    use HasDatabase, HasDomains, Billable;

    // ... código existente ...

    /**
     * Subscribe tenant to a plan
     */
    public function subscribeToPlan(Plan $plan, string $paymentMethod = null): void
    {
        // Create Stripe subscription
        $subscription = $this->newSubscription('default', $plan->stripe_price_id)
            ->create($paymentMethod);

        // Update tenant plan
        $this->update(['plan_id' => $plan->id]);

        // Log activity
        activity()
            ->performedOn($this)
            ->withProperties([
                'plan' => $plan->name,
                'price' => $plan->price,
            ])
            ->log('Subscribed to plan');
    }

    /**
     * Change tenant plan
     */
    public function changePlan(Plan $newPlan): void
    {
        $oldPlan = $this->plan;

        // Swap Stripe subscription
        $this->subscription('default')->swap($newPlan->stripe_price_id);

        // Update tenant plan
        $this->update(['plan_id' => $newPlan->id]);

        // Log activity
        activity()
            ->performedOn($this)
            ->withProperties([
                'old_plan' => $oldPlan->name,
                'new_plan' => $newPlan->name,
            ])
            ->log('Changed plan');
    }

    /**
     * Cancel subscription
     */
    public function cancelSubscription(): void
    {
        $this->subscription('default')->cancel();

        // Log activity
        activity()
            ->performedOn($this)
            ->log('Cancelled subscription');
    }
}
```

#### Webhook Handler

```php
// app/Http/Controllers/WebhookController.php
namespace App\Http\Controllers;

use Laravel\Cashier\Http\Controllers\WebhookController as CashierController;

class WebhookController extends CashierController
{
    /**
     * Handle subscription updated
     */
    public function handleCustomerSubscriptionUpdated(array $payload)
    {
        $tenant = $this->getTenantByStripeId($payload['data']['object']['customer']);

        if ($tenant) {
            // Sync plan based on Stripe subscription
            $stripePriceId = $payload['data']['object']['items']['data'][0]['price']['id'];
            $plan = Plan::where('stripe_price_id', $stripePriceId)->first();

            if ($plan) {
                $tenant->update(['plan_id' => $plan->id]);
            }
        }

        return parent::handleCustomerSubscriptionUpdated($payload);
    }

    protected function getTenantByStripeId(string $stripeId): ?Tenant
    {
        return Tenant::where('stripe_id', $stripeId)->first();
    }
}
```

---

## Exemplos Práticos

### Exemplo 1: Controller com Limit Check

```php
namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TeamController extends Controller
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:tenant.team:invite', only: ['invite']),
            new Middleware('plan.limit:users', only: ['invite']), // ⭐ Check limit
        ];
    }

    public function index()
    {
        $tenant = tenant();
        $members = $tenant->users()->get();

        return Inertia::render('tenant/team/index', [
            'members' => $members,
            'plan' => [
                'name' => $tenant->plan->name,
                'usersLimit' => $tenant->getLimit('users'),
                'usersCount' => $tenant->getCurrentUsage('users'),
                'canAddUsers' => !$tenant->hasReachedLimit('users'),
            ],
        ]);
    }

    public function invite(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'role' => 'required|in:admin,member',
        ]);

        // Create user
        $user = User::create([
            'email' => $validated['email'],
            'tenant_id' => tenant()->id,
        ]);

        // Assign role
        $user->assignRole($validated['role']);

        // Increment usage
        tenant()->incrementUsage('users');

        return redirect()->back()->with('success', 'User invited successfully!');
    }

    public function remove(User $user)
    {
        $user->delete();

        // Decrement usage
        tenant()->decrementUsage('users');

        return redirect()->back()->with('success', 'User removed successfully!');
    }
}
```

### Exemplo 2: Controller com Feature Check

```php
namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use Inertia\Inertia;

class ApiTokenController extends Controller
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:tenant.apiTokens:view', only: ['index']),
            new Middleware('plan.feature:apiAccess', only: ['index', 'create', 'destroy']), // ⭐ Check feature
        ];
    }

    public function index()
    {
        $tokens = auth()->user()->tokens;

        return Inertia::render('tenant/api-tokens/index', [
            'tokens' => $tokens,
            'plan' => [
                'name' => tenant()->plan->name,
                'hasApiAccess' => tenant()->hasFeature('apiAccess'),
                'apiCallsLimit' => tenant()->getLimit('apiCalls'),
                'apiCallsUsed' => tenant()->getCurrentUsage('apiCalls'),
            ],
        ]);
    }

    public function create(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $token = auth()->user()->createToken($validated['name']);

        return redirect()->back()->with('success', 'API token created!');
    }
}
```

### Exemplo 3: Frontend - Check Limits

```typescript
// resources/js/pages/tenant/team/index.tsx
import { usePermissions } from '@/hooks/use-permissions';
import { Button } from '@/components/ui/button';

interface TeamIndexProps {
    members: User[];
    plan: {
        name: string;
        usersLimit: number;
        usersCount: number;
        canAddUsers: boolean;
    };
}

export default function TeamIndex({ members, plan }: TeamIndexProps) {
    const { has } = usePermissions();

    return (
        <div>
            <h1>Team Members</h1>

            {/* Show usage */}
            <div className="mb-4">
                <p>
                    {plan.usersCount} / {plan.usersLimit === -1 ? 'Unlimited' : plan.usersLimit} users
                </p>
                {!plan.canAddUsers && (
                    <p className="text-red-600">
                        You've reached your plan limit.
                        <a href="/billing" className="underline">Upgrade your plan</a>
                    </p>
                )}
            </div>

            {/* Invite button */}
            {has('tenant.team:invite') && plan.canAddUsers && (
                <Button>Invite Member</Button>
            )}

            {/* Members list */}
            {members.map(member => (
                <div key={member.id}>{member.email}</div>
            ))}
        </div>
    );
}
```

### Exemplo 4: Frontend - Check Features

```typescript
// resources/js/pages/tenant/settings/index.tsx
import { usePermissions } from '@/hooks/use-permissions';

interface SettingsProps {
    tenant: Tenant;
    plan: {
        name: string;
        hasCustomRoles: boolean;
        hasWhiteLabel: boolean;
    };
}

export default function Settings({ tenant, plan }: SettingsProps) {
    const { has } = usePermissions();

    return (
        <div>
            <h1>Settings</h1>

            {/* Custom Roles - requires Pro+ */}
            {plan.hasCustomRoles ? (
                <div>
                    <h2>Custom Roles</h2>
                    <p>Create custom roles for your team</p>
                    {/* Custom roles UI */}
                </div>
            ) : (
                <div className="p-4 bg-gray-100 rounded">
                    <h2>Custom Roles</h2>
                    <p>Available on Professional and Enterprise plans</p>
                    <a href="/billing" className="text-blue-600 underline">
                        Upgrade to unlock
                    </a>
                </div>
            )}

            {/* White Label - Enterprise only */}
            {plan.hasWhiteLabel ? (
                <div>
                    <h2>Branding</h2>
                    <p>Customize your app's branding</p>
                    {/* White label UI */}
                </div>
            ) : (
                <div className="p-4 bg-gray-100 rounded">
                    <h2>Branding</h2>
                    <p>Available on Enterprise plan</p>
                    <a href="/billing" className="text-blue-600 underline">
                        Contact Sales
                    </a>
                </div>
            )}
        </div>
    );
}
```

### Exemplo 5: Observer para Track Usage

```php
// app/Observers/UserObserver.php
namespace App\Observers;

use App\Models\User;

class UserObserver
{
    public function created(User $user): void
    {
        // Increment users count
        if ($tenant = tenant()) {
            $tenant->incrementUsage('users');
        }
    }

    public function deleted(User $user): void
    {
        // Decrement users count
        if ($tenant = tenant()) {
            $tenant->decrementUsage('users');
        }
    }
}

// app/Providers/AppServiceProvider.php
public function boot(): void
{
    User::observe(UserObserver::class);
}
```

---

## Testes

### Feature Test: Plan Limits

```php
<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use Tests\TenantTestCase;
use PHPUnit\Framework\Attributes\Test;

class PlanLimitsTest extends TenantTestCase
{
    #[Test]
    public function starter_plan_cannot_add_more_than_one_user()
    {
        // Arrange: Tenant with Starter plan (limit: 1 user)
        $starterPlan = Plan::factory()->create([
            'slug' => 'starter',
            'limits' => ['users' => 1],
        ]);

        $this->tenant->update(['plan_id' => $starterPlan->id]);
        $this->tenant->incrementUsage('users'); // Owner already exists

        $this->actingAs($this->user);

        // Act: Try to invite second user
        $response = $this->post(route('tenant.team.invite'), [
            'email' => 'newuser@example.com',
            'role' => 'member',
        ]);

        // Assert: Should be blocked by middleware
        $response->assertForbidden();
        $this->assertDatabaseCount('users', 1);
    }

    #[Test]
    public function professional_plan_can_add_up_to_50_users()
    {
        // Arrange: Tenant with Professional plan
        $proPlan = Plan::factory()->create([
            'slug' => 'professional',
            'limits' => ['users' => 50],
        ]);

        $this->tenant->update(['plan_id' => $proPlan->id]);
        $this->actingAs($this->user);

        // Act: Invite 49 users (1 owner + 49 = 50)
        for ($i = 0; $i < 49; $i++) {
            $response = $this->post(route('tenant.team.invite'), [
                'email' => "user{$i}@example.com",
                'role' => 'member',
            ]);
            $response->assertSuccessful();
        }

        // Try to invite 51st user
        $response = $this->post(route('tenant.team.invite'), [
            'email' => 'user50@example.com',
            'role' => 'member',
        ]);

        // Assert: 51st should fail
        $response->assertForbidden();
    }

    #[Test]
    public function enterprise_plan_has_unlimited_users()
    {
        // Arrange: Tenant with Enterprise plan
        $enterprisePlan = Plan::factory()->create([
            'slug' => 'enterprise',
            'limits' => ['users' => -1], // unlimited
        ]);

        $this->tenant->update(['plan_id' => $enterprisePlan->id]);
        $this->actingAs($this->user);

        // Act: Add 100 users
        for ($i = 0; $i < 100; $i++) {
            $response = $this->post(route('tenant.team.invite'), [
                'email' => "user{$i}@example.com",
                'role' => 'member',
            ]);
            $response->assertSuccessful();
        }

        // Assert: All 100 should succeed
        $this->assertDatabaseCount('users', 101); // 100 + owner
    }
}
```

### Feature Test: Plan Features

```php
<?php

namespace Tests\Feature;

use App\Models\Plan;
use Tests\TenantTestCase;
use PHPUnit\Framework\Attributes\Test;

class PlanFeaturesTest extends TenantTestCase
{
    #[Test]
    public function starter_plan_cannot_access_api_tokens()
    {
        // Arrange: Tenant with Starter plan (no API access)
        $starterPlan = Plan::factory()->create([
            'slug' => 'starter',
            'features' => ['apiAccess' => false],
        ]);

        $this->tenant->update(['plan_id' => $starterPlan->id]);
        $this->actingAs($this->user);

        // Act
        $response = $this->get(route('tenant.api-tokens.index'));

        // Assert: Blocked by middleware
        $response->assertForbidden();
    }

    #[Test]
    public function professional_plan_can_access_api_tokens()
    {
        // Arrange: Tenant with Professional plan
        $proPlan = Plan::factory()->create([
            'slug' => 'professional',
            'features' => ['apiAccess' => true],
        ]);

        $this->tenant->update(['plan_id' => $proPlan->id]);
        $this->actingAs($this->user);

        // Act
        $response = $this->get(route('tenant.api-tokens.index'));

        // Assert
        $response->assertSuccessful();
    }

    #[Test]
    public function tenant_with_custom_override_uses_override_value()
    {
        // Arrange: Tenant with Starter plan but custom override
        $starterPlan = Plan::factory()->create([
            'slug' => 'starter',
            'features' => ['apiAccess' => false],
        ]);

        $this->tenant->update([
            'plan_id' => $starterPlan->id,
            'plan_features_override' => ['apiAccess' => true], // Override!
        ]);

        $this->actingAs($this->user);

        // Act
        $response = $this->get(route('tenant.api-tokens.index'));

        // Assert: Should work due to override
        $response->assertSuccessful();
    }
}
```

---

## Roadmap

### Phase 1: MVP (Current)
- ✅ Database schema (plans, tenant.plan_id)
- ✅ Models (Plan, Tenant with limits/features)
- ✅ Middleware (CheckPlanLimit, CheckPlanFeature)
- ✅ Helpers (tenant_has_feature, tenant_get_limit)
- ✅ Seeders (3 plans: Starter, Professional, Enterprise)
- ✅ Basic observers (auto-increment usage)

### Phase 2: Billing Integration (Q1 2025)
- ⏳ Laravel Cashier installation
- ⏳ Stripe integration (subscriptions)
- ⏳ Webhook handlers (subscription updates)
- ⏳ Billing UI (Inertia pages)
- ⏳ Invoice management

### Phase 3: Usage Tracking & Analytics (Q2 2025)
- ⏳ Detailed usage tracking (per resource)
- ⏳ Usage charts/graphs (frontend)
- ⏳ Usage alerts (approaching limits)
- ⏳ Historical usage data

### Phase 4: Advanced Features (Q2-Q3 2025)
- ⏳ Laravel Pennant integration (optional)
- ⏳ Plan add-ons (extra storage, users, etc.)
- ⏳ Multi-currency support
- ⏳ Annual billing discounts
- ⏳ Custom enterprise contracts

### Phase 5: Automation (Q3 2025)
- ⏳ Auto-assign roles based on plan
- ⏳ Auto-downgrade on subscription cancel
- ⏳ Grace period handling
- ⏳ Trial management automation

---

## Considerações Finais

### Integração com Sistema Atual

#### Spatie Permission
- **Roles continuam iguais**: owner, admin, member
- **Novo**: Roles podem ser restritas por plano
  - Starter: apenas `owner` pode ser atribuída
  - Professional+: todas as roles disponíveis

#### MediaLibrary
- **Quotas de storage**: `tenant()->getLimit('storage')` em MB
- **Check antes de upload**: `tenant()->hasReachedLimit('storage')`

#### ActivityLog
- **Log mudanças de plano**: `activity()->log('Changed plan')`
- **Log quando atinge limites**: Criar event/listener

### Performance

#### Cache Strategy
```php
// Cache plan para reduzir queries
$plan = Cache::remember("tenant.{$tenantId}.plan", 3600, fn() =>
    Tenant::find($tenantId)->plan
);
```

#### Eager Loading
```php
// Sempre carregar plan com tenant
Tenant::with('plan')->find($tenantId);
```

### Security

#### Validações Críticas
- ✅ **Backend validation**: NUNCA confiar apenas no frontend
- ✅ **Middleware layers**: Permissions + Limits + Features
- ✅ **Observer pattern**: Auto-track usage em model events
- ✅ **Atomic operations**: Usar transactions para mudanças de plano

---

## Conclusão

A **arquitetura Database-Driven** é a escolha recomendada por:

1. **Flexibilidade total**: Admin pode gerenciar planos
2. **Cashier-ready**: Integração natural com Stripe/Paddle
3. **Overrides**: Custom deals para clientes especiais
4. **Auditável**: ActivityLog rastreia mudanças
5. **Escalável**: Suporta features futuras (addons, multi-currency)

**Next Steps**:
1. Rodar migrations (`create_plans_table`, `add_plan_to_tenants_table`)
2. Rodar seeder (`PlanSeeder`)
3. Implementar middlewares (`CheckPlanLimit`, `CheckPlanFeature`)
4. Adicionar observers (UserObserver, ProjectObserver, etc.)
5. Criar UI de billing (listar planos, upgrade/downgrade)
6. Testes de integração

**Timeline Estimado**:
- MVP Implementation: 2-3 semanas
- Cashier Integration: 1-2 semanas
- Testing & Refinement: 1 semana
- **Total: ~1 mês para sistema completo**
