# Arquitetura do Sistema: Tenants, Plans, Addons, Features, Limits e Roles

Este documento descreve a arquitetura completa do sistema multi-tenant com planos de assinatura, add-ons, features, limites e controle de acesso baseado em roles.

## Visao Geral

```
┌─────────────────────────────────────────────────────────────────────────┐
│                           ARQUITETURA GERAL                             │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                         │
│  ┌─────────┐    ┌─────────┐    ┌───────────────────┐    ┌──────────────┐ │
│  │  Plan   │───>│ Tenant  │<───│ AddonSubscription │<───│    Addon     │ │
│  └────┬────┘    └────┬────┘    └───────────────────┘    └──────────────┘ │
│       │              │                                                  │
│       │              │         ┌──────────────────────────────────────┐ │
│       │              │         │        Feature Definitions          │ │
│       │              │         │        Limit Definitions            │ │
│       │              │         └──────────────────────────────────────┘ │
│       │              │                                                  │
│       v              v                                                  │
│  ┌─────────────────────────────────────┐                               │
│  │     Permissions (Spatie)            │                               │
│  │  ┌──────────┐    ┌──────────────┐   │                               │
│  │  │   Role   │───>│  Permission  │   │                               │
│  │  └──────────┘    └──────────────┘   │                               │
│  └─────────────────────────────────────┘                               │
│                                                                         │
│  ┌─────────────────────────────────────┐                               │
│  │     Feature Flags (Pennant)         │                               │
│  └─────────────────────────────────────┘                               │
│                                                                         │
└─────────────────────────────────────────────────────────────────────────┘
```

---

## 1. Tenant (Multi-Tenancy)

### Conceito
O **Tenant** e a unidade central do sistema multi-tenant. Cada tenant representa uma organizacao/empresa que possui seus proprios usuarios, projetos, configuracoes e assinatura.

### Model: `App\Models\Central\Tenant`

```php
// Relacionamentos principais
belongsTo('plan')              // Plano de assinatura atual
hasMany('domains')             // Dominios customizados
hasMany('addonSubscriptions')  // Add-ons ativos (AddonSubscription)
hasMany('addonPurchases')      // Historico de compras

// Nota: Com multi-database tenancy, users, roles e projects
// ficam no banco do tenant (App\Models\Tenant\*)
```

### Colunas Importantes

| Coluna | Tipo | Descricao |
|--------|------|-----------|
| `plan_id` | FK | Plano de assinatura atual |
| `plan_features_override` | JSON | Sobrescreve features do plano |
| `plan_limits_override` | JSON | Sobrescreve limites do plano |
| `current_usage` | JSON | Uso atual: `{users: 5, projects: 23, storage: 2048}` |
| `plan_enabled_permissions` | JSON | Cache de permissoes habilitadas pelo plano |
| `trial_ends_at` | timestamp | Fim do periodo de teste |
| `stripe_id` | string | ID do cliente no Stripe |

### Metodos de Features e Limites

```php
// Verificar se feature esta habilitada
$tenant->hasFeature('customRoles');  // Prioridade: override > trial > plan

// Obter limite de um recurso
$tenant->getLimit('users');          // Retorna int (-1 = ilimitado)

// Verificar se atingiu o limite
$tenant->hasReachedLimit('projects'); // usage >= limit

// Obter limites efetivos (plan + addons + overrides)
$tenant->getEffectiveLimits();

// Incrementar uso
$tenant->incrementUsage('projects');
$tenant->decrementUsage('projects');

// Verificar permissao do plano
$tenant->isPlanPermissionEnabled('roles:create');
```

### Fluxo de Verificacao de Features

```
hasFeature('customRoles')
    │
    ├──> 1. Verifica plan_features_override['customRoles']
    │        Se definido → retorna valor
    │
    ├──> 2. Verifica se esta em trial (trial_ends_at > now)
    │        Se sim → retorna true (trial tem tudo)
    │
    └──> 3. Verifica plan->hasFeature('customRoles')
             Retorna valor do plano
```

---

## 2. Plan (Planos de Assinatura)

### Conceito
O **Plan** define os tiers de assinatura com suas features, limites e mapeamento de permissoes.

### Model: `App\Models\Central\Plan`

```php
// Relacionamentos
hasMany('tenants')             // Tenants usando este plano
belongsToMany('addons')        // Add-ons disponiveis via pivot addon_plan
```

