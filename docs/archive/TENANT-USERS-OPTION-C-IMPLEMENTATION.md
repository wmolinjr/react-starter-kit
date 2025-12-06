# Implementação: Usuários Apenas em Tenant (Opção C)

> **Status**: Documento de Implementação
> **Data**: Dezembro 2025
> **Versão Tenancy**: Stancl/Tenancy v4
> **Abordagem**: Máximo Isolamento (Sem Resource Syncing)

---

## 1. Visão Geral

### 1.1 Arquitetura Alvo

A Opção C implementa **isolamento total** de usuários, seguindo a recomendação do Stancl v4 de separar modelos:

- **`Admin`**: Usuários administrativos no banco **central** (super admins, suporte)
- **`User`**: Usuários de tenant no banco do **tenant** (owners, admins, members)

```
┌─────────────────────────────────────────────────────────────────────┐
│                     CENTRAL DATABASE (laravel)                       │
├─────────────────────────────────────────────────────────────────────┤
│  ✅ admins                      │  ✅ tenants                        │
│     - id (UUID)                 │     - id (UUID)                    │
│     - name                      │     - name                         │
│     - email                     │     - data (JSON)                  │
│     - password                  │                                    │
│     - is_super_admin            │  ✅ domains                        │
│                                 │                                    │
│  ✅ plans, addons               │  ✅ subscriptions                  │
│  ✅ telescope_entries           │  ✅ impersonation_tokens           │
│  ❌ users (REMOVIDA)            │  ❌ tenant_user (REMOVIDA)         │
└─────────────────────────────────────────────────────────────────────┘
                                    │
                                    │ Sem sincronização
                                    │ Apenas impersonation tokens
                                    │
                ┌───────────────────┼───────────────────┐
                ▼                   ▼                   ▼
┌─────────────────────┐  ┌─────────────────────┐  ┌─────────────────────┐
│ TENANT DB 1         │  │ TENANT DB 2         │  │ TENANT DB N         │
├─────────────────────┤  ├─────────────────────┤  ├─────────────────────┤
│ ✅ users            │  │ ✅ users            │  │ ✅ users            │
│    - id (UUID)      │  │    - id (UUID)      │  │    - id (UUID)      │
│    - name           │  │    - name           │  │    - name           │
│    - email          │  │    - email          │  │    - email          │
│    - password       │  │    - password       │  │    - password       │
│    - role           │  │    - role           │  │    - role           │
│                     │  │                     │  │                     │
│ ✅ roles            │  │ ✅ roles            │  │ ✅ roles            │
│ ✅ permissions      │  │ ✅ permissions      │  │ ✅ permissions      │
│ ✅ model_has_roles  │  │ ✅ model_has_roles  │  │ ✅ model_has_roles  │
│ ✅ projects         │  │ ✅ projects         │  │ ✅ projects         │
│ ✅ activity_log     │  │ ✅ activity_log     │  │ ✅ activity_log     │
│ ✅ sessions         │  │ ✅ sessions         │  │ ✅ sessions         │
│ ✅ personal_access_ │  │ ✅ personal_access_ │  │ ✅ personal_access_ │
│    tokens           │  │    tokens           │  │    tokens           │
└─────────────────────┘  └─────────────────────┘  └─────────────────────┘
```

### 1.2 Benefícios

| Benefício | Descrição |
|-----------|-----------|
| **Isolamento Total** | Nenhum dado de usuário no banco central |
| **Compliance LGPD/HIPAA** | Deletar tenant = deletar todos os dados |
| **Performance Máxima** | Todas as queries são locais |
| **Simplicidade** | Sem sincronização, sem eventos complexos |
| **Autonomia do Tenant** | Tenant gerencia 100% dos seus usuários |

### 1.3 Trade-offs

| Trade-off | Mitigação |
|-----------|-----------|
| Usuário pode ter múltiplas contas (uma por tenant) | UUID + email único por tenant |
| Admin central precisa impersonation para acessar tenants | v4 UserImpersonation nativo |
| Sem login "único" cross-tenant | Cada tenant é independente |
| Recuperação de senha é por tenant | Fortify configurado por tenant |

---

## 2. Features Nativas do v4 Utilizadas

### 2.1 DatabaseTenancyBootstrapper

Troca automática de conexão de banco quando tenancy é inicializado.

```php
// config/tenancy.php
'bootstrappers' => [
    Stancl\Tenancy\Bootstrappers\DatabaseTenancyBootstrapper::class,
    // ... outros bootstrappers
],
```

### 2.2 DatabaseSessionBootstrapper

Sessões armazenadas no banco do tenant (isolamento automático).

```php
// config/tenancy.php
'bootstrappers' => [
    // ...
    Stancl\Tenancy\Bootstrappers\DatabaseSessionBootstrapper::class,
],
```

### 2.3 ScopeSessions Middleware

Previne session forgery entre tenants (v4 feature).

```php
// routes/tenant.php
Route::middleware([
    'web',
    InitializeTenancyByDomain::class,
    PreventAccessFromUnwantedDomains::class,
    'scope.sessions', // v4: Prevent session hijacking
])->group(function () {
    // ...
});
```

### 2.4 FortifyRouteBootstrapper

Configura redirects do Fortify para rotas tenant-aware.

```php
// config/tenancy.php
'bootstrappers' => [
    // ...
    Stancl\Tenancy\Bootstrappers\FortifyRouteBootstrapper::class,
],
```

### 2.5 UserImpersonation

Permite admin central acessar tenant via token single-use.

```php
// Central admin gera token
$token = tenancy()->impersonate(
    tenant: $tenant,
    userId: null, // Admin não tem userId no tenant
    redirectUrl: '/admin/dashboard',
);

// Redireciona para tenant com token
return Inertia::location($tenant->url() . '/impersonate/' . $token->token);
```

---

## 3. Estrutura de Arquivos

### 3.1 Models

```
app/Models/
├── Admin.php                    # NOVO: Usuários administrativos (central)
├── User.php                     # ALTERADO: Agora é tenant-only
├── Tenant.php                   # SEM ALTERAÇÃO
└── ...
```

### 3.2 Migrations (Central)

```
database/migrations/
├── 0001_01_01_000001_create_admins_table.php    # NOVA
├── xxxx_drop_users_table.php                    # NOVA (opcional, pode manter vazia)
├── xxxx_drop_tenant_user_table.php              # NOVA
└── ...
```

### 3.3 Migrations (Tenant)

```
database/migrations/tenant/
├── 0001_01_01_000000_create_users_table.php     # ALTERADA (completa)
├── 0001_01_01_000001_create_sessions_table.php  # NOVA
├── 0001_01_01_000002_create_cache_table.php     # NOVA (opcional)
├── xxxx_create_personal_access_tokens_table.php # NOVA
├── xxxx_create_password_reset_tokens_table.php  # NOVA
└── ...
```

### 3.4 Controllers

```
app/Http/Controllers/
├── Central/
│   ├── Admin/                   # Painel de super admin
│   │   ├── DashboardController.php
│   │   ├── TenantController.php
│   │   └── ImpersonationController.php  # NOVO
│   └── Auth/
│       ├── AdminLoginController.php     # NOVO
│       └── AdminLogoutController.php    # NOVO
└── Tenant/
    └── Admin/                   # SEM ALTERAÇÃO (já usa auth padrão)
```

---

## 4. Implementação Detalhada

### 4.1 Model Admin (Central)

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * Admin - Usuários administrativos do sistema central.
 *
 * Usados APENAS para:
 * - Super admins que gerenciam tenants
 * - Suporte técnico que acessa tenants via impersonation
 * - Operações de billing e planos
 *
 * NÃO são usuários de tenant. Não têm roles/permissions de tenant.
 */
class Admin extends Authenticatable
{
    use HasUuids, CentralConnection, Notifiable;

    protected $table = 'admins';

