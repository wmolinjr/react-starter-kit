# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is a Laravel + React starter kit that combines Laravel 12 backend with React 19 frontend using Inertia.js. The stack includes:
- **Backend**: Laravel 12, Laravel Fortify (authentication), Laravel Wayfinder
- **Frontend**: React 19, TypeScript, Tailwind CSS 4, shadcn/ui components, Radix UI, Lucide icons
- **Build Tools**: Vite 7, Laravel Vite plugin
- **Other**: React Compiler enabled, SSR support available

## Development Commands

**Nota**: Este projeto suporta desenvolvimento com Laravel Sail (Docker) ou localmente. Ver seção "Ambiente de Desenvolvimento" para comandos Sail completos.

### Initial Setup

**Com Sail (Recomendado)**:
```bash
sail up -d               # Inicia containers Docker
sail artisan migrate     # Roda migrations
sail npm install         # Instala dependências frontend
sail npm run build       # Build inicial
```

**Sem Sail (Local)**:
```bash
composer setup
# Runs: composer install, copies .env, generates key, runs migrations, npm install, npm run build
```

### Development Server

**Com Sail**:
```bash
sail up -d              # Containers em background (PostgreSQL, Redis, App já rodando)
sail npm run dev        # Vite dev server (porta 5173)
```

**Sem Sail**:
```bash
composer dev
# Starts 4 concurrent processes:
# - PHP dev server (php artisan serve)
# - Queue worker (php artisan queue:listen)
# - Log viewer (php artisan pail)
# - Vite dev server (npm run dev)
```

### SSR Development
```bash
composer dev:ssr
# Builds SSR bundle first, then runs dev server with Inertia SSR instead of Vite
```

### Frontend Commands
```bash
npm run dev          # Start Vite dev server
npm run build        # Build for production
npm run build:ssr    # Build with SSR support
npm run lint         # Run ESLint with auto-fix
npm run format       # Format code with Prettier
npm run format:check # Check formatting without changes
npm run types        # Type-check TypeScript without emitting files
```

### Backend Commands

**Com Sail**:
```bash
sail artisan test                      # Run PHPUnit tests
sail artisan test --filter TestName   # Run specific test
sail artisan migrate                   # Run migrations
sail artisan tinker                    # Interactive shell
sail shell                             # Bash shell no container
```

**Sem Sail**:
```bash
composer test              # Run PHPUnit tests
php artisan test           # Run tests (alternative)
php artisan test --filter TestName  # Run specific test
vendor/bin/phpunit --testsuite Unit # Run unit tests only
vendor/bin/phpunit --testsuite Feature # Run feature tests only
```

### Code Quality
```bash
vendor/bin/pint        # Format PHP code with Laravel Pint
vendor/bin/pint --test # Check formatting without changes
```

## Ambiente de Desenvolvimento

Este projeto suporta desenvolvimento com **Laravel Sail** (Docker) ou localmente com PHP nativo.

### Laravel Sail (Docker - Recomendado)

**Serviços Configurados:**
- **App**: PHP 8.4 com Laravel
- **PostgreSQL**: 18-alpine (porta 5432)
- **Redis**: alpine (porta 6379)
- **Volumes**: Persistência para banco e cache

**Comandos Principais:**
```bash
# Gerenciamento de containers
sail up                 # Inicia todos os serviços
sail up -d              # Inicia em background
sail down               # Para todos os containers
sail restart            # Reinicia containers
sail ps                 # Status dos containers

# Desenvolvimento
sail artisan migrate    # Rodar migrations
sail artisan tinker     # PHP interactive shell
sail npm run dev        # Vite dev server (porta 5173)
sail npm run build      # Build para produção
sail artisan test       # Rodar testes
sail shell              # Acessa shell do container

# Logs
sail logs               # Ver logs de todos os serviços
sail logs -f            # Seguir logs em tempo real
```

**Configuração do Banco (.env com Sail):**
```env
DB_CONNECTION=pgsql
DB_HOST=pgsql          # Nome do serviço Docker
DB_PORT=5432
DB_DATABASE=laravel
DB_USERNAME=sail
DB_PASSWORD=password

REDIS_HOST=redis       # Nome do serviço Docker
```

**Portas Expostas:**
- App: `APP_PORT` (padrão: 80)
- Vite: `VITE_PORT` (padrão: 5173)
- PostgreSQL: `FORWARD_DB_PORT` (padrão: 5432)
- Redis: `FORWARD_REDIS_PORT` (padrão: 6379)

### Desenvolvimento Local (Sem Docker)

Use os comandos `composer dev` conforme documentado acima. O projeto usa SQLite por padrão quando não está com Sail.

## Architecture

### Inertia Integration

Inertia bridges Laravel and React, enabling classic server-side routing with React components as views.

**Key Flow:**
1. Routes defined in `routes/web.php` and `routes/settings.php`
2. Controllers return `Inertia::render('page-name', $props)` instead of Blade views
3. Inertia middleware (`HandleInertiaRequests.php`) shares global props to all pages
4. React pages in `resources/js/pages/` receive props and render

**Shared Props** (available to all pages via `HandleInertiaRequests.php:38-49`):
- `name`: App name from config
- `quote`: Random inspiring quote
- `auth.user`: Current authenticated user
- `sidebarOpen`: Sidebar state from cookie

### Frontend Structure

**Pages** (`resources/js/pages/`): Inertia page components
- Auto-resolved via glob pattern: `./pages/${name}.tsx`
- Examples: `auth/login.tsx`, `dashboard.tsx`, `settings/profile.tsx`

**Layouts** (`resources/js/layouts/`):
- `app-layout.tsx`: Main authenticated app layout
- `auth-layout.tsx`: Authentication pages layout
- Nested layouts in subdirectories: `app/`, `auth/`, `settings/`

**Components**:
- `resources/js/components/`: Application-specific components
- `resources/js/components/ui/`: shadcn/ui components (managed by shadcn CLI)
- Import aliases configured in `components.json` and `tsconfig.json`

**Hooks** (`resources/js/hooks/`):
- `use-appearance.tsx`: Light/dark theme management
- `use-mobile.tsx`, `use-mobile-navigation.ts`: Mobile responsiveness
- `use-two-factor-auth.ts`: 2FA functionality
- `use-clipboard.ts`, `use-initials.tsx`: Utilities