### Estrutura do Plano

```php
// Exemplo de plano Professional
[
    'name' => 'Professional',
    'slug' => 'professional',
    'price' => 9900,           // Em centavos ($99.00)
    'currency' => 'BRL',
    'billing_period' => 'monthly',

    // Features habilitadas
    'features' => [
        'projects' => true,
        'customRoles' => true,
        'apiAccess' => true,
        'advancedReports' => false,
        'sso' => false,
        'whiteLabel' => false,
    ],

    // Limites de recursos
    'limits' => [
        'users' => 50,           // 50 usuarios
        'projects' => -1,        // Ilimitado
        'storage' => 10240,      // 10GB em MB
        'apiCalls' => 100000,    // 100k chamadas/mes
    ],

    // Mapeamento feature -> permissoes
    'permission_map' => [
        'projects' => [
            'projects:view',
            'projects:create',
            'projects:edit',
            'projects:delete',
        ],
        'customRoles' => [
            'roles:view',
            'roles:create',
            'roles:edit',
            'roles:delete',
        ],
        'apiAccess' => [
            'apiTokens:view',
            'apiTokens:create',
            'apiTokens:delete',
        ],
    ],
]
```

### Metodos Importantes

```php
// Verificar feature
$plan->hasFeature('customRoles');

// Obter limite
$plan->getLimit('users');

// Obter permissoes de uma feature
$plan->getPermissionsForFeature('customRoles');

// Obter todas as permissoes habilitadas
$plan->getAllEnabledPermissions();

// Expandir wildcards (roles:* → todas as permissoes de roles)
$plan->expandPermissions(['roles:*']);
```

### Planos Padrao (Seeder)

| Plano | Preco | Features | Limites |
|-------|-------|----------|---------|
| **Starter** | $29/mes | Projetos basicos | 1 usuario, 3 projetos, 1GB |
| **Professional** | $99/mes | + Custom Roles, API Access | 50 usuarios, ilimitado projetos, 10GB |
| **Enterprise** | Custom | Tudo + SSO, White Label, Audit | Ilimitado |

---

## 3. Addon (Add-ons)

### Conceito
**Addons** sao recursos extras que podem ser comprados alem do plano. Podem aumentar limites (storage, usuarios) ou habilitar features.

### Model: `App\Models\Central\Addon` (Catalogo)

```php
// Relacionamentos
belongsToMany('plans')  // Disponibilidade por plano via addon_plan
```

### Estrutura do Addon

```php
[
    'slug' => 'storage_50gb',
    'name' => 'Storage 50GB',
    'type' => AddonType::STORAGE,  // STORAGE, USERS, PROJECTS, FEATURE, etc.
    'limit_key' => 'storage',      // Qual limite afeta
    'unit_value' => 50000,         // 50GB em MB
    'unit_label' => 'GB',

    // Precos em centavos
    'price_monthly' => 4900,
    'price_yearly' => 49000,
    'price_one_time' => null,

    // Quantidade
    'min_quantity' => 1,
    'max_quantity' => 20,
    'stackable' => true,           // Pode comprar multiplos

    // Stripe
    'stripe_product_id' => 'prod_xxx',
    'stripe_price_monthly_id' => 'price_xxx',
]
```

### Model: `App\Models\Central\AddonSubscription` (Instancia Comprada)

```php
// Relacionamentos
belongsTo('tenant')
hasMany('purchases')  // Historico de compras
```

### Estrutura do AddonSubscription

```php
[
    'tenant_id' => 1,
    'addon_slug' => 'storage_50gb',
    'addon_type' => AddonType::STORAGE,
    'quantity' => 2,               // 2 x 50GB = 100GB
    'price' => 4900,               // Preco por unidade
    'billing_period' => BillingPeriod::MONTHLY,
    'status' => AddonStatus::ACTIVE,
    'started_at' => '2024-01-01',
    'expires_at' => null,          // null = recorrente

    // Para add-ons metered
    'metered_usage' => 0,
    'metered_reset_at' => '2024-02-01',
]
```

### Tipos de Add-on (Enum)

