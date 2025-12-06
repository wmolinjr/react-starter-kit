# Plano de Migração: Single-Database para Multi-Database Tenancy

> **⚠️ NOTA (Dezembro 2025)**: Este documento foi o plano original de migração. A implementação atual usa **UUID para TODOS os modelos** (não apenas Tenant). Veja [docs/DATABASE-IDS.md](DATABASE-IDS.md) para a arquitetura atual de IDs.

## Sumário Executivo

**Objetivo:** Migrar o sistema de single-database (tenant_id isolation) para multi-database tenancy, garantindo isolamento físico de dados para conformidade com LGPD e HIPAA.

**Contexto:**
- Sistema em fase MVP - sem preocupação com dados legados
- Estratégia: Refatorar migrações e rodar `migrate:fresh --seed`
- Framework: Stancl/Tenancy v4 para Laravel
- **IDs**: UUID para todos os modelos (consistência e segurança)

---

## Arquitetura Proposta

```
┌─────────────────────────────────────────────────────────────────┐
│                        CENTRAL DATABASE                          │
│                     (laravel_central)                            │
├─────────────────────────────────────────────────────────────────┤
│  tenants          │  users              │  plans                │
│  domains          │  personal_access_   │  addons               │
│  tenant_user      │    tokens           │  addon_plan           │
│  tenant_          │  password_reset_    │  subscriptions        │
│    invitations    │    tokens           │  subscription_items   │
│                   │  sessions           │                       │
├─────────────────────────────────────────────────────────────────┤
│  permissions (global)  │  roles (templates)  │  telescope_*     │
│  features              │  jobs               │  cache           │
└─────────────────────────────────────────────────────────────────┘
                                │
                ┌───────────────┼───────────────┐
                ▼               ▼               ▼
┌─────────────────┐  ┌─────────────────┐  ┌─────────────────┐
│ TENANT DB 1     │  │ TENANT DB 2     │  │ TENANT DB N     │
│ (tenant_xxx)    │  │ (tenant_yyy)    │  │ (tenant_zzz)    │
├─────────────────┤  ├─────────────────┤  ├─────────────────┤
│ projects        │  │ projects        │  │ projects        │
│ media           │  │ media           │  │ media           │
│ activity_log    │  │ activity_log    │  │ activity_log    │
│ roles           │  │ roles           │  │ roles           │
│ permissions     │  │ permissions     │  │ permissions     │
│ model_has_*     │  │ model_has_*     │  │ model_has_*     │
│ tenant_addons   │  │ tenant_addons   │  │ tenant_addons   │
│ tenant_addon_   │  │ tenant_addon_   │  │ tenant_addon_   │
│   purchases     │  │   purchases     │  │   purchases     │
│ translation_    │  │ translation_    │  │ translation_    │
│   overrides     │  │   overrides     │  │   overrides     │
└─────────────────┘  └─────────────────┘  └─────────────────┘
```

---

## Fase 1: Configuração de Infraestrutura

### 1.1 Atualizar Variáveis de Ambiente

```env
# .env

# Central Database
DB_CONNECTION=central
DB_HOST=pgsql
DB_PORT=5432
DB_DATABASE=laravel_central
DB_USERNAME=sail
DB_PASSWORD=password

# Tenant Database Template
TENANT_DB_CONNECTION=tenant
TENANT_DB_HOST=pgsql
TENANT_DB_PORT=5432
TENANT_DB_USERNAME=sail
TENANT_DB_PASSWORD=password
# Nota: TENANT_DB_DATABASE é dinâmico (tenant_{uuid})
```

### 1.2 Configurar Conexões de Banco de Dados