**Entry Point** (`resources/js/app.tsx`):
- Initializes Inertia app
- Configures page resolution
- Sets theme on load

### Backend Structure

**Authentication**: Laravel Fortify handles registration, login, password reset, email verification, and 2FA
- Configuration in `FortifyServiceProvider.php`
- All auth views return Inertia pages instead of Blade
- Custom actions in `app/Actions/Fortify/`

**Controllers** (`app/Http/Controllers/`):
- `Settings/`: Profile, password, 2FA controllers for settings pages
- All return Inertia responses

**Middleware**:
- `HandleInertiaRequests`: Shares global props
- `HandleAppearance`: Manages theme preference cookie

**Routes**:
- `routes/web.php`: Public routes and dashboard
- `routes/settings.php`: Settings pages (all auth-protected)
- `routes/console.php`: Artisan commands

### Type Safety

**TypeScript Configuration**:
- Path aliases: `@/` maps to `resources/js/`
- Strict type checking enabled
- Inertia page props are typed via `resources/js/types/index.d.ts`

**Laravel Wayfinder**: Generates type-safe route helpers for TypeScript from Laravel routes

### Database IDs Architecture

**Decision: Auto-Increment IDs (bigint)**

This project uses **auto-increment integer IDs** (PostgreSQL BIGSERIAL/bigint) for all primary keys, including multi-tenant models.

**Rationale:**

1. **Security Through Proper Authorization:** Multi-tenancy security relies on global scopes (`BelongsToTenant` trait), route model binding validation, and middleware—NOT on ID obscurity. UUIDs provide zero additional security.

2. **Performance Benefits:**
   - 50% smaller database storage (8 bytes vs 16-36 bytes per ID)
   - Faster queries (integer comparison vs string/binary)
   - Better index performance (sequential inserts, less B-tree fragmentation)
   - Optimal PostgreSQL performance

3. **Developer Experience:**
   - Clean URLs: `/projects/123` vs `/projects/550e8400-e29b-41d4-a716...`
   - Easy debugging: "Project 123 updated" vs "Project 550e8400... updated"
   - Consistent TypeScript types: `id: number` everywhere

4. **Future-Proof:** Auto-increment IDs work perfectly for database-per-tenant migration (if needed). Each tenant's isolated database will have independent ID sequences without conflicts.

**Security Model:**

```php
// Example: Route model binding with tenant validation
Route::bind('project', function (string $value) {
    if (tenancy()->initialized) {
        return Project::where('id', $value)
            ->where('tenant_id', tenant('id'))  // ✅ Validates tenant ownership
            ->firstOrFail();
    }
    return Project::findOrFail($value);
});
```

**Attack Scenario (Mitigated):**
```
Attacker in Tenant 1 tries: GET /projects/123
→ Global Scope filters: WHERE tenant_id = 1 AND id = 123
→ If project 123 belongs to Tenant 2: 404 Not Found
→ Attack fails regardless of ID type
```

**Configuration:**
- `config/tenancy.php:10` → `'id_generator' => null` (auto-increment)
- All migrations use `$table->id()` (bigint auto-increment)
- TypeScript interfaces use `id: number` consistently

**Note:** Previous versions had a type inconsistency where `Tenant.id` and `TenantInfo.id` were typed as `string` in TypeScript but used `number` in the backend. This has been corrected.

## shadcn/ui Integration

This project uses shadcn/ui components configured with:
- Style: "new-york"
- Base color: neutral
- CSS variables enabled
- Import aliases for components, utils, hooks
- Icon library: lucide-react

Add new components: `npx shadcn@latest add <component-name>`

## Styling

- Tailwind CSS 4 with Vite plugin
- CSS variables for theming (defined in `resources/css/app.css`)
- Utility functions: `cn()` from `lib/utils` for conditional classes
- `class-variance-authority` for component variants
- Prettier plugin auto-sorts Tailwind classes

## SSR Support

SSR entry point: `resources/js/ssr.tsx`
Build SSR assets: `npm run build:ssr`
Run SSR: `php artisan inertia:start-ssr` (after building)

## React Compiler

React Compiler (Babel plugin) is enabled in `vite.config.ts` for automatic optimizations.

## Permissions & Roles

Este projeto usa **Spatie Laravel Permission** integrado com **multi-tenancy** para controle de acesso granular.

### Documentação Completa

Ver **[docs/PERMISSIONS.md](docs/PERMISSIONS.md)** para documentação completa incluindo:
- Lista de todas as 19 permissions organizadas por categoria
- 3 roles MVP (owner, admin, member)
- Como usar permissions no código
- Como adicionar novas permissions
- Troubleshooting

### Quick Reference

**Sincronizar Permissions**:
```bash
# Atualizar/criar permissions (idempotente)
sail artisan permissions:sync

# Limpar e recriar tudo
sail artisan permissions:sync --fresh
```

**Nomenclatura**: `tenant.resource:action`
- Exemplo: `tenant.projects:view`, `tenant.team:invite`, `tenant.billing:manage`

**Categorias**:
- `projects` (8 permissions)
- `team` (5 permissions)
- `settings` (3 permissions)
- `billing` (3 permissions)

**Uso no Código**:
```php
// Em Controllers
Gate::authorize('tenant.projects:create');

// Em Policies
public function update(User $user, Project $project): bool {
    return $user->can('tenant.projects:edit')
        || ($user->can('tenant.projects:edit-own') && $project->user_id === $user->id);
}

// Check direto
if ($user->can('tenant.team:invite')) {
    // ...
}
```

**Roles MVP**:
- `owner`: 19 permissions (todas) - Acesso total
- `admin`: 13 permissions - Gerencia projetos e equipe (sem billing)
- `member`: 6 permissions - Cria e edita próprios projetos

## Testing

- PHPUnit 11 configured with `phpunit.xml`
- Test suites: Unit (`tests/Unit`) and Feature (`tests/Feature`)
- Test database: SQLite (`:memory:` or `testing` database)
- Testing environment vars in `phpunit.xml` (cache=array, queue=sync, etc.)
- **Telescope desabilitado durante testes** para evitar ruído nos dados de teste