    protected $fillable = [
        'name',
        'email',
        'password',
        'is_super_admin',
        'locale',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_super_admin' => 'boolean',
        ];
    }

    /**
     * Verifica se o admin pode acessar um tenant específico.
     * Super admins podem acessar qualquer tenant.
     */
    public function canAccessTenant(Tenant $tenant): bool
    {
        return $this->is_super_admin;
    }

    /**
     * Scope para super admins.
     */
    public function scopeSuperAdmins($query)
    {
        return $query->where('is_super_admin', true);
    }
}
```

### 4.2 Model User (Tenant)

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\CausesActivity;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Permission\Traits\HasRoles;

/**
 * User - Usuários de tenant.
 *
 * Cada tenant tem sua própria tabela de users com isolamento total.
 * Não há conexão com banco central.
 *
 * Roles e permissions são locais (mesmo banco = sem workarounds).
 */
class User extends Authenticatable
{
    use HasApiTokens;
    use HasFactory;
    use HasRoles;
    use HasUuids;
    use Notifiable;
    use SoftDeletes;
    use LogsActivity;
    use CausesActivity;

    // NÃO usa CentralConnection - é tenant-only

    protected $fillable = [
        'name',
        'email',
        'password',
        'locale',
        'email_verified_at',
        // Campos específicos do tenant (opcionais)
        'department',
        'employee_id',
        'custom_settings',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'custom_settings' => 'array',
        ];
    }

    /**
     * Activity log configuration.
     * Agora funciona nativamente (mesmo banco).
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'email'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /**
     * Verifica se o usuário é owner do tenant.
     */
    public function isOwner(): bool
    {
        return $this->hasRole('owner');
    }

    /**
     * Verifica se o usuário é admin do tenant.
     */
    public function isAdmin(): bool
    {
        return $this->hasRole(['owner', 'admin']);
    }

    /**
     * Projects do usuário (mesmo banco = JOIN nativo).
     */
    public function projects()
    {
        return $this->hasMany(Project::class, 'created_by');
    }
}
```

### 4.3 Migration: Admins (Central)

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('admins', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->boolean('is_super_admin')->default(false);
            $table->string('locale')->default('pt_BR');
            $table->rememberToken();
            $table->timestamps();

            $table->index('is_super_admin');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('admins');
    }
};
```

### 4.4 Migration: Users (Tenant)

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->string('locale')->default('pt_BR');

            // Campos específicos do tenant (opcionais)
            $table->string('department')->nullable();
            $table->string('employee_id')->nullable();
            $table->json('custom_settings')->nullable();

            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();

            // Índices para performance
            $table->index('email');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
```

### 4.5 Migration: Sessions (Tenant)

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignUuid('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sessions');
    }
};
```

### 4.6 Migration: Password Reset Tokens (Tenant)

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('password_reset_tokens');
    }
};
```

### 4.7 Migration: Personal Access Tokens (Tenant)

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('personal_access_tokens', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuidMorphs('tokenable');
            $table->string('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('personal_access_tokens');
    }
};
```

---

## 5. Configuração de Autenticação

### 5.1 Auth Guards (config/auth.php)

```php
<?php

return [
    'defaults' => [
        'guard' => 'web',
        'passwords' => 'users',
    ],

    'guards' => [
        // Guard para usuários de tenant (padrão)
        'web' => [
            'driver' => 'session',
            'provider' => 'users',
        ],

        // Guard para admins centrais
        'admin' => [
            'driver' => 'session',
            'provider' => 'admins',
        ],

        // API tokens para tenant
        'sanctum' => [
            'driver' => 'sanctum',
            'provider' => 'users',
        ],
    ],

    'providers' => [
        // Usuários de tenant (banco do tenant)
        'users' => [
            'driver' => 'eloquent',
            'model' => App\Models\User::class,
        ],

        // Admins centrais (banco central)
        'admins' => [
            'driver' => 'eloquent',
            'model' => App\Models\Admin::class,
        ],
    ],

    'passwords' => [
        // Password reset para tenant users
        'users' => [
            'provider' => 'users',
            'table' => 'password_reset_tokens',
            'expire' => 60,
            'throttle' => 60,
        ],

        // Password reset para admins (central)
        'admins' => [
            'provider' => 'admins',
            'table' => 'password_reset_tokens',
            'expire' => 60,
            'throttle' => 60,
            'connection' => 'central', // Usa conexão central
        ],
    ],
];
```

### 5.2 Fortify Configuration

```php
<?php
// config/fortify.php

return [
    // Guard dinâmico baseado no contexto
    'guard' => env('FORTIFY_GUARD', 'web'),

    // Middleware para Fortify
    'middleware' => ['web'],

    // Limiter
    'limiters' => [
        'login' => 'login',
        'two-factor' => 'two-factor',
    ],

    // Features habilitadas
    'features' => [
        Features::registration(),
        Features::resetPasswords(),
        Features::emailVerification(),
        Features::updateProfileInformation(),
        Features::updatePasswords(),
        // Features::twoFactorAuthentication([
        //     'confirm' => true,
        //     'confirmPassword' => true,
        // ]),
    ],

    // Home redirect (tenant-aware via FortifyRouteBootstrapper)
    'home' => '/admin/dashboard',

    // Login view (Inertia)
    'views' => true,
];
```

### 5.3 FortifyServiceProvider

```php
<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Rate limiting
        RateLimiter::for('login', function (Request $request) {
            $throttleKey = strtolower($request->input(Fortify::username())).'|'.$request->ip();
            return Limit::perMinute(5)->by($throttleKey);
        });

        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });

        // Autenticação customizada (busca no banco do tenant)
        Fortify::authenticateUsing(function (Request $request) {
            // Em contexto de tenant, usa User do tenant
            $user = User::where('email', $request->email)->first();

            if ($user && Hash::check($request->password, $user->password)) {
                return $user;
            }

            return null;
        });

        // Views Inertia
        Fortify::loginView(fn () => inertia('auth/login'));
        Fortify::registerView(fn () => inertia('auth/register'));
        Fortify::requestPasswordResetLinkView(fn () => inertia('auth/forgot-password'));
        Fortify::resetPasswordView(fn (Request $request) => inertia('auth/reset-password', [
            'email' => $request->email,
            'token' => $request->route('token'),
        ]));
        Fortify::verifyEmailView(fn () => inertia('auth/verify-email'));
        Fortify::confirmPasswordView(fn () => inertia('auth/confirm-password'));
    }
}
```

---

## 6. Fluxos de Autenticação

### 6.1 Login de Usuário em Tenant

```
┌─────────────────┐
│ tenant1.localhost│
│    /login       │
└────────┬────────┘
         │
         ▼
┌─────────────────────────────────────────────────────────────┐
│ 1. InitializeTenancyByDomain middleware                     │
│    - Identifica tenant pelo domínio                         │
│    - DatabaseTenancyBootstrapper troca para tenant_xxx DB   │
└─────────────────────────────────────────────────────────────┘
         │
         ▼
┌─────────────────────────────────────────────────────────────┐
│ 2. Fortify::authenticateUsing()                             │
│    - User::where('email', $email)->first()                  │
│    - Query vai para banco do TENANT (já switchado)          │
│    - Verifica password hash                                 │
└─────────────────────────────────────────────────────────────┘
         │
         ▼
┌─────────────────────────────────────────────────────────────┐
│ 3. Session criada no banco do TENANT                        │
│    - DatabaseSessionBootstrapper ativo                      │
│    - sessions table no tenant_xxx database                  │
│    - Isolamento total de sessão                             │
└─────────────────────────────────────────────────────────────┘
         │
         ▼
┌─────────────────────────────────────────────────────────────┐
│ 4. Redirect para /admin/dashboard                           │
│    - FortifyRouteBootstrapper configura redirect            │
│    - Usuário autenticado no contexto do tenant              │
└─────────────────────────────────────────────────────────────┘
```

### 6.2 Login de Admin no Central

```
┌─────────────────┐
│  setor3.app     │
│  /admin/login   │
└────────┬────────┘
         │
         ▼
┌─────────────────────────────────────────────────────────────┐
│ 1. SEM tenancy (rota central)                               │
│    - RouteMode::CENTRAL é default                           │
│    - Conexão padrão (banco central)                         │
└─────────────────────────────────────────────────────────────┘
         │
         ▼