```php
// config/database.php

'connections' => [
    // Conexão central (padrão)
    'central' => [
        'driver' => 'pgsql',
        'host' => env('DB_HOST', '127.0.0.1'),
        'port' => env('DB_PORT', '5432'),
        'database' => env('DB_DATABASE', 'laravel_central'),
        'username' => env('DB_USERNAME', 'sail'),
        'password' => env('DB_PASSWORD', ''),
        'charset' => 'utf8',
        'prefix' => '',
        'prefix_indexes' => true,
        'search_path' => 'public',
        'sslmode' => 'prefer',
    ],

    // Template para conexões de tenant (dinâmica)
    'tenant' => [
        'driver' => 'pgsql',
        'host' => env('TENANT_DB_HOST', '127.0.0.1'),
        'port' => env('TENANT_DB_PORT', '5432'),
        'database' => null, // Definido dinamicamente
        'username' => env('TENANT_DB_USERNAME', 'sail'),
        'password' => env('TENANT_DB_PASSWORD', ''),
        'charset' => 'utf8',
        'prefix' => '',
        'prefix_indexes' => true,
        'search_path' => 'public',
        'sslmode' => 'prefer',
    ],
],
```

### 1.3 Configurar Tenancy para Multi-Database

```php
// config/tenancy.php

return [
    'tenant_model' => \App\Models\Tenant::class,
    'domain_model' => \App\Models\Domain::class,

    /**
     * Conexão central para tabelas de gerenciamento
     */
    'central_connection' => env('DB_CONNECTION', 'central'),

    /**
     * Template de conexão para bancos de tenant
     */
    'template_tenant_connection' => 'tenant',

    /**
     * Bootstrappers - HABILITAR DatabaseTenancyBootstrapper
     */
    'bootstrappers' => [
        Stancl\Tenancy\Bootstrappers\DatabaseTenancyBootstrapper::class, // HABILITADO
        Stancl\Tenancy\Bootstrappers\CacheTenancyBootstrapper::class,
        Stancl\Tenancy\Bootstrappers\FilesystemTenancyBootstrapper::class,
        Stancl\Tenancy\Bootstrappers\QueueTenancyBootstrapper::class,
        Stancl\Tenancy\Bootstrappers\RedisTenancyBootstrapper::class,
        App\Tenancy\Bootstrappers\SpatiePermissionsBootstrapper::class,
    ],

    /**
     * Database managers por driver
     */
    'database' => [
        'managers' => [
            'pgsql' => Stancl\Tenancy\Database\TenantDatabaseManagers\PostgreSQLDatabaseManager::class,
        ],

        /**
         * Prefixo para nomes de banco de dados de tenant
         */
        'prefix' => 'tenant_',

        /**
         * Sufixo para nomes de banco de dados de tenant
         */
        'suffix' => '',

        /**
         * Drop tenant databases on migrate:fresh
         */
        'drop_tenant_databases_on_migrate_fresh' => true,
    ],

    /**
     * Parâmetros de migração para tenants
     */
    'migration_parameters' => [
        '--force' => true,
        '--path' => [database_path('migrations/tenant')],
        '--realpath' => true,
    ],

    // ... resto da configuração existente
];
```

---

## Fase 2: Reestruturar Migrações

### 2.1 Nova Estrutura de Diretórios

```
database/
├── migrations/                    # Migrações CENTRAIS (default Laravel)
│   ├── 0001_01_01_000000_create_users_table.php
│   ├── 0001_01_01_000001_create_cache_table.php
│   ├── 0001_01_01_000002_create_jobs_table.php
│   ├── 2019_09_15_000010_create_tenants_table.php
│   ├── 2019_09_15_000020_create_domains_table.php
│   ├── 2025_01_01_000001_create_plans_table.php
│   ├── 2025_01_01_000002_create_addons_table.php
│   ├── 2025_01_01_000003_create_addon_plan_table.php
│   ├── 2025_01_01_000004_create_tenant_user_table.php
│   ├── 2025_01_01_000005_create_tenant_invitations_table.php
│   ├── 2025_01_01_000006_create_subscriptions_table.php
│   ├── 2025_01_01_000007_create_subscription_items_table.php
│   ├── 2025_01_01_000008_create_customer_columns.php
│   ├── 2025_01_01_000009_create_personal_access_tokens_table.php
│   ├── 2025_01_01_000010_create_telescope_entries_table.php
│   └── 2025_01_01_000011_create_features_table.php
│
└── migrations/tenant/             # Migrações de TENANT (novo diretório)
    ├── 2025_01_01_000001_create_permission_tables.php
    ├── 2025_01_01_000002_create_projects_table.php
    ├── 2025_01_01_000003_create_media_table.php
    ├── 2025_01_01_000004_create_activity_log_table.php
    ├── 2025_01_01_000005_create_tenant_addons_table.php
    ├── 2025_01_01_000006_create_tenant_addon_purchases_table.php
    └── 2025_01_01_000007_create_translation_overrides_table.php
```

