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

### Tenant Commands (Stancl/Tenancy v4)

```bash
# Migrar todos os tenants
sail artisan tenants:migrate

# Migrar em paralelo (4 processos) - v4 feature
sail artisan tenants:migrate -p 4

# Migrar com skip de falhas
sail artisan tenants:migrate -p 4 --skip-failing

# Rollback em paralelo
sail artisan tenants:rollback -p 4

# Seed em paralelo
sail artisan tenants:seed -p 4

# Tinker no contexto de um tenant - v4 feature
sail artisan tenant:tinker <tenant-id>

# Listar tenants
sail artisan tenants:list

# Limpar pending tenants antigos
sail artisan tenants:pending-clear --older-than-days=7
```

### Permissions & Roles

```bash
# Sincronizar permissions dos enums para o banco
sail artisan permissions:sync

# Limpar e recriar tudo
sail artisan permissions:sync --fresh

# Sincronizar permissions de um tenant específico
sail artisan tenant:sync-permissions <tenant-id>

# Sincronizar permissions de todos os tenants
sail artisan tenant:sync-permissions --all

# Só atualizar cache (não modifica banco do tenant)
sail artisan tenant:sync-permissions --all --cache-only

# Cleanup após downgrade (remove permissions não autorizadas)
sail artisan tenant:sync-permissions --all --cleanup

# Dry run (mostra o que seria alterado)
sail artisan tenant:sync-permissions --all --dry-run
```

### Sail Configuration