┌─────────────────────────────────────────────────────────────┐
│ 2. AdminLoginController                                     │
│    - Auth::guard('admin')->attempt()                        │
│    - Admin::where('email', $email)->first()                 │
│    - Verifica is_super_admin se necessário                  │
└─────────────────────────────────────────────────────────────┘
         │
         ▼
┌─────────────────────────────────────────────────────────────┐
│ 3. Session criada no banco CENTRAL                          │
│    - SESSION_DRIVER=redis ou database                       │
│    - Sessão separada de tenants                             │
└─────────────────────────────────────────────────────────────┘
         │
         ▼
┌─────────────────────────────────────────────────────────────┐
│ 4. Redirect para /admin/tenants                             │
│    - Dashboard de gerenciamento de tenants                  │
│    - Admin pode impersonate para acessar tenants            │
└─────────────────────────────────────────────────────────────┘
```

### 6.3 Admin Impersonation em Tenant

```
┌─────────────────┐
│  setor3.app     │
│  /admin/tenants │
│  [Acessar]      │
└────────┬────────┘
         │
         ▼
┌─────────────────────────────────────────────────────────────┐
│ 1. ImpersonationController::redirect($tenant)               │
│    - Verifica Admin::canAccessTenant($tenant)               │
│    - Gera token via tenancy()->impersonate()                │
└─────────────────────────────────────────────────────────────┘
         │
         ▼
┌─────────────────────────────────────────────────────────────┐
│ 2. Token gerado e salvo em impersonation_tokens (central)   │
│    - Single-use token                                       │
│    - Expira em 60 segundos                                  │
│    - user_id = NULL (admin não tem user no tenant)          │
└─────────────────────────────────────────────────────────────┘
         │
         ▼
┌─────────────────────────────────────────────────────────────┐
│ 3. Inertia::location() redirect para tenant                 │
│    - URL: tenant1.localhost/impersonate/{token}             │
│    - Cross-domain redirect                                  │
└─────────────────────────────────────────────────────────────┘
         │
         ▼
┌─────────────────────────────────────────────────────────────┐
│ 4. Tenant consome token                                     │
│    - UserImpersonation::makeResponse($token)                │
│    - Cria sessão temporária com flag `tenancy_impersonating`│
│    - Redirect para /admin/dashboard                         │
│    - Banner de impersonation visível                        │
└─────────────────────────────────────────────────────────────┘
```

### 6.4 Registro de Novo Usuário em Tenant

```
┌─────────────────┐
│ tenant1.localhost│
│    /register    │
└────────┬────────┘
         │
         ▼
┌─────────────────────────────────────────────────────────────┐
│ 1. Fortify registration                                     │
│    - CreateNewUser action                                   │
│    - User::create() no banco do TENANT                      │
└─────────────────────────────────────────────────────────────┘
         │
         ▼
┌─────────────────────────────────────────────────────────────┐
│ 2. Atribui role padrão                                      │
│    - $user->assignRole('member')                            │
│    - Roles estão no MESMO banco (sem workaround!)           │
└─────────────────────────────────────────────────────────────┘
         │
         ▼
┌─────────────────────────────────────────────────────────────┐
│ 3. Login automático                                         │
│    - Auth::login($user)                                     │
│    - Session no banco do tenant                             │
└─────────────────────────────────────────────────────────────┘
         │
         ▼
┌─────────────────────────────────────────────────────────────┐
│ 4. Redirect para dashboard                                  │
│    - Usuário autenticado e pronto                           │
│    - Nenhuma operação no banco central                      │
└─────────────────────────────────────────────────────────────┘
```

---

## 7. Configuração do Tenancy

### 7.1 TenancyServiceProvider

```php
<?php

namespace App\Providers;

use App\Jobs\CreateDatabase;
use App\Jobs\MigrateDatabase;
use App\Jobs\SeedTenantDatabase;
use App\Models\Tenant;
use Stancl\Tenancy\Events;
use Stancl\Tenancy\Jobs\DeleteDatabase;
use Stancl\Tenancy\Middleware;
use Stancl\Tenancy\Tenancy;
use Stancl\JobPipeline\JobPipeline;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class TenancyServiceProvider extends ServiceProvider
{
    public function events(): array
    {
        return [
            // Tenant creation pipeline
            Events\TenantCreated::class => [
                JobPipeline::make([
                    CreateDatabase::class,
                    MigrateDatabase::class,
                    SeedTenantDatabase::class,
                ])->send(function (Events\TenantCreated $event) {
                    return $event->tenant;
                })->shouldBeQueued(false),
            ],

            // Tenant deletion
            Events\DeletingTenant::class => [
                JobPipeline::make([
                    DeleteDatabase::class,
                ])->send(function (Events\DeletingTenant $event) {
                    return $event->tenant;
                })->shouldBeQueued(false),
            ],

            // Tenancy initialized
            Events\TenancyInitialized::class => [
                // Bootstrappers já configurados em config/tenancy.php
            ],

            // NÃO há eventos de Resource Syncing (Opção C não usa)
        ];
    }

    public function boot(): void
    {
        $this->configureStatefulDomains();
        $this->configureMiddleware();
    }

    protected function configureStatefulDomains(): void
    {
        // Domínios que podem ter sessão (central + tenants)
        Tenant::all()->each(function (Tenant $tenant) {
            foreach ($tenant->domains as $domain) {
                config(['sanctum.stateful' => array_merge(
                    config('sanctum.stateful', []),
                    [$domain->domain]
                )]);
            }
        });
    }

    protected function configureMiddleware(): void
    {
        // onFail handler para identificação
        Middleware\InitializeTenancyByDomain::$onFail = function ($exception, $request, $next) {
            // Se já inicializado, continua
            if (tenancy()->initialized) {
                return $next($request);
            }

            // Rota central, continua sem tenant
            return $next($request);
        };
    }
}
```

### 7.2 config/tenancy.php (Bootstrappers)

```php
<?php

return [
    'tenant_model' => \App\Models\Tenant::class,
    'domain_model' => \Stancl\Tenancy\Database\Models\Domain::class,

    'central_domains' => [
        'localhost',
        env('APP_CENTRAL_DOMAIN', 'setor3.app'),
    ],

    'bootstrappers' => [
        // Database switching (ESSENCIAL)
        Stancl\Tenancy\Bootstrappers\DatabaseTenancyBootstrapper::class,

        // Cache prefixing por tenant
        Stancl\Tenancy\Bootstrappers\CacheTenancyBootstrapper::class,

        // Filesystem paths por tenant
        Stancl\Tenancy\Bootstrappers\FilesystemTenancyBootstrapper::class,

        // Queue context
        Stancl\Tenancy\Bootstrappers\QueueTenancyBootstrapper::class,

        // Redis prefixing
        Stancl\Tenancy\Bootstrappers\RedisTenancyBootstrapper::class,

        // SESSION NO BANCO DO TENANT (v4)
        Stancl\Tenancy\Bootstrappers\DatabaseSessionBootstrapper::class,

        // Fortify redirects tenant-aware (v4)
        Stancl\Tenancy\Bootstrappers\FortifyRouteBootstrapper::class,

        // Spatie Permissions cache clearing
        App\Bootstrappers\SpatiePermissionsBootstrapper::class,
    ],

    'features' => [
        // User impersonation para admin acessar tenants
        Stancl\Tenancy\Features\UserImpersonation::class,

        // Cross-domain redirect (Inertia)
        Stancl\Tenancy\Features\CrossDomainRedirect::class,

        // Telescope tags
        Stancl\Tenancy\Features\TelescopeTags::class,
    ],

    // Database configuration
    'database' => [
        'prefix' => 'tenant_',
        'suffix' => '',
        'manager' => Stancl\Tenancy\Database\DatabaseManager::class,
    ],

    // Migração de seeds e migrations
    'migration_parameters' => [
        '--force' => true,
        '--path' => database_path('migrations/tenant'),
    ],

    'seeder_parameters' => [
        '--class' => 'TenantDatabaseSeeder',
    ],
];
```

---

## 8. Middleware e Rotas

### 8.1 Middleware VerifyTenantAccess (Simplificado)

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Stancl\Tenancy\Features\UserImpersonation;
use Symfony\Component\HttpFoundation\Response;

/**
 * Verifica se o usuário tem acesso ao tenant.
 *
 * Com Opção C, a verificação é MUITO mais simples:
 * - Se o usuário está autenticado, ele tem acesso
 * - Porque o usuário SÓ existe no banco do tenant onde foi criado
 * - Não há cross-database workarounds
 */
class VerifyTenantAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Impersonation tem acesso total
        if (UserImpersonation::isImpersonating()) {
            return $next($request);
        }

        // Se não autenticado, redireciona para login
        if (!$user) {
            return redirect()->route('login');
        }

        // Se autenticado, tem acesso (usuário só existe neste tenant)
        // Verificação de role específica fica nos controllers
        return $next($request);
    }
}
```

