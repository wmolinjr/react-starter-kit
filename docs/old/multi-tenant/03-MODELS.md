# 03 - Models e Relacionamentos

## Índice

- [Tenant Model](#tenant-model)
- [Domain Model](#domain-model)
- [User Model](#user-model)
- [Tenant-Scoped Models](#tenant-scoped-models)
- [Traits Customizados](#traits-customizados)
- [Helpers e Macros](#helpers-e-macros)
- [Checklist](#checklist)

---

## Tenant Model

### Criar Model

```bash
php artisan make:model Tenant
```

### `app/Models/Tenant.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravel\Cashier\Billable;

class Tenant extends Model
{
    use HasFactory, Billable;

    protected $fillable = [
        'name',
        'slug',
        'settings',
    ];

    protected $casts = [
        'settings' => 'array',
    ];

    /**
     * Tenant tem muitos domínios
     */
    public function domains(): HasMany
    {
        return $this->hasMany(Domain::class);
    }

    /**
     * Tenant tem muitos usuários (N:N via pivot)
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot('role', 'permissions', 'invited_at', 'invitation_token', 'joined_at')
            ->withTimestamps();
    }

    /**
     * Owners do tenant
     */
    public function owners(): BelongsToMany
    {
        return $this->users()->wherePivot('role', 'owner');
    }

    /**
     * Admins do tenant
     */
    public function admins(): BelongsToMany
    {
        return $this->users()->wherePivot('role', 'admin');
    }

    /**
     * Members do tenant
     */
    public function members(): BelongsToMany
    {
        return $this->users()->whereIn('role', ['owner', 'admin', 'member']);
    }

    /**
     * Domínio primário do tenant
     */
    public function primaryDomain()
    {
        return $this->domains()->where('is_primary', true)->first();
    }

    /**
     * URL do tenant
     */
    public function url(): string
    {
        $domain = $this->primaryDomain();

        if (!$domain) {
            return config('app.url');
        }

        $protocol = config('app.env') === 'local' ? 'http://' : 'https://';

        return $protocol . $domain->domain;
    }

    /**
     * Obter setting específico
     */
    public function getSetting(string $key, mixed $default = null): mixed
    {
        return data_get($this->settings, $key, $default);
    }

    /**
     * Atualizar setting
     */
    public function updateSetting(string $key, mixed $value): bool
    {
        $settings = $this->settings ?? [];
        data_set($settings, $key, $value);

        return $this->update(['settings' => $settings]);
    }

    /**
     * Verificar se tenant tem feature habilitada
     */
    public function hasFeature(string $feature): bool
    {
        return (bool) $this->getSetting("features.{$feature}", false);
    }

    /**
     * Verificar limite
     */
    public function hasReachedLimit(string $limit, int $current): bool
    {
        $max = $this->getSetting("limits.{$limit}");

        if ($max === null) {
            return false; // Sem limite
        }

        return $current >= $max;
    }

    /**
     * Stripe customer name para Cashier
     */
    public function stripeCustomerName(): string
    {
        return $this->name;
    }

    /**
     * Stripe customer email para Cashier
     */
    public function stripeEmail(): string
    {
        $owner = $this->owners()->first();

        return $owner?->email ?? 'noreply@example.com';
    }
}
```

---

## Domain Model

### Criar Model

```bash
php artisan make:model Domain
```

### `app/Models/Domain.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Domain extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'domain',
        'is_primary',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
    ];

    /**
     * Domain pertence a um tenant
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Tornar este domínio primário
     * (remove is_primary de outros domínios do mesmo tenant)
     */
    public function makePrimary(): bool
    {
        // Remove primary de outros domínios
        $this->tenant->domains()->update(['is_primary' => false]);

        // Define este como primary
        return $this->update(['is_primary' => true]);
    }

    /**
     * Validar formato do domínio
     */
    public static function isValidDomain(string $domain): bool
    {
        return (bool) filter_var("http://{$domain}", FILTER_VALIDATE_URL);
    }
}
```

---

## User Model

### Atualizar `app/Models/User.php`

```php
<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'is_super_admin',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'is_super_admin' => 'boolean',
        'two_factor_confirmed_at' => 'datetime',
    ];

    /**
     * User pertence a muitos tenants (N:N via pivot)
     */
    public function tenants(): BelongsToMany
    {
        return $this->belongsToMany(Tenant::class)
            ->withPivot('role', 'permissions', 'invited_at', 'invitation_token', 'joined_at')
            ->withTimestamps();
    }

    /**
     * Obter tenant atual do contexto
     */
    public function currentTenant(): ?Tenant
    {
        if (!tenancy()->initialized) {
            return null;
        }

        return Tenant::find(tenant('id'));
    }

    /**
     * Role do usuário no tenant atual
     */
    public function currentTenantRole(): ?string
    {
        if (!tenancy()->initialized) {
            return null;
        }

        return $this->tenants()
            ->where('tenant_id', tenant('id'))
            ->first()
            ?->pivot
            ->role;
    }

    /**
     * Verificar se user tem role específico no tenant atual
     */
    public function hasRole(string $role): bool
    {
        return $this->currentTenantRole() === $role;
    }

    /**
     * Verificar se user tem um dos roles no tenant atual
     */
    public function hasAnyRole(array $roles): bool
    {
        return in_array($this->currentTenantRole(), $roles);
    }

    /**
     * Verificar se user é owner do tenant atual
     */
    public function isOwner(): bool
    {
        return $this->hasRole('owner');
    }

    /**
     * Verificar se user é admin ou owner do tenant atual
     */
    public function isAdminOrOwner(): bool
    {
        return $this->hasAnyRole(['owner', 'admin']);
    }

    /**
     * Verificar se user tem permissão específica no tenant atual
     */
    public function hasPermissionInTenant(string $permission): bool
    {
        $role = $this->currentTenantRole();

        return match($role) {
            'owner', 'admin' => true,
            'member' => in_array($permission, ['read', 'create', 'update']),
            'guest' => in_array($permission, ['read']),
            default => false,
        };
    }

    /**
     * Verificar se user pertence ao tenant atual
     */
    public function belongsToCurrentTenant(): bool
    {
        if (!tenancy()->initialized) {
            return false;
        }

        return $this->tenants()
            ->where('tenant_id', tenant('id'))
            ->exists();
    }

    /**
     * Obter todos os tenants onde user é owner
     */
    public function ownedTenants(): BelongsToMany
    {
        return $this->tenants()->wherePivot('role', 'owner');
    }

    /**
     * Switch para outro tenant
     */
    public function switchToTenant(Tenant $tenant): bool
    {
        if (!$this->tenants()->where('tenant_id', $tenant->id)->exists()) {
            return false;
        }

        tenancy()->initialize($tenant);

        return true;
    }
}
```

---

## Tenant-Scoped Models

### Trait BelongsToTenant

Crie um trait customizado (ou use o do pacote):

```bash
mkdir -p app/Traits
touch app/Traits/BelongsToTenant.php
```

### `app/Traits/BelongsToTenant.php`

```php
<?php

namespace App\Traits;

use App\Models\Tenant;
use App\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToTenant
{
    /**
     * Boot trait - adiciona global scope
     */
    protected static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new TenantScope);

        // Automaticamente define tenant_id ao criar
        static::creating(function ($model) {
            if (tenancy()->initialized && !$model->tenant_id) {
                $model->tenant_id = tenant('id');
            }
        });
    }

    /**
     * Model pertence a um tenant
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
```

### TenantScope (Global Scope)

```bash
mkdir -p app/Scopes
touch app/Scopes/TenantScope.php
```

### `app/Scopes/TenantScope.php`

```php
<?php

namespace App\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class TenantScope implements Scope
{
    /**
     * Aplica scope à query
     */
    public function apply(Builder $builder, Model $model): void
    {
        if (tenancy()->initialized) {
            $builder->where($model->getTable() . '.tenant_id', tenant('id'));
        }
    }
}
```

### Exemplo: Project Model

```bash
php artisan make:model Project
```

### `app/Models/Project.php`

```php
<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Project extends Model implements HasMedia
{
    use HasFactory, BelongsToTenant, InteractsWithMedia;

    protected $fillable = [
        'tenant_id',
        'user_id',
        'name',
        'description',
        'status',
    ];

    protected $casts = [
        'status' => 'string',
    ];

    /**
     * Project criado por um usuário
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Registrar media collections (Spatie)
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('attachments')
            ->useDisk('tenant_uploads');

        $this->addMediaCollection('images')
            ->useDisk('tenant_uploads')
            ->registerMediaConversions(function () {
                $this->addMediaConversion('thumb')
                    ->width(300)
                    ->height(300);
            });
    }

    /**
     * Scopes
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeArchived($query)
    {
        return $query->where('status', 'archived');
    }
}
```

---

## Traits Customizados

### HasTenantUsers Trait

Para facilitar relacionamentos com users no tenant atual:

```bash
touch app/Traits/HasTenantUsers.php
```

### `app/Traits/HasTenantUsers.php`

```php
<?php

namespace App\Traits;

use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait HasTenantUsers
{
    /**
     * Obter apenas users do tenant atual
     */
    public function scopeWithTenantUsers($query)
    {
        return $query->whereHas('user', function ($q) {
            $q->whereHas('tenants', function ($q) {
                $q->where('tenant_id', tenant('id'));
            });
        });
    }

    /**
     * Verificar se user tem acesso a este resource
     */
    public function userHasAccess(User $user): bool
    {
        return $this->tenant_id === tenant('id') && $user->belongsToCurrentTenant();
    }
}
```

---

## Helpers e Macros

### Helper Functions

Crie `app/Helpers/tenant_helpers.php`:

```php
<?php

use App\Models\Tenant;
use App\Models\User;

if (!function_exists('current_tenant')) {
    /**
     * Obter tenant atual
     */
    function current_tenant(): ?Tenant
    {
        if (!tenancy()->initialized) {
            return null;
        }

        return Tenant::find(tenant('id'));
    }
}

if (!function_exists('current_tenant_id')) {
    /**
     * Obter ID do tenant atual
     */
    function current_tenant_id(): ?int
    {
        return tenancy()->initialized ? tenant('id') : null;
    }
}

if (!function_exists('tenant_url')) {
    /**
     * Gerar URL para o tenant atual
     */
    function tenant_url(string $path = '/'): string
    {
        $tenant = current_tenant();

        if (!$tenant) {
            return url($path);
        }

        return $tenant->url() . $path;
    }
}

if (!function_exists('can_manage_team')) {
    /**
     * Verificar se user pode gerenciar equipe
     */
    function can_manage_team(?User $user = null): bool
    {
        $user = $user ?? auth()->user();

        if (!$user) {
            return false;
        }

        return $user->hasAnyRole(['owner', 'admin']);
    }
}

if (!function_exists('can_manage_billing')) {
    /**
     * Verificar se user pode gerenciar billing
     */
    function can_manage_billing(?User $user = null): bool
    {
        $user = $user ?? auth()->user();

        if (!$user) {
            return false;
        }

        return $user->isOwner();
    }
}
```

**Registrar helpers em `composer.json`:**

```json
{
    "autoload": {
        "files": [
            "app/Helpers/tenant_helpers.php"
        ],
        "psr-4": {
            "App\\": "app/",
            "Database\\Factories\\": "database/factories/",
            "Database\\Seeders\\": "database/seeders/"
        }
    }
}
```

**Executar:**
```bash
composer dump-autoload
```

### Query Builder Macros

Adicione ao `AppServiceProvider`:

```php
// app/Providers/AppServiceProvider.php

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Macro para queries tenant-scoped
        Builder::macro('forTenant', function ($tenantId = null) {
            $tenantId = $tenantId ?? current_tenant_id();

            return $this->where('tenant_id', $tenantId);
        });

        // Macro para queries SEM tenant scope (para admins)
        Builder::macro('withoutTenantScope', function () {
            return $this->withoutGlobalScope(TenantScope::class);
        });

        // Macro para verificar se user é owner do model
        Builder::macro('ownedBy', function (User $user) {
            return $this->where('user_id', $user->id);
        });
    }
}
```

**Uso:**

```php
// Query para tenant atual
$projects = Project::forTenant()->get();

