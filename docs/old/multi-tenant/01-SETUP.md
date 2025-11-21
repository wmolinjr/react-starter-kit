# 01 - Setup e Instalação

## Índice

- [Pré-requisitos](#pré-requisitos)
- [Instalação dos Pacotes](#instalação-dos-pacotes)
- [Configuração do Tenancy](#configuração-do-tenancy)
- [Variáveis de Ambiente](#variáveis-de-ambiente)
- [Configuração de Domínios Locais](#configuração-de-domínios-locais)
- [Verificação da Instalação](#verificação-da-instalação)
- [Checklist](#checklist)

---

## Pré-requisitos

Antes de começar, certifique-se de que você tem:

- ✅ Laravel 12 instalado (já temos no starter kit)
- ✅ PHP 8.4+
- ✅ PostgreSQL 18+ (ou SQLite para desenvolvimento)
- ✅ Redis (para cache e queues)
- ✅ Node.js 20+ e npm
- ✅ Composer 2+

**Ambiente de Desenvolvimento:**
- Laravel Herd (macOS) **[Recomendado]**
- Laravel Valet (macOS)
- Laravel Sail (Docker - todas as plataformas)
- Ambiente local (PHP + PostgreSQL nativos)

---

## Instalação dos Pacotes

### 1. Instalar archtechx/tenancy

```bash
composer require archtechx/tenancy
```

**Publicar configuração:**
```bash
php artisan vendor:publish --provider="Stancl\Tenancy\TenancyServiceProvider"
```

Isso criará:
- `config/tenancy.php` - Configuração principal
- `app/Providers/TenancyServiceProvider.php` - Service provider
- `routes/tenant.php` - Rotas tenant-scoped

**Registrar Service Provider (Laravel 12):**

Adicione ao `bootstrap/providers.php`:
```php
return [
    App\Providers\AppServiceProvider::class,
    App\Providers\TenancyServiceProvider::class, // ← Adicionar
];
```

### 2. Instalar Laravel Cashier (Stripe)

```bash
composer require laravel/cashier
```

**Publicar migration e configuração:**
```bash
php artisan vendor:publish --tag="cashier-migrations"
php artisan vendor:publish --tag="cashier-config"
```

**Adicionar ao `.env`:**
```env
STRIPE_KEY=pk_test_...
STRIPE_SECRET=sk_test_...
STRIPE_WEBHOOK_SECRET=whsec_...
```

### 3. Instalar Spatie Laravel MediaLibrary

```bash
composer require spatie/laravel-medialibrary
```

**Publicar migration e configuração:**
```bash
php artisan vendor:publish --provider="Spatie\MediaLibrary\MediaLibraryServiceProvider" --tag="medialibrary-migrations"
php artisan vendor:publish --provider="Spatie\MediaLibrary\MediaLibraryServiceProvider" --tag="medialibrary-config"
```

### 4. Instalar Laravel Sanctum (se ainda não estiver)

```bash
# Verificar se já está instalado
composer show laravel/sanctum

# Se não estiver:
composer require laravel/sanctum
php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"
```

**Verificar instalações:**
```bash
composer show | grep -E "archtechx/tenancy|laravel/cashier|spatie/laravel-medialibrary|laravel/sanctum"
```

Saída esperada:
```
archtechx/tenancy              v4.x-dev
laravel/cashier                v15.x-dev
laravel/sanctum                v4.x-dev
spatie/laravel-medialibrary    v11.x-dev
```

---

## Configuração do Tenancy

### 1. Editar `config/tenancy.php`

Abra o arquivo `config/tenancy.php` e configure:

```php
<?php

use Stancl\Tenancy\Database\Models\Domain;

return [
    /**
     * Tenant model usado pela aplicação
     */
    'tenant_model' => \App\Models\Tenant::class,

    /**
     * ID do tenant (coluna usada nas tabelas)
     */
    'id_generator' => null, // Auto-increment

    /**
     * Domain model
     */
    'domain_model' => Domain::class,

    /**
     * Domínios centrais (não são tenants)
     */
    'central_domains' => [
        'myapp.test',        // Local
        'app.setor3.app',     // Produção
    ],

    /**
     * Estratégia de identificação do tenant
     */
    'bootstrappers' => [
        Stancl\Tenancy\Bootstrappers\DatabaseTenancyBootstrapper::class,
        Stancl\Tenancy\Bootstrappers\CacheTenancyBootstrapper::class,
        Stancl\Tenancy\Bootstrappers\FilesystemTenancyBootstrapper::class,
        Stancl\Tenancy\Bootstrappers\QueueTenancyBootstrapper::class,
        // Stancl\Tenancy\Bootstrappers\RedisTenancyBootstrapper::class, // Se usar Redis
    ],

    /**
     * Features habilitadas
     */
    'features' => [
        // Stancl\Tenancy\Features\UserImpersonation::class,
        // Stancl\Tenancy\Features\TelescopeTags::class,
        // Stancl\Tenancy\Features\Tenancy::class,
    ],

    /**
     * Ações a executar quando o tenant é criado, atualizado, deletado
     */
    'database' => [
        'central_connection' => env('DB_CONNECTION', 'pgsql'),

        /**
         * Usamos SAME DATABASE (tenant_id isolation)
         * Não multi-database
         */
        'template_tenant_connection' => null,
    ],

    'redis' => [
        'prefixed_connections' => [
            // 'default',
        ],
    ],

    'cache' => [
        'tag_base' => 'tenant',
    ],

    'filesystem' => [
        /**
         * Disks que devem ter prefixo por tenant
         */
        'disks' => [
            'public',
            // 's3',
        ],

        /**
         * Root override - cria pasta por tenant
         */
        'root_override' => [
            'public' => '%storage_path%/app/public/tenants/%tenant_id%',
        ],

        /**
         * URL override
         */
        'url_override' => [
            'public' => '/storage/tenants/%tenant_id%',
        ],
    ],
];
```

### 2. Editar `app/Providers/TenancyServiceProvider.php`

Este arquivo foi criado ao publicar o package. Vamos configurá-lo:

```php
<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Stancl\Tenancy\Events;
use Stancl\Tenancy\Jobs;
use Stancl\Tenancy\Listeners;

class TenancyServiceProvider extends ServiceProvider
{
    /**
     * Eventos e listeners do tenancy
     */
    public function boot(): void
    {
        $this->bootEvents();
        $this->bootMiddleware();
    }

    protected function bootEvents(): void
    {
        // Executar quando tenant é criado
        Events\TenantCreated::class => [
            // Jobs\CreateDatabase::class, // Apenas para multi-database
            // Jobs\MigrateDatabase::class, // Apenas para multi-database

            // Criar domínio padrão baseado no slug
            function (Events\TenantCreated $event) {
                $tenant = $event->tenant;

                // Criar domínio slug.myapp.test (local) ou slug.setor3.app (prod)
                $domain = config('app.env') === 'local'
                    ? "{$tenant->slug}.myapp.test"
                    : "{$tenant->slug}.setor3.app";

                $tenant->domains()->create([
                    'domain' => $domain,
                    'is_primary' => true,
                ]);
            },
        ];

        // Executar quando tenant é deletado
        Events\TenantDeleted::class => [
            // Jobs\DeleteDatabase::class, // Apenas para multi-database
        ];
    }

    protected function bootMiddleware(): void
    {
        // Middleware já está registrado no tenancy config
    }
}
```

### 3. Criar arquivo `routes/tenant.php`

Se não foi criado automaticamente:

```php
<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

/*
|--------------------------------------------------------------------------
| Tenant Routes
|--------------------------------------------------------------------------
|
| Aqui ficam TODAS as rotas que devem ter contexto de tenant.
| O middleware InitializeTenancyByDomain identifica o tenant pelo domínio.
|
*/

Route::middleware([
    'web',
    InitializeTenancyByDomain::class,
    PreventAccessFromCentralDomains::class,
])->group(function () {

    // Rotas públicas do tenant (sem auth)
    Route::get('/', function () {
        return inertia('tenant/welcome');
    });

    // Rotas autenticadas do tenant
    Route::middleware(['auth'])->group(function () {
        Route::get('/dashboard', function () {
            return inertia('dashboard');
        })->name('dashboard');

        // Mais rotas serão adicionadas nas próximas etapas
    });
});
```

---

## Variáveis de Ambiente

### Atualizar `.env`

Adicione/atualize as seguintes variáveis:

```env
# App
APP_NAME="My SaaS App"
APP_ENV=local
APP_URL=http://myapp.test

# Tenancy
CENTRAL_DOMAIN=myapp.test
TENANT_DOMAIN_SUFFIX=.myapp.test

# Database (PostgreSQL recomendado para produção)
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=laravel
DB_USERNAME=sail
DB_PASSWORD=password

# Stripe (Cashier)
STRIPE_KEY=pk_test_...
STRIPE_SECRET=sk_test_...
STRIPE_WEBHOOK_SECRET=whsec_...

# Cache & Queue (Redis recomendado)
CACHE_STORE=redis
QUEUE_CONNECTION=redis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

# Mail (para convites)
MAIL_MAILER=smtp
MAIL_HOST=mailpit
MAIL_PORT=1025
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_ENCRYPTION=null
MAIL_FROM_ADDRESS="hello@myapp.test"
MAIL_FROM_NAME="${APP_NAME}"

# Telescope
TELESCOPE_ENABLED=true
```

### Criar `.env.example`

Copie o `.env` e remova valores sensíveis:

```bash
cp .env .env.example
```

Edite `.env.example` e remova:
- `STRIPE_KEY`, `STRIPE_SECRET`, `STRIPE_WEBHOOK_SECRET`
- `DB_PASSWORD`
- Qualquer chave secreta

---

## Configuração de Domínios Locais

Para testar multi-tenancy localmente, você precisa configurar subdomínios.

### Opção 1: Laravel Herd (macOS - Recomendado)

```bash
# Herd já suporta subdomínios automaticamente!
# Apenas certifique-se de que o projeto está na pasta do Herd

# Acesse:
# - myapp.test (central)
# - tenant1.myapp.test (tenant)
# - tenant2.myapp.test (tenant)
```

### Opção 2: Laravel Valet (macOS)

```bash
# No diretório do projeto
valet link myapp
valet domain test

# Agora você pode acessar:
# - myapp.test
# - tenant1.myapp.test
# - tenant2.myapp.test
```

### Opção 3: Laravel Sail (Docker)

**Editar `docker-compose.yml`:**

```yaml
services:
    laravel.test:
        # ...
        environment:
            # ...
            APP_URL: 'http://myapp.test'
        labels:
            - "traefik.enable=true"
            - "traefik.http.routers.laravel.rule=HostRegexp(`{subdomain:[a-z0-9-]+}.myapp.test`)"
```

**Adicionar ao `/etc/hosts`:**

```bash
sudo nano /etc/hosts
```

Adicione:
```
127.0.0.1 myapp.test
127.0.0.1 tenant1.myapp.test
127.0.0.1 tenant2.myapp.test
```

### Opção 4: Local (sem Herd/Valet/Sail)

**Editar `/etc/hosts` (Linux/macOS) ou `C:\Windows\System32\drivers\etc\hosts` (Windows):**

```bash
sudo nano /etc/hosts
```

Adicionar:
```
127.0.0.1 myapp.test
127.0.0.1 tenant1.myapp.test
127.0.0.1 tenant2.myapp.test
```

**Configurar servidor web:**

**Apache (`/etc/apache2/sites-available/myapp.conf`):**
```apache
<VirtualHost *:80>
    ServerName myapp.test
    ServerAlias *.myapp.test
    DocumentRoot /path/to/project/public

    <Directory /path/to/project/public>
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

**Nginx (`/etc/nginx/sites-available/myapp`):**
```nginx
server {
    listen 80;
    server_name myapp.test *.myapp.test;
    root /path/to/project/public;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    index index.php;

    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.4-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

Habilitar site:
```bash
# Apache
sudo a2ensite myapp
sudo systemctl reload apache2

# Nginx
sudo ln -s /etc/nginx/sites-available/myapp /etc/nginx/sites-enabled/
sudo systemctl reload nginx
```

---

## Verificação da Instalação

### 1. Verificar Pacotes

```bash
composer show | grep -E "archtechx|cashier|medialibrary|sanctum"
```

### 2. Verificar Configurações

```bash
# Verificar tenancy config
php artisan config:show tenancy

# Verificar cashier config
php artisan config:show cashier
```

### 3. Teste de Domínios

**Acessar central app:**
```bash
curl -H "Host: myapp.test" http://localhost
```

**Criar tenant de teste (Console):**
```bash
php artisan tinker
```

```php
$tenant = \App\Models\Tenant::create([
    'name' => 'Acme Corp',
    'slug' => 'acme',
]);

$tenant->domains()->create([
    'domain' => 'acme.myapp.test',
    'is_primary' => true,
]);
```

**Acessar tenant app:**
```bash
curl -H "Host: acme.myapp.test" http://localhost
```

### 4. Verificar Middleware

Criar rota de teste em `routes/tenant.php`:

```php
Route::get('/test', function () {
    return response()->json([
        'tenant_id' => tenant('id'),
        'tenant_name' => tenant('name'),
        'domain' => request()->getHost(),
    ]);
})->middleware([
    InitializeTenancyByDomain::class,
]);
```

Acessar: `http://acme.myapp.test/test`

Resposta esperada:
```json
{
    "tenant_id": 1,
    "tenant_name": "Acme Corp",
    "domain": "acme.myapp.test"
}
```

---

## Checklist

Antes de prosseguir para o próximo passo, certifique-se:

- [x] Todos os pacotes instalados (`stancl/tenancy`, `cashier`, `medialibrary`, `sanctum`) ✅
- [x] `config/tenancy.php` configurado corretamente ✅
- [x] `TenancyServiceProvider` registrado em `bootstrap/providers.php` ✅
- [x] `routes/tenant.php` criado ✅
- [x] `.env` configurado com variáveis necessárias ✅
- [ ] Domínios locais configurados (Herd/Valet/Sail ou `/etc/hosts`) - ⏳ Etapa 04
- [ ] Teste de acesso ao central app (`myapp.test`) funciona - ⏳ Etapa 04
- [ ] Teste de acesso ao tenant app (`tenant.myapp.test`) funciona - ⏳ Etapa 04
- [ ] Middleware de tenancy funciona (tenant context inicializado) - ⏳ Etapa 04

---

## Próximo Passo

Agora que o setup está completo, vamos criar o schema do banco de dados:

➡️ **[02-DATABASE.md](02-DATABASE.md)** - Schema e Migrations

---

## Troubleshooting

### Problema: "Class Stancl\Tenancy\TenancyServiceProvider not found"

**Solução:**
```bash
composer dump-autoload
php artisan optimize:clear
```

### Problema: Subdomain não resolve localmente

**Solução:**
- Verifique `/etc/hosts` (ou Windows equivalent)
- Verifique Herd/Valet: `valet links`
- Limpe cache DNS: `sudo dscacheutil -flushcache` (macOS)

### Problema: "No tenant found for domain"

**Solução:**
- Verifique se o domínio existe na tabela `domains`
- Verifique se o tenant existe na tabela `tenants`
- Verifique `config/tenancy.php` central_domains

---

**Versão:** 1.0
**Última atualização:** 2025-11-19