## Stancl/Tenancy Features

Este projeto usa **Stancl/Tenancy for Laravel v3** com estratégia de **single database + tenant_id isolation**.

### Features Habilitadas

**Configuração:** `config/tenancy.php:169-176`

```php
'features' => [
    Stancl\Tenancy\Features\UserImpersonation::class,
    Stancl\Tenancy\Features\TelescopeTags::class,
    Stancl\Tenancy\Features\CrossDomainRedirect::class,
],
```

#### 1. **UserImpersonation** ⭐⭐⭐⭐⭐
**O que faz:** Sistema de impersonation seguro com tokens single-use e time-limited (60s TTL).

**Por que usar:**
- Essencial para suporte admin e debugging
- Tokens descartáveis (consumidos após uso)
- Sessões isoladas por domínio (segurança)
- Audit trail via session markers

**Implementação:**
- Controller: `app/Http/Controllers/ImpersonationController.php`
- Rota de consumo: `routes/tenant.php:34` (`/impersonate/{token}`)
- Middleware de proteção: `prevent.impersonation` bloqueia ações sensíveis

**Uso:**
```php
// Admin cria token
$token = tenancy()->impersonate($tenant, (string) $userId, '/dashboard');

// Redireciona para tenant domain
return redirect()->route('impersonate.consume', $token->token)
    ->domain($tenant->primaryDomain()->domain);

// Token consumido automaticamente em tenant domain
```

#### 2. **TelescopeTags** ⭐⭐⭐⭐⭐
**O que faz:** Tag automático de todas as entradas do Telescope com `tenant:{id}`.

**Por que usar:**
- Debug por tenant (filtrar queries lentas, exceções)
- Métricas de performance por tenant
- Identificação de tenants problemáticos
- Integração perfeita com Telescope MCP

**Resultado:**
- Cada request em contexto tenant recebe tag `tenant:1`, `tenant:2`, etc.
- Tags visíveis e clicáveis na interface do Telescope
- Filtragem instantânea: `/telescope/requests?tag=tenant%3A1`

**Zero configuração necessária** - ativa automaticamente ao habilitar a feature.

#### 3. **CrossDomainRedirect** ⭐⭐⭐⭐
**O que faz:** Adiciona método `->domain($domain)` a `RedirectResponse` para redirects entre domínios.

**Por que usar:**
- Código mais limpo e type-safe com Wayfinder
- Útil para impersonation, convites de equipe, SSO
- Zero overhead (apenas macro)

**Exemplo:**
```php
// ANTES (manual):
return Inertia::location($tenant->url() . '/impersonate/' . $token);

// DEPOIS (type-safe):
return redirect()->route('impersonate.consume', $token)
    ->domain($tenant->primaryDomain()->domain);
```

### Features Desabilitadas (e Por Quê)

#### ⚠️ **TenantConfig** - Considerar Futuro
**O que faz:** Mapeia atributos do tenant para valores do Laravel `config()`.

**Por que NÃO usar agora:**
- Coluna `settings` JSON já resolve (mais flexível)
- Requer migrations para cada novo config
- Overhead de event listeners
- Não é crítico para MVP

**Quando habilitar:**
- Integração com Stripe/PayPal (chaves por tenant)
- Packages que só leem de `config()`
- Migração para database-per-tenant

#### ❌ **UniversalRoutes** - Não Necessário
**O que faz:** Permite rotas funcionarem em ambos os domínios (central + tenant).

**Por que NÃO usar:**
- Arquitetura atual separa intencionalmente domínios
- Nenhuma rota precisa funcionar em ambos
- Adiciona complexidade sem benefício
- Security model depende de isolamento estrito

#### ❌ **ViteBundler** - Incompatível
**O que faz:** Per-tenant Vite builds e assets.

**Por que NÃO usar:**
- ❌ Incompatível com Inertia.js SSR (requer single bundle)
- ❌ Incompatível com React Compiler
- ❌ Multiplica build time por N tenants
- ❌ Quebra Hot Module Replacement
- ✅ CSS variables já resolvem theming

**Arquitetura correta:**
```
Single Vite Build (SSR)
   ↓
Todos os Tenants
   ↓
Theming via CSS Variables
   ↓
Assets específicos via Storage Isolation
```

### Documentação Oficial

- **Stancl/Tenancy:** https://tenancyforlaravel.com/docs/v3
- **Features:** https://tenancyforlaravel.com/docs/v3/features/overview

## Session Security & Multi-Tenancy

### Configuração Segura de Sessões

Este projeto usa **multi-tenancy com isolamento de sessões** para segurança máxima entre tenants.

#### ⚠️ CONFIGURAÇÃO CRÍTICA: SESSION_DOMAIN

**Para PRODUÇÃO** (obrigatório):
```env
SESSION_DOMAIN=              # VAZIO = Isolamento por domínio exato
SESSION_SAME_SITE=lax        # Proteção CSRF + permite impersonation
SESSION_SECURE_COOKIE=true   # HTTPS obrigatório
```

**Para DESENVOLVIMENTO** (opcional):
```env
SESSION_DOMAIN=.localhost    # Facilita testes locais
SESSION_SAME_SITE=lax
```

#### Implicações de Segurança

| Configuração | Comportamento | Segurança | Produção |
|--------------|---------------|-----------|----------|
| `SESSION_DOMAIN=null` | Cookies isolados por domínio exato | ✅ **Seguro** - Tenants isolados | ✅ **Recomendado** |
| `SESSION_DOMAIN=.yourdomain.com` | Cookies compartilhados entre subdomains | ❌ **Inseguro** - Session leakage | ❌ **Nunca usar** |
| `SESSION_SAME_SITE=strict` | Máxima proteção CSRF | ✅ Seguro (pode quebrar flows) | ⚠️ Requer testes |
| `SESSION_SAME_SITE=lax` | Proteção CSRF + permite GET redirects | ✅ **Recomendado** | ✅ **Ideal** |
| `SESSION_SAME_SITE=none` | Permite cross-site requests | ❌ **Muito inseguro** | ❌ **Nunca usar** |