### 2.2 Migrações Centrais (Exemplos)

```php
// database/migrations/2019_09_15_000010_create_tenants_table.php
// (Mantém como está - já é central)

// database/migrations/2025_01_01_000001_create_plans_table.php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Central migration - runs on central database
     */
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->json('name');
            $table->json('description')->nullable();
            $table->string('stripe_price_id')->nullable();
            $table->integer('price_monthly')->default(0);
            $table->integer('price_yearly')->default(0);
            $table->json('features')->nullable();
            $table->json('limits')->nullable();
            $table->json('permission_map')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
```

### 2.3 Migrações de Tenant (Exemplos)

```php
// database/migrations/tenant/2025_01_01_000001_create_permission_tables.php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tenant migration - runs on each tenant database
     * NOTA: Sem tenant_id - cada tenant tem seu próprio banco
     */
    public function up(): void
    {
        $tableNames = config('permission.table_names');
        $columnNames = config('permission.column_names');
        $teams = config('permission.teams');

        // Permissions table (tenant-local)
        Schema::create($tableNames['permissions'], function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('guard_name');
            $table->string('group')->nullable();
            $table->timestamps();

            $table->unique(['name', 'guard_name']);
        });

        // Roles table (tenant-local)
        Schema::create($tableNames['roles'], function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('display_name')->nullable();
            $table->string('guard_name');
            $table->string('type')->default('tenant'); // Apenas tenant roles
            $table->boolean('is_protected')->default(false);
            $table->timestamps();

            $table->unique(['name', 'guard_name']);
        });

        // model_has_permissions pivot
        Schema::create($tableNames['model_has_permissions'], function (Blueprint $table) use ($tableNames, $columnNames) {
            $table->foreignId('permission_id')->constrained($tableNames['permissions'])->cascadeOnDelete();
            $table->string('model_type');
            $table->unsignedBigInteger($columnNames['model_morph_key']);
            $table->index([$columnNames['model_morph_key'], 'model_type'], 'mhp_model_id_model_type_index');
            $table->primary(['permission_id', $columnNames['model_morph_key'], 'model_type'], 'mhp_primary');
        });

        // model_has_roles pivot
        Schema::create($tableNames['model_has_roles'], function (Blueprint $table) use ($tableNames, $columnNames) {
            $table->foreignId('role_id')->constrained($tableNames['roles'])->cascadeOnDelete();
            $table->string('model_type');
            $table->unsignedBigInteger($columnNames['model_morph_key']);
            $table->index([$columnNames['model_morph_key'], 'model_type'], 'mhr_model_id_model_type_index');
            $table->primary(['role_id', $columnNames['model_morph_key'], 'model_type'], 'mhr_primary');
        });

        // role_has_permissions pivot
        Schema::create($tableNames['role_has_permissions'], function (Blueprint $table) use ($tableNames) {
            $table->foreignId('permission_id')->constrained($tableNames['permissions'])->cascadeOnDelete();
            $table->foreignId('role_id')->constrained($tableNames['roles'])->cascadeOnDelete();
            $table->primary(['permission_id', 'role_id'], 'rhp_primary');
        });
    }

    public function down(): void
    {
        $tableNames = config('permission.table_names');

        Schema::dropIfExists($tableNames['role_has_permissions']);
        Schema::dropIfExists($tableNames['model_has_roles']);
        Schema::dropIfExists($tableNames['model_has_permissions']);
        Schema::dropIfExists($tableNames['roles']);
        Schema::dropIfExists($tableNames['permissions']);
    }
};
```

```php
// database/migrations/tenant/2025_01_01_000002_create_projects_table.php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tenant migration - SEM tenant_id (implícito pelo banco)
     */
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            // REMOVIDO: $table->foreignId('tenant_id')
            $table->unsignedBigInteger('user_id'); // Referência ao user global
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();
            $table->softDeletes();

            // Índice sem tenant_id
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
```

---

## Fase 3: Atualizar Models

### 3.1 Tenant Model (HasDatabase)