### 8.2 Rotas Tenant (routes/tenant.php)

```php
<?php

declare(strict_types=1);

use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromUnwantedDomains;
use App\Http\Middleware\VerifyTenantAccess;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Stancl\Tenancy\Features\UserImpersonation;

Route::middleware([
    'web',
    InitializeTenancyByDomain::class,
    PreventAccessFromUnwantedDomains::class,
    'scope.sessions', // v4: Isolamento de sessão
])->name('tenant.')->group(function () {

    // ================================================
    // Rotas Públicas (sem autenticação)
    // ================================================

    // Impersonation token consumption (v4 nativo)
    Route::get('/impersonate/{token}', function ($token) {
        return UserImpersonation::makeResponse($token);
    })->name('impersonate.consume');

    // Stop impersonation
    Route::post('/impersonate/stop', function () {
        if (!UserImpersonation::isImpersonating()) {
            return redirect()->route('tenant.admin.dashboard');
        }

        $centralUrl = config('app.url') . '/admin/tenants';
        UserImpersonation::stopImpersonating();

        return Inertia::location($centralUrl);
    })->middleware('auth')->name('impersonate.stop');

    // Root redirect
    Route::get('/', function () {
        return redirect()->route('tenant.admin.dashboard');
    });

    // ================================================
    // Rotas do Painel Admin (autenticadas)
    // ================================================

    Route::middleware(['auth', 'verified', VerifyTenantAccess::class])
        ->prefix('admin')
        ->name('admin.')
        ->group(function () {
            // Dashboard
            Route::get('/dashboard', \App\Http\Controllers\Tenant\Admin\DashboardController::class)
                ->name('dashboard');

            // Projects
            Route::resource('projects', \App\Http\Controllers\Tenant\Admin\ProjectController::class);

            // Team (requer permissão via controller)
            Route::prefix('team')->name('team.')->group(function () {
                Route::get('/', [\App\Http\Controllers\Tenant\Admin\TeamController::class, 'index'])
                    ->name('index');
                Route::post('/invite', [\App\Http\Controllers\Tenant\Admin\TeamController::class, 'invite'])
                    ->name('invite');
                Route::patch('/{user}/role', [\App\Http\Controllers\Tenant\Admin\TeamController::class, 'updateRole'])
                    ->name('update-role');
                Route::delete('/{user}', [\App\Http\Controllers\Tenant\Admin\TeamController::class, 'remove'])
                    ->name('remove');
            });

            // Settings
            Route::prefix('tenant-settings')->name('settings.')->group(function () {
                Route::get('/', [\App\Http\Controllers\Tenant\Admin\TenantSettingsController::class, 'index'])
                    ->name('index');
                // ... outras rotas de settings
            });
        });
});
```

### 8.3 Rotas Central Admin (routes/central-admin.php)

```php
<?php

declare(strict_types=1);

use App\Http\Controllers\Central\Admin\DashboardController;
use App\Http\Controllers\Central\Admin\TenantController;
use App\Http\Controllers\Central\Admin\ImpersonationController;
use App\Http\Controllers\Central\Auth\AdminLoginController;
use App\Http\Controllers\Central\Auth\AdminLogoutController;
use Illuminate\Support\Facades\Route;

// ================================================
// Rotas de Autenticação Admin (central)
// ================================================

Route::prefix('admin')->name('central.admin.')->group(function () {
    // Login
    Route::get('/login', [AdminLoginController::class, 'create'])
        ->middleware('guest:admin')
        ->name('login');

    Route::post('/login', [AdminLoginController::class, 'store'])
        ->middleware('guest:admin')
        ->name('login.store');

    // Logout
    Route::post('/logout', [AdminLogoutController::class, 'destroy'])
        ->middleware('auth:admin')
        ->name('logout');
});

// ================================================
// Rotas do Painel Admin Central (autenticadas)
// ================================================

Route::prefix('admin')
    ->name('central.admin.')
    ->middleware(['web', 'auth:admin'])
    ->group(function () {
        // Dashboard
        Route::get('/dashboard', DashboardController::class)->name('dashboard');

        // Gerenciamento de Tenants
        Route::resource('tenants', TenantController::class);

        // Impersonation (acessar tenant como admin)
        Route::get('/tenants/{tenant}/impersonate', [ImpersonationController::class, 'redirect'])
            ->name('tenants.impersonate');
    });
```

---

## 9. Controllers

### 9.1 AdminLoginController (Central)

```php
<?php

namespace App\Http\Controllers\Central\Auth;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;

class AdminLoginController extends Controller
{
    public function create()
    {
        return Inertia::render('central/admin/auth/login');
    }

    public function store(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        $admin = Admin::where('email', $credentials['email'])->first();

        if (!$admin || !Hash::check($credentials['password'], $admin->password)) {
            return back()->withErrors([
                'email' => __('The provided credentials do not match our records.'),
            ]);
        }

        Auth::guard('admin')->login($admin, $request->boolean('remember'));

        $request->session()->regenerate();

        return redirect()->intended(route('central.admin.dashboard'));
    }
}
```

### 9.2 ImpersonationController (Central) - Completo

O ImpersonationController suporta dois cenários:

1. **Admin Mode**: Admin entra no tenant sem impersonar usuário específico
2. **Impersonate User**: Admin entra como um usuário específico do tenant

```
┌─────────────────────────────────────────────────────────────────────────┐
│                        CENÁRIOS DE IMPERSONATION                         │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                          │
│  CENÁRIO 1: Admin Mode (sem usuário específico)                         │
│  ─────────────────────────────────────────────────                      │
│  - Admin quer apenas "ver" o tenant                                     │
│  - Não loga como nenhum usuário específico                              │
│  - Sessão especial com flag `tenancy_impersonating`                     │
│  - Acesso limitado (view-only ou admin temporário)                      │
│                                                                          │
│  CENÁRIO 2: Impersonate Usuário Específico                              │
│  ─────────────────────────────────────────────────                      │
│  - Admin seleciona um usuário DO TENANT                                 │
│  - Sistema busca users do tenant via tenancy()->run()                   │
│  - Token gerado com user_id do banco do TENANT                          │
│  - Admin vê exatamente o que aquele usuário vê                          │
│                                                                          │
└─────────────────────────────────────────────────────────────────────────┘
```