```php
enum AddonType: string {
    case STORAGE = 'storage';      // Aumenta storage
    case USERS = 'users';          // Aumenta seats
    case PROJECTS = 'projects';    // Aumenta projetos
    case FEATURE = 'feature';      // Habilita feature
    case BANDWIDTH = 'bandwidth';  // Aumenta bandwidth
    case API_CALLS = 'api_calls';  // Aumenta chamadas API
}
```

### Status do Add-on (Enum)

```php
enum AddonStatus: string {
    case PENDING = 'pending';      // Aguardando pagamento
    case ACTIVE = 'active';        // Ativo
    case CANCELED = 'canceled';    // Cancelado
    case EXPIRED = 'expired';      // Expirado (one-time)
    case FAILED = 'failed';        // Falha no pagamento
}
```

### Calculo de Limites Efetivos

```
Limite Efetivo = Limite do Plano + (Valor Unitario x Quantidade) + Override

Exemplo:
- Plano Starter: 1GB storage
- Addon Storage 50GB: quantidade=2
- Limite Efetivo: 1GB + (50GB × 2) = 101GB
```

---

## 4. Feature Definitions

### Conceito
**FeatureDefinition** define o catalogo de features disponiveis no sistema, usadas na UI administrativa.

### Model: `App\Models\FeatureDefinition`

### Estrutura

```php
[
    'key' => 'customRoles',
    'name' => 'Custom Roles',
    'description' => 'Create and manage custom roles with granular permissions',
    'category' => 'security',
    'icon' => 'shield',
    'permissions' => [
        'roles:view',
        'roles:create',
        'roles:edit',
        'roles:delete',
    ],
    'is_active' => true,
    'sort_order' => 1,
]
```

### Features Padrao

| Key | Nome | Categoria | Permissoes |
|-----|------|-----------|------------|
| `customRoles` | Custom Roles | security | roles:* |
| `apiAccess` | API Access | integration | apiTokens:* |
| `advancedReports` | Advanced Reports | analytics | reports:* |
| `sso` | Single Sign-On | security | sso:* |
| `whiteLabel` | White Label | customization | branding:* |
| `auditLog` | Audit Log | security | audit:* |
| `prioritySupport` | Priority Support | support | - |

---

## 5. Limit Definitions

### Conceito
**LimitDefinition** define o catalogo de limites trackeaveis do sistema.

### Model: `App\Models\LimitDefinition`

### Estrutura

```php
[
    'key' => 'storage',
    'name' => 'Storage',
    'description' => 'Total storage space for files and media',
    'unit' => 'MB',
    'unit_label' => 'MB',
    'default_value' => 1024,       // 1GB default
    'allows_unlimited' => true,    // Pode ser -1
    'icon' => 'hard-drive',
    'is_active' => true,
    'sort_order' => 3,
]
```

### Limites Padrao

| Key | Nome | Unidade | Padrao |
|-----|------|---------|--------|
| `users` | Users | seats | 1 |
| `projects` | Projects | count | 3 |
| `storage` | Storage | MB | 1024 |
| `apiCalls` | API Calls | calls/month | 1000 |
| `logRetention` | Log Retention | days | 30 |

---

## 6. Roles e Permissions

### Conceito
Sistema de controle de acesso baseado em **Spatie Laravel Permission** com isolamento por tenant.

### Model: `App\Models\Shared\Role`

```php
// Estende Spatie Role - isolado por banco de dados
class Role extends SpatieRole {
    // Com multi-database tenancy, roles sao automaticamente
    // isolados por banco de dados do tenant
}
```

### Model: `App\Models\Shared\Permission`

```php
class Permission extends SpatiePermission {
    // Com multi-database tenancy, permissions sao automaticamente
    // isolados por banco de dados do tenant
}
```

### Convencao de Nomenclatura

```
{resource}:{action}

Exemplos:
- projects:view
- projects:create
- projects:editOwn
- projects:delete
- roles:*          // Wildcard = todas as acoes
- apiTokens:create
```

**Nota**: Formato simplificado (v5.0). Anteriormente usava-se `tenant.{resource}:{action}`.

### Roles Padrao

| Role | Tipo | Descricao | Permissoes |
|------|------|-----------|------------|
| `Super Admin` | Central | Acesso total ao sistema | Todas |
| `Central Admin` | Central | Admin da plataforma | Admin central |
| `owner` | Tenant | Dono do tenant | 22 permissoes |
| `admin` | Tenant | Administrador | 13 permissoes |
| `member` | Tenant | Membro basico | 6 permissoes |

