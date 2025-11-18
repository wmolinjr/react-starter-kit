# Sistema Multi-Tenant de Page Builder - MVP

**Status:** Em Desenvolvimento
**Versão:** 1.0.0-MVP
**Data:** 2025-11-18

---

## 📋 Índice

1. [Visão Geral](#visão-geral)
2. [Arquitetura Multi-Tenancy](#arquitetura-multi-tenancy)
3. [Database Schema](#database-schema)
4. [Backend - Componentes](#backend---componentes)
5. [Frontend - Componentes](#frontend---componentes)
6. [Page Builder - Arquitetura](#page-builder---arquitetura)
7. [Fluxos de Uso](#fluxos-de-uso)
8. [Rotas e APIs](#rotas-e-apis)
9. [Segurança](#segurança)
10. [Roadmap de Implementação](#roadmap-de-implementação)

---

## Visão Geral

Sistema de construção de páginas web multi-tenant que permite que múltiplas organizações (tenants) criem e gerenciem suas próprias páginas usando um editor visual de blocos, similar ao WordPress Gutenberg.

### Características Principais

- **Multi-Tenancy**: Cada tenant tem seu próprio workspace isolado
- **Domínios Customizados**: Suporte para subdomínios (*.seuapp.com) e domínios próprios (www.cliente.com)
- **Page Builder Visual**: Editor de blocos drag-and-drop
- **Versionamento**: Sistema de versões para páginas
- **SEO Completo**: Meta tags, Open Graph, preview de compartilhamento
- **Templates**: Páginas pré-construídas para início rápido
- **Preview**: Visualização antes de publicar
- **Roles**: Sistema de permissões por tenant (owner/admin/member)

### Stack Tecnológico

**Backend:**
- Laravel 12
- PostgreSQL/SQLite
- Inertia.js 2.x
- Spatie Laravel Permission

**Frontend:**
- React 19
- TypeScript
- Tailwind CSS 4
- shadcn/ui
- @dnd-kit (drag and drop)
- @tiptap (rich text editor)
- Lucide Icons

---

## Arquitetura Multi-Tenancy

### Estratégia: Single Database com Tenant ID

Optamos por **Single Database com coluna `tenant_id`** nas tabelas relevantes por:
- ✅ Simplicidade de implementação
- ✅ Compatibilidade com SQLite (desenvolvimento) e PostgreSQL (produção)
- ✅ Queries eficientes com índices
- ✅ Migrations simples
- ✅ Backup único

**Isolamento de Dados:**
- Global Scopes automáticos via trait `BelongsToTenant`
- Middleware de verificação de acesso
- Policies do Laravel para autorização

### Identificação de Tenant

**Método Primário:** Path-based routing
```
https://app.com/{tenant-slug}/pages
https://app.com/acme-corp/pages
https://app.com/acme-corp/pages/create
```

**Método Secundário:** Domain-based (produção)
```
https://acme-corp.seuapp.com/pages  (subdomínio)
https://www.acmecorp.com/pages      (domínio customizado)
```

### Fluxo de Identificação

```
Request → IdentifyTenant Middleware → EnsureTenantAccess Middleware → Controller
    ↓
1. Extrai slug da rota ({tenant})
2. Busca Tenant ativo no DB
3. Armazena em app('tenant')
4. Verifica se user pertence ao tenant
5. Atualiza current_tenant_id do user
6. Processa request
```

---

## Database Schema

### Tabela: `tenants`

```sql
CREATE TABLE tenants (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(255) UNIQUE NOT NULL,
    domain VARCHAR(255) UNIQUE NULL,
    settings JSON NULL,
    status ENUM('active', 'inactive', 'suspended') DEFAULT 'active',
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```

**Campos:**
- `name`: Nome do tenant (ex: "Acme Corporation")
- `slug`: URL-friendly identifier (ex: "acme-corp") - **gerado automaticamente**
- `domain`: Domínio customizado opcional (ex: "www.acme.com")
- `settings`: Configurações JSON (cores, logo, meta defaults, etc.)
- `status`: Estado do tenant

**Índices:**
- PRIMARY KEY (id)
- UNIQUE (slug)
- UNIQUE (domain)
- INDEX (status)

---

### Tabela: `tenant_user` (Pivot)

```sql
CREATE TABLE tenant_user (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    tenant_id BIGINT NOT NULL,
    user_id BIGINT NOT NULL,
    role ENUM('owner', 'admin', 'member') DEFAULT 'member',
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE (tenant_id, user_id)
);
```

**Roles:**
- `owner`: Criador do tenant, controle total
- `admin`: Gerenciamento de páginas e membros
- `member`: Apenas edição de páginas

---

### Tabela: `users` (Modificada)

```sql
ALTER TABLE users ADD COLUMN current_tenant_id BIGINT NULL;
ALTER TABLE users ADD FOREIGN KEY (current_tenant_id) REFERENCES tenants(id) ON DELETE SET NULL;
```

**Novo Campo:**
- `current_tenant_id`: Tenant ativo no momento (para contexto)

---

### Tabela: `pages`

```sql
CREATE TABLE pages (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    tenant_id BIGINT NOT NULL,
    title VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL,
    meta_title VARCHAR(60) NULL,
    meta_description VARCHAR(160) NULL,
    meta_image VARCHAR(255) NULL,
    og_title VARCHAR(60) NULL,
    og_description VARCHAR(160) NULL,
    og_image VARCHAR(255) NULL,
    status ENUM('draft', 'published') DEFAULT 'draft',
    published_at TIMESTAMP NULL,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    UNIQUE (tenant_id, slug)
);
```

**Campos SEO:**
- `meta_title`, `meta_description`: Para Google
- `og_title`, `og_description`, `og_image`: Para redes sociais

**Índices:**
- PRIMARY KEY (id)
- FOREIGN KEY (tenant_id)
- UNIQUE (tenant_id, slug)
- INDEX (status, published_at)

---

### Tabela: `page_blocks`

```sql
CREATE TABLE page_blocks (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    page_id BIGINT NOT NULL,
    type VARCHAR(50) NOT NULL,
    content JSON NOT NULL,
    styles JSON NULL,
    order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    FOREIGN KEY (page_id) REFERENCES pages(id) ON DELETE CASCADE
);
```

**Campos:**
- `type`: Tipo do bloco (heading, text, image, button, hero, columns, etc.)
- `content`: Props do bloco em JSON (text, url, alignment, etc.)
- `styles`: Custom CSS/Tailwind classes
- `order`: Posição do bloco na página

**Block Types (MVP):**
1. `heading` - Títulos H1-H6
2. `text` - Parágrafo com rich text (TipTap)
3. `image` - Imagem com caption
4. `button` - Call-to-action button
5. `spacer` - Espaçamento vertical
6. `divider` - Linha horizontal
7. `hero` - Hero section (heading + text + button + background)
8. `columns` - Layout em 2 ou 3 colunas

---

### Tabela: `page_versions`

```sql
CREATE TABLE page_versions (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    page_id BIGINT NOT NULL,
    blocks_snapshot JSON NOT NULL,
    published_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP,
    FOREIGN KEY (page_id) REFERENCES pages(id) ON DELETE CASCADE
);
```

**Uso:**
- Snapshot completo dos blocos ao publicar
- Permite rollback para versões anteriores
- Histórico de mudanças

---

### Tabela: `page_templates` (Opcional MVP)

```sql
CREATE TABLE page_templates (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    thumbnail VARCHAR(255) NULL,
    category VARCHAR(50) NULL,
    blocks JSON NOT NULL,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```

**Templates Iniciais:**
- Landing Page
- About Us
- Contact
- Pricing

---

## Backend - Componentes

### Models

#### `Tenant` Model
**Localização:** `app/Models/Tenant.php`

```php
class Tenant extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'slug', 'domain', 'settings', 'status'];
    protected $casts = ['settings' => 'array'];

    // Relacionamentos
    public function users(): BelongsToMany;
    public function pages(): HasMany;

    // Métodos
    public function owner(): User;
    public function isActive(): bool;

    // Scopes
    public function scopeActive($query);

    // Boot
    protected static function boot() {
        // Auto-gera slug de 'name'
        static::creating(fn($tenant) => $tenant->slug = Str::slug($tenant->name));
    }
}
```

---

#### `User` Model (Estendido)
**Localização:** `app/Models/User.php`

```php
class User extends Authenticatable
{
    // ... código existente

    // Novos relacionamentos
    public function tenants(): BelongsToMany;
    public function currentTenant(): BelongsTo;

    // Métodos de tenancy
    public function switchTenant(Tenant $tenant): bool;
    public function hasAccessToTenant(Tenant $tenant): bool;
    public function roleInTenant(Tenant $tenant): ?string;
}
```

---

#### `Page` Model
**Localização:** `app/Models/Page.php`

```php
class Page extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'title', 'slug',
        'meta_title', 'meta_description', 'meta_image',
        'og_title', 'og_description', 'og_image',
        'status', 'published_at'
    ];

    protected $casts = [
        'published_at' => 'datetime',
    ];

    // Relacionamentos
    public function tenant(): BelongsTo;
    public function blocks(): HasMany;
    public function versions(): HasMany;

    // Métodos
    public function publish(): void;
    public function unpublish(): void;
    public function createVersion(): PageVersion;
    public function isPublished(): bool;

    // Scopes
    public function scopePublished($query);
    public function scopeDraft($query);
}
```

---

#### `PageBlock` Model
**Localização:** `app/Models/PageBlock.php`

```php
class PageBlock extends Model
{
    use HasFactory;

    protected $fillable = ['page_id', 'type', 'content', 'styles', 'order'];
    protected $casts = [
        'content' => 'array',
        'styles' => 'array',
    ];

    public function page(): BelongsTo;

    // Scopes
    public function scopeOrdered($query) {
        return $query->orderBy('order');
    }
}
```

---

#### `PageVersion` Model
**Localização:** `app/Models/PageVersion.php`

```php
class PageVersion extends Model
{
    use HasFactory;

    protected $fillable = ['page_id', 'blocks_snapshot', 'published_at'];
    protected $casts = [
        'blocks_snapshot' => 'array',
        'published_at' => 'datetime',
    ];

    public function page(): BelongsTo;
}
```

---

### Trait: `BelongsToTenant`
**Localização:** `app/Models/Concerns/BelongsToTenant.php`

```php
trait BelongsToTenant
{
    protected static function bootBelongsToTenant(): void {
        // Global scope: filtra automaticamente por tenant
        static::addGlobalScope('tenant', function (Builder $builder) {
            if (auth()->check() && auth()->user()->current_tenant_id) {
                $builder->where('tenant_id', auth()->user()->current_tenant_id);
            }
        });

        // Auto-preenche tenant_id ao criar
        static::creating(function (Model $model) {
            if (auth()->check() && auth()->user()->current_tenant_id) {
                $model->tenant_id = auth()->user()->current_tenant_id;
            }
        });
    }

    public function tenant(): BelongsTo;
    public function scopeWithoutTenantScope(Builder $query): Builder;
    public function scopeForTenant(Builder $query, Tenant|int $tenant): Builder;
}
```

**Uso:** Adicionar `use BelongsToTenant;` em qualquer model que pertence a tenant.

---

### Middlewares

#### `IdentifyTenant`
**Localização:** `app/Http/Middleware/IdentifyTenant.php`

**Função:** Identifica o tenant pela rota e o armazena no container.

```php
public function handle(Request $request, Closure $next): Response
{
    $tenantSlug = $request->route('tenant');
    $tenant = Tenant::where('slug', $tenantSlug)
        ->where('status', 'active')
        ->firstOrFail();

    $request->merge(['current_tenant' => $tenant]);
    app()->instance('tenant', $tenant);

    return $next($request);
}
```

---

#### `EnsureTenantAccess`
**Localização:** `app/Http/Middleware/EnsureTenantAccess.php`

**Função:** Verifica se o usuário tem acesso ao tenant.

```php
public function handle(Request $request, Closure $next): Response
{
    $user = $request->user();
    $tenant = app('tenant');

    if (!$user->hasAccessToTenant($tenant)) {
        abort(403, 'You do not have access to this tenant');
    }

    // Atualiza current_tenant_id
    if ($user->current_tenant_id !== $tenant->id) {
        $user->update(['current_tenant_id' => $tenant->id]);
    }

    return $next($request);
}
```

---

### Controllers

#### `TenantController`
**Localização:** `app/Http/Controllers/TenantController.php`

```php
class TenantController extends Controller
{
    public function index() {
        // Lista todos os tenants do usuário
        $tenants = auth()->user()->tenants()->with('users')->get();
        return Inertia::render('tenants/index', ['tenants' => $tenants]);
    }

    public function create() {
        return Inertia::render('tenants/create');
    }

    public function store(Request $request) {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|unique:tenants,slug',
        ]);

        $tenant = Tenant::create($validated);

        // Adiciona o criador como owner
        $tenant->users()->attach(auth()->id(), ['role' => 'owner']);

        // Atualiza current_tenant_id
        auth()->user()->update(['current_tenant_id' => $tenant->id]);

        return redirect()->route('tenant.pages.index', $tenant->slug);
    }

    public function show(string $slug) {
        $tenant = Tenant::where('slug', $slug)->firstOrFail();
        $this->authorize('view', $tenant);

        return Inertia::render('tenants/settings', ['tenant' => $tenant]);
    }

    public function update(Request $request, string $slug) {
        $tenant = Tenant::where('slug', $slug)->firstOrFail();
        $this->authorize('update', $tenant);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'domain' => 'nullable|url|unique:tenants,domain,' . $tenant->id,
            'settings' => 'nullable|array',
        ]);

        $tenant->update($validated);

        return back()->with('success', 'Tenant updated successfully');
    }

    public function destroy(string $slug) {
        $tenant = Tenant::where('slug', $slug)->firstOrFail();
        $this->authorize('delete', $tenant);

        $tenant->delete();

        return redirect()->route('tenants.index');
    }
}
```

---

#### `PageController`
**Localização:** `app/Http/Controllers/PageController.php`

```php
class PageController extends Controller
{
    public function index(string $tenant) {
        $pages = Page::with('blocks')
            ->orderBy('updated_at', 'desc')
            ->paginate(20);

        return Inertia::render('pages/index', ['pages' => $pages]);
    }

    public function create(string $tenant) {
        return Inertia::render('pages/create');
    }

    public function store(Request $request, string $tenant) {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'slug' => 'required|string|unique:pages,slug',
        ]);

        $page = Page::create($validated);

        return redirect()->route('tenant.pages.edit', [$tenant, $page]);
    }

    public function edit(string $tenant, Page $page) {
        $this->authorize('update', $page);

        $page->load('blocks');

        return Inertia::render('pages/edit', ['page' => $page]);
    }

    public function update(Request $request, string $tenant, Page $page) {
        $this->authorize('update', $page);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'meta_title' => 'nullable|string|max:60',
            'meta_description' => 'nullable|string|max:160',
            'blocks' => 'required|array',
        ]);

        $page->update($validated);

        // Sync blocks
        $page->blocks()->delete();
        foreach ($validated['blocks'] as $index => $blockData) {
            $page->blocks()->create([
                'type' => $blockData['type'],
                'content' => $blockData['content'],
                'styles' => $blockData['styles'] ?? null,
                'order' => $index,
            ]);
        }

        return back()->with('success', 'Page saved');
    }

    public function destroy(string $tenant, Page $page) {
        $this->authorize('delete', $page);

        $page->delete();

        return redirect()->route('tenant.pages.index', $tenant);
    }
}
```

---

#### `PagePublishController`
**Localização:** `app/Http/Controllers/PagePublishController.php`

```php
class PagePublishController extends Controller
{
    public function publish(string $tenant, Page $page) {
        $this->authorize('update', $page);

        DB::transaction(function () use ($page) {
            // Cria versão
            $page->versions()->create([
                'blocks_snapshot' => $page->blocks->toArray(),
                'published_at' => now(),
            ]);

            // Publica
            $page->update([
                'status' => 'published',
                'published_at' => now(),
            ]);
        });

        return back()->with('success', 'Page published');
    }

    public function unpublish(string $tenant, Page $page) {
        $this->authorize('update', $page);

        $page->update(['status' => 'draft']);

        return back()->with('success', 'Page unpublished');
    }
}
```

---

### Policies

#### `TenantPolicy`
**Localização:** `app/Policies/TenantPolicy.php`

```php
class TenantPolicy
{
    public function view(User $user, Tenant $tenant): bool {
        return $user->hasAccessToTenant($tenant);
    }

    public function update(User $user, Tenant $tenant): bool {
        return in_array($user->roleInTenant($tenant), ['owner', 'admin']);
    }

    public function delete(User $user, Tenant $tenant): bool {
        return $user->roleInTenant($tenant) === 'owner';
    }

    public function manageMembers(User $user, Tenant $tenant): bool {
        return in_array($user->roleInTenant($tenant), ['owner', 'admin']);
    }
}
```

---

#### `PagePolicy`
**Localização:** `app/Policies/PagePolicy.php`

```php
class PagePolicy
{
    public function viewAny(User $user): bool {
        return $user->current_tenant_id !== null;
    }

    public function view(User $user, Page $page): bool {
        return $user->hasAccessToTenant($page->tenant);
    }

    public function create(User $user): bool {
        return $user->current_tenant_id !== null;
    }

    public function update(User $user, Page $page): bool {
        return $user->hasAccessToTenant($page->tenant);
    }

    public function delete(User $user, Page $page): bool {
        return $user->hasAccessToTenant($page->tenant);
    }
}
```

---

### Shared Props (Inertia)

**Modificar:** `app/Http/Middleware/HandleInertiaRequests.php`

```php
public function share(Request $request): array
{
    return [
        ...parent::share($request),
        'name' => config('app.name'),
        'quote' => $this->randomQuote(),
        'auth' => [
            'user' => $request->user() ? [
                'id' => $request->user()->id,
                'name' => $request->user()->name,
                'email' => $request->user()->email,
                'avatar' => $request->user()->avatar,
                'two_factor_enabled' => $request->user()->two_factor_confirmed_at !== null,
            ] : null,
        ],
        'tenant' => app()->has('tenant') ? [
            'id' => app('tenant')->id,
            'name' => app('tenant')->name,
            'slug' => app('tenant')->slug,
            'settings' => app('tenant')->settings,
        ] : null,
        'tenants' => $request->user()?->tenants()->get()->map(fn($t) => [
            'id' => $t->id,
            'name' => $t->name,
            'slug' => $t->slug,
            'role' => $t->pivot->role,
        ]),
        'sidebarOpen' => Cookie::get('sidebar_state') === 'open',
    ];
}
```

---

## Frontend - Componentes

### TypeScript Types

**Localização:** `resources/js/types/index.d.ts`

```typescript
// Adicionar aos tipos existentes

interface Tenant {
  id: number;
  name: string;
  slug: string;
  domain?: string;
  settings?: Record<string, any>;
  role?: 'owner' | 'admin' | 'member'; // Role do usuário neste tenant
}

interface Page {
  id: number;
  tenant_id: number;
  title: string;
  slug: string;
  meta_title?: string;
  meta_description?: string;
  meta_image?: string;
  og_title?: string;
  og_description?: string;
  og_image?: string;
  status: 'draft' | 'published';
  published_at?: string;
  blocks: PageBlock[];
  created_at: string;
  updated_at: string;
}

interface PageBlock {
  id?: number;
  page_id?: number;
  type: BlockType;
  content: Record<string, any>;
  styles?: Record<string, any>;
  order: number;
}

type BlockType =
  | 'heading'
  | 'text'
  | 'image'
  | 'button'
  | 'spacer'
  | 'divider'
  | 'hero'
  | 'columns';

interface BlockDefinition {
  type: BlockType;
  label: string;
  icon: LucideIcon;
  defaultContent: Record<string, any>;
  component: React.ComponentType<BlockRendererProps>;
  settingsComponent: React.ComponentType<BlockSettingsProps>;
}

interface SharedData {
  name: string;
  quote: { message: string; author: string };
  auth: {
    user: User | null;
  };
  tenant?: Tenant;
  tenants?: Tenant[];
  sidebarOpen: boolean;
}
```

---

### Hooks

#### `use-tenant.ts`
**Localização:** `resources/js/hooks/use-tenant.ts`

```typescript
import { usePage } from '@inertiajs/react';
import type { SharedData, Tenant } from '@/types';

export function useTenant(): Tenant | null {
  const { tenant } = usePage<SharedData>().props;
  return tenant || null;
}

export function useTenants(): Tenant[] {
  const { tenants } = usePage<SharedData>().props;
  return tenants || [];
}

export function useCurrentTenantRole(): string | null {
  const tenant = useTenant();
  return tenant?.role || null;
}

export function useCanManageTenant(): boolean {
  const role = useCurrentTenantRole();
  return role === 'owner' || role === 'admin';
}
```

---

### Componentes - Tenant Management

#### `tenant-switcher.tsx`
**Localização:** `resources/js/components/tenant-switcher.tsx`

```typescript
import { useTenant, useTenants } from '@/hooks/use-tenant';
import { Link } from '@inertiajs/react';
import { Building2, Check, Plus } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';

export function TenantSwitcher() {
  const currentTenant = useTenant();
  const tenants = useTenants();

  return (
    <DropdownMenu>
      <DropdownMenuTrigger asChild>
        <Button variant="outline" className="w-full justify-between">
          <div className="flex items-center gap-2">
            <Building2 className="h-4 w-4" />
            <span>{currentTenant?.name || 'Select Workspace'}</span>
          </div>
        </Button>
      </DropdownMenuTrigger>
      <DropdownMenuContent align="start" className="w-64">
        <DropdownMenuLabel>Workspaces</DropdownMenuLabel>
        <DropdownMenuSeparator />
        {tenants.map((tenant) => (
          <DropdownMenuItem key={tenant.id} asChild>
            <Link
              href={`/${tenant.slug}/pages`}
              className="flex items-center justify-between"
            >
              <div className="flex items-center gap-2">
                <Building2 className="h-4 w-4" />
                <span>{tenant.name}</span>
              </div>
              {currentTenant?.id === tenant.id && (
                <Check className="h-4 w-4" />
              )}
            </Link>
          </DropdownMenuItem>
        ))}
        <DropdownMenuSeparator />
        <DropdownMenuItem asChild>
          <Link href="/tenants/create" className="flex items-center gap-2">
            <Plus className="h-4 w-4" />
            <span>Create Workspace</span>
          </Link>
        </DropdownMenuItem>
      </DropdownMenuContent>
    </DropdownMenu>
  );
}
```

---

### Páginas - Tenant Management

#### `tenants/index.tsx`
**Localização:** `resources/js/pages/tenants/index.tsx`

Lista de todos os workspaces do usuário com opção de criar novo.

#### `tenants/create.tsx`
**Localização:** `resources/js/pages/tenants/create.tsx`

Formulário para criar novo workspace (tenant).

#### `tenants/settings.tsx`
**Localização:** `resources/js/pages/tenants/settings.tsx`

Configurações do tenant (nome, domínio, membros, etc.).

---

### Páginas - Page Management

#### `pages/index.tsx`
**Localização:** `resources/js/pages/pages/index.tsx`

Lista de todas as páginas do tenant com filtros e busca.

#### `pages/create.tsx`
**Localização:** `resources/js/pages/pages/create.tsx`

Formulário inicial para criar página (título, slug, template).

#### `pages/edit.tsx`
**Localização:** `resources/js/pages/pages/edit.tsx`

**Editor Visual Principal** com:
- Canvas de blocos (drag-and-drop)
- Sidebar com paleta de blocos
- Painel de settings do bloco selecionado
- Toolbar (save, preview, publish)
- Auto-save a cada 3 segundos

---

## Page Builder - Arquitetura

### Estrutura de Componentes

```
resources/js/components/page-builder/
├── builder-canvas.tsx         # Canvas principal
├── block-palette.tsx          # Lista de blocos disponíveis
├── block-renderer.tsx         # Renderiza bloco por type
├── block-settings-panel.tsx   # Painel de configurações
├── block-toolbar.tsx          # Ações (move, delete, duplicate)
└── blocks/
    ├── heading-block.tsx
    ├── text-block.tsx
    ├── image-block.tsx
    ├── button-block.tsx
    ├── spacer-block.tsx
    ├── divider-block.tsx
    ├── hero-block.tsx
    └── columns-block.tsx
```

---

### Block Registry Pattern

```typescript
// resources/js/lib/block-registry.ts

import { Heading, Type, Image, MousePointer, Space, Minus, Rocket, Columns } from 'lucide-react';
import type { BlockDefinition } from '@/types';

export const blockRegistry: Record<BlockType, BlockDefinition> = {
  heading: {
    type: 'heading',
    label: 'Heading',
    icon: Heading,
    defaultContent: { text: 'Heading', level: 2, alignment: 'left' },
    component: HeadingBlock,
    settingsComponent: HeadingSettings,
  },
  text: {
    type: 'text',
    label: 'Text',
    icon: Type,
    defaultContent: { html: '<p>Start typing...</p>' },
    component: TextBlock,
    settingsComponent: TextSettings,
  },
  // ... outros blocos
};

export function getBlockDefinition(type: BlockType): BlockDefinition {
  return blockRegistry[type];
}
```

---

### Drag and Drop com @dnd-kit

```typescript
import { DndContext, closestCenter, DragEndEvent } from '@dnd-kit/core';
import { SortableContext, verticalListSortingStrategy } from '@dnd-kit/sortable';

export function BuilderCanvas({ blocks, onReorder }: BuilderCanvasProps) {
  const handleDragEnd = (event: DragEndEvent) => {
    const { active, over } = event;

    if (over && active.id !== over.id) {
      const oldIndex = blocks.findIndex((b) => b.id === active.id);
      const newIndex = blocks.findIndex((b) => b.id === over.id);

      const newBlocks = arrayMove(blocks, oldIndex, newIndex);
      onReorder(newBlocks);
    }
  };

  return (
    <DndContext collisionDetection={closestCenter} onDragEnd={handleDragEnd}>
      <SortableContext items={blocks} strategy={verticalListSortingStrategy}>
        {blocks.map((block) => (
          <SortableBlock key={block.id} block={block} />
        ))}
      </SortableContext>
    </DndContext>
  );
}
```

---

### Auto-save

```typescript
import { useDebounce } from '@/hooks/use-debounce';
import { router } from '@inertiajs/react';

export function PageEditor({ page }: { page: Page }) {
  const [blocks, setBlocks] = useState<PageBlock[]>(page.blocks);
  const [isSaving, setIsSaving] = useState(false);

  const debouncedBlocks = useDebounce(blocks, 3000);

  useEffect(() => {
    if (debouncedBlocks) {
      setIsSaving(true);
      router.put(
        route('tenant.pages.update', [page.tenant_id, page.id]),
        { blocks: debouncedBlocks },
        {
          preserveScroll: true,
          onFinish: () => setIsSaving(false),
        }
      );
    }
  }, [debouncedBlocks]);

  return (
    <div>
      {isSaving && <span>Saving...</span>}
      <BuilderCanvas blocks={blocks} onReorder={setBlocks} />
    </div>
  );
}
```

---

## Fluxos de Uso

### 1. Criar Tenant e Primeira Página

```
1. User registra conta → Dashboard
2. User clica "Create Workspace" → tenants/create
3. User preenche nome → POST /tenants
4. Redirect para /{slug}/pages (vazio)
5. User clica "Create Page" → pages/create
6. User escolhe template ou "Blank"
7. Redirect para /{slug}/pages/{page}/edit
8. User arrasta blocos, edita conteúdo
9. Auto-save a cada 3s
10. User clica "Publish"
11. Página publicada, versão criada
```

---

### 2. Trocar de Tenant

```
1. User clica no TenantSwitcher
2. Dropdown mostra todos os tenants do user
3. User seleciona outro tenant
4. Redirect para /{novo-slug}/pages
5. current_tenant_id atualizado
6. Todas as queries agora filtram pelo novo tenant
```

---

### 3. Convidar Membro

```
1. Owner/Admin vai em /{slug}/settings/members
2. Clica "Invite Member"
3. Digita email + escolhe role (admin/member)
4. Envia convite por email
5. Membro aceita convite
6. Registro criado em tenant_user
7. Membro pode acessar /{slug}/pages
```

---

### 4. Publicar Página com Versão

```
1. User edita página em draft
2. User clica "Publish"
3. Backend:
   a. Cria snapshot em page_versions
   b. Atualiza page.status = 'published'
   c. Atualiza page.published_at = now()
4. Página agora visível publicamente
5. Histórico de versões disponível para rollback
```

---

## Rotas e APIs

### Rotas de Tenant

```php
// routes/web.php

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/tenants', [TenantController::class, 'index'])->name('tenants.index');
    Route::get('/tenants/create', [TenantController::class, 'create'])->name('tenants.create');
    Route::post('/tenants', [TenantController::class, 'store'])->name('tenants.store');
});
```

---

### Rotas Scoped por Tenant

```php
// routes/tenant.php

Route::middleware(['auth', 'verified', 'identify.tenant', 'ensure.tenant.access'])
    ->prefix('/{tenant}')
    ->group(function () {

        // Pages
        Route::get('/pages', [PageController::class, 'index'])->name('tenant.pages.index');
        Route::get('/pages/create', [PageController::class, 'create'])->name('tenant.pages.create');
        Route::post('/pages', [PageController::class, 'store'])->name('tenant.pages.store');
        Route::get('/pages/{page}/edit', [PageController::class, 'edit'])->name('tenant.pages.edit');
        Route::put('/pages/{page}', [PageController::class, 'update'])->name('tenant.pages.update');
        Route::delete('/pages/{page}', [PageController::class, 'destroy'])->name('tenant.pages.destroy');

        // Publish
        Route::post('/pages/{page}/publish', [PagePublishController::class, 'publish'])->name('tenant.pages.publish');
        Route::post('/pages/{page}/unpublish', [PagePublishController::class, 'unpublish'])->name('tenant.pages.unpublish');

        // Tenant Settings
        Route::get('/settings', [TenantController::class, 'show'])->name('tenant.settings');
        Route::put('/settings', [TenantController::class, 'update'])->name('tenant.settings.update');
    });
```

---

### Rotas Públicas (Visualização de Páginas)

```php
// routes/public.php ou routes/web.php

// Para subdomínios
Route::domain('{tenant}.seuapp.com')->group(function () {
    Route::get('/{slug}', [PublicPageController::class, 'show'])->name('public.page.show');
});

// Para path-based
Route::get('/{tenant}/{slug}', [PublicPageController::class, 'show'])->name('public.page.show');
```

---

## Segurança

### Isolamento de Dados

1. **Global Scopes**: Trait `BelongsToTenant` adiciona `where('tenant_id', ...)` automaticamente
2. **Policies**: Verificação de ownership em todas as actions
3. **Middlewares**: Dupla verificação (IdentifyTenant + EnsureTenantAccess)
4. **Foreign Keys com CASCADE**: Ao deletar tenant, deleta tudo relacionado

---

### Validação de Input

```php
// Sempre validar slug único POR TENANT
'slug' => [
    'required',
    'string',
    Rule::unique('pages', 'slug')->where('tenant_id', auth()->user()->current_tenant_id)
]
```

---

### Rate Limiting

```php
// Limitar criação de tenants
Route::post('/tenants', [TenantController::class, 'store'])
    ->middleware('throttle:3,60'); // 3 por hora

// Limitar publicações
Route::post('/{tenant}/pages/{page}/publish', ...)
    ->middleware('throttle:10,60'); // 10 por hora
```

---

### Sanitização de HTML

```php
// Para blocos de texto com rich text
use HTMLPurifier;

$cleanHtml = app(HTMLPurifier::class)->purify($request->input('content.html'));
```

---

## Roadmap de Implementação

### ✅ Fase 1: Multi-Tenancy Base (ATUAL)

- [x] Migrations (tenants, tenant_user, current_tenant_id)
- [x] Models (Tenant, User extensions)
- [x] Trait BelongsToTenant
- [x] Middlewares (IdentifyTenant, EnsureTenantAccess)
- [ ] TenantController
- [ ] TenantPolicy
- [ ] HandleInertiaRequests (shared props)
- [ ] Routes tenant.php
- [ ] Rodar migrations
- [ ] Testar tenant creation e switching

---

### 📋 Fase 2: Page Builder Backend

- [ ] Migration: pages, page_blocks, page_versions
- [ ] Models: Page, PageBlock, PageVersion
- [ ] PageController (CRUD)
- [ ] PagePublishController
- [ ] PagePolicy
- [ ] Validation Rules
- [ ] Testar CRUD de páginas via API

---

### 🎨 Fase 3: Page Builder Frontend

- [ ] TypeScript types (Page, PageBlock, BlockType)
- [ ] Hook use-tenant
- [ ] Componentes UI base (TenantSwitcher)
- [ ] Páginas: tenants/index, create, settings
- [ ] Páginas: pages/index, create
- [ ] BuilderCanvas component
- [ ] BlockPalette component
- [ ] BlockRenderer component
- [ ] BlockSettingsPanel component
- [ ] Implementar 6-8 blocos iniciais
- [ ] Drag-and-drop com @dnd-kit
- [ ] Auto-save
- [ ] Testar editor completo

---

### 🚀 Fase 4: Templates & Publish

- [ ] Migration: page_templates
- [ ] Model: PageTemplate
- [ ] Seed 3-4 templates
- [ ] Template selector em pages/create
- [ ] Publish workflow
- [ ] Version history UI
- [ ] Rollback functionality
- [ ] Preview mode
- [ ] PublicPageController
- [ ] SEO meta tags rendering

---

### 🌐 Fase 5: Domains & Polish

- [ ] Domain verification system
- [ ] DNS instructions UI
- [ ] Wildcard subdomain support
- [ ] Custom domain mapping
- [ ] Error boundaries
- [ ] Loading states
- [ ] Toast notifications
- [ ] Keyboard shortcuts (⌘S, ⌘P)
- [ ] Mobile responsiveness
- [ ] Accessibility (a11y)

---

### ✅ Fase 6: Testing & Docs

- [ ] Feature tests (TenantAccessTest, PageCRUDTest)
- [ ] Policy tests
- [ ] Frontend: Playwright tests
- [ ] Telescope verification
- [ ] Performance optimization
- [ ] MULTI_TENANCY.md (este arquivo)
- [ ] PAGE_BUILDER.md
- [ ] DEPLOYMENT.md

---

## Comandos Úteis

```bash
# Migrations
php artisan migrate
php artisan migrate:fresh --seed

# Models
php artisan make:model Page -m
php artisan make:model PageBlock -m

# Controllers
php artisan make:controller PageController --resource
php artisan make:controller PagePublishController

# Policies
php artisan make:policy PagePolicy --model=Page

# Middleware
php artisan make:middleware IdentifyTenant

# Testes
php artisan test
php artisan test --filter TenantAccessTest

# Frontend
npm run dev
npm run build
npm run types
npm run lint

# Telescope
php artisan telescope:install
php artisan migrate
```

---

## Troubleshooting

### Tenant não identificado

- Verificar se middleware está registrado em `bootstrap/app.php`
- Verificar ordem dos middlewares (identify antes de ensure)
- Checar se rota tem parâmetro `{tenant}`

### Queries não filtradas por tenant

- Verificar se model usa trait `BelongsToTenant`
- Verificar se `current_tenant_id` está setado no user
- Usar `withoutTenantScope()` apenas quando necessário

### Auto-save não funciona

- Verificar debounce está configurado (3000ms)
- Verificar network tab para requests PUT
- Checar Telescope para erros de validação

---

## Recursos Externos

- [Laravel Multi-Tenancy Docs](https://tenancy.dev/docs)
- [Inertia.js Guide](https://inertiajs.com)
- [dnd-kit Documentation](https://docs.dndkit.com)
- [TipTap Editor Guide](https://tiptap.dev/introduction)
- [shadcn/ui Components](https://ui.shadcn.com)

---

**Última Atualização:** 2025-11-18
**Versão:** 1.0.0-MVP
**Autor:** Sistema Claude Code