#### ❌ Vulnerabilidades de SESSION_DOMAIN=.yourdomain.com

1. **Session Fixation**: Atacante em `tenant1.yourdomain.com` pode fixar sessão em `tenant2.yourdomain.com`
2. **Cross-Tenant Cookie Leakage**: Sessões vazam entre tenants diferentes
3. **Session Hijacking via XSS**: XSS em qualquer subdomain compromete todos os outros
4. **CSRF Cross-Domain**: Ataques CSRF facilitados entre subdomains

#### ✅ Sistema de Impersonation Seguro

O impersonation **NÃO DEPENDE** de cookies compartilhados. Funciona perfeitamente com `SESSION_DOMAIN=null`:

**Fluxo Seguro:**
1. Admin em `admin.yourdomain.com` cria token de impersonation
2. Redireciona para `tenant1.yourdomain.com/impersonate/{token}`
3. Token validado no tenant domain
4. **NOVA sessão criada** exclusivamente em `tenant1.yourdomain.com`
5. Cookies **NÃO compartilhados** entre admin e tenant

**Implementação** (`app/Http/Controllers/TenantImpersonationController.php:41`):
```php
// Cria nova sessão isolada no domínio do tenant
auth()->login($impersonationToken->user, true);
session()->regenerate(); // Sessão independente
```

**Segurança do Token:**
- ✅ Single-use (consumido após primeiro uso)
- ✅ Time-limited (expira em 5 minutos)
- ✅ Tenant-scoped (validado contra tenant atual)
- ✅ Audit trail (registra quem impersonou quem)

#### Checklist de Segurança para Produção

Ver arquivo `.env.production.example` com configuração completa e comentada.

**Sessões:**
- [ ] `SESSION_DOMAIN` vazio (isolamento por domínio)
- [ ] `SESSION_SAME_SITE=lax` ou `strict`
- [ ] `SESSION_SECURE_COOKIE=true` (HTTPS)
- [ ] `SESSION_DRIVER=redis` (performance + escalabilidade)

**Multi-Tenancy:**
- [ ] Route model binding verifica `tenant_id` (`bootstrap/app.php:68`)
- [ ] Middleware `VerifyTenantAccess` em todas as rotas tenant
- [ ] Models usam `BelongsToTenant` trait
- [ ] Storage isolado por tenant (`tenants/{tenant_id}/...`)

**Impersonation:**
- [ ] Tokens single-use e time-limited
- [ ] Audit trail habilitado
- [ ] Middleware `prevent.impersonation` em ações sensíveis
- [ ] Rota `/impersonate/{token}` apenas em tenant domains

**Redis Session Scoping:**
- [ ] `SESSION_DRIVER=redis` configurado
- [ ] `tenancy.cache.scope_sessions=true` habilitado
- [ ] `RedisTenancyBootstrapper` habilitado
- [ ] Redis connections separadas (default, cache, queue)
- [ ] Queue connection NÃO em `prefixed_connections`

### Redis Session Scoping (Stancl Tenancy)

Este projeto usa **Redis para sessions** com **isolamento automático por tenant** seguindo as melhores práticas do Stancl Tenancy v4.

#### Arquitetura Multi-Database Redis

**Estratégia**: Múltiplas databases Redis para separação de concerns.

```env
REDIS_DB=0          # Default: Sessions + Direct Redis calls (tenant-prefixed)
REDIS_CACHE_DB=1    # Cache: Application cache (tenant-tagged)
REDIS_QUEUE_DB=2    # Queue: Background jobs (NO tenant prefix)
```

**Configuração** (`config/database.php:145-194`):

```php
'redis' => [
    'client' => env('REDIS_CLIENT', 'phpredis'),

    'default' => [
        'database' => env('REDIS_DB', '0'),  // Sessions + Redis facade
        // ...
    ],

    'cache' => [
        'database' => env('REDIS_CACHE_DB', '1'),  // Cache store
        // ...
    ],

    'queue' => [
        'database' => env('REDIS_QUEUE_DB', '2'),  // Queue connection
        // ⚠️ CRITICAL: Separate DB to avoid tenant prefixes breaking jobs
    ],
],
```

#### Session Scoping via CacheTenancyBootstrapper

**Configuração** (`config/tenancy.php:103-106`):

```php
'cache' => [
    'tag_base' => 'tenant',
    'scope_sessions' => true,  // ✅ Automatic session scoping for cache-based drivers
],
```

**Como Funciona**:

1. **`CacheTenancyBootstrapper`** detecta que `SESSION_DRIVER=redis` é cache-based
2. Com `scope_sessions => true`, aplica **tenant tagging** automático nas sessions
3. Sessions são isoladas por tenant usando cache tags: `tenant:{tenant_id}`
4. Garante que `tenant1` nunca acesse sessions de `tenant2`, mesmo compartilhando o mesmo Redis database