### Isolamento por Tenant

```php
// Configuracao em config/permission.php
'teams' => true,
'team_foreign_key' => 'tenant_id',

// Cache por tenant (TenancyServiceProvider)
Spatie cache key: 'spatie.permission.cache.tenant.{tenant_id}'
```

---

## 7. Integracao Plan → Permissions

### Fluxo Completo

```
┌─────────────────────────────────────────────────────────────────────┐
│                    FLUXO: PLAN → PERMISSIONS                        │
├─────────────────────────────────────────────────────────────────────┤
│                                                                     │
│  1. Usuario assina plano Professional                               │
│     │                                                               │
│     v                                                               │
│  2. Tenant.plan_id = Plan (Professional)                           │
│     │                                                               │
│     v                                                               │
│  3. TenantObserver detecta mudanca em plan_id                      │
│     │                                                               │
│     v                                                               │
│  4. Tenant.regeneratePlanPermissions()                             │
│     ├─> Plan.getAllEnabledPermissions()                            │
│     │   ├─> Itera Plan.features {customRoles: true, ...}           │
│     │   ├─> Para cada feature habilitada, pega permission_map      │
│     │   └─> Coleta todas as permissoes                             │
│     │                                                               │
│     ├─> Plan.expandPermissions()                                   │
│     │   └─> Expande wildcards: roles:* → [view,create,...]         │
│     │                                                               │
│     └─> Salva em Tenant.plan_enabled_permissions (cache JSON)      │
│         │                                                           │
│         v                                                           │
│  5. TenantObserver.syncRolePermissions()                           │
│     ├─> Pega todas as roles do tenant                              │
│     ├─> Para cada role, filtra permissoes                          │
│     │   └─> Mantem apenas permissoes habilitadas pelo plano        │
│     └─> Remove permissoes revogadas                                │
│         │                                                           │
│         v                                                           │
│  6. Gate::before() verifica em cada acao                           │
│     ├─> Ability 'projects:view'                                    │
│     ├─> Esta em plan_enabled_permissions?                          │
│     │   ├─> Nao → DENY (independente da role)                      │
│     │   └─> Sim → continua verificacao normal                      │
│     └─> Usuario tem permissao via role? → ALLOW/DENY               │
│                                                                     │
└─────────────────────────────────────────────────────────────────────┘
```

### Exemplo Pratico

```php
// Plano Professional
$plan->features = [
    'projects' => true,
    'customRoles' => true,   // Habilitado
    'apiAccess' => true,     // Habilitado
    'advancedReports' => false,  // Desabilitado
];

$plan->permission_map = [
    'customRoles' => [
        'roles:view',
        'roles:create',
        'roles:edit',
        'roles:delete',
    ],
    'apiAccess' => [
        'apiTokens:view',
        'apiTokens:create',
        'apiTokens:delete',
    ],
    'advancedReports' => [
        'reports:view',
        'reports:export',
    ],
];

// Tenant no plano Professional
$tenant->plan_enabled_permissions = [
    'projects:view',
    'projects:create',
    // ... outras de projects
    'roles:view',
    'roles:create',
    'roles:edit',
    'roles:delete',
    'apiTokens:view',
    'apiTokens:create',
    'apiTokens:delete',
    // NAO inclui reports:* (feature desabilitada)
];

// Usuario com role 'admin' tenta criar role
Gate::allows('roles:create');
// 1. Gate::before() verifica: 'roles:create' em plan_enabled_permissions? SIM
// 2. Verifica role: admin tem 'roles:create'? SIM
// 3. Resultado: ALLOW

// Usuario com role 'admin' tenta exportar report
Gate::allows('reports:export');
// 1. Gate::before() verifica: 'reports:export' em plan_enabled_permissions? NAO
// 2. Resultado: DENY (nem verifica role)
```

---

## 8. Observers

### TenantObserver

**Arquivo:** `app/Observers/TenantObserver.php`

**Triggers:** Quando `Tenant` e atualizado

```php
public function updated(Tenant $tenant): void
{
    if ($tenant->isDirty('plan_id')) {
        // 1. Inicializa contexto de tenancy
        tenancy()->initialize($tenant);

        // 2. Regenera cache de permissoes
        $tenant->regeneratePlanPermissions();

        // 3. Limpa cache do Pennant
        Feature::for($tenant)->flushCache();

        // 4. Sincroniza permissoes das roles
        $this->syncRolePermissions($tenant);

        // 5. Loga atividade
        activity()->log('Plan changed');
    }
}
```

