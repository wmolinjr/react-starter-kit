# 02 - Database Schema e Migrations

## Índice

- [Visão Geral do Schema](#visão-geral-do-schema)
- [Tabelas Centrais (Global)](#tabelas-centrais-global)
- [Tabelas Tenant-Scoped](#tabelas-tenant-scoped)
- [Migrations Completas](#migrations-completas)
- [Indexes de Performance](#indexes-de-performance)
- [Seeding de Dados](#seeding-de-dados)
- [Checklist](#checklist)

---

## Visão Geral do Schema

Nossa arquitetura usa **single database com tenant_id isolation**. Isso significa:

- ✅ Um único banco de dados PostgreSQL/MySQL
- ✅ Tabelas globais (sem `tenant_id`): `users`, `tenants`, `domains`
- ✅ Tabelas tenant-scoped (com `tenant_id`): `projects`, `invoices`, etc.
- ✅ Pivot table `tenant_user` (N:N) para relacionar users ↔ tenants

```
┌─────────────────────────────────────────────────────────────┐
│                    PostgreSQL Database                       │
│                                                               │
│  GLOBAL TABLES (sem tenant_id)                               │
│  ┌──────────┐  ┌──────────┐  ┌──────────┐                  │
│  │  users   │  │ tenants  │  │ domains  │                  │
│  └─────┬────┘  └────┬─────┘  └────┬─────┘                  │
│        │            │              │                         │
│        └────────┬───┴──────────────┘                         │
│                 │                                            │
│  PIVOT TABLE    ▼                                            │
│  ┌─────────────────────┐                                    │
│  │   tenant_user       │                                    │
│  │   (N:N + roles)     │                                    │
│  └─────────────────────┘                                    │
│                                                               │
│  TENANT-SCOPED TABLES (com tenant_id)                        │
│  ┌──────────┐  ┌──────────┐  ┌──────────┐                  │
│  │projects  │  │ invoices │  │ documents│                  │
│  └──────────┘  └──────────┘  └──────────┘                  │
│                                                               │
│  BILLING (Cashier)                                           │
│  ┌──────────────┐  ┌───────────────┐                       │
│  │subscriptions │  │ subscription_ │                       │
│  │              │  │    items      │                       │
│  └──────────────┘  └───────────────┘                       │
│                                                               │
│  MEDIA (Spatie)                                              │
│  ┌─────────┐                                                 │
│  │  media  │                                                 │
│  └─────────┘                                                 │
└─────────────────────────────────────────────────────────────┘
```

---

## Tabelas Centrais (Global)

Estas tabelas **NÃO** têm `tenant_id` pois são compartilhadas:

### 1. `users` (já existe via Fortify)

```sql
CREATE TABLE users (
    id BIGSERIAL PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    email_verified_at TIMESTAMP NULL,
    password VARCHAR(255) NOT NULL,
    two_factor_secret TEXT NULL,
    two_factor_recovery_codes TEXT NULL,
    two_factor_confirmed_at TIMESTAMP NULL,
    remember_token VARCHAR(100) NULL,
    is_super_admin BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP NOT NULL,
    updated_at TIMESTAMP NOT NULL
);

CREATE INDEX users_email_index ON users(email);
CREATE INDEX users_is_super_admin_index ON users(is_super_admin);
```

### 2. `tenants`

```sql
CREATE TABLE tenants (
    id BIGSERIAL PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(255) UNIQUE NOT NULL,
    settings JSON NULL,
    created_at TIMESTAMP NOT NULL,
    updated_at TIMESTAMP NOT NULL
);

CREATE UNIQUE INDEX tenants_slug_unique ON tenants(slug);
```

**Coluna `settings` (JSON):**
```json
{
  "branding": {
    "logo_url": "https://...",
    "primary_color": "#3b82f6",
    "custom_css": "..."
  },
  "features": {
    "api_enabled": true,
    "custom_domain": true,
    "sso_enabled": false
  },
  "limits": {
    "max_users": 10,
    "max_projects": 50,
    "storage_mb": 1000
  }
}
```

### 3. `domains`

```sql
CREATE TABLE domains (
    id BIGSERIAL PRIMARY KEY,
    tenant_id BIGINT NOT NULL,
    domain VARCHAR(255) UNIQUE NOT NULL,
    is_primary BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP NOT NULL,
    updated_at TIMESTAMP NOT NULL,

    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
);

CREATE UNIQUE INDEX domains_domain_unique ON domains(domain);
CREATE INDEX domains_tenant_id_index ON domains(tenant_id);
CREATE INDEX domains_is_primary_index ON domains(tenant_id, is_primary);
```

**Exemplos:**
```
id | tenant_id | domain            | is_primary
---|-----------|-------------------|------------
1  | 1         | acme.setor3.app    | true
2  | 1         | acme.com          | false
3  | 2         | startup.setor3.app | true
```

### 4. `tenant_user` (Pivot N:N)

```sql
CREATE TABLE tenant_user (
    tenant_id BIGINT NOT NULL,
    user_id BIGINT NOT NULL,
    role VARCHAR(50) DEFAULT 'member' NOT NULL,
    permissions JSON NULL,
    invited_at TIMESTAMP NULL,
    invitation_token VARCHAR(255) NULL UNIQUE,
    joined_at TIMESTAMP NULL,
    created_at TIMESTAMP NOT NULL,
    updated_at TIMESTAMP NOT NULL,

    PRIMARY KEY (tenant_id, user_id),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,

    CHECK (role IN ('owner', 'admin', 'member', 'guest'))
);

CREATE INDEX tenant_user_user_id_index ON tenant_user(user_id);
CREATE INDEX tenant_user_invitation_token_index ON tenant_user(invitation_token);
```

**Roles:**
- `owner` - Criador do tenant, acesso total incluindo billing
- `admin` - Gerencia equipe e configurações (exceto billing)
- `member` - Usa a aplicação, acesso limitado
- `guest` - Apenas leitura

**Exemplos:**
```
tenant_id | user_id | role   | invited_at          | joined_at
----------|---------|--------|---------------------|--------------------
1         | 10      | owner  | NULL                | 2025-01-15 10:00
1         | 11      | admin  | 2025-01-15 10:30    | 2025-01-15 11:00
1         | 12      | member | 2025-01-16 14:00    | NULL (pending)
```

---

## Tabelas Tenant-Scoped

Todas as tabelas abaixo **DEVEM** ter `tenant_id` e índices apropriados.

### Tabelas do Cashier (Billing)

Geradas automaticamente por `php artisan cashier:install`:

#### `subscriptions`

```sql
CREATE TABLE subscriptions (
    id BIGSERIAL PRIMARY KEY,
    tenant_id BIGINT NOT NULL,  -- ⚠️ Adicionar manualmente!
    type VARCHAR(255) NOT NULL,
    stripe_id VARCHAR(255) UNIQUE NOT NULL,
    stripe_status VARCHAR(255) NOT NULL,
    stripe_price VARCHAR(255) NULL,
    quantity INTEGER NULL,
    trial_ends_at TIMESTAMP NULL,
    ends_at TIMESTAMP NULL,
    created_at TIMESTAMP NOT NULL,
    updated_at TIMESTAMP NOT NULL,

    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
);

CREATE INDEX subscriptions_tenant_id_index ON subscriptions(tenant_id);
CREATE INDEX subscriptions_stripe_id_index ON subscriptions(stripe_id);
CREATE INDEX subscriptions_stripe_status_index ON subscriptions(stripe_status);
```

#### `subscription_items`

```sql
CREATE TABLE subscription_items (
    id BIGSERIAL PRIMARY KEY,
    subscription_id BIGINT NOT NULL,
    stripe_id VARCHAR(255) UNIQUE NOT NULL,
    stripe_product VARCHAR(255) NOT NULL,
    stripe_price VARCHAR(255) NOT NULL,
    quantity INTEGER NULL,
    created_at TIMESTAMP NOT NULL,
    updated_at TIMESTAMP NOT NULL,

    FOREIGN KEY (subscription_id) REFERENCES subscriptions(id) ON DELETE CASCADE
);

CREATE INDEX subscription_items_subscription_id_index ON subscription_items(subscription_id);
```

### Tabelas do MediaLibrary (Files)

Geradas por `php artisan migrate` após publicar migrations:

#### `media`

```sql
CREATE TABLE media (
    id BIGSERIAL PRIMARY KEY,
    model_type VARCHAR(255) NOT NULL,
    model_id BIGINT NOT NULL,
    uuid UUID NULL UNIQUE,
    collection_name VARCHAR(255) NOT NULL,
    name VARCHAR(255) NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    mime_type VARCHAR(255) NULL,
    disk VARCHAR(255) NOT NULL,
    conversions_disk VARCHAR(255) NULL,
    size BIGINT NOT NULL,
    manipulations JSON NULL,
    custom_properties JSON NULL,
    generated_conversions JSON NULL,
    responsive_images JSON NULL,
    order_column INTEGER NULL,
    created_at TIMESTAMP NOT NULL,
    updated_at TIMESTAMP NOT NULL
);

CREATE INDEX media_model_type_model_id_index ON media(model_type, model_id);
CREATE INDEX media_uuid_unique ON media(uuid);
```

**⚠️ Importante:** A tabela `media` usa polymorphic relationships, então o isolamento acontece via modelo (ex: `Project` tem `tenant_id`).

### Tabelas Custom (Exemplo: Projects)

Estas você criará conforme sua aplicação:

#### `projects`

```sql
CREATE TABLE projects (
    id BIGSERIAL PRIMARY KEY,
    tenant_id BIGINT NOT NULL,
    user_id BIGINT NOT NULL,  -- Creator
    name VARCHAR(255) NOT NULL,
    description TEXT NULL,
    status VARCHAR(50) DEFAULT 'active' NOT NULL,
    created_at TIMESTAMP NOT NULL,
    updated_at TIMESTAMP NOT NULL,

    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,

    CHECK (status IN ('active', 'archived', 'deleted'))
);

-- ⚠️ CRÍTICO: Index em tenant_id para performance
CREATE INDEX projects_tenant_id_index ON projects(tenant_id);
CREATE INDEX projects_user_id_index ON projects(user_id);
CREATE INDEX projects_tenant_id_created_at_index ON projects(tenant_id, created_at);
```

---

## Migrations Completas

### Migration 1: Create Tenants Table

```bash
php artisan make:migration create_tenants_table
```

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->json('settings')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};
```

### Migration 2: Create Domains Table

```bash
php artisan make:migration create_domains_table
```

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('domains', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('domain')->unique();
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            $table->index('tenant_id');
            $table->index(['tenant_id', 'is_primary']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('domains');
    }
};
```

### Migration 3: Create Tenant User Pivot Table

```bash
php artisan make:migration create_tenant_user_table
```

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_user', function (Blueprint $table) {
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('role', ['owner', 'admin', 'member', 'guest'])->default('member');
            $table->json('permissions')->nullable();
            $table->timestamp('invited_at')->nullable();
            $table->string('invitation_token')->nullable()->unique();
            $table->timestamp('joined_at')->nullable();
            $table->timestamps();

            $table->primary(['tenant_id', 'user_id']);
            $table->index('user_id');
            $table->index('invitation_token');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_user');
    }
};
```

### Migration 4: Add is_super_admin to Users

```bash
php artisan make:migration add_is_super_admin_to_users_table
```

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_super_admin')->default(false)->after('remember_token');
            $table->index('is_super_admin');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['is_super_admin']);
            $table->dropColumn('is_super_admin');
        });
    }
};
```

### Migration 5: Add Tenant ID to Subscriptions (Cashier)

**⚠️ Executar APÓS instalar Cashier**

```bash
php artisan make:migration add_tenant_id_to_subscriptions_table
```

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->foreignId('tenant_id')
                ->after('id')
                ->constrained()
                ->cascadeOnDelete();

            $table->index('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropForeign(['tenant_id']);
            $table->dropIndex(['tenant_id']);
            $table->dropColumn('tenant_id');
        });
    }
};
```

### Migration 6: Create Projects Table (Exemplo)

```bash
php artisan make:migration create_projects_table
```

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->enum('status', ['active', 'archived', 'deleted'])->default('active');
            $table->timestamps();

            // ⚠️ CRÍTICO: Sempre indexar tenant_id
            $table->index('tenant_id');
            $table->index('user_id');
            $table->index(['tenant_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
```

---

## Indexes de Performance

### Por que indexes são críticos?

Sem indexes em `tenant_id`, queries podem fazer **table scans completos**, tornando a aplicação lenta com muitos dados.

**❌ Sem index:**
```sql
SELECT * FROM projects WHERE tenant_id = 1;
-- Scan ALL rows (slow!)
```

**✅ Com index:**
```sql
SELECT * FROM projects WHERE tenant_id = 1;
-- Use index, instant!
```

### Índices Obrigatórios

Para **TODAS** as tabelas tenant-scoped:

```php
// Migration
Schema::create('your_table', function (Blueprint $table) {
    $table->id();
    $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
    // ... outras colunas

    // ⚠️ OBRIGATÓRIO
    $table->index('tenant_id');

    // ⚠️ RECOMENDADO: Composite index para queries comuns
    $table->index(['tenant_id', 'created_at']);
    $table->index(['tenant_id', 'status']);
});
```

### Verificar Indexes Existentes

```sql
-- PostgreSQL
SELECT
    tablename,
    indexname,
    indexdef
FROM pg_indexes
WHERE schemaname = 'public'
ORDER BY tablename, indexname;
```

---

## Seeding de Dados

### Criar Seeder para Tenants de Teste

```bash
php artisan make:seeder TenantSeeder
```

```php
<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class TenantSeeder extends Seeder
{
    public function run(): void
    {
        // Criar super admin
        $superAdmin = User::create([
            'name' => 'Super Admin',
            'email' => 'admin@setor3.app',
            'password' => bcrypt('password'),
            'is_super_admin' => true,
            'email_verified_at' => now(),
        ]);

        // Tenant 1: Acme Corp
        $acme = Tenant::create([
            'name' => 'Acme Corp',
            'slug' => 'acme',
            'settings' => [
                'branding' => [
                    'primary_color' => '#3b82f6',
                ],
                'limits' => [
                    'max_users' => 50,
                    'max_projects' => 100,
                ],
            ],
        ]);

        $acme->domains()->create([
            'domain' => 'acme.myapp.test',
            'is_primary' => true,
        ]);

        $acmeOwner = User::create([
            'name' => 'John Doe',
            'email' => 'john@acme.com',
            'password' => bcrypt('password'),
            'email_verified_at' => now(),
        ]);

        $acme->users()->attach($acmeOwner->id, [
            'role' => 'owner',
            'joined_at' => now(),
        ]);

        // Tenant 2: Startup Inc
        $startup = Tenant::create([
            'name' => 'Startup Inc',
            'slug' => 'startup',
        ]);

        $startup->domains()->create([
            'domain' => 'startup.myapp.test',
            'is_primary' => true,
        ]);

        $startupOwner = User::create([
            'name' => 'Jane Smith',
            'email' => 'jane@startup.com',
            'password' => bcrypt('password'),
            'email_verified_at' => now(),
        ]);

        $startup->users()->attach($startupOwner->id, [
            'role' => 'owner',
            'joined_at' => now(),
        ]);

        $this->command->info('✅ Tenants created successfully!');
        $this->command->info('  - acme.myapp.test (john@acme.com)');
        $this->command->info('  - startup.myapp.test (jane@startup.com)');
        $this->command->info('  - Super admin: admin@setor3.app');
    }
}
```

**Adicionar ao DatabaseSeeder:**

```php
// database/seeders/DatabaseSeeder.php
public function run(): void
{
    $this->call([
        TenantSeeder::class,
    ]);
}
```

**Executar:**

```bash
php artisan migrate:fresh --seed
```

---

## Checklist

Antes de prosseguir, certifique-se:

- [x] Migration `create_tenants_table` criada e executada ✅
- [x] Migration `create_domains_table` criada e executada ✅
- [x] Migration `create_tenant_user_table` criada e executada ✅
- [x] Migration `add_is_super_admin_to_users_table` criada e executada ✅
- [x] Cashier migrations executadas ✅
- [x] Migration `add_tenant_id_to_subscriptions_table` criada e executada ✅
- [x] MediaLibrary migrations executadas ✅
- [x] Todas as tabelas tenant-scoped têm `tenant_id` **E** indexes ✅
- [x] Seeder de tenants de teste criado ✅
- [x] Comando `php artisan migrate:fresh --seed` executado com sucesso ✅
- [x] Tabelas criadas corretamente (verificar com `php artisan migrate:status`) ✅

**Verificar tabelas:**
```bash
# PostgreSQL
psql -U sail -d laravel -c "\dt"

# MySQL
mysql -u sail -p -e "SHOW TABLES FROM laravel;"
```

---

## Próximo Passo

Agora que o schema está pronto, vamos criar os Models com relacionamentos:

➡️ **[03-MODELS.md](./03-MODELS.md)** - Models e Relacionamentos

---

**Versão:** 1.0
**Última atualização:** 2025-11-19