```php
<?php

namespace App\Http\Controllers\Central\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Stancl\Tenancy\Database\Models\ImpersonationToken;

class ImpersonationController extends Controller
{
    /**
     * Lista usuários do tenant para impersonation.
     * Admin pode escolher qual usuário impersonar.
     */
    public function index(Tenant $tenant)
    {
        $admin = auth('admin')->user();

        if (!$admin->canAccessTenant($tenant)) {
            abort(403);
        }

        // Busca usuários DO BANCO DO TENANT
        $users = tenancy()->run($tenant, function () {
            return User::select('id', 'name', 'email')
                ->with('roles:name')
                ->orderBy('name')
                ->get();
        });

        return Inertia::render('central/admin/tenants/impersonate', [
            'tenant' => $tenant,
            'users' => $users,
        ]);
    }

    /**
     * CENÁRIO 1: Impersonate sem usuário específico (Admin Mode)
     *
     * Cria sessão temporária com acesso admin ao tenant.
     * Útil para suporte técnico que precisa ver configurações.
     */
    public function adminMode(Request $request, Tenant $tenant)
    {
        $admin = $request->user('admin');

        if (!$admin->canAccessTenant($tenant)) {
            abort(403);
        }

        // Gera token SEM user_id específico
        // v4 permite user_id = null para "admin mode"
        $token = tenancy()->impersonate(
            tenant: $tenant,
            userId: null, // Sem usuário específico
            redirectUrl: '/admin/dashboard',
        );

        $url = $tenant->url() . '/impersonate/' . $token->token;

        return Inertia::location($url);
    }

    /**
     * CENÁRIO 2: Impersonate usuário específico do tenant.
     *
     * Admin vê o sistema exatamente como o usuário selecionado.
     */
    public function asUser(Request $request, Tenant $tenant, string $userId)
    {
        $admin = $request->user('admin');

        if (!$admin->canAccessTenant($tenant)) {
            abort(403);
        }

        // Verifica se o usuário existe no tenant
        $userExists = tenancy()->run($tenant, function () use ($userId) {
            return User::where('id', $userId)->exists();
        });

        if (!$userExists) {
            abort(404, __('User not found in this tenant.'));
        }

        // Gera token COM user_id do tenant
        $token = tenancy()->impersonate(
            tenant: $tenant,
            userId: $userId, // ID do usuário NO TENANT
            redirectUrl: '/admin/dashboard',
        );

        $url = $tenant->url() . '/impersonate/' . $token->token;

        return Inertia::location($url);
    }
}
```

### 9.3 Rotas de Impersonation (Central)

```php
<?php
// routes/central-admin.php (adicionar às rotas existentes)

Route::prefix('admin/tenants/{tenant}')
    ->name('central.admin.tenants.')
    ->middleware(['web', 'auth:admin'])
    ->group(function () {
        // Lista usuários para impersonation
        Route::get('/impersonate', [ImpersonationController::class, 'index'])
            ->name('impersonate.index');

        // Impersonate como admin (sem usuário específico)
        Route::post('/impersonate/admin-mode', [ImpersonationController::class, 'adminMode'])
            ->name('impersonate.admin-mode');

        // Impersonate como usuário específico
        Route::post('/impersonate/as/{userId}', [ImpersonationController::class, 'asUser'])
            ->name('impersonate.as-user');
    });
```

### 9.4 Consumo de Token no Tenant (Atualizado)

```php
<?php
// routes/tenant.php - Rota de consumo de impersonation token

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Stancl\Tenancy\Database\Models\ImpersonationToken;

// Consumir token de impersonation (com suporte a Admin Mode)
Route::get('/impersonate/{token}', function (string $token) {
    $impersonationToken = ImpersonationToken::where('token', $token)->first();

    if (!$impersonationToken) {
        abort(403, __('Invalid or expired token.'));
    }

    // CENÁRIO 1: Admin Mode (sem user_id)
    if ($impersonationToken->user_id === null) {
        // Cria sessão especial de admin sem logar como usuário
        session()->put('tenancy_impersonating', true);
        session()->put('tenancy_admin_mode', true);

        $redirectUrl = $impersonationToken->redirect_url;
        $impersonationToken->delete();

        return redirect($redirectUrl);
    }

    // CENÁRIO 2: Impersonate usuário específico
    // Busca usuário NO BANCO DO TENANT (já está no contexto correto)
    $user = User::find($impersonationToken->user_id);

    if (!$user) {
        $impersonationToken->delete();
        abort(403, __('User not found.'));
    }

    // Login como o usuário do tenant
    Auth::guard($impersonationToken->auth_guard)->login($user);

    // Marca sessão como impersonation
    session()->put('tenancy_impersonating', true);
    session()->forget('tenancy_admin_mode'); // Garante que não está em admin mode

    $redirectUrl = $impersonationToken->redirect_url;
    $impersonationToken->delete();

    return redirect($redirectUrl);
})->name('impersonate.consume');
```

### 9.5 Middleware AllowAdminMode (Tenant)

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware que permite acesso em "Admin Mode" sem usuário logado.
 * Usado quando admin central está inspecionando tenant.
 */
class AllowAdminMode
{
    public function handle(Request $request, Closure $next): Response
    {
        // Se está em admin mode (impersonation sem user)
        if (session('tenancy_admin_mode')) {
            // Permite acesso mesmo sem auth
            // Mas com permissões limitadas (view-only)
            return $next($request);
        }

        // Caso contrário, requer autenticação normal
        if (!$request->user()) {
            return redirect()->route('login');
        }

        return $next($request);
    }
}
```

### 9.6 Fluxo Visual de Impersonation

```
┌─────────────────────────────────────────────────────────────────────────┐
│                     CENTRAL (setor3.app)                                 │
│                                                                          │
│  Admin logado ──▶ /admin/tenants/tenant1/impersonate                    │
│                          │                                               │
│                          ▼                                               │
│         ┌────────────────────────────────────┐                          │
│         │  Lista de usuários do tenant1:     │                          │
│         │                                     │                          │
│         │  ┌─────────────────────────────┐   │                          │
│         │  │ 🔑 Entrar como Admin        │   │  ◀── Admin Mode          │
│         │  └─────────────────────────────┘   │                          │
│         │                                     │                          │
│         │  👤 John Doe (owner)    [Entrar]   │  ◀── Impersonate User    │
│         │  👤 Jane Smith (admin)  [Entrar]   │                          │
│         │  👤 Bob Wilson (member) [Entrar]   │                          │
│         └────────────────────────────────────┘                          │
│                          │                                               │
│                          ▼                                               │
│         tenancy()->impersonate($tenant, $userId, ...)                   │
│                          │                                               │
│                          ▼                                               │
│         Token salvo em impersonation_tokens (central)                   │
│         Redirect: tenant1.localhost/impersonate/{token}                 │
└─────────────────────────────────────────────────────────────────────────┘
                           │
                           ▼
┌─────────────────────────────────────────────────────────────────────────┐
│                     TENANT (tenant1.localhost)                           │
│                                                                          │
│  /impersonate/{token}                                                   │
│         │                                                                │
│         ▼                                                                │
│  Token encontrado em central DB                                         │
│         │                                                                │
│         ├── user_id = null ──▶ Admin Mode (sem login)                   │
│         │                      session('tenancy_admin_mode', true)      │
│         │                                                                │
│         └── user_id = "xxx" ──▶ Busca User::find($userId)               │
│                                 NO BANCO DO TENANT                       │
│                                 Auth::login($user)                       │
│                                 session('tenancy_impersonating', true)  │
│         │                                                                │
│         ▼                                                                │
│  Redirect para /admin/dashboard                                         │
│  Banner de impersonation visível                                        │
└─────────────────────────────────────────────────────────────────────────┘
```

### 9.7 FAQ: Impersonation

| Pergunta | Resposta |
|----------|----------|
| **Podemos impersonar usuários que não estão no central?** | SIM - o token carrega o user_id do tenant |
| **Como o sistema encontra o usuário?** | Busca no banco do TENANT após consumir o token |
| **O user_id no token vem de onde?** | Do banco do tenant (buscado via `tenancy()->run()`) |
| **E se o usuário não existir?** | Retorna 404 ao consumir o token |
| **Podemos entrar sem impersonar um usuário?** | SIM, via "Admin Mode" com `user_id = null` |
| **O Admin Mode permite editar dados?** | Configurável - pode ser view-only ou full access |

### 9.8 TeamController (Tenant) - Simplificado

```php
<?php

namespace App\Http\Controllers\Tenant\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Inertia\Inertia;

class TeamController extends Controller
{
    /**
     * Lista membros do time.
     * NOTA: Sem workarounds de conexão! Query simples no mesmo banco.
     */
    public function index()
    {
        $this->authorize('viewAny', User::class);

        // Query simples - users e roles no MESMO banco
        $members = User::with('roles')->orderBy('name')->get();

        return Inertia::render('tenant/admin/team/index', [
            'members' => $members,
        ]);
    }