### AddonSubscriptionObserver

**Arquivo:** `app/Observers/AddonSubscriptionObserver.php`

**Triggers:** CRUD em `AddonSubscription`

```php
// Quando addon e criado, atualizado (status/quantity), deletado ou restaurado
public function created/updated/deleted/restored(AddonSubscription $addon): void
{
    $this->syncLimits($addon->tenant);
}

private function syncLimits(Tenant $tenant): void
{
    // Recalcula limites efetivos (plan + addons + overrides)
    app(AddonService::class)->syncTenantLimits($tenant);
}
```

---

## 9. Services

### Arquitetura de Services (Central/Tenant)

Os Services estao organizados por contexto:

```
app/Services/
├── Central/           # Operam no banco central
│   ├── AddonService.php
│   ├── CheckoutService.php
│   ├── ImpersonationService.php
│   ├── MeteredBillingService.php
│   ├── PlanFeatureResolver.php
│   ├── PlanPermissionResolver.php
│   ├── PlanService.php
│   ├── PlanSyncService.php
│   ├── RoleService.php
│   └── StripeSyncService.php
└── Tenant/            # Operam no banco do tenant
    ├── AuditLogService.php
    ├── BillingService.php
    ├── RoleService.php
    ├── TeamService.php
    └── TenantSettingsService.php
```

### Central Services

#### AddonService
**Arquivo:** `app/Services/Central/AddonService.php`

**Responsabilidades:**
- Gerenciar ciclo de vida de add-ons
- Integrar com Stripe
- Sincronizar limites

```php
class AddonService
{
    public function getCatalog(string $slug): ?array;
    public function getAvailableAddons(Tenant $tenant): array;
    public function purchase(Tenant $tenant, string $addonSlug, int $quantity, BillingPeriod $period): AddonSubscription;
    public function updateQuantity(AddonSubscription $addon, int $quantity): AddonSubscription;
    public function cancel(AddonSubscription $addon, ?string $reason): void;
    public function syncTenantLimits(Tenant $tenant): void;
    public function calculateTotalMonthlyCost(Tenant $tenant): int;
}
```

#### PlanService
**Arquivo:** `app/Services/Central/PlanService.php`

```php
class PlanService
{
    public function getAllPlans(): Collection;
    public function createPlan(array $data): Plan;
    public function updatePlan(Plan $plan, array $data): Plan;
    public function deletePlan(Plan $plan): void;
    public function syncToStripe(Plan $plan): void;
    public function syncAllToStripe(): int;
}
```

#### RoleService (Central)
**Arquivo:** `app/Services/Central/RoleService.php`

```php
class RoleService
{
    public function getAllRoles(): Collection;
    public function createRole(array $data): Role;
    public function updateRole(Role $role, array $data): Role;
    public function deleteRole(Role $role): void;
    public function getAllPermissions(): Collection;
    public function formatPermissionsByCategory(Collection $permissions): array;
}
```

### Tenant Services

#### TeamService
**Arquivo:** `app/Services/Tenant/TeamService.php`

```php
class TeamService
{
    public function getTeamMembers(): Collection;
    public function invite(string $email, string $role): TenantInvitation;
    public function acceptInvitation(string $token, array $userData): User;
    public function updateMemberRole(User $user, string $role): void;
    public function removeMember(User $user): void;
}
```

#### BillingService
**Arquivo:** `app/Services/Tenant/BillingService.php`

```php
class BillingService
{
    public function getBillingOverview(Tenant $tenant): array;
    public function createCheckout(Tenant $tenant, string $planSlug): Checkout;
    public function handleSuccessfulCheckout(Tenant $tenant): void;
    public function redirectToPortal(Tenant $tenant): Response;
    public function getDetailedInvoices(Tenant $tenant): array;
}
```

#### TenantSettingsService
**Arquivo:** `app/Services/Tenant/TenantSettingsService.php`