**Serviços**:
- **App**: PHP 8.4 com Laravel (porta 80)
- **PostgreSQL**: 18-alpine (porta 5432)
- **Redis**: alpine (porta 6379)
- **Vite**: Dev server (porta 5173)
- **Mailpit**: Email testing (SMTP 1025, Web UI http://localhost:8025)

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
- **Controllers**: `app/Http/Controllers/` - Thin controllers (Inertia responses only)
- **Resources**: `app/Http/Resources/` - API Resources for data transformation
- **Middleware**: HandleInertiaRequests, HandleAppearance, tenant middleware
- **Routes**: web.php (public), settings.php (settings), tenant.php (tenant routes)
- **Multi-Tenancy**: Multi-database tenancy (each tenant has dedicated database)
- **Permissions**: Spatie Laravel Permission + Enums (`app/Enums/TenantPermission.php`, `CentralPermission.php`, `TenantRole.php`)
- **Services**: `app/Services/` - Business logic (return Eloquent models, not arrays)

### Models Structure

Models are organized by database context:

```
app/Models/
├── Central/           # Banco central (dados globais)
│   ├── Addon.php, AddonBundle.php, AddonPurchase.php, AddonSubscription.php
│   ├── Domain.php, Plan.php, Tenant.php
│   └── User.php       # Admins centrais (Super Admin, Central Admin)
├── Tenant/            # Banco do tenant (dados isolados)
│   ├── Activity.php, Media.php, Project.php, TenantTranslationOverride.php
│   ├── User.php       # Usuarios do tenant (owner, admin, member)
│   └── UserInvitation.php  # Team invitations (isolated per tenant)
└── Shared/            # Funcionam em ambos contextos
    ├── Permission.php
    └── Role.php
```

**MorphMap Configuration** (`AppServiceProvider.php`):
```php
Relation::enforceMorphMap([
    'user' => \App\Models\Tenant\User::class,
    'admin' => \App\Models\Central\User::class,
    'tenant' => \App\Models\Central\Tenant::class,
    'project' => \App\Models\Tenant\Project::class,
]);
```

### Services Structure

Business logic organized by context:

```
app/Services/
├── Central/           # Operam no banco central
│   ├── AddonService.php, CheckoutService.php, ImpersonationService.php
│   ├── MeteredBillingService.php, PlanFeatureResolver.php
│   ├── PlanPermissionResolver.php, PlanService.php, PlanSyncService.php
│   ├── RoleService.php, StripeSyncService.php
└── Tenant/            # Operam no banco do tenant
    ├── AuditLogService.php, BillingService.php, RoleService.php
    ├── TeamService.php, TenantSettingsService.php
```

### Additional Organized Folders

Other app folders also follow the Central/Tenant/Shared pattern:

```
app/Exceptions/
├── Central/           # PlanException, AddonException, AddonLimitExceededException
├── Tenant/            # TeamException, TeamAuthorizationException, SettingsException
└── Shared/            # RoleException

app/Jobs/
├── Central/           # SyncTenantPermissions, SeedTenantDatabase
├── Tenant/            # .gitkeep
└── Shared/            # .gitkeep

app/Listeners/
├── Central/           # UpdateTenantLimits, SyncPermissionsOnSubscriptionChange
├── Tenant/            # .gitkeep
└── Shared/            # .gitkeep

app/Mail/
├── Central/           # .gitkeep
├── Tenant/            # TeamInvitation
└── Shared/            # .gitkeep

app/Http/Middleware/
├── Central/           # .gitkeep
├── Tenant/            # AllowAdminMode, VerifyTenantAccess, CheckPlan
└── Shared/            # HandleInertiaRequests, AddSecurityHeaders, HandleAppearance, SetLocale

app/Actions/Fortify/
├── Tenant/            # CreateNewUser, ResetUserPassword
└── Shared/            # PasswordValidationRules (trait)

app/Policies/
├── Central/           # .gitkeep
├── Tenant/            # ProjectPolicy
└── Shared/            # .gitkeep
```

### Controller Pattern (Thin Controllers)

Controllers should only handle HTTP concerns. Business logic goes in Services.

**Pattern**:
```php
class TeamController extends Controller
{
    public function __construct(
        protected TeamService $teamService  // Inject service
    ) {}

    public function invite(InviteMemberRequest $request): RedirectResponse
    {
        // Form Request handles validation
        $this->teamService->invite(
            $request->validated()['email'],
            $request->validated()['role']
        );
        return back()->with('success', __('flash.invitation.sent'));
    }
}
```

**Form Requests** (validation extracted from controllers):
```
app/Http/Requests/
├── Central/           # StorePlanRequest, UpdatePlanRequest, StoreRoleRequest, UpdateRoleRequest
└── Tenant/            # InviteMemberRequest, StoreProjectRequest, CheckoutRequest, etc.
```

### API Resources

All Inertia responses use Laravel API Resources for consistent data transformation:

**Structure**:
```
app/Http/Resources/
├── BaseResource.php          # Base class with helpers (trans, formatIso, etc.)
├── Central/                  # TenantResource, PlanResource, DomainResource, etc.
├── Tenant/                   # UserResource, ProjectResource, ActivityResource, etc.
└── Shared/                   # RoleResource, PermissionResource (works in both contexts)
```

**Naming Conventions**:
- `Resource` - Listing views (e.g., `ProjectResource`)
- `DetailResource` - Show pages with relationships (e.g., `ProjectDetailResource`)
- `EditResource` - Edit forms with field values (e.g., `ProjectEditResource`)
- `SummaryResource` - Minimal info for dropdowns (e.g., `PlanSummaryResource`)

**Usage**:
```php
// Controller uses Resources for transformation
return Inertia::render('tenant/admin/projects/index', [
    'projects' => ProjectResource::collection($projects),
]);

// Services return models, NOT arrays
public function getTeamMembers(): Collection
{
    return User::with('roles')->orderBy('name')->get();
}
```

**See**: [docs/API-RESOURCES.md](docs/API-RESOURCES.md) for complete guide.

### Type Safety

- **Path aliases**: `@/` maps to `resources/js/`
- **Strict TypeScript**: Type checking enabled
- **Inertia props**: Typed via `resources/js/types/index.d.ts`
- **Laravel Wayfinder**: Type-safe route helpers for TypeScript

### Laravel Wayfinder

**Generate TypeScript routes with form support:**
```bash
sail artisan wayfinder:generate --with-form
```

**Usage with Inertia Form:**
```tsx
import { Form } from '@inertiajs/react'
import { store } from '@/routes/register'

// Standard pattern: use route.form() (returns { action, method })
<Form {...store.form()}>
  <input name="email" />
  <button type="submit">Submit</button>
</Form>
```

**Standard Pattern:** Always use `route.form()` instead of method-specific variants (`route.form.post()`, `route.form.delete()`, etc.). The `.form()` method automatically uses the default HTTP method for the route.

**Important:** Always use `--with-form` flag when regenerating routes to maintain `.form()` method support.

### Database IDs

**Decision**: UUID for ALL models (consistency and security).

**Why UUID everywhere**:
- **Consistency**: One pattern for all models - no decisions needed
- **Security**: No enumeration attacks, IDs safe to expose in URLs/logs
- **Multi-database ready**: Globally unique, works across tenant databases
- **MediaLibrary**: `uuidMorphs` works seamlessly with all models
- **Laravel 11+**: Uses UUID v7 (ordered) for better index performance

**Implementation**:
- All models use `HasUuids` trait
- All migrations use `$table->uuid('id')->primary()`
- Foreign keys use `$table->foreignUuid()`
- TypeScript types use `string` for all IDs

**See**: [docs/DATABASE-IDS.md](docs/DATABASE-IDS.md) for complete architecture.

## Multi-Tenancy & Security

### Stancl/Tenancy v4

**Version**: v4 (dev-master)
**Strategy**: Multi-database tenancy (physical isolation for LGPD/HIPAA compliance)

**Architecture (Option C: Tenant-Only Users)**:
- **Central Database** (`laravel`): tenants, domains, admins, plans, subscriptions
- **Tenant Databases** (`tenant_{id}`): users, projects, media, roles, permissions, activity_log

**User Models**:
- **Central\\User** (`app/Models/Central/User.php`): Central administrators with `is_super_admin` flag
- **Tenant\\User** (`app/Models/Tenant/User.php`): Tenant users (owners, admins, members) - isolated per database

**v4 Bootstrappers Ativos**:
- ✅ **DatabaseTenancyBootstrapper**: Switches database connection per tenant
- ✅ **CacheTenancyBootstrapper**: Cache prefixing por tenant
- ✅ **FilesystemTenancyBootstrapper**: Storage paths isolados
- ✅ **QueueTenancyBootstrapper**: Jobs mantêm contexto do tenant
- ✅ **RedisTenancyBootstrapper**: Redis keys prefixadas por tenant
- ✅ **DatabaseSessionBootstrapper**: Sessões em banco isoladas (v4)
- ✅ **FortifyRouteBootstrapper**: Redirects tenant-aware para Fortify (v4)
- ✅ **SpatiePermissionsBootstrapper**: Cache de permissions por tenant

**v4 Features Ativas**:
- ✅ **UserImpersonation**: Admin impersonation com tokens single-use
- ✅ **TelescopeTags**: Tags automáticas no Telescope
- ✅ **CrossDomainRedirect**: Redirects entre domínios central/tenant

**v4 Route Configuration**:
- **Default Route Mode**: `RouteMode::CENTRAL`
- **Early Identification**: Tenancy inicializado ANTES de StartSession
- **Shared Routes**: `/settings/*` funcionam em ambos contextos

**Tenant Creation Flow**:
1. `Tenant::create()` fires `TenantCreated` event
2. `Jobs\CreateDatabase` creates `tenant_{id}` PostgreSQL database
3. `Jobs\MigrateDatabase` runs tenant migrations
4. `SeedTenantDatabase` seeds roles/permissions

**Migrations Structure**:
```
database/migrations/          # Central database (default)
database/migrations/tenant/   # Tenant databases
```

**See**:
- [docs/MULTI-DATABASE-MIGRATION-PLAN.md](docs/MULTI-DATABASE-MIGRATION-PLAN.md) - Migration strategy
- [docs/TENANCY-V4-IMPROVEMENT-PLAN.md](docs/TENANCY-V4-IMPROVEMENT-PLAN.md) - v4 improvement roadmap

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

**Middleware** (`bootstrap/app.php:88-93`):
```php
$middleware->priority([
    \Stancl\Tenancy\Middleware\InitializeTenancyByDomain::class,
    \Illuminate\Session\Middleware\StartSession::class,
]);
$middleware->prepend(\Stancl\Tenancy\Middleware\InitializeTenancyByDomain::class);
```

**Propósito**: Garante que tenancy seja inicializado ANTES do StartSession para Redis session prefixing correto.

**$onFail Handler** (`TenancyServiceProvider::boot()`):
```php
Middleware\InitializeTenancyByDomain::$onFail = function ($exception, $request, $next) {
    if (tenancy()->initialized) {
        return $next($request);
    }
    return $next($request);
};
```

**Reference**: [Tenancy v4 docs - Early Identification Middleware](https://v4.tenancyforlaravel.com/version-4)

### MediaLibrary Integration

**Spatie MediaLibrary** com isolamento multi-tenant completo:

- ✅ Media model em `App\Models\Tenant\Media` (isolado por banco de dados)
- ✅ TenantPathGenerator para paths isolados (`tenants/{id}/media/...`)
- ✅ QueueTenancyBootstrapper para conversion jobs
- ✅ Testes completos (MediaLibraryQueueTenancyTest)

**See**: [docs/MEDIALIBRARY.md](docs/MEDIALIBRARY.md) for integration details.

## Permissions & Roles

**System**: Spatie Laravel Permission + PHP Enums (Single Source of Truth)

**Architecture**:
```
app/Enums/
├── TenantPermission.php    # 41 tenant permissions + descriptions
├── CentralPermission.php   # 38 central permissions + descriptions
```

**Quick Reference**:
```bash
sail artisan permissions:sync    # Sync all permissions from enums
```

**Adding a New Permission**:
1. Edit enum (`TenantPermission.php` or `CentralPermission.php`)
2. Add case + description in `description()` method
3. Run `sail artisan permissions:sync`

**Nomenclatura**: `resource:action`
- Tenant: `projects:view`, `team:invite`, `billing:manage`
- Central: `tenants:view`, `plans:edit`, `system:logs`

**Tenant Roles**:
- `owner`: All permissions - Full access including billing
- `admin`: 13 permissions - Team & projects (no billing/danger)
- `member`: 6 permissions - Own projects only

**See**: [docs/PERMISSIONS.md](docs/PERMISSIONS.md) for complete guide.

## Plans System (Hybrid Architecture)

**Architecture**: Database + Laravel Pennant + Spatie Permission

**Quick Commands**:
```bash
# Seed plans (Starter, Professional, Enterprise)
sail artisan db:seed --class=PlanSeeder

# Run plan tests
sail artisan test --filter=Plan
```

**Key Concepts**:
- **Plans** (Database): Defines subscription tiers with features, limits, and permission mappings
- **Pennant** (Feature Flags): Resolves features and limits at runtime
- **Spatie Permission**: Maps plan features to granular user permissions
- **Enums**: `PlanFeature` and `PlanLimit` for type-safe feature/limit references

**Usage**:
```php
// Backend - Check features
use Laravel\Pennant\Feature;
if (Feature::for($tenant)->active('customRoles')) { }

// Backend - Check limits
if ($tenant->hasReachedLimit('users')) { }

// Frontend - React hook
const { hasFeature, hasReachedLimit } = usePlan();
```

**⚠️ DO NOT**:
- Bypass `Feature::for()` by checking plan JSON directly
- Modify `plan_enabled_permissions` manually (auto-generated)
- Hardcode plan checks (use features instead)

**See**: [docs/SYSTEM-ARCHITECTURE.md](docs/SYSTEM-ARCHITECTURE.md) for complete plans documentation.

## Testing

```bash
sail artisan test                      # Run all tests
sail artisan test --filter TestName   # Run specific test
sail artisan migrate:fresh --seed     # Reset database and seed test users
```

**Test Users** (created by seeders):

| Type | Email | Password | Domain | Guard | Model |
|------|-------|----------|--------|-------|-------|
| Super Admin | `admin@setor3.app` | `password` | localhost/admin/login | `central` | Central\\User |
| Support Admin | `support@setor3.app` | `password` | localhost/admin/login | `central` | Central\\User |
| Tenant 1 Owner | `john@acme.com` | `password` | tenant1.localhost | `tenant` | Tenant\\User |
| Tenant 2 Owner | `jane@startup.com` | `password` | tenant2.localhost | `tenant` | Tenant\\User |
| Tenant 3 Owner | `mike@enterprise.com` | `password` | tenant3.localhost | `tenant` | Tenant\\User |

**Authentication Guards (Option C)**:
- `central` guard: Central administrators (Central\\User) at `localhost/admin/login`
- `tenant` guard: Tenant users (Tenant\\User) at `{tenant}.localhost/login`

**See**: [docs/MCP-WORKFLOW.md](docs/MCP-WORKFLOW.md#usuários-de-teste-seeders) for detailed test scenarios.

- PHPUnit 11 configured with `phpunit.xml`
- Test suites: Unit (`tests/Unit`) and Feature (`tests/Feature`)
- Test database: PostgreSQL (via Sail)
- Telescope desabilitado durante testes

### Playwright E2E Tests

**Purpose**: Test runtime session/cache isolation between tenants (requires real browser HTTP lifecycle).

```bash
# Run all E2E tests
sail npm run test:e2e

# Run with headed browser (visible)
sail npm run test:e2e:headed

# Run with Playwright UI
sail npm run test:e2e:ui

# Show HTML report
sail npm run test:e2e:report
```

**Test Location**: `tests/Browser/`

**Key Test**: `session-isolation.spec.ts`
- Sessions not leaking between tenant domains
- Independent sessions for each tenant
- Logout isolation (one tenant doesn't affect another)
- Cache isolation verification
- Session cookie domain scoping

**Prerequisites**:
- Sail containers running (`sail up -d`)
- Tenants seeded (`tenant1.localhost`, `tenant2.localhost`)
- Hosts configured (`/etc/hosts` with `127.0.0.1 tenant1.localhost tenant2.localhost`)

**Why Playwright for Session Tests?**
PHPUnit cannot properly test runtime session isolation because switching tenants mid-process doesn't reinitialize session handlers. Browser tests exercise the full HTTP lifecycle including:
1. `InitializeTenancyByDomain` middleware
2. `RedisTenancyBootstrapper` (Redis key prefixing)
3. `CacheTenancyBootstrapper` (session scoping)
4. `StartSession` middleware

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

- **[docs/PERMISSIONS.md](docs/PERMISSIONS.md)** - Permissions system (enums, roles, usage)
- **[docs/SYSTEM-ARCHITECTURE.md](docs/SYSTEM-ARCHITECTURE.md)** - Plans, features, limits, addons
- **[docs/DATABASE-IDS.md](docs/DATABASE-IDS.md)** - UUID architecture decision
- **[docs/STANCL-FEATURES.md](docs/STANCL-FEATURES.md)** - Multi-tenancy features
- **[docs/SESSION-SECURITY.md](docs/SESSION-SECURITY.md)** - Session security and Redis
- **[docs/MEDIALIBRARY.md](docs/MEDIALIBRARY.md)** - MediaLibrary integration
- **[docs/MCP-WORKFLOW.md](docs/MCP-WORKFLOW.md)** - MCP tools workflow
- **[docs/I18N.md](docs/I18N.md)** - Internationalization guide
- **[docs/ADDONS.md](docs/ADDONS.md)** - Add-ons system
- **[docs/API-RESOURCES.md](docs/API-RESOURCES.md)** - API Resources for data transformation