```php
// app/Models/Tenant.php
<?php

namespace App\Models;

use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;
use Stancl\Tenancy\Database\Concerns\HasDatabase;
use Stancl\Tenancy\Database\Concerns\HasDomains;
use Stancl\Tenancy\Database\Concerns\MaintenanceMode;
use Stancl\Tenancy\Database\Contracts\TenantWithDatabase;
use Laravel\Cashier\Billable;

class Tenant extends BaseTenant implements TenantWithDatabase
{
    use HasDatabase, HasDomains, MaintenanceMode, Billable;

    /**
     * Custom columns no banco central
     */
    public static function getCustomColumns(): array
    {
        return [
            'id',
            'name',
            'slug',
            'plan_id',
            'owner_id',
            'trial_ends_at',
            'settings',
            // Cashier columns
            'stripe_id',
            'pm_type',
            'pm_last_four',
            'trial_ends_at',
        ];
    }

    /**
     * Atributos castáveis
     */
    protected $casts = [
        'trial_ends_at' => 'datetime',
        'settings' => 'array',
    ];

    /**
     * Relacionamentos (no banco central)
     */
    public function plan()
    {
        return $this->belongsTo(Plan::class);
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'tenant_user')
            ->withPivot(['role', 'invited_at', 'joined_at'])
            ->withTimestamps();
    }

    public function invitations()
    {
        return $this->hasMany(TenantInvitation::class);
    }
}
```

### 3.2 Project Model (Sem BelongsToTenant)

```php
// app/Models/Project.php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class Project extends Model implements HasMedia
{
    use SoftDeletes, InteractsWithMedia, LogsActivity;

    // REMOVIDO: use BelongsToTenant;
    // REMOVIDO: tenant_id do fillable

    protected $fillable = [
        'user_id',
        'name',
        'description',
        'status',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * User que criou o projeto
     * Nota: user_id referencia tabela GLOBAL de users
     */
    public function user()
    {
        // Força conexão central para buscar user
        return $this->belongsTo(User::class)->on('central');
    }

    /**
     * Activity log options
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'description', 'status'])
            ->logOnlyDirty();
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

### 3.3 User Model (CentralConnection)

```php
// app/Models/User.php
<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Stancl\Tenancy\Database\Concerns\CentralConnection;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use CentralConnection, HasRoles;

    /**
     * Força conexão central - users são globais
     */
    protected $connection = 'central';

    protected $fillable = [
        'name',
        'email',
        'password',
        'locale',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    /**
     * Tenants que o usuário tem acesso
     */
    public function tenants()
    {
        return $this->belongsToMany(Tenant::class, 'tenant_user')
            ->withPivot(['role', 'invited_at', 'joined_at'])
            ->withTimestamps();
    }

    /**
     * Tenant atual (do contexto de tenancy)
     */
    public function currentTenant(): ?Tenant
    {
        return tenant();
    }

    /**
     * Verifica se é owner do tenant atual
     */
    public function isOwnerOfCurrentTenant(): bool
    {
        $tenant = tenant();
        return $tenant && $tenant->owner_id === $this->id;
    }
}
```

### 3.4 Trait CentralConnection para Models Globais

```php
// app/Traits/CentralConnection.php
<?php

namespace App\Traits;

trait CentralConnection
{
    /**
     * Get the database connection for the model.
     */
    public function getConnectionName(): string
    {
        return 'central';
    }
}
```

---

## Fase 4: Atualizar TenancyServiceProvider

```php
// app/Providers/TenancyServiceProvider.php
<?php

namespace App\Providers;

use App\Jobs\SeedTenantDatabase;
use App\Listeners\CreateTenantRoles;
use Stancl\Tenancy\Events;
use Stancl\Tenancy\Jobs;
use Stancl\Tenancy\Listeners;
use Stancl\JobPipeline\JobPipeline;
use Illuminate\Support\ServiceProvider;

class TenancyServiceProvider extends ServiceProvider
{
    /**
     * Events para multi-database tenancy
     */
    public static array $events = [];

    public function boot(): void
    {
        $this->configureEvents();
    }