```php
class TenantSettingsService
{
    public function getAllSettings(Tenant $tenant): array;
    public function getBrandingSettings(Tenant $tenant): array;
    public function updateBranding(Tenant $tenant, array $data): void;
    public function addDomain(Tenant $tenant, string $domain): Domain;
    public function removeDomain(Tenant $tenant, string $domainId): void;
    public function updateLanguage(Tenant $tenant, string $locale): void;
    public function deleteTenant(Tenant $tenant): void;
}
```

---

## 10. Laravel Pennant (Feature Flags)

### Configuracao

```php
// config/pennant.php
'default' => 'database',
'stores' => [
    'database' => [
        'driver' => 'database',
        'table' => 'features',
    ]
],
'scope' => \App\Models\Central\Tenant::class,
```

### Uso

```php
// Verificar feature para tenant
Feature::for($tenant)->active('customRoles');

// No Tenant model (via HasFeatures trait)
$tenant->hasFeature('customRoles');

// Definir feature
Feature::for($tenant)->activate('customRoles');
Feature::for($tenant)->deactivate('customRoles');

// Limpar cache
Feature::for($tenant)->flushCache();
```

---

## 11. Diagrama de Banco de Dados

```
┌─────────────────┐     ┌─────────────────┐     ┌─────────────────┐
│     plans       │     │    tenants      │     │     addons      │
├─────────────────┤     ├─────────────────┤     ├─────────────────┤
│ id              │<────│ plan_id         │     │ id              │
│ name            │     │ id              │     │ slug            │
│ slug            │     │ name            │     │ name            │
│ price           │     │ slug            │     │ type            │
│ features (JSON) │     │ settings (JSON) │     │ limit_key       │
│ limits (JSON)   │     │ plan_features_  │     │ unit_value      │
│ permission_map  │     │   override      │     │ price_monthly   │
│ stripe_price_id │     │ plan_limits_    │     │ stripe_*        │
└─────────────────┘     │   override      │     └────────┬────────┘
                        │ current_usage   │              │
                        │ plan_enabled_   │              │
                        │   permissions   │              │
                        │ trial_ends_at   │     ┌────────┴────────┐
                        │ stripe_id       │     │   addon_plan    │
                        └────────┬────────┘     ├─────────────────┤
                                 │              │ addon_id        │
        ┌────────────────────────┼──────────────│ plan_id         │
        │                        │              │ price_override  │
        │                        │              │ included        │
        v                        v              └─────────────────┘
┌─────────────────────┐  ┌─────────────────┐
│ addon_subscriptions │  │  tenant_user    │
├─────────────────────┤  ├─────────────────┤
│ id                  │  │ tenant_id       │
│ tenant_id           │─>│ user_id         │
│ addon_slug          │  │ role            │
│ quantity            │  └─────────────────┘
│ price               │
│ billing_period      │  ┌─────────────────┐
│ status              │  │     roles       │
│ started_at          │  ├─────────────────┤
│ expires_at          │  │ id              │
│ metered_usage       │  │ tenant_id       │───> NULL = central
└─────────────────────┘  │ name            │
                        │ guard_name      │
                        │ display_name    │
                        └────────┬────────┘
                                 │
                                 │ role_has_permissions
                                 v
                        ┌─────────────────┐
                        │  permissions    │
                        ├─────────────────┤
                        │ id              │
                        │ tenant_id       │───> NULL = central
                        │ name            │
                        │ guard_name      │
                        │ category        │
                        │ central         │
                        └─────────────────┘
```

---

## 12. Casos de Uso Comuns

### Upgrade de Plano

```php
// 1. Usuario seleciona novo plano
$newPlan = Plan::find($planId);

// 2. Atualiza tenant (dispara observer)
$tenant->update(['plan_id' => $newPlan->id]);

// 3. Observer automaticamente:
//    - Regenera plan_enabled_permissions
//    - Sincroniza roles (remove permissoes revogadas)
//    - Limpa cache Pennant
```

### Compra de Add-on

```php
// 1. Usuario compra addon de storage
$addon = app(AddonService::class)->purchase(
    tenant: $tenant,
    addonSlug: 'storage_50gb',
    quantity: 2,
    period: BillingPeriod::MONTHLY
);

// 2. Observer automaticamente:
//    - Sincroniza limites efetivos
//    - Atualiza plan_limits_override
```

### Verificacao de Permissao