    /**
     * Convida novo membro.
     * Cria User DIRETO no banco do tenant (sem central).
     */
    public function invite(Request $request)
    {
        $this->authorize('create', User::class);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'role' => ['required', 'string', 'exists:roles,name'],
        ]);

        // Cria usuário no banco do TENANT (único lugar)
        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make(Str::random(32)), // Senha temporária
        ]);

        // Atribui role LOCAL (mesmo banco, sem workaround)
        $user->assignRole($validated['role']);

        // TODO: Enviar email com link de reset de senha

        return back()->with('success', __('Member invited successfully.'));
    }

    /**
     * Atualiza role do membro.
     */
    public function updateRole(Request $request, User $user)
    {
        $this->authorize('update', $user);

        $validated = $request->validate([
            'role' => ['required', 'string', 'exists:roles,name'],
        ]);

        // Sync role LOCAL (mesmo banco)
        $user->syncRoles([$validated['role']]);

        return back()->with('success', __('Role updated successfully.'));
    }

    /**
     * Remove membro do tenant.
     */
    public function remove(User $user)
    {
        $this->authorize('delete', $user);

        // Previne self-delete
        if ($user->id === auth()->id()) {
            return back()->withErrors(['error' => __('You cannot remove yourself.')]);
        }

        // Soft delete no banco do TENANT
        $user->delete();

        return back()->with('success', __('Member removed successfully.'));
    }
}
```

---

## 10. Seeders

### 10.1 AdminSeeder (Central)

```php
<?php

namespace Database\Seeders;

use App\Models\Admin;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        Admin::create([
            'name' => 'Super Admin',
            'email' => 'admin@setor3.app',
            'password' => Hash::make('password'),
            'is_super_admin' => true,
            'email_verified_at' => now(),
        ]);
    }
}
```

### 10.2 TenantDatabaseSeeder (Tenant)

```php
<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class TenantDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Cria roles do tenant
        $this->seedRoles();

        // Cria owner do tenant (se especificado)
        $this->seedOwner();
    }

    protected function seedRoles(): void
    {
        // Roles locais do tenant
        Role::create(['name' => 'owner', 'guard_name' => 'web']);
        Role::create(['name' => 'admin', 'guard_name' => 'web']);
        Role::create(['name' => 'member', 'guard_name' => 'web']);
    }

    protected function seedOwner(): void
    {
        $tenant = tenant();

        // Se tenant tem dados de owner, cria o user
        if ($ownerData = $tenant->owner_data ?? null) {
            $user = User::create([
                'name' => $ownerData['name'],
                'email' => $ownerData['email'],
                'password' => Hash::make($ownerData['password'] ?? 'password'),
                'email_verified_at' => now(),
            ]);

            $user->assignRole('owner');
        }
    }
}
```

### 10.3 TenantSeeder (para testes)

```php
<?php

namespace Database\Seeders;

use App\Models\Tenant;
use Illuminate\Database\Seeder;

class TenantSeeder extends Seeder
{
    public function run(): void
    {
        // Tenant 1 - Professional
        $tenant1 = Tenant::create([
            'id' => 'tenant1',
            'name' => 'ACME Corporation',
            'owner_data' => [
                'name' => 'John Doe',
                'email' => 'john@acme.com',
                'password' => 'password',
            ],
        ]);
        $tenant1->domains()->create(['domain' => 'tenant1.localhost']);

        // Tenant 2 - Starter
        $tenant2 = Tenant::create([
            'id' => 'tenant2',
            'name' => 'Startup Inc',
            'owner_data' => [
                'name' => 'Jane Smith',
                'email' => 'jane@startup.com',
                'password' => 'password',
            ],
        ]);
        $tenant2->domains()->create(['domain' => 'tenant2.localhost']);

        // Tenant 3 - Enterprise
        $tenant3 = Tenant::create([
            'id' => 'tenant3',
            'name' => 'Enterprise Corp',
            'owner_data' => [
                'name' => 'Mike Johnson',
                'email' => 'mike@enterprise.com',
                'password' => 'password',
            ],
        ]);
        $tenant3->domains()->create(['domain' => 'tenant3.localhost']);
    }
}
```

---

## 11. Plano de Migração

### 11.1 Fase 1: Preparação

```bash
# Criar branch
git checkout -b feature/tenant-only-users

# Criar migrations centrais
sail artisan make:migration create_admins_table
sail artisan make:migration drop_tenant_user_table

# Criar migrations tenant
sail artisan make:migration create_users_table --path=database/migrations/tenant
sail artisan make:migration create_sessions_table --path=database/migrations/tenant
sail artisan make:migration create_password_reset_tokens_table --path=database/migrations/tenant
sail artisan make:migration create_personal_access_tokens_table --path=database/migrations/tenant
```

### 11.2 Fase 2: Models e Auth

```bash
# Criar model Admin
sail artisan make:model Admin

# Atualizar User model (remover CentralConnection)
# Atualizar config/auth.php
# Atualizar FortifyServiceProvider
```

### 11.3 Fase 3: Controllers e Rotas

```bash
# Criar controllers de autenticação admin
sail artisan make:controller Central/Auth/AdminLoginController
sail artisan make:controller Central/Auth/AdminLogoutController
sail artisan make:controller Central/Admin/ImpersonationController

# Criar arquivo de rotas
touch routes/central-admin.php
```

### 11.4 Fase 4: Migração de Dados

```php
<?php

// Script de migração de dados existentes
// database/seeders/MigrateUsersToTenantsSeeder.php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Tenant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MigrateUsersToTenantsSeeder extends Seeder
{
    public function run(): void
    {
        // Para cada usuário no banco central
        $centralUsers = DB::connection('central')
            ->table('users')
            ->get();

        foreach ($centralUsers as $centralUser) {
            // Busca tenants associados via pivot atual
            $tenantIds = DB::connection('central')
                ->table('tenant_user')
                ->where('user_id', $centralUser->id)
                ->pluck('tenant_id');

            foreach ($tenantIds as $tenantId) {
                $tenant = Tenant::find($tenantId);

                tenancy()->initialize($tenant);

                // Cria user no banco do tenant
                User::create([
                    'id' => $centralUser->id, // Mantém mesmo UUID
                    'name' => $centralUser->name,
                    'email' => $centralUser->email,
                    'password' => $centralUser->password,
                    'email_verified_at' => $centralUser->email_verified_at,
                    'locale' => $centralUser->locale,
                ]);

                // Migra roles existentes
                $roles = DB::table('model_has_roles')
                    ->where('model_id', $centralUser->id)
                    ->pluck('role_id');

                foreach ($roles as $roleId) {
                    DB::table('model_has_roles')->insert([
                        'role_id' => $roleId,
                        'model_type' => User::class,
                        'model_id' => $centralUser->id,
                    ]);
                }

                tenancy()->end();
            }
        }

        $this->command->info('Users migrated successfully.');
    }
}
```

### 11.5 Fase 5: Cleanup

```bash
# Após validação completa:

# 1. Remover tabelas centrais não utilizadas
sail artisan make:migration drop_users_table_from_central
sail artisan make:migration drop_tenant_user_table_from_central

# 2. Remover workarounds do código
# - CentralConnection trait do User
# - Connection switching no User model
# - tenant_user relacionamentos

# 3. Atualizar CLAUDE.md com nova arquitetura
```

---

## 12. Testes

### 12.1 Testes de Autenticação

```php
<?php