    protected function configureEvents(): void
    {
        // Quando tenant é criado: criar banco, migrar, seed
        Events\TenantCreated::class => [
            JobPipeline::make([
                Jobs\CreateDatabase::class,
                Jobs\MigrateDatabase::class,
                SeedTenantDatabase::class, // Custom job para seed
                CreateTenantRoles::class,  // Criar roles padrão
            ])->send(function (Events\TenantCreated $event) {
                return $event->tenant;
            })->shouldBeQueued(false), // Sync para MVP
        ],

        // Quando tenant é deletado: remover banco
        Events\TenantDeleted::class => [
            JobPipeline::make([
                Jobs\DeleteDatabase::class,
            ])->send(function (Events\TenantDeleted $event) {
                return $event->tenant;
            })->shouldBeQueued(false),
        ],

        // Quando domínio é criado
        Events\DomainCreated::class => [
            // Listeners personalizados se necessário
        ],

        // Bootstrap events
        Events\TenancyInitialized::class => [
            Listeners\BootstrapTenancy::class,
        ],

        Events\TenancyEnded::class => [
            Listeners\RevertToCentralContext::class,
        ],
    }
}
```

---

## Fase 5: Jobs para Criação de Tenant

### 5.1 SeedTenantDatabase Job

```php
// app/Jobs/SeedTenantDatabase.php
<?php

namespace App\Jobs;

use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SeedTenantDatabase implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public Tenant $tenant
    ) {}

    public function handle(): void
    {
        $this->tenant->run(function () {
            // Seed dados iniciais do tenant
            $this->seedDefaultRoles();
            $this->seedDefaultPermissions();
        });
    }

    protected function seedDefaultRoles(): void
    {
        $roles = [
            ['name' => 'owner', 'display_name' => 'Owner', 'is_protected' => true],
            ['name' => 'admin', 'display_name' => 'Admin', 'is_protected' => true],
            ['name' => 'member', 'display_name' => 'Member', 'is_protected' => false],
        ];

        foreach ($roles as $role) {
            \App\Models\Role::create($role);
        }
    }

    protected function seedDefaultPermissions(): void
    {
        // Copiar permissions do template ou criar padrões
        $permissions = config('permission.default_permissions', []);

        foreach ($permissions as $permission) {
            \App\Models\Permission::create([
                'name' => $permission,
                'guard_name' => 'web',
            ]);
        }
    }
}
```

---

## Fase 6: Configurar Spatie Permission para Multi-Database

### 6.1 Atualizar config/permission.php

```php
// config/permission.php

return [
    'models' => [
        'permission' => App\Models\Permission::class,
        'role' => App\Models\Role::class,
    ],

    'table_names' => [
        'roles' => 'roles',
        'permissions' => 'permissions',
        'model_has_permissions' => 'model_has_permissions',
        'model_has_roles' => 'model_has_roles',
        'role_has_permissions' => 'role_has_permissions',
    ],

    /**
     * IMPORTANTE: Desabilitar teams para multi-database
     * Cada tenant tem seu próprio banco, não precisa de team_id
     */
    'teams' => false,

    /**
     * Cache - usar prefixo por tenant
     */
    'cache' => [
        'expiration_time' => \DateInterval::createFromDateString('24 hours'),
        'key' => 'spatie.permission.cache',
        'store' => 'default',
    ],
];
```

### 6.2 Bootstrapper Customizado para Permissions

```php
// app/Tenancy/Bootstrappers/SpatiePermissionsBootstrapper.php
<?php

namespace App\Tenancy\Bootstrappers;

use Stancl\Tenancy\Contracts\TenancyBootstrapper;
use Stancl\Tenancy\Contracts\Tenant;
use Spatie\Permission\PermissionRegistrar;

class SpatiePermissionsBootstrapper implements TenancyBootstrapper
{
    public function __construct(
        protected PermissionRegistrar $permissionRegistrar
    ) {}

    public function bootstrap(Tenant $tenant): void
    {
        // Limpar cache de permissions ao inicializar tenant
        $this->permissionRegistrar->forgetCachedPermissions();

        // Recarregar permissions do banco do tenant
        $this->permissionRegistrar->registerPermissions();
    }