```php
// No controller
public function store(Request $request)
{
    // Gate::before() verifica automaticamente se plano permite
    $this->authorize('projects:create');

    // Ou manualmente
    if (!$tenant->isPlanPermissionEnabled('projects:create')) {
        abort(403, 'Upgrade your plan to create projects');
    }
}
```

### Verificacao de Limite

```php
// Antes de criar projeto
if ($tenant->hasReachedLimit('projects')) {
    return back()->with('error', 'Project limit reached. Upgrade your plan.');
}

// Criar projeto
$project = $tenant->projects()->create($data);

// Incrementar uso
$tenant->incrementUsage('projects');
```

---

## 13. Arquitetura de Models

### Organizacao por Namespace

```
app/Models/
├── Central/           # Banco central (dados globais)
│   ├── Addon.php
│   ├── AddonBundle.php
│   ├── AddonPurchase.php
│   ├── AddonSubscription.php
│   ├── Domain.php
│   ├── Plan.php
│   ├── Tenant.php
│   ├── TenantInvitation.php
│   └── User.php       # Admins centrais (Super Admin, Central Admin)
├── Tenant/            # Banco do tenant (dados isolados)
│   ├── Activity.php
│   ├── Media.php
│   ├── Project.php
│   ├── TenantTranslationOverride.php
│   └── User.php       # Usuarios do tenant (owner, admin, member)
└── Shared/         # Funcionam em ambos contextos
    ├── Permission.php
    └── Role.php
```

### Traits Importantes

| Trait | Namespace | Proposito |
|-------|-----------|-----------|
| `CentralConnection` | Central models | Forca conexao com banco central |
| `HasUuids` | Todos os models | UUID v7 como primary key |
| `BelongsToTenant` | Tenant models | Scope automatico por tenant |
| `HasTenantTranslations` | Role, Permission | Suporte a traducoes |

---

## 14. Arquivos Importantes

### Models

| Arquivo | Descricao |
|---------|-----------|
| `app/Models/Central/Tenant.php` | Model principal multi-tenant |
| `app/Models/Central/Plan.php` | Model de planos de assinatura |
| `app/Models/Central/Addon.php` | Catalogo de add-ons |
| `app/Models/Central/AddonSubscription.php` | Add-ons ativos |
| `app/Models/Central/User.php` | Admins centrais |
| `app/Models/Tenant/User.php` | Usuarios do tenant |
| `app/Models/Shared/Role.php` | Roles (isolado por banco) |
| `app/Models/Shared/Permission.php` | Permissoes (isolado por banco) |

### Services

| Arquivo | Descricao |
|---------|-----------|
| `app/Services/Central/AddonService.php` | Logica de add-ons |
| `app/Services/Central/PlanService.php` | CRUD de planos |
| `app/Services/Central/RoleService.php` | Roles centrais |
| `app/Services/Tenant/TeamService.php` | Gestao de equipe |
| `app/Services/Tenant/BillingService.php` | Billing e Stripe |
| `app/Services/Tenant/RoleService.php` | Roles do tenant |

### Observers e Providers

| Arquivo | Descricao |
|---------|-----------|
| `app/Observers/TenantObserver.php` | Sync de permissoes |
| `app/Observers/AddonSubscriptionObserver.php` | Sync de limites |
| `app/Providers/AppServiceProvider.php` | Gate::before(), MorphMap |

### Configuracao

| Arquivo | Descricao |
|---------|-----------|
| `config/permission.php` | Config Spatie Permission |
| `config/pennant.php` | Config Laravel Pennant |
| `config/tenancy.php` | Config Stancl Tenancy v4 |

---

## 15. Comandos Uteis

```bash
# Sincronizar permissoes
sail artisan permissions:sync

# Sincronizar permissoes dos planos
sail artisan plans:sync-permissions

# Seed de planos
sail artisan db:seed --class=PlanSeeder

# Testes relacionados
sail artisan test --filter=Plan
sail artisan test --filter=Permission
sail artisan test --filter=Tenant
```

---

## 16. Boas Praticas

1. **Nunca acesse `plan->features` diretamente** - Use `tenant->hasFeature()`
2. **Nunca modifique `plan_enabled_permissions` manualmente** - E auto-gerado
3. **Use Feature::for($tenant)** para feature flags do Pennant
4. **Sempre verifique limites antes de criar recursos**
5. **Use observers para manter caches sincronizados**
6. **Nao hardcode verificacoes de plano** - Use features como abstracaoY