namespace Tests\Feature\Tenant;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create(['id' => 'test']);
        $this->tenant->domains()->create(['domain' => 'test.localhost']);
    }

    public function test_user_can_login_to_tenant(): void
    {
        tenancy()->initialize($this->tenant);

        $user = User::factory()->create([
            'email' => 'user@test.com',
            'password' => bcrypt('password'),
        ]);

        tenancy()->end();

        $response = $this->post(
            'http://test.localhost/login',
            [
                'email' => 'user@test.com',
                'password' => 'password',
            ]
        );

        $response->assertRedirect('/admin/dashboard');
    }

    public function test_user_cannot_login_to_different_tenant(): void
    {
        // Cria user no tenant 1
        tenancy()->initialize($this->tenant);
        User::factory()->create([
            'email' => 'user@test.com',
            'password' => bcrypt('password'),
        ]);
        tenancy()->end();

        // Cria tenant 2
        $tenant2 = Tenant::create(['id' => 'test2']);
        $tenant2->domains()->create(['domain' => 'test2.localhost']);

        // Tenta login no tenant 2
        $response = $this->post(
            'http://test2.localhost/login',
            [
                'email' => 'user@test.com',
                'password' => 'password',
            ]
        );

        $response->assertSessionHasErrors('email');
    }

    public function test_session_is_isolated_per_tenant(): void
    {
        // Login no tenant 1
        tenancy()->initialize($this->tenant);
        $user1 = User::factory()->create();
        tenancy()->end();

        $this->actingAs($user1);

        // Request no tenant 1 - autenticado
        $response1 = $this->get('http://test.localhost/admin/dashboard');
        $response1->assertOk();

        // Request no tenant 2 - NÃO autenticado (sessão isolada)
        $tenant2 = Tenant::create(['id' => 'test2']);
        $tenant2->domains()->create(['domain' => 'test2.localhost']);

        $response2 = $this->get('http://test2.localhost/admin/dashboard');
        $response2->assertRedirect('/login');
    }
}
```

### 12.2 Testes de Impersonation

```php
<?php

namespace Tests\Feature\Central;

use App\Models\Admin;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminImpersonationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_impersonate_tenant(): void
    {
        $admin = Admin::factory()->create(['is_super_admin' => true]);
        $tenant = Tenant::create(['id' => 'test']);
        $tenant->domains()->create(['domain' => 'test.localhost']);

        $response = $this
            ->actingAs($admin, 'admin')
            ->get(route('central.admin.tenants.impersonate', $tenant));

        $response->assertRedirect();
        $this->assertStringContainsString(
            'test.localhost/impersonate/',
            $response->headers->get('Location')
        );
    }

    public function test_non_super_admin_cannot_impersonate(): void
    {
        $admin = Admin::factory()->create(['is_super_admin' => false]);
        $tenant = Tenant::create(['id' => 'test']);
        $tenant->domains()->create(['domain' => 'test.localhost']);

        $response = $this
            ->actingAs($admin, 'admin')
            ->get(route('central.admin.tenants.impersonate', $tenant));

        $response->assertForbidden();
    }

    public function test_admin_can_list_tenant_users_for_impersonation(): void
    {
        $admin = Admin::factory()->create(['is_super_admin' => true]);
        $tenant = Tenant::create(['id' => 'test']);
        $tenant->domains()->create(['domain' => 'test.localhost']);

        // Cria usuários no tenant
        tenancy()->initialize($tenant);
        User::factory()->create(['name' => 'John Doe', 'email' => 'john@test.com']);
        User::factory()->create(['name' => 'Jane Smith', 'email' => 'jane@test.com']);
        tenancy()->end();

        $response = $this
            ->actingAs($admin, 'admin')
            ->get(route('central.admin.tenants.impersonate.index', $tenant));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('users', 2)
            ->where('users.0.name', 'Jane Smith') // ordenado por nome
            ->where('users.1.name', 'John Doe')
        );
    }

    public function test_admin_can_impersonate_specific_tenant_user(): void
    {
        $admin = Admin::factory()->create(['is_super_admin' => true]);
        $tenant = Tenant::create(['id' => 'test']);
        $tenant->domains()->create(['domain' => 'test.localhost']);

        // Cria usuário no tenant
        tenancy()->initialize($tenant);
        $user = User::factory()->create(['email' => 'john@test.com']);
        tenancy()->end();

        $response = $this
            ->actingAs($admin, 'admin')
            ->post(route('central.admin.tenants.impersonate.as-user', [
                'tenant' => $tenant,
                'userId' => $user->id,
            ]));

        $response->assertRedirect();
        $this->assertStringContainsString(
            'test.localhost/impersonate/',
            $response->headers->get('Location')
        );
    }

    public function test_admin_cannot_impersonate_nonexistent_user(): void
    {
        $admin = Admin::factory()->create(['is_super_admin' => true]);
        $tenant = Tenant::create(['id' => 'test']);
        $tenant->domains()->create(['domain' => 'test.localhost']);

        $response = $this
            ->actingAs($admin, 'admin')
            ->post(route('central.admin.tenants.impersonate.as-user', [
                'tenant' => $tenant,
                'userId' => 'nonexistent-uuid',
            ]));

        $response->assertNotFound();
    }

    public function test_admin_mode_creates_session_without_user(): void
    {
        $admin = Admin::factory()->create(['is_super_admin' => true]);
        $tenant = Tenant::create(['id' => 'test']);
        $tenant->domains()->create(['domain' => 'test.localhost']);

        $response = $this
            ->actingAs($admin, 'admin')
            ->post(route('central.admin.tenants.impersonate.admin-mode', $tenant));

        $response->assertRedirect();
        $this->assertStringContainsString(
            'test.localhost/impersonate/',
            $response->headers->get('Location')
        );
    }
}
```

### 12.3 Testes de Consumo de Token (Tenant)

```php
<?php

namespace Tests\Feature\Tenant;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Stancl\Tenancy\Database\Models\ImpersonationToken;
use Tests\TestCase;

class ImpersonationTokenConsumptionTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create(['id' => 'test']);
        $this->tenant->domains()->create(['domain' => 'test.localhost']);
    }

    public function test_token_with_user_id_logs_in_as_that_user(): void
    {
        // Cria usuário no tenant
        tenancy()->initialize($this->tenant);
        $user = User::factory()->create(['email' => 'john@test.com']);
        tenancy()->end();

        // Cria token de impersonation com user_id
        $token = ImpersonationToken::create([
            'token' => 'test-token-123',
            'tenant_id' => $this->tenant->id,
            'user_id' => $user->id,
            'auth_guard' => 'web',
            'redirect_url' => '/admin/dashboard',
        ]);

        // Consome o token
        $response = $this->get('http://test.localhost/impersonate/test-token-123');

        $response->assertRedirect('/admin/dashboard');

        // Verifica que está logado como o usuário
        $this->assertAuthenticatedAs($user);

        // Verifica flag de impersonation
        $this->assertTrue(session('tenancy_impersonating'));
        $this->assertFalse(session('tenancy_admin_mode', false));

        // Token deve ter sido deletado
        $this->assertDatabaseMissing('impersonation_tokens', ['token' => 'test-token-123']);
    }

    public function test_token_without_user_id_creates_admin_mode_session(): void
    {
        // Cria token de impersonation SEM user_id (admin mode)
        $token = ImpersonationToken::create([
            'token' => 'admin-mode-token',
            'tenant_id' => $this->tenant->id,
            'user_id' => null, // Admin mode
            'auth_guard' => 'web',
            'redirect_url' => '/admin/dashboard',
        ]);

        // Consome o token
        $response = $this->get('http://test.localhost/impersonate/admin-mode-token');

        $response->assertRedirect('/admin/dashboard');

        // NÃO deve estar logado como usuário
        $this->assertGuest();

        // Verifica flags de sessão
        $this->assertTrue(session('tenancy_impersonating'));
        $this->assertTrue(session('tenancy_admin_mode'));
    }

    public function test_invalid_token_returns_403(): void
    {
        $response = $this->get('http://test.localhost/impersonate/invalid-token');

        $response->assertForbidden();
    }

    public function test_token_with_deleted_user_returns_403(): void
    {
        // Cria token com user_id que não existe mais
        $token = ImpersonationToken::create([
            'token' => 'orphan-token',
            'tenant_id' => $this->tenant->id,
            'user_id' => 'deleted-user-uuid',
            'auth_guard' => 'web',
            'redirect_url' => '/admin/dashboard',
        ]);

        $response = $this->get('http://test.localhost/impersonate/orphan-token');

        $response->assertForbidden();

        // Token deve ter sido deletado mesmo assim
        $this->assertDatabaseMissing('impersonation_tokens', ['token' => 'orphan-token']);
    }
}
```

### 12.4 Testes E2E (Playwright)

```typescript
// tests/Browser/tenant-user-isolation.spec.ts