    public function revert(): void
    {
        // Limpar cache ao sair do contexto
        $this->permissionRegistrar->forgetCachedPermissions();
    }
}
```

---

## Fase 7: Comandos Artisan Úteis

### 7.1 Comando para Criar Tenant

```php
// app/Console/Commands/CreateTenantCommand.php
<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Console\Command;

class CreateTenantCommand extends Command
{
    protected $signature = 'tenant:create
                            {name : Nome do tenant}
                            {domain : Domínio do tenant}
                            {--owner= : Email do owner}';

    protected $description = 'Cria um novo tenant com banco de dados dedicado';

    public function handle(): int
    {
        $name = $this->argument('name');
        $domain = $this->argument('domain');
        $ownerEmail = $this->option('owner');

        $this->info("Criando tenant: {$name}");

        // Buscar ou criar owner
        $owner = null;
        if ($ownerEmail) {
            $owner = User::where('email', $ownerEmail)->first();
            if (!$owner) {
                $this->error("Usuário não encontrado: {$ownerEmail}");
                return 1;
            }
        }

        // Criar tenant (dispara eventos de criação de banco)
        $tenant = Tenant::create([
            'name' => $name,
            'slug' => \Str::slug($name),
            'owner_id' => $owner?->id,
        ]);

        // Criar domínio
        $tenant->domains()->create([
            'domain' => $domain,
        ]);

        // Associar owner ao tenant
        if ($owner) {
            $tenant->users()->attach($owner->id, [
                'role' => 'owner',
                'joined_at' => now(),
            ]);
        }

        $this->info("Tenant criado com sucesso!");
        $this->info("ID: {$tenant->id}");
        $this->info("Database: tenant_{$tenant->id}");
        $this->info("Domain: {$domain}");

        return 0;
    }
}
```

### 7.2 Comando para Migrar Todos os Tenants

```bash
# Migrar banco central
php artisan migrate

# Migrar todos os tenants
php artisan tenants:migrate

# Migrar tenant específico
php artisan tenants:migrate --tenants=abc123

# Fresh migrate em todos (cuidado em produção!)
php artisan tenants:migrate-fresh

# Seed todos os tenants
php artisan tenants:seed
```

---

## Fase 8: Atualizar Seeders

### 8.1 DatabaseSeeder Central

```php
// database/seeders/DatabaseSeeder.php
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed central database
     */
    public function run(): void
    {
        $this->call([
            PlanSeeder::class,
            AddonSeeder::class,
            UserSeeder::class,     // Users globais (admin, etc.)
            TenantSeeder::class,   // Cria tenants de teste
        ]);
    }
}
```

### 8.2 TenantSeeder

```php
// database/seeders/TenantSeeder.php
<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;

class TenantSeeder extends Seeder
{
    public function run(): void
    {
        $tenants = [
            [
                'name' => 'Acme Corp',
                'slug' => 'acme',
                'domain' => 'tenant1.localhost',
                'owner_email' => 'john@acme.com',
                'plan' => 'professional',
            ],
            [
                'name' => 'Startup Inc',
                'slug' => 'startup',
                'domain' => 'tenant2.localhost',
                'owner_email' => 'jane@startup.com',
                'plan' => 'starter',
            ],
            [
                'name' => 'Enterprise Ltd',
                'slug' => 'enterprise',
                'domain' => 'tenant3.localhost',
                'owner_email' => 'mike@enterprise.com',
                'plan' => 'enterprise',
            ],
        ];

        foreach ($tenants as $data) {
            // Criar owner
            $owner = User::firstOrCreate(
                ['email' => $data['owner_email']],
                [
                    'name' => explode('@', $data['owner_email'])[0],
                    'password' => bcrypt('password'),
                    'email_verified_at' => now(),
                ]
            );

            // Criar tenant (dispara criação do banco)
            $tenant = Tenant::create([
                'name' => $data['name'],
                'slug' => $data['slug'],
                'owner_id' => $owner->id,
                'plan_id' => \App\Models\Plan::where('slug', $data['plan'])->first()?->id,
            ]);

            // Criar domínio
            $tenant->domains()->create([
                'domain' => $data['domain'],
            ]);

            // Associar owner
            $tenant->users()->attach($owner->id, [
                'role' => 'owner',
                'joined_at' => now(),
            ]);

            $this->command->info("Tenant criado: {$data['name']} ({$data['domain']})");
        }
    }
}
```

### 8.3 TenantDatabaseSeeder (Roda em cada tenant)

```php
// database/seeders/TenantDatabaseSeeder.php
<?php