**Referência**: [Session Scoping - Tenancy for Laravel v4](https://v4.tenancyforlaravel.com/session-scoping)

#### Redis Prefix Scoping via RedisTenancyBootstrapper

**Configuração** (`config/tenancy.php:174-180`):

```php
'redis' => [
    'prefix_base' => 'tenant',
    'prefixed_connections' => [
        'default',  // Tenant-prefixed: tenant_{id}:key
        // 'queue' is intentionally NOT here
    ],
],
```

**Como Funciona**:

- **Connection `default`** (DB 0): Chaves prefixadas com `tenant_{tenant_id}:`
  - Sessions: `tenant_1:laravel_session:abc123`
  - Direct Redis calls: `Redis::set('key', 'val')` → `tenant_1:key`

- **Connection `cache`** (DB 1): Usa tagging (via `CacheTenancyBootstrapper`)
  - Cache keys: Tagged com `tenant:{tenant_id}`

- **Connection `queue`** (DB 2): **SEM prefixo** (critical!)
  - Queue jobs: `queues:default:job123` (global, sem tenant prefix)
  - ⚠️ **IMPORTANTE**: Jobs NÃO devem ser tenant-prefixed para evitar conflitos

**Referência**: [Queue Bootstrapper - Tenancy for Laravel v4](https://v4.tenancyforlaravel.com/bootstrappers/queue)

#### ⚠️ CRITICAL: Queue Connection Isolation

**Problema**: Se a queue connection estiver em `prefixed_connections`, jobs ficam inacessíveis.

**Exemplo de Problema**:
```php
// ❌ ERRADO: 'queue' em prefixed_connections
'prefixed_connections' => ['default', 'queue'],

// Job dispatch: tenant_1:queues:default:job123
// Worker procura: queues:default:job123
// Resultado: Job nunca processado!
```

**Solução** (`config/queue.php:69`):
```php
'redis' => [
    'driver' => 'redis',
    'connection' => env('REDIS_QUEUE_CONNECTION', 'queue'),  // Uses separate 'queue' connection
    // ...
],
```

```php
// ✅ CORRETO: 'queue' NÃO em prefixed_connections
'prefixed_connections' => ['default'],  // Only 'default' is prefixed

// Queue connection usa DB 2 (separada) sem prefixo tenant
// Jobs ficam acessíveis globalmente para workers
```

#### Fluxo Completo de Session Isolation

**Tenant Initialization**:
```php
// 1. Middleware InitializeTenancyByDomain identifica tenant
// 2. Tenancy::initialize($tenant) dispara bootstrappers:

// CacheTenancyBootstrapper:
$this->app['cache']->forgetDriver();  // Reset cache driver
// Session scope: tenant:{tenant_id} tag applied

// RedisTenancyBootstrapper:
Redis::connection('default')->client()->select(0);  // DB 0
// Prefix: tenant_{tenant_id}: applied to keys
```

**Session Read/Write** (Tenant 1):
```php
// User in tenant1.yourdomain.com
session()->put('user_id', 123);

// Redis key (DB 0): tenant_1:laravel_session:abc123
// Cache tag: tenant:1
// Result: Isolated to Tenant 1 only
```

**Session Read/Write** (Tenant 2):
```php
// User in tenant2.yourdomain.com
session()->put('user_id', 456);

// Redis key (DB 0): tenant_2:laravel_session:xyz789
// Cache tag: tenant:2
// Result: Completely isolated from Tenant 1
```

#### Verificação de Configuração

**Checklist Técnico**:

1. **`.env` Configuration**:
   ```env
   SESSION_DRIVER=redis
   REDIS_DB=0
   REDIS_CACHE_DB=1
   REDIS_QUEUE_DB=2
   REDIS_QUEUE_CONNECTION=queue
   ```

2. **`config/tenancy.php` Bootstrappers**:
   ```php
   'bootstrappers' => [
       Stancl\Tenancy\Bootstrappers\CacheTenancyBootstrapper::class,
       Stancl\Tenancy\Bootstrappers\RedisTenancyBootstrapper::class,
       // ...
   ],
   ```

3. **`config/tenancy.php` Cache Config**:
   ```php
   'cache' => [
       'tag_base' => 'tenant',
       'scope_sessions' => true,  // ✅ MUST be true
   ],
   ```

4. **`config/tenancy.php` Redis Config**:
   ```php
   'redis' => [
       'prefix_base' => 'tenant',
       'prefixed_connections' => [
           'default',  // ✅ Sessions + direct Redis
           // 'queue' NOT here!
       ],
   ],
   ```

5. **`config/queue.php`**:
   ```php
   'connections' => [
       'redis' => [
           'connection' => 'queue',  // ✅ Separate connection
       ],
   ],
   ```

6. **`config/database.php` Redis Connections**:
   ```php
   'default' => ['database' => env('REDIS_DB', '0')],
   'cache' => ['database' => env('REDIS_CACHE_DB', '1')],
   'queue' => ['database' => env('REDIS_QUEUE_DB', '2')],  // ✅ Separate DB
   ```

**Teste de Isolamento**:
```bash
# Terminal 1: Monitor Redis (DB 0 - sessions)
redis-cli -n 0 MONITOR | grep laravel_session

# Terminal 2: Acessar tenant1.localhost
# Expected: tenant_1:laravel_session:...

# Terminal 3: Acessar tenant2.localhost
# Expected: tenant_2:laravel_session:...

# Verify: Different prefixes = isolated sessions ✅
```

#### Benefícios da Arquitetura

1. **Performance**:
   - Redis mais rápido que database sessions
   - Databases separadas evitam key conflicts
   - Cache tags eficientes (O(1) lookups)

2. **Security**:
   - Isolamento automático por tenant (via prefix + tags)
   - Impossível cross-tenant session access
   - Queue jobs não "vazam" entre tenants

3. **Scalability**:
   - Redis cluster-ready
   - Databases independentes podem ser movidas para Redis instances separados
   - Horizontal scaling sem mudanças de código

4. **Maintainability**:
   - Configuração declarativa (via config files)
   - Zero código customizado para session scoping
   - Stancl Tenancy gerencia automaticamente

## MediaLibrary Queue Integration (Spatie + Stancl Tenancy)

### Arquitetura de Integração

Este projeto usa **Spatie MediaLibrary** para gerenciar uploads e conversões de imagens, integrado com **Stancl Tenancy** para isolamento multi-tenant completo.

#### Como Funciona a Integração

**1. QueueTenancyBootstrapper** (config/tenancy.php:34)

O `QueueTenancyBootstrapper` garante que jobs de conversão de imagem executam no contexto do tenant correto:

```php
// Job serialization (quando imagem é enviada)
Upload → MediaLibrary cria Media record com tenant_id
      → Queue PerformConversionsJob($media)
      → SerializesModels serializa Media model
      → QueueTenancyBootstrapper adiciona tenant_id ao payload do job

// Job deserialization (quando worker processa)
Worker pega job da queue
      → QueueTenancyBootstrapper inicializa tenant context (tenant_id)
      → SerializesModels deserializa Media → busca com tenant_id scope
      → Job executa conversão → salva em tenants/{tenant_id}/media/{id}/
```

**2. Media Model com BelongsToTenant** (app/Models/Media.php:8)

```php
class Media extends BaseMedia
{
    use BelongsToTenant;  // ✅ Automatic tenant scoping

    // Media records sempre têm tenant_id
    // Queries automáticas filtram por tenant atual
}
```

**3. TenantPathGenerator** (app/Support/TenantPathGenerator.php:17)

Garante que arquivos e conversões são salvos em paths isolados por tenant:

```php
public function getPath(Media $media): string
{
    $tenantId = $media->tenant_id ?? 'global';
    return "tenants/{$tenantId}/media/{$media->id}/";
}

public function getPathForConversions(Media $media): string
{
    return $this->getPath($media) . 'conversions/';
}
```

**Exemplo de Paths**:
```
storage/app/tenants/
├── 1/
│   └── media/
│       └── 123/
│           ├── photo.jpg (original)
│           └── conversions/
│               └── thumb-photo.jpg (300x300)
├── 2/
│   └── media/
│       └── 456/
│           ├── document.pdf
│           └── conversions/
│               └── preview-document.jpg
```

#### Componentes da Integração

| Componente | Arquivo | Função |
|------------|---------|---------|
| **QueueTenancyBootstrapper** | config/tenancy.php:34 | Injeta tenant_id em jobs, inicializa contexto |
| **Media Model** | app/Models/Media.php | Model customizado com BelongsToTenant trait |
| **TenantPathGenerator** | app/Support/TenantPathGenerator.php | Gera paths isolados por tenant |
| **Project Model** | app/Models/Project.php:52-64 | Define media collections e conversions |
| **MediaLibrary Config** | config/media-library.php | Configuração global (queue, path generator) |

#### Configuração MediaLibrary

**config/media-library.php** (principais configurações):

```php
return [
    // Queue conversions para performance (async)
    'queue_connection_name' => env('QUEUE_CONNECTION', 'sync'),
    'queue_conversions_by_default' => env('QUEUE_CONVERSIONS_BY_DEFAULT', true),

    // Path generator customizado (tenant-isolated)
    'path_generator' => App\Support\TenantPathGenerator::class,

    // Media model customizado (com BelongsToTenant)
    'media_model' => App\Models\Media::class,
];
```

#### Exemplo de Uso: Project com Imagens

**app/Models/Project.php:52-64**:

```php
public function registerMediaCollections(): void
{
    $this->addMediaCollection('attachments')
        ->useDisk('tenant_uploads');

    $this->addMediaCollection('images')
        ->useDisk('tenant_uploads')
        ->registerMediaConversions(function () {
            $this->addMediaConversion('thumb')
                ->width(300)
                ->height(300);  // ← Conversion queued automatically
        });
}
```

**Upload e Conversão**:

```php
// Controller (app/Http/Controllers/Tenant/ProjectController.php:169)
$project->addMediaFromRequest('file')
    ->toMediaCollection('images');

// Fluxo interno:
// 1. MediaLibrary salva original em: tenants/1/media/123/photo.jpg
// 2. Queue PerformConversionsJob com tenant_id=1
// 3. Worker processa no contexto de Tenant 1
// 4. Salva thumb em: tenants/1/media/123/conversions/thumb-photo.jpg
```

#### Segurança Multi-Tenant

**✅ Isolamento Garantido Por**:

1. **BelongsToTenant Trait**: Media queries automáticas filtram por tenant_id
2. **TenantPathGenerator**: Arquivos físicos em paths separados por tenant
3. **QueueTenancyBootstrapper**: Jobs executam no contexto correto
4. **Validações no Controller** (ProjectController.php:187-189):
   ```php
   // Verificar se media pertence ao tenant atual
   if ($project->tenant_id !== current_tenant_id()) {
       abort(404);
   }
   ```

#### Testes

**tests/Feature/MediaLibraryQueueTenancyTest.php** (10 tests):

1. ✅ Media model tem tenant_id e usa BelongsToTenant
2. ✅ Arquivos salvos em paths tenant-isolated
3. ✅ Conversion jobs queued com tenant context
4. ✅ Conversions salvas no path correto do tenant
5. ✅ Media isolada entre tenants (Tenant 2 não vê Tenant 1)
6. ✅ QueueTenancyBootstrapper está habilitado
7. ✅ MediaLibrary configurado para queue conversions
8. ✅ TenantPathGenerator configurado
9. ✅ Custom Media model configurado
10. ✅ Project media collections configuradas

**Rodar Testes**:

```bash
# Com Sail (recomendado - PostgreSQL)
sail artisan test --filter=MediaLibraryQueueTenancyTest

# Sem Sail (pode ter issues com PHP 8.4 + SQLite :memory:)
php artisan test --filter=MediaLibraryQueueTenancyTest
```

**⚠️ Limitação Conhecida**: PHP 8.4 + SQLite :memory: + RefreshDatabase tem problema com nested transactions. Use Sail (PostgreSQL) para rodar os testes.

#### Queue Configuration

**Importante**: Queue connection deve usar conexão separada (não prefixada):

```php
// config/queue.php:69
'redis' => [
    'driver' => 'redis',
    'connection' => 'queue',  // ✅ Separate connection, NOT 'default'
],

// config/database.php:179
'queue' => [
    'database' => env('REDIS_QUEUE_DB', '2'),  // ✅ DB 2 (isolated)
],

// config/tenancy.php:176-179
'redis' => [
    'prefixed_connections' => [
        'default',  // ✅ Sessions + direct Redis
        // 'queue' is intentionally NOT here
    ],
],
```

**Por que queue não pode ser prefixada**:

```
❌ COM prefixo:
Queue job key: tenant_1:queues:default:job123
Worker procura: queues:default:job123
Resultado: Job NUNCA processado (key mismatch)

✅ SEM prefixo:
Queue job key: queues:default:job123
Worker procura: queues:default:job123
Resultado: Job processado, tenant context inicializado via QueueTenancyBootstrapper
```

#### Best Practices

1. **Sempre use BelongsToTenant** em models que armazenam media
2. **Sempre configure TenantPathGenerator** para isolamento de arquivos
3. **Queue connection separada** (nunca prefixar com tenant_id)
4. **Valide tenant_id** no controller antes de retornar media
5. **Use Telescope MCP** para debug (verificar jobs queued, paths, exceptions)

#### Verificação com Telescope MCP

Após upload de media, sempre verificar:

```bash
# 1. Jobs queued (verificar PerformConversionsJob)
Telescope → Jobs → Ver payload com tenant_id

# 2. Queries (verificar se Media foi salva com tenant_id)
Telescope → Queries → INSERT into media (tenant_id, ...)

# 3. Exceptions (garantir sem erros)
Telescope → Exceptions → Vazio (sem erros)
```

## MCP Tools (Model Context Protocol)

Este projeto tem acesso a ferramentas MCP para debugging, testes e documentação. **Use estas ferramentas proativamente durante o desenvolvimento.**

### 1. Laravel Telescope MCP (Monitoramento Backend)

**Status**: Instalado e configurado (`laravel/telescope` + `lucianotonet/laravel-telescope-mcp`)

**Acesso**:
- Interface Web: `http://localhost/telescope` ou `http://127.0.0.1:8000/telescope`
- MCP Endpoint: `http://127.0.0.1:8000/telescope-mcp`

**⚠️ USO OBRIGATÓRIO**: Sempre verificar Telescope MCP **AUTOMATICAMENTE** após **QUALQUER** mudança no backend.

**Quando Verificar**:
- ✅ Após criar ou modificar Controllers
- ✅ Após criar ou modificar Models (verificar queries N+1)
- ✅ Após criar Jobs ou Events
- ✅ Após modificar Migrations
- ✅ Após testes falharem (verificar exceptions)
- ✅ Após qualquer request Inertia

**Ferramentas Disponíveis** (19 no total):

| Ferramenta | Uso | O que Verificar |
|------------|-----|-----------------|
| **Requests** | HTTP requests | Status codes, tempo de resposta, payloads |
| **Exceptions** | Erros da aplicação | Stack traces, erros não tratados |
| **Queries** | Database queries | Queries lentas, N+1 problems, duplicatas |
| **Logs** | Application logs | Erros, warnings, debug info |
| **Jobs** | Queue jobs | Jobs falhados, retry attempts, payloads |
| **Models** | Eloquent operations | Queries geradas, eventos disparados |
| **Events** | Event dispatches | Listeners executados, ordem de eventos |
| **Mail** | Emails enviados | Recipients, assunto, conteúdo |
| **Cache** | Cache operations | Hits/misses, keys, TTL |
| **Redis** | Redis commands | Operações, performance |
| **HTTP Client** | Outgoing HTTP | APIs externas, responses, timeouts |
| **Notifications** | Notificações | Canais, status, conteúdo |
| **Commands** | Artisan commands | Execuções, status, output |
| **Views** | View renders | Templates renderizados, dados passados |
| **Dumps** | var_dump/dd() | Debug outputs, valores |
| **Gates** | Authorization | Checks de permissão, results |
| **Schedule** | Scheduled tasks | Cron jobs, execuções |
| **Batches** | Batch operations | Jobs em batch, progresso |
| **Prune** | Limpar dados antigos | Limpeza de entries |

**Exemplo de Workflow**:
```
1. Criar ProfileController com método update()
2. ✅ OBRIGATÓRIO: Verificar Telescope MCP
   - Ferramenta: Requests (verificar request POST/PATCH)
   - Ferramenta: Queries (verificar se não há N+1)
   - Ferramenta: Exceptions (verificar se não há erros)
3. Se encontrar problema:
   - Analisar stack trace em Exceptions
   - Verificar queries geradas em Queries
   - Ver logs em Logs
4. Corrigir e verificar novamente
```

**Queries N+1 - Exemplo**:
```php
// ❌ Problema N+1 detectado no Telescope
User::all()->each(fn($user) => $user->posts);
// Telescope mostrará: 1 query para users + N queries para posts

// ✅ Solução verificada no Telescope
User::with('posts')->get();
// Telescope mostrará: 2 queries apenas (users + posts)
```

### 2. Context7 MCP (Documentação de Bibliotecas)

**Status**: Disponível via MCP

**⚠️ PRIORIDADE MÁXIMA**: **SEMPRE** consultar Context7 **ANTES** de implementar qualquer feature.

**Quando Usar**:
1. ✅ **Antes de implementar** features com Inertia, React, Laravel
2. ✅ **Para buscar exemplos** de código corretos
3. ✅ **Para verificar best practices** das bibliotecas
4. ✅ **Quando encontrar erros** - buscar soluções primeiro no Context7
5. ✅ **Antes de usar** novas bibliotecas ou APIs

**Bibliotecas Principais**:
- `/laravel/framework` - Laravel 12 documentation
- `/inertiajs/inertia-laravel` - Inertia.js backend
- `/inertiajs/inertia` - Inertia.js core
- `/facebook/react` - React 19
- `/tailwindlabs/tailwindcss` - Tailwind CSS 4
- `/radix-ui/primitives` - Radix UI components
- `/shadcn/ui` - shadcn/ui components

**Como Usar**:
```
1. Identificar a biblioteca: "preciso usar Inertia Form"
2. Resolver library ID: Context7.resolve-library-id("inertia")
3. Buscar docs: Context7.get-library-docs("/inertiajs/inertia", topic="forms")
4. Ler exemplos e best practices
5. Implementar seguindo os padrões
```

**Exemplo de Workflow**:
```
Tarefa: Criar formulário de login com Inertia

1. ✅ Consultar Context7 PRIMEIRO
   - resolve-library-id("inertia react")
   - get-library-docs("/inertiajs/inertia", topic="forms")
   - get-library-docs("/inertiajs/inertia", topic="validation")

2. Aprender o padrão correto:
   - Form component com render props
   - Wayfinder para routes type-safe
   - Error handling automático

3. Implementar seguindo documentação

4. ✅ Verificar no Telescope que funcionou
```

**Quando Encontrar Erros**:
```
1. ✅ Context7: Buscar solução na documentação oficial
   - Procurar por mensagens de erro similares
   - Ver exemplos de código correto

2. ✅ Telescope MCP: Ver detalhes do erro
   - Stack trace completo
   - Request/response data

3. Corrigir baseado nas informações
4. Validar que funciona
```

### 3. Playwright MCP (Testes Frontend)

**Status**: Disponível via MCP (20+ ferramentas de browser)

**Quando Usar**:
1. ✅ **Após criar/modificar** páginas Inertia
2. ✅ **Testar fluxos de formulários** end-to-end
3. ✅ **Verificar erros de console** JavaScript
4. ✅ **Capturar screenshots** para review visual
5. ✅ **Validar navegação** entre páginas

**Ferramentas Principais**:

| Ferramenta | Uso |
|------------|-----|
| `browser_navigate` | Navegar para URLs |
| `browser_snapshot` | Capturar estado da página (accessibility tree) |
| `browser_click` | Clicar em elementos |
| `browser_fill_form` | Preencher formulários |
| `browser_type` | Digitar em inputs |
| `browser_console_messages` | Ver erros/logs do console |
| `browser_take_screenshot` | Capturar screenshot |
| `browser_wait_for` | Esperar elementos/texto |
| `browser_network_requests` | Ver requisições de rede |

**Exemplo de Workflow - Testar Página de Perfil**:
```
1. Criar/modificar resources/js/pages/settings/profile.tsx

2. ✅ Testar com Playwright:
   a. browser_navigate("http://localhost/settings/profile")
   b. browser_console_messages() - verificar erros JavaScript
   c. browser_snapshot() - ver estrutura da página
   d. browser_fill_form([
        {name: "name", value: "Teste User"},
        {name: "email", value: "test@example.com"}
      ])
   e. browser_click("button[type=submit]")
   f. browser_wait_for(text: "Profile updated")
   g. browser_network_requests() - verificar request Inertia

3. ✅ Verificar no Telescope MCP:
   - Requests: ver PATCH /settings/profile
   - Queries: verificar update executado
   - Exceptions: garantir sem erros

4. Se houver erros:
   - Console: browser_console_messages(onlyErrors: true)
   - Network: browser_network_requests() - ver failed requests
   - Telescope: Exceptions - ver backend errors
```

**Exemplo - Verificar Erros de Console**:
```
Sempre após modificar componentes React:

1. browser_navigate("http://localhost/dashboard")
2. browser_console_messages(onlyErrors: true)
3. Se houver erros:
   - Corrigir componentes
   - Verificar imports
   - Testar novamente
```

## MCP Workflow - Fluxo de Trabalho Obrigatório

### Ao Criar/Modificar Backend (Controllers, Models, etc.)

```
1. ✅ Consultar Context7
   - Buscar best practices Laravel/Inertia
   - Ver exemplos de código correto
   - Verificar API correta

2. ✅ Implementar mudanças
   - Seguir padrões da documentação
   - Usar type hints e validações

3. ✅ OBRIGATÓRIO: Verificar Telescope MCP
   - Exceptions: garantir sem erros
   - Queries: verificar performance (sem N+1)
   - Requests: validar request/response
   - Logs: verificar warnings

4. ✅ Rodar testes
   - sail artisan test (ou php artisan test)
   - Se falhar: verificar Telescope Exceptions

5. ✅ Validação final
   - Todas as ferramentas Telescope em verde
   - Testes passando
   - Sem queries lentas
```

### Ao Criar/Modificar Frontend (Páginas, Componentes)

```
1. ✅ Consultar Context7
   - Buscar best practices React/Inertia
   - Ver exemplos de Form, usePage, Link
   - Verificar hooks corretos

2. ✅ Implementar componente/página
   - Seguir padrões do INERTIA.md
   - Usar TypeScript com tipos corretos
   - Importar de @/routes (Wayfinder)

3. ✅ OBRIGATÓRIO: Testar com Playwright MCP
   - browser_navigate: acessar página
   - browser_console_messages: verificar erros
   - browser_snapshot: ver renderização
   - browser_fill_form + click: testar formulários
   - browser_take_screenshot: capturar visual

4. ✅ Verificar Telescope MCP
   - Requests: ver requests Inertia (JSON)
   - Exceptions: garantir sem backend errors
   - Queries: verificar dados carregados

5. ✅ Validação final
   - Console sem erros JavaScript
   - Formulários funcionando
   - Navegação correta
   - Backend respondendo JSON (Inertia v2)
```

### Ao Encontrar Erros

```
1. ✅ Identificar origem
   - Frontend? browser_console_messages
   - Backend? Telescope Exceptions
   - Ambos? Verificar os dois

2. ✅ Context7: Buscar solução
   - Procurar erro na documentação
   - Ver exemplos corretos
   - Verificar breaking changes

3. ✅ Telescope MCP: Detalhes do erro
   - Stack trace completo
   - Request/response data
   - Queries executadas

4. ✅ Playwright MCP: Reproduzir erro
   - browser_navigate para página
   - Executar ações que causam erro
   - Verificar console e network

5. ✅ Corrigir e validar
   - Implementar correção
   - Testar novamente com Playwright
   - Verificar Telescope sem erros
   - Confirmar testes passando
```

### Checklist Antes de Considerar Tarefa Completa

- [ ] Context7 consultado para best practices
- [ ] Código implementado seguindo padrões
- [ ] Telescope MCP verificado (sem exceptions, queries OK)
- [ ] Playwright MCP testado (página funciona, sem console errors)
- [ ] Testes automatizados passando
- [ ] TypeScript sem erros (`npm run types`)
- [ ] ESLint sem warnings (`npm run lint`)
- [ ] Código formatado (`npm run format`)

## Telescope - Acesso e Configuração

**Arquivo de Configuração**: `config/telescope.php`
**Service Provider**: `app/Providers/TelescopeServiceProvider.php`
**Migration**: `database/migrations/2025_11_18_134555_create_telescope_entries_table.php`

**Watchers Habilitados**: Todos (Cache, Command, Dump, Event, Exception, Gate, Job, Log, Mail, Model, Notification, Query, Redis, Request, Schedule, View)

**Variáveis de Ambiente**:
```env
TELESCOPE_ENABLED=true          # Habilitar Telescope
TELESCOPE_PATH=telescope        # Caminho de acesso
TELESCOPE_MCP_ENABLED=true      # Habilitar MCP endpoint
TELESCOPE_MCP_PATH=telescope-mcp
```

**Segurança**:
- Telescope só registra em ambiente `local`
- Em produção, requer autenticação
- Dados sensíveis filtrados (`_token`, cookies, CSRF headers)