import { test, expect } from '@playwright/test';

test.describe('Tenant User Isolation', () => {
    test('user credentials only work on their tenant', async ({ page }) => {
        // Login no tenant1
        await page.goto('http://tenant1.localhost/login');
        await page.fill('input[name="email"]', 'john@acme.com');
        await page.fill('input[name="password"]', 'password');
        await page.click('button[type="submit"]');

        await expect(page).toHaveURL(/\/admin\/dashboard/);

        // Logout
        await page.goto('http://tenant1.localhost/logout', { method: 'POST' });

        // Mesmas credenciais NÃO funcionam no tenant2
        await page.goto('http://tenant2.localhost/login');
        await page.fill('input[name="email"]', 'john@acme.com');
        await page.fill('input[name="password"]', 'password');
        await page.click('button[type="submit"]');

        // Deve mostrar erro de credenciais
        await expect(page.locator('.text-red-500')).toBeVisible();
    });

    test('sessions are isolated between tenants', async ({ browser }) => {
        // Contexto 1: tenant1
        const context1 = await browser.newContext();
        const page1 = await context1.newPage();

        await page1.goto('http://tenant1.localhost/login');
        await page1.fill('input[name="email"]', 'john@acme.com');
        await page1.fill('input[name="password"]', 'password');
        await page1.click('button[type="submit"]');
        await expect(page1).toHaveURL(/\/admin\/dashboard/);

        // Contexto 2: tenant2 (mesmo browser, diferentes cookies)
        const context2 = await browser.newContext();
        const page2 = await context2.newPage();

        // Não deve estar autenticado no tenant2
        await page2.goto('http://tenant2.localhost/admin/dashboard');
        await expect(page2).toHaveURL(/\/login/);

        await context1.close();
        await context2.close();
    });

    test('admin can impersonate tenant in admin mode', async ({ page }) => {
        // Login como admin central
        await page.goto('http://localhost/admin/login');
        await page.fill('input[name="email"]', 'admin@setor3.app');
        await page.fill('input[name="password"]', 'password');
        await page.click('button[type="submit"]');

        // Navega para página de impersonation do tenant
        await page.goto('http://localhost/admin/tenants/tenant1/impersonate');

        // Clica em "Entrar como Admin"
        await page.click('button[data-admin-mode]');

        // Deve ser redirecionado para tenant com banner de impersonation
        await expect(page).toHaveURL(/tenant1\.localhost/);
        await expect(page.locator('[data-impersonation-banner]')).toBeVisible();
        await expect(page.locator('[data-admin-mode-indicator]')).toBeVisible();
    });

    test('admin can impersonate specific tenant user', async ({ page }) => {
        // Login como admin central
        await page.goto('http://localhost/admin/login');
        await page.fill('input[name="email"]', 'admin@setor3.app');
        await page.fill('input[name="password"]', 'password');
        await page.click('button[type="submit"]');

        // Navega para página de impersonation do tenant
        await page.goto('http://localhost/admin/tenants/tenant1/impersonate');

        // Lista de usuários deve aparecer
        await expect(page.locator('text=John Doe')).toBeVisible();

        // Clica em impersonate para John Doe
        await page.click('button[data-impersonate-user="john@acme.com"]');

        // Deve ser redirecionado para tenant
        await expect(page).toHaveURL(/tenant1\.localhost/);

        // Banner de impersonation visível
        await expect(page.locator('[data-impersonation-banner]')).toBeVisible();

        // Deve estar logado como John Doe
        await expect(page.locator('text=John Doe')).toBeVisible();
    });

    test('impersonating user sees same permissions as that user', async ({ page }) => {
        // Login como admin central
        await page.goto('http://localhost/admin/login');
        await page.fill('input[name="email"]', 'admin@setor3.app');
        await page.fill('input[name="password"]', 'password');
        await page.click('button[type="submit"]');

        // Impersonate um member (permissões limitadas)
        await page.goto('http://localhost/admin/tenants/tenant1/impersonate');
        await page.click('button[data-impersonate-user="member@acme.com"]');

        // Deve estar no tenant
        await expect(page).toHaveURL(/tenant1\.localhost/);

        // Member não deve ver configurações de tenant
        await page.goto('http://tenant1.localhost/admin/tenant-settings');
        await expect(page).toHaveURL(/403|forbidden|dashboard/);
    });
});
```

---

## 13. Checklist de Implementação

### 13.1 Models e Database

- [ ] Criar migration `create_admins_table` (central)
- [ ] Criar migration `create_users_table` (tenant)
- [ ] Criar migration `create_sessions_table` (tenant)
- [ ] Criar migration `create_password_reset_tokens_table` (tenant)
- [ ] Criar migration `create_personal_access_tokens_table` (tenant)
- [ ] Criar model `Admin`
- [ ] Atualizar model `User` (remover CentralConnection)
- [ ] Criar factory para `Admin`
- [ ] Atualizar factory de `User`

### 13.2 Autenticação

- [ ] Atualizar `config/auth.php` com guards admin/web
- [ ] Atualizar `FortifyServiceProvider`
- [ ] Criar `AdminLoginController`
- [ ] Criar `AdminLogoutController`
- [ ] Criar views Inertia para login admin

### 13.3 Tenancy

- [ ] Atualizar `config/tenancy.php` (bootstrappers)
- [ ] Atualizar `TenancyServiceProvider`
- [ ] Atualizar `VerifyTenantAccess` middleware

### 13.4 Impersonation

- [ ] Criar `ImpersonationController` com métodos `index`, `adminMode`, `asUser`
- [ ] Criar middleware `AllowAdminMode`
- [ ] Atualizar rota `/impersonate/{token}` para suportar Admin Mode
- [ ] Criar página Inertia `central/admin/tenants/impersonate`
- [ ] Adicionar rotas de impersonation em `routes/central-admin.php`
- [ ] Testar impersonation com usuário específico do tenant
- [ ] Testar Admin Mode (sem usuário específico)

### 13.5 Rotas

- [ ] Criar `routes/central-admin.php`
- [ ] Atualizar `routes/tenant.php`
- [ ] Atualizar `bootstrap/app.php`

### 13.6 Seeders

- [ ] Criar `AdminSeeder`
- [ ] Atualizar `TenantDatabaseSeeder`
- [ ] Criar script de migração de dados

### 13.7 Frontend

- [ ] Criar páginas de login admin (central)
- [ ] Atualizar dashboard admin (central)
- [ ] Criar página de seleção de usuário para impersonation
- [ ] Verificar componente de impersonation banner
- [ ] Adicionar indicador de Admin Mode no banner

### 13.8 Testes

- [ ] Testes de autenticação tenant
- [ ] Testes de autenticação admin
- [ ] Testes de impersonation (Admin Mode)
- [ ] Testes de impersonation (usuário específico)
- [ ] Testes de isolamento de sessão
- [ ] Testes E2E com Playwright

### 13.9 Documentação

- [ ] Atualizar CLAUDE.md
- [ ] Atualizar docs/SESSION-SECURITY.md
- [ ] Atualizar docs/PERMISSIONS.md

---

## 14. Referências

- [Stancl/Tenancy v4 - Multi-Database Tenancy](https://v4.tenancyforlaravel.com/multi-database-tenancy)
- [Stancl/Tenancy v4 - Session Scoping](https://v4.tenancyforlaravel.com/features/session-scoping)
- [Stancl/Tenancy v4 - User Impersonation](https://v4.tenancyforlaravel.com/features/user-impersonation)
- [Stancl/Tenancy v4 - Cross-Domain Redirect](https://v4.tenancyforlaravel.com/features/cross-domain-redirect)
- [Laravel Fortify](https://laravel.com/docs/11.x/fortify)
- [Spatie Laravel Permission](https://spatie.be/docs/laravel-permission)
