# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Laravel + React starter kit combining Laravel 12 backend with React 19 frontend using Inertia.js.

**Stack:**
- **Backend**: Laravel 12, Fortify (auth), Stancl Tenancy (multi-tenant), Spatie Permission
- **Frontend**: React 19, TypeScript, Tailwind CSS 4, shadcn/ui, Radix UI, Lucide icons
- **Build**: Vite 7, React Compiler enabled, SSR support
- **Infrastructure**: Laravel Sail (Docker), PostgreSQL 18, Redis

## Development Commands (Laravel Sail)

**Este projeto usa Laravel Sail (Docker) para desenvolvimento. Todos os comandos devem usar `sail`.**

### Initial Setup

```bash
sail up -d               # Inicia containers (PostgreSQL, Redis, App)
sail artisan migrate     # Roda migrations
sail npm install         # Instala dependências frontend
sail npm run build       # Build inicial
```

### Development Server

```bash
sail up -d              # Containers em background
sail npm run dev        # Vite dev server (porta 5173)
```

### Backend Commands

```bash
sail artisan test                      # Run PHPUnit tests
sail artisan test --filter TestName   # Run specific test
sail artisan migrate                   # Run migrations
sail artisan tinker                    # Interactive shell
sail shell                             # Bash shell no container
sail logs -f                           # Seguir logs em tempo real
```

### Frontend Commands

```bash
sail npm run dev          # Start Vite dev server
sail npm run build        # Build for production
sail npm run build:ssr    # Build with SSR support
sail npm run lint         # Run ESLint with auto-fix
sail npm run format       # Format code with Prettier
sail npm run types        # Type-check TypeScript
```

### Code Quality

```bash
vendor/bin/pint        # Format PHP code with Laravel Pint
vendor/bin/pint --test # Check formatting without changes
```

### Permissions & Roles

```bash
# Sincronizar permissions (atualizar/criar)
sail artisan permissions:sync

# Limpar e recriar tudo
sail artisan permissions:sync --fresh
```

### Sail Configuration

**Serviços**:
- **App**: PHP 8.4 com Laravel (porta 80)
- **PostgreSQL**: 18-alpine (porta 5432)
- **Redis**: alpine (porta 6379)
- **Vite**: Dev server (porta 5173)

**Database (.env)**:
```env
DB_CONNECTION=pgsql
DB_HOST=pgsql          # Nome do serviço Docker
DB_PORT=5432
DB_DATABASE=laravel
DB_USERNAME=sail
DB_PASSWORD=password

REDIS_HOST=redis       # Nome do serviço Docker
```

**Nota para Produção**: Comandos sem Sail (php artisan, composer, npm) são usados apenas em servidores de produção sem Docker.

## Architecture

### Inertia Integration

Inertia bridges Laravel and React, enabling server-side routing with React components as views.

**Key Flow:**
1. Routes defined in `routes/web.php`, `routes/settings.php`, `routes/tenant.php`
2. Controllers return `Inertia::render('page-name', $props)`
3. Inertia middleware (`HandleInertiaRequests.php`) shares global props
4. React pages in `resources/js/pages/` receive props and render

**Shared Props** (available to all pages):
- `name`: App name from config
- `quote`: Random inspiring quote
- `auth.user`: Current authenticated user
- `sidebarOpen`: Sidebar state from cookie

### Vite & Asset Bundling

**IMPORTANT**: Follow Inertia.js best practices for Vite configuration.

**✅ CORRECT Configuration** (`resources/views/app.blade.php:43`):
```blade
@viteReactRefresh
@vite(['resources/css/app.css', 'resources/js/app.tsx'])
@inertiaHead
```

**❌ NEVER do this:**
```blade
{{-- DON'T: This breaks Vite manifest and Inertia's module resolution --}}
@vite(['resources/js/app.tsx', "resources/js/pages/{$page['component']}.tsx"])
```

**Why?**
- Vite only builds entry points defined in `vite.config.ts` (`app.tsx` and `ssr.tsx`)
- Individual page components are NOT in the Vite manifest
- Inertia handles dynamic page loading via `import.meta.glob('./pages/**/*.tsx')` in `app.tsx:16`
- Attempting to preload individual pages causes `ViteException: Unable to locate file in Vite manifest`

**How Inertia Loads Pages** (`resources/js/app.tsx:13-17`):
```typescript
resolve: (name) =>
    resolvePageComponent(
        `./pages/${name}.tsx`,
        import.meta.glob('./pages/**/*.tsx'),
    ),
```

**Vite Configuration** (`vite.config.ts:9-11`):
```typescript
laravel({
    input: ['resources/css/app.css', 'resources/js/app.tsx'],
    ssr: 'resources/js/ssr.tsx',
    refresh: true,
}),
```

**Multi-Tenant Considerations:**
- Same Vite setup works for both Central and Tenant domains
- Pages are resolved at runtime based on route
- No need for domain-specific manifests or builds