// Query sem tenant scope (super admin)
$allProjects = Project::withoutTenantScope()->get();

// Query para projetos do user
$myProjects = Project::ownedBy(auth()->user())->get();
```

---

## Checklist

Antes de prosseguir, certifique-se:

- [x] Model `Tenant` criado com relacionamentos ✅
- [x] Model `Domain` criado ✅
- [x] Model `User` atualizado com relacionamentos tenants ✅
- [x] Trait `BelongsToTenant` criado ✅
- [x] Scope `TenantScope` criado ✅
- [x] Model `Project` (ou outro tenant-scoped) criado como exemplo ✅
- [x] Helpers de tenant criados e registrados ✅
- [x] Macros de Query Builder registrados no AppServiceProvider ✅
- [x] `composer dump-autoload` executado ✅

**Testar no Tinker:**

```bash
php artisan tinker
```

```php
// Criar tenant
$tenant = Tenant::create(['name' => 'Test Corp', 'slug' => 'test']);
$tenant->domains()->create(['domain' => 'test.myapp.test', 'is_primary' => true]);

// Criar user e associar ao tenant
$user = User::create([
    'name' => 'Test User',
    'email' => 'test@test.com',
    'password' => bcrypt('password'),
]);

$tenant->users()->attach($user->id, ['role' => 'owner', 'joined_at' => now()]);

// Verificar
$user->tenants; // Deve retornar 1 tenant
$tenant->owners; // Deve retornar 1 user
```

---

## Próximo Passo

Agora que os models estão prontos, vamos configurar as rotas:

➡️ **[04-ROUTING.md](04-ROUTING.md)** - Estratégia de Rotas

---

**Versão:** 1.0
**Última atualização:** 2025-11-19