namespace Database\Seeders;

use App\Models\Project;
use Illuminate\Database\Seeder;

class TenantDatabaseSeeder extends Seeder
{
    /**
     * Seed tenant database
     */
    public function run(): void
    {
        // Exemplo: criar projetos de demo
        Project::create([
            'user_id' => tenant()->owner_id,
            'name' => 'Projeto Demo',
            'description' => 'Projeto de demonstração',
            'status' => 'active',
        ]);
    }
}
```

---

## Fase 9: Checklist de Implementação

### 9.1 Pré-requisitos

- [ ] Backup do banco atual (se houver dados)
- [ ] Atualizar Stancl/Tenancy para v4 mais recente
- [ ] Verificar permissões do PostgreSQL para criar bancos

### 9.2 Configuração

- [ ] Atualizar `.env` com conexões central/tenant
- [ ] Atualizar `config/database.php`
- [ ] Atualizar `config/tenancy.php`
- [ ] Atualizar `config/permission.php`

### 9.3 Migrações

- [ ] Criar diretório `database/migrations/tenant/`
- [ ] Mover migrações de tenant para novo diretório
- [ ] Remover `tenant_id` das migrações de tenant
- [ ] Atualizar índices (remover tenant_id)

### 9.4 Models

- [ ] Atualizar `Tenant.php` com `HasDatabase`
- [ ] Remover `BelongsToTenant` dos models de tenant
- [ ] Adicionar `CentralConnection` aos models globais
- [ ] Atualizar relacionamentos cross-database

### 9.5 Providers & Bootstrappers

- [ ] Atualizar `TenancyServiceProvider`
- [ ] Criar `SpatiePermissionsBootstrapper`
- [ ] Habilitar `DatabaseTenancyBootstrapper`

### 9.6 Seeders

- [ ] Atualizar `DatabaseSeeder` (central)
- [ ] Criar `TenantDatabaseSeeder`
- [ ] Atualizar `TenantSeeder`

### 9.7 Testes

- [ ] Rodar `php artisan migrate:fresh --seed`
- [ ] Verificar criação de bancos de tenant
- [ ] Testar isolamento de dados
- [ ] Testar cross-database queries

---

## Fase 10: Comandos de Execução

```bash
# 1. Limpar tudo
php artisan cache:clear
php artisan config:clear

# 2. Dropar bancos existentes (se houver)
# No PostgreSQL: DROP DATABASE laravel; DROP DATABASE tenant_*;

# 3. Criar banco central
# No PostgreSQL: CREATE DATABASE laravel_central;

# 4. Rodar migrações centrais
php artisan migrate --path=database/migrations --database=central

# 5. Seed central (inclui criação de tenants e seus bancos)
php artisan db:seed

# 6. Verificar bancos criados
# No PostgreSQL: \l (lista bancos)

# 7. Testar acesso
curl http://tenant1.localhost
```

---

## Considerações de Segurança (LGPD/HIPAA)

### Isolamento Garantido

1. **Físico:** Cada tenant tem banco separado
2. **Lógico:** Conexões isoladas por request
3. **Backup:** Backup individual por tenant possível
4. **Auditoria:** Logs separados por banco

### Compliance Checklist

- [ ] Dados de tenant nunca cruzam bancos
- [ ] Backups individuais configurados
- [ ] Logs de acesso por tenant
- [ ] Criptografia em repouso (PostgreSQL)
- [ ] Criptografia em trânsito (SSL)
- [ ] Política de retenção de dados por tenant
- [ ] Processo de exclusão de dados (Right to Erasure)

---

## Referências

- [Stancl/Tenancy v4 - Multi-Database](https://v4.tenancyforlaravel.com/multi-database-tenancy)
- [Stancl/Tenancy v4 - Database Managers](https://v4.tenancyforlaravel.com/database-managers)
- [Stancl/Tenancy v4 - Migrations](https://v4.tenancyforlaravel.com/migrations)
- [Spatie Laravel Permission](https://spatie.be/docs/laravel-permission)