### Frontend Structure

- **Pages**: `resources/js/pages/` - Inertia page components
- **Layouts**: `resources/js/layouts/` - App layouts (app, auth, settings)
- **Components**: `resources/js/components/` - App components
- **UI Components**: `resources/js/components/ui/` - shadcn/ui (managed by CLI)
- **Hooks**: `resources/js/hooks/` - Custom React hooks
- **Entry Point**: `resources/js/app.tsx` - Initializes Inertia app

### Backend Structure

- **Authentication**: Laravel Fortify (registration, login, password reset, 2FA)
- **Controllers**: `app/Http/Controllers/` - Inertia responses
- **Middleware**: HandleInertiaRequests, HandleAppearance, tenant middleware
- **Routes**: web.php (public), settings.php (settings), tenant.php (tenant routes)
- **Multi-Tenancy**: Single database + tenant_id isolation (BelongsToTenant trait)
- **Permissions**: Spatie Laravel Permission (role-based access control)

### Type Safety

- **Path aliases**: `@/` maps to `resources/js/`
- **Strict TypeScript**: Type checking enabled
- **Inertia props**: Typed via `resources/js/types/index.d.ts`
- **Laravel Wayfinder**: Type-safe route helpers for TypeScript

### Database IDs

**Decision**: Auto-increment IDs (bigint) for all models.

**Why**: Security through authorization (not obscurity), better performance, cleaner URLs, easier debugging.

**See**: [docs/DATABASE-IDS.md](docs/DATABASE-IDS.md) for detailed rationale.

## Multi-Tenancy & Security

### Stancl/Tenancy

**Strategy**: Single database + tenant_id isolation

**Features Enabled**:
- ✅ **UserImpersonation**: Secure admin impersonation with single-use tokens
- ✅ **TelescopeTags**: Auto-tag Telescope entries with tenant ID
- ✅ **CrossDomainRedirect**: Type-safe cross-domain redirects

**See**: [docs/STANCL-FEATURES.md](docs/STANCL-FEATURES.md) for detailed documentation.

### Session Security

**Critical Configuration** (`.env`):
```env
SESSION_DOMAIN=              # VAZIO = Isolamento por domínio (PRODUÇÃO)
SESSION_SAME_SITE=lax        # Proteção CSRF + permite impersonation
SESSION_SECURE_COOKIE=true   # HTTPS obrigatório (PRODUÇÃO)
SESSION_DRIVER=redis         # Performance + escalabilidade
```

**Redis Multi-Database Strategy**:
- DB 0: Sessions + Direct Redis (tenant-prefixed)
- DB 1: Cache (tenant-tagged)
- DB 2: Queue (NO tenant prefix - workers need global access)

**See**: [docs/SESSION-SECURITY.md](docs/SESSION-SECURITY.md) for complete security guide.

### User Impersonation & Session Configuration

**⚠️ CRÍTICO**: Impersonation requer configuração correta de `SESSION_DOMAIN` e middleware de tenancy.

#### 1. SESSION_DOMAIN DEVE ESTAR VAZIO

**Problema**: `SESSION_DOMAIN=.localhost` compartilha cookies entre todos os subdomains
- Cookies criados em `localhost` são acessíveis em `tenant1.localhost`
- Session fixation e leakage entre tenants
- Impersonation não funciona (sessões não isoladas)

**Solução** (`.env:39`):
```env
SESSION_DOMAIN=    # VAZIO = isolamento por domínio exato
```

**Benefícios**:
- ✅ Cookies isolados por domínio exato (`localhost` ≠ `tenant1.localhost`)
- ✅ Segurança multi-tenant (sem session leakage)
- ✅ Impersonation funciona corretamente
- ✅ Válido para DEV e PROD

#### 2. Early Middleware Initialization

**Middleware** (`bootstrap/app.php:89-94`):
```php
$middleware->priority([
    \App\Http\Middleware\InitializeTenancyByDomainExceptTests::class,
    \Illuminate\Session\Middleware\StartSession::class,
]);
$middleware->prepend(\App\Http\Middleware\InitializeTenancyByDomainExceptTests::class);
```

**Propósito**: Garante que tenancy seja inicializado ANTES do StartSession para Redis session prefixing correto.

**Reference**: [Tenancy v4 docs - Early Identification Middleware](https://v4.tenancyforlaravel.com/version-4)

### MediaLibrary Integration

**Spatie MediaLibrary** com isolamento multi-tenant completo:

- ✅ Media model com `BelongsToTenant` trait
- ✅ TenantPathGenerator para paths isolados (`tenants/{id}/media/...`)
- ✅ QueueTenancyBootstrapper para conversion jobs
- ✅ Testes completos (MediaLibraryQueueTenancyTest)

**See**: [docs/MEDIALIBRARY.md](docs/MEDIALIBRARY.md) for integration details.

## Permissions & Roles

**System**: Spatie Laravel Permission integrado com multi-tenancy

**Quick Reference**:
```bash
# Sincronizar permissions
sail artisan permissions:sync
```

**Nomenclatura**: `tenant.resource:action`
- Exemplo: `tenant.projects:view`, `tenant.team:invite`, `tenant.billing:manage`

**Roles MVP**:
- `owner`: 22 permissions (todas) - Acesso total incluindo billing e API tokens
- `admin`: 13 permissions - Gerencia projetos e equipe (sem billing/API tokens)
- `member`: 6 permissions - Cria e edita próprios projetos

**See**: [docs/PERMISSIONS.md](docs/PERMISSIONS.md) for complete permission list and usage.

## Testing

```bash
sail artisan test                      # Run all tests
sail artisan test --filter TestName   # Run specific test
sail artisan migrate:fresh --seed     # Reset database and seed test users
```

**Test Users** (created by seeders):

| Type | Email | Password | Domain | Role |
|------|-------|----------|--------|------|
| Super Admin | `admin@setor3.app` | `password` | Global | Super Admin (all permissions) |
| Tenant 1 Owner | `john@acme.com` | `password` | tenant1.localhost | owner (22 permissions) |
| Tenant 2 Owner | `jane@startup.com` | `password` | tenant2.localhost | owner (22 permissions) |

**See**: [docs/MCP-WORKFLOW.md](docs/MCP-WORKFLOW.md#usuários-de-teste-seeders) for detailed test scenarios.

- PHPUnit 11 configured with `phpunit.xml`
- Test suites: Unit (`tests/Unit`) and Feature (`tests/Feature`)
- Test database: PostgreSQL (via Sail)
- Telescope desabilitado durante testes

## MCP Tools (Model Context Protocol)

**⚠️ USE PROATIVAMENTE DURANTE O DESENVOLVIMENTO**

### 1. Laravel Telescope MCP

**Uso Obrigatório**: Verificar **AUTOMATICAMENTE** após qualquer mudança no backend.

**Acesso**: http://localhost/telescope

**Verificar**:
- ✅ Exceptions (stack traces)
- ✅ Queries (N+1 problems, slow queries)
- ✅ Requests (status codes, payloads)
- ✅ Jobs (queue jobs, failures)

### 2. Context7 MCP

**Prioridade Máxima**: Consultar **ANTES** de implementar qualquer feature.

**Bibliotecas**:
- `/laravel/framework` - Laravel 12
- `/inertiajs/inertia` - Inertia.js
- `/facebook/react` - React 19
- `/shadcn/ui` - shadcn/ui

### 3. Playwright MCP

**Uso**: Testar páginas Inertia, verificar console errors, capturar screenshots.

**Ferramentas**: browser_navigate, browser_console_messages, browser_fill_form, browser_snapshot

**See**: [docs/MCP-WORKFLOW.md](docs/MCP-WORKFLOW.md) for complete workflow guide.

## Styling

- **Tailwind CSS 4** with Vite plugin
- **shadcn/ui**: Style "new-york", base color neutral
- **CSS variables**: Theming support (resources/css/app.css)
- **Add components**: `npx shadcn@latest add <component-name>`
- **Prettier**: Auto-sorts Tailwind classes

## SSR Support

```bash
sail npm run build:ssr          # Build with SSR
sail artisan inertia:start-ssr  # Start SSR server
```

- SSR entry point: `resources/js/ssr.tsx`
- React Compiler enabled for optimizations

## Telescope Configuration

**Files**:
- Config: `config/telescope.php`
- Provider: `app/Providers/TelescopeServiceProvider.php`
- Migration: `database/migrations/2025_11_18_134555_create_telescope_entries_table.php`

**Environment Variables**:
```env
TELESCOPE_ENABLED=true
TELESCOPE_PATH=telescope
TELESCOPE_MCP_ENABLED=true
TELESCOPE_MCP_PATH=telescope-mcp
```

**Security**:
- Telescope só registra em ambiente `local`
- Em produção, requer autenticação
- Dados sensíveis filtrados (`_token`, cookies, CSRF headers)

## Detailed Documentation

For in-depth technical documentation, see:

- **[docs/DATABASE-IDS.md](docs/DATABASE-IDS.md)** - Database IDs architecture decision
- **[docs/PERMISSIONS.md](docs/PERMISSIONS.md)** - Complete permissions and roles guide
- **[docs/STANCL-FEATURES.md](docs/STANCL-FEATURES.md)** - Stancl/Tenancy features explained
- **[docs/SESSION-SECURITY.md](docs/SESSION-SECURITY.md)** - Session security and Redis scoping
- **[docs/MEDIALIBRARY.md](docs/MEDIALIBRARY.md)** - MediaLibrary queue integration
- **[docs/MCP-WORKFLOW.md](docs/MCP-WORKFLOW.md)** - MCP tools workflow guide
