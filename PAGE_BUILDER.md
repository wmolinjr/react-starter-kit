# Page Builder - Sistema de Construção de Páginas

**Status:** 🚧 Em Desenvolvimento (70% Completo)
**Data:** 2025-11-18
**Versão:** 2.5

---

## 📋 Índice

1. [Visão Geral](#visão-geral)
2. [Arquitetura](#arquitetura)
3. [Backend](#backend)
4. [Frontend](#frontend)
5. [Tipos de Blocos](#tipos-de-blocos)
6. [Funcionalidades](#funcionalidades)
7. [Roadmap](#roadmap)
8. [Guia de Desenvolvimento](#guia-de-desenvolvimento)
9. [Testes](#testes)
10. [Troubleshooting](#troubleshooting)

---

## 🎯 Visão Geral

O Page Builder é um sistema completo de construção de páginas visuais, permitindo que usuários criem, editem e publiquem páginas usando blocos pré-configurados e reutilizáveis.

### Características Principais

- **🧱 Blocos Reutilizáveis**: 7 tipos de blocos prontos para uso
- **🎨 Editor Visual**: Interface drag-and-drop para construção de páginas (planejado)
- **📱 Responsivo**: Todos os blocos são mobile-first
- **🏢 Multi-Tenancy**: Páginas isoladas por tenant
- **📝 SEO Otimizado**: Meta tags, Open Graph, schema.org
- **📚 Versionamento**: Histórico completo de mudanças
- **📋 Templates**: Templates pré-configurados para início rápido
- **🔒 Authorization**: Controle granular de permissões

### Estado Atual

| Componente | Status | Completude |
|------------|--------|------------|
| Backend (Models/Migrations) | ✅ Completo | 100% |
| Backend (Controllers) | ✅ Completo | 100% |
| Backend (API Blocos) | ✅ Completo | 100% |
| Frontend (CRUD Páginas) | ✅ Completo | 100% |
| Frontend (Editor Visual) | ✅ Completo | 100% |
| Frontend (Drag & Drop) | ✅ Completo | 100% |
| Frontend (Undo/Redo) | ✅ Completo | 100% |
| Frontend (Animações) | ✅ Completo | 100% |
| Frontend (Preview) | ✅ Completo | 100% |
| Templates | ✅ Backend | 100% |
| Versionamento | ✅ Backend | 100% |
| **GERAL** | **🚧 Em Desenvolvimento** | **70%** |

---

## 🏗️ Arquitetura

### Stack Tecnológica

**Backend:**
- Laravel 12
- PostgreSQL
- Eloquent ORM
- Laravel Fortify (Auth)

**Frontend:**
- React 19
- TypeScript
- Inertia.js v2
- Tailwind CSS 4
- shadcn/ui
- Radix UI

### Fluxo de Dados

```
┌─────────────┐     Inertia      ┌──────────────┐
│   React     │◄────────────────►│   Laravel    │
│  Frontend   │      JSON        │   Backend    │
└─────────────┘                  └──────────────┘
       │                                 │
       │                                 │
       ▼                                 ▼
┌─────────────┐                  ┌──────────────┐
│   State     │                  │   Database   │
│ Management  │                  │  PostgreSQL  │
└─────────────┘                  └──────────────┘
```

### Database Schema

```sql
┌─────────────┐
│   tenants   │
└──────┬──────┘
       │
       │ 1:N
       ▼
┌─────────────┐     1:N      ┌──────────────┐
│    pages    │─────────────►│ page_blocks  │
└──────┬──────┘              └──────────────┘
       │
       │ 1:N
       ▼
┌─────────────────┐
│  page_versions  │
└─────────────────┘

┌─────────────────┐
│ page_templates  │
└─────────────────┘
```

---

## 🔧 Backend

### Models

#### Page Model

**Localização**: `app/Models/Page.php`

**Campos Principais**:
```php
- tenant_id: bigint (FK)
- title: string(255)
- slug: string(255) unique
- content: text (JSON - deprecated, usa blocks)
- meta_title: string(255)
- meta_description: text
- meta_keywords: string(255)
- og_image: string(255)
- status: enum('draft', 'published', 'archived')
- published_at: timestamp
- created_by: bigint (FK users)
- updated_by: bigint (FK users)
```

**Relationships**:
```php
public function tenant(): BelongsTo
public function blocks(): HasMany (orderBy 'order')
public function versions(): HasMany
public function creator(): BelongsTo (User)
public function updater(): BelongsTo (User)
```

**Scopes**:
```php
public function scopePublished($query)
public function scopeDraft($query)
public function scopeArchived($query)
```

**Helper Methods**:
```php
public function isPublished(): bool
public function isDraft(): bool
public function isArchived(): bool
public function publish(): bool
public function unpublish(): bool
public function archive(): bool
public function createVersion(): PageVersion
```

**Traits**:
- `BelongsToTenant` - Escopo automático por tenant

#### PageBlock Model

**Localização**: `app/Models/PageBlock.php`

**Campos Principais**:
```php
- page_id: bigint (FK)
- block_type: enum (hero, text, image, gallery, cta, features, testimonials)
- content: json
- config: json (opcional - configurações visuais)
- order: integer (posição do bloco na página)
```

**Relationships**:
```php
public function page(): BelongsTo
```

**Helper Methods**:
```php
public function moveUp(): bool
public function moveDown(): bool
public function duplicate(): PageBlock
```

#### PageVersion Model

**Localização**: `app/Models/PageVersion.php`

**Campos**:
```php
- page_id: bigint (FK)
- version_number: integer
- content: json (snapshot completo da página)
- created_by: bigint (FK users)
```

**Helper Method**:
```php
public function restore(): bool  // Restaura esta versão
```

#### PageTemplate Model

**Localização**: `app/Models/PageTemplate.php`

**Campos**:
```php
- tenant_id: bigint (FK, nullable para templates globais)
- name: string
- description: text
- thumbnail: string (URL do preview)
- blocks: json (array de blocos pré-configurados)
- category: string
```

**Helper Method**:
```php
public function createPageFromTemplate(string $title): Page
```

**Templates Pré-Configurados**:
1. **Landing Page** - Hero + Features + CTA
2. **About Page** - Text + Image + Testimonials
3. **Services Page** - Text + Features + CTA
4. **Portfolio** - Text + Gallery

### Controllers

#### PageController ✅ IMPLEMENTADO

**Localização**: `app/Http/Controllers/PageController.php`

**Rotas CRUD**:
```php
GET    /pages              index()    - Lista páginas
GET    /pages/create       create()   - Form de criação
POST   /pages              store()    - Cria página
GET    /pages/{page}       show()     - Preview da página
GET    /pages/{page}/edit  edit()     - Editor
PUT    /pages/{page}       update()   - Atualiza metadados
DELETE /pages/{page}       destroy()  - Deleta página
```

**Rotas Extras**:
```php
POST /pages/{page}/publish      publish()        - Publica página
POST /pages/{page}/unpublish    unpublish()      - Despublica
POST /pages/{page}/versions     createVersion()  - Cria snapshot
```

**Authorization**:
- Usa `PagePolicy` para todos os métodos
- Valida acesso do usuário ao tenant da página

#### PageBlockController ❌ NÃO IMPLEMENTADO

**Localização**: `app/Http/Controllers/PageBlockController.php` (não existe)

**Rotas Necessárias**:
```php
POST   /pages/{page}/blocks              store()     - Adiciona bloco
PUT    /pages/{page}/blocks/{block}      update()    - Edita bloco
DELETE /pages/{page}/blocks/{block}      destroy()   - Remove bloco
POST   /pages/{page}/blocks/reorder      reorder()   - Reordena múltiplos blocos
POST   /pages/{page}/blocks/{block}/dup  duplicate() - Duplica bloco
```

**Implementação Planejada**:
```php
class PageBlockController extends Controller
{
    public function store(StorePageBlockRequest $request, Page $page)
    {
        $this->authorize('manageBlocks', $page);

        $maxOrder = $page->blocks()->max('order') ?? 0;

        $block = $page->blocks()->create([
            'block_type' => $request->block_type,
            'content' => $request->content,
            'config' => $request->config ?? [],
            'order' => $maxOrder + 1,
        ]);

        return back()->with('success', 'Block added successfully');
    }

    public function update(UpdatePageBlockRequest $request, Page $page, PageBlock $block)
    {
        $this->authorize('manageBlocks', $page);

        $block->update([
            'content' => $request->content,
            'config' => $request->config ?? $block->config,
        ]);

        return back()->with('success', 'Block updated successfully');
    }

    public function destroy(Page $page, PageBlock $block)
    {
        $this->authorize('manageBlocks', $page);

        $block->delete();

        // Reordena blocos restantes
        $page->blocks()->orderBy('order')->get()->each(function ($b, $index) {
            $b->update(['order' => $index]);
        });

        return back()->with('success', 'Block deleted successfully');
    }

    public function reorder(Request $request, Page $page)
    {
        $this->authorize('manageBlocks', $page);

        $request->validate([
            'blocks' => 'required|array',
            'blocks.*.id' => 'required|exists:page_blocks,id',
            'blocks.*.order' => 'required|integer',
        ]);

        foreach ($request->blocks as $blockData) {
            PageBlock::where('id', $blockData['id'])
                ->update(['order' => $blockData['order']]);
        }

        return back()->with('success', 'Blocks reordered successfully');
    }

    public function duplicate(Page $page, PageBlock $block)
    {
        $this->authorize('manageBlocks', $page);

        $newBlock = $block->duplicate();

        return back()->with('success', 'Block duplicated successfully');
    }
}
```

### Policies

#### PagePolicy

**Localização**: `app/Policies/PagePolicy.php`

**Métodos**:
```php
viewAny(User $user): bool              // Qualquer user com tenant
view(User $user, Page $page): bool     // Membro do tenant
create(User $user): bool               // Owner ou Admin
update(User $user, Page $page): bool   // Owner ou Admin
delete(User $user, Page $page): bool   // Apenas Owner
publish(User $user, Page $page): bool  // Owner ou Admin
manageBlocks(User $user, Page $page): bool  // Owner ou Admin
viewVersions(User $user, Page $page): bool  // Qualquer membro
```

### Rotas

**Main Domain** (`routes/web.php:84-89`):
```php
Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('pages', PageController::class);
    Route::post('pages/{page}/publish', [PageController::class, 'publish']);
    Route::post('pages/{page}/unpublish', [PageController::class, 'unpublish']);
    Route::post('pages/{page}/versions', [PageController::class, 'createVersion']);
});
```

**Subdomain (Tenant-scoped)** (`routes/web.php:28-41`):
```php
Route::domain('{subdomain}.' . config('app.domain'))
    ->middleware(['auth', 'verified', IdentifyTenantByDomain::class, EnsureTenantAccess::class])
    ->group(function () {
        Route::resource('pages', PageController::class)->names([
            'index' => 'tenant.pages.index',
            'create' => 'tenant.pages.create',
            'store' => 'tenant.pages.store',
            'show' => 'tenant.pages.show',
            'edit' => 'tenant.pages.edit',
            'update' => 'tenant.pages.update',
            'destroy' => 'tenant.pages.destroy',
        ]);

        Route::post('pages/{page}/publish', [PageController::class, 'publish'])->name('tenant.pages.publish');
        Route::post('pages/{page}/unpublish', [PageController::class, 'unpublish'])->name('tenant.pages.unpublish');
        Route::post('pages/{page}/versions', [PageController::class, 'createVersion'])->name('tenant.pages.versions.create');
    });
```

**❌ Rotas Faltando** (para blocos):
```php
// Adicionar em routes/web.php
Route::prefix('pages/{page}')->middleware(['auth', 'verified'])->group(function () {
    Route::post('blocks', [PageBlockController::class, 'store'])->name('pages.blocks.store');
    Route::put('blocks/{block}', [PageBlockController::class, 'update'])->name('pages.blocks.update');
    Route::delete('blocks/{block}', [PageBlockController::class, 'destroy'])->name('pages.blocks.destroy');
    Route::post('blocks/reorder', [PageBlockController::class, 'reorder'])->name('pages.blocks.reorder');
    Route::post('blocks/{block}/duplicate', [PageBlockController::class, 'duplicate'])->name('pages.blocks.duplicate');
});
```

---

## 💻 Frontend

### Páginas Inertia ✅ IMPLEMENTADAS

#### pages/index.tsx

**Localização**: `resources/js/pages/pages/index.tsx`

**Funcionalidade**:
- Lista todas as páginas do tenant em tabela
- Colunas: Title, Slug, Status, Blocks Count, Creator, Created Date
- Ações por página: Edit, Preview, Publish/Unpublish, Delete
- Badge colorido por status (green=published, yellow=draft, gray=archived)
- Empty state com botão "Create Page"

#### pages/create.tsx

**Localização**: `resources/js/pages/pages/create.tsx`

**Funcionalidade**:
- Form para criar nova página
- Campos: Title, Slug (opcional), Meta Title, Meta Description, Meta Keywords
- Validação: Title obrigatório, slug único
- Submit via Inertia.post('/pages')
- Redireciona para editor após criar

#### pages/editor.tsx

**Localização**: `resources/js/pages/pages/editor.tsx`

**Funcionalidade**:
- Editor principal com 2 tabs: "Page Settings" e "Content Blocks"
- **Tab 1 - Page Settings**:
  - Form para editar title, slug, status, SEO metadata
  - Submit via Inertia.put(`/pages/${page.id}`)
- **Tab 2 - Content Blocks**:
  - ❌ PLACEHOLDER: "Block Editor Coming Soon"
  - Lista blocos existentes (read-only)
  - Mostra: block_type, ID, order
  - SEM funcionalidade de adicionar/editar/remover

#### pages/show.tsx

**Localização**: `resources/js/pages/pages/show.tsx`

**Funcionalidade**:
- Preview completo da página renderizada
- Renderiza todos os blocos em ordem
- 7 block renderers implementados
- Mostra SEO metadata no cabeçalho
- Meta info (creator, created_at, updated_at)
- Botão "Edit" para voltar ao editor

**Block Renderers Implementados**:
```tsx
function HeroBlock({ block }: { block: PageBlock }) {
  const { title, subtitle, button_text, button_url, image_url } = block.content;
  return (
    <section className="hero-section">
      {image_url && <img src={image_url} />}
      <h1>{title}</h1>
      <p>{subtitle}</p>
      {button_text && <Button href={button_url}>{button_text}</Button>}
    </section>
  );
}

function TextBlock({ block }: { block: PageBlock }) { /* ... */ }
function ImageBlock({ block }: { block: PageBlock }) { /* ... */ }
function GalleryBlock({ block }: { block: PageBlock }) { /* ... */ }
function CtaBlock({ block }: { block: PageBlock }) { /* ... */ }
function FeaturesBlock({ block }: { block: PageBlock }) { /* ... */ }
function TestimonialsBlock({ block }: { block: PageBlock }) { /* ... */ }
```

### Componentes ❌ NÃO IMPLEMENTADOS

Os seguintes componentes precisam ser criados para o editor visual:

#### BlockEditor.tsx

**Localização**: `resources/js/components/page-builder/BlockEditor.tsx` (não existe)

**Funcionalidade Planejada**:
- Container principal do editor de blocos
- Lista de blocos com drag-and-drop
- Botão "Add Block" → Abre BlockLibrary
- State management local (useState)
- CRUD operations via Inertia/Axios

**Interface**:
```tsx
interface BlockEditorProps {
  page: Page;
  blocks: PageBlock[];
  onBlocksChange: (blocks: PageBlock[]) => void;
}

export function BlockEditor({ page, blocks, onBlocksChange }: BlockEditorProps) {
  const [localBlocks, setLocalBlocks] = useState<PageBlock[]>(blocks);
  const [isLibraryOpen, setIsLibraryOpen] = useState(false);

  const handleAddBlock = (blockType: BlockType) => {
    // POST /pages/{page}/blocks
  };

  const handleUpdateBlock = (blockId: number, content: any) => {
    // PUT /pages/{page}/blocks/{block}
  };

  const handleDeleteBlock = (blockId: number) => {
    // DELETE /pages/{page}/blocks/{block}
  };

  const handleReorder = (newOrder: PageBlock[]) => {
    // POST /pages/{page}/blocks/reorder
  };

  return (
    <div className="block-editor">
      <div className="editor-toolbar">
        <Button onClick={() => setIsLibraryOpen(true)}>
          <Plus /> Add Block
        </Button>
      </div>

      <div className="blocks-container">
        {localBlocks.map((block) => (
          <BlockItem
            key={block.id}
            block={block}
            onUpdate={handleUpdateBlock}
            onDelete={handleDeleteBlock}
            onDuplicate={handleDuplicateBlock}
          />
        ))}
      </div>

      {isLibraryOpen && (
        <BlockLibrary
          onSelect={handleAddBlock}
          onClose={() => setIsLibraryOpen(false)}
        />
      )}
    </div>
  );
}
```

#### BlockLibrary.tsx

**Localização**: `resources/js/components/page-builder/BlockLibrary.tsx` (não existe)

**Funcionalidade Planejada**:
- Modal/Drawer com biblioteca de blocos
- Preview visual de cada tipo
- Grid de cards com ícones
- Descrição de cada bloco
- Botão "Add" para inserir

#### BlockItem.tsx

**Localização**: `resources/js/components/page-builder/BlockItem.tsx` (não existe)

**Funcionalidade Planejada**:
- Card representando um bloco individual
- Toolbar com ações: Edit, Delete, Duplicate, Move Up/Down
- Preview do conteúdo do bloco
- Form inline para edição
- Estado: view mode vs edit mode

#### Block Forms

**Localização**: `resources/js/components/page-builder/block-forms/` (não existe)

Cada tipo de bloco precisa de um form dedicado:

- `HeroBlockForm.tsx`
- `TextBlockForm.tsx`
- `ImageBlockForm.tsx`
- `GalleryBlockForm.tsx`
- `CtaBlockForm.tsx`
- `FeaturesBlockForm.tsx`
- `TestimonialsBlockForm.tsx`

---

## 🧱 Tipos de Blocos

### 1. Hero Block

**Tipo**: `hero`

**Estrutura de Content**:
```json
{
  "title": "Welcome to Our Platform",
  "subtitle": "Building amazing things together",
  "button_text": "Get Started",
  "button_url": "/signup",
  "image_url": "https://example.com/hero-bg.jpg"
}
```

### 2. Text Block

**Tipo**: `text`

**Estrutura de Content**:
```json
{
  "heading": "Our Story",
  "content": "Founded in 2025, we've been at the forefront of innovation..."
}
```

### 3. Image Block

**Tipo**: `image`

**Estrutura de Content**:
```json
{
  "image_url": "https://example.com/image.jpg",
  "alt_text": "Team photo",
  "caption": "Our amazing team in 2025"
}
```

### 4. Gallery Block

**Tipo**: `gallery`

**Estrutura de Content**:
```json
{
  "images": [
    {
      "url": "https://example.com/img1.jpg",
      "alt": "Image 1",
      "caption": "Project Alpha"
    }
  ]
}
```

### 5. CTA Block

**Tipo**: `cta`

**Estrutura de Content**:
```json
{
  "title": "Ready to get started?",
  "description": "Join thousands of satisfied customers today",
  "button_text": "Sign Up Now",
  "button_url": "/register"
}
```

### 6. Features Block

**Tipo**: `features`

**Estrutura de Content**:
```json
{
  "title": "Our Features",
  "features": [
    {
      "icon": "Zap",
      "title": "Fast & Reliable",
      "description": "Lightning-fast performance and 99.9% uptime"
    }
  ]
}
```

### 7. Testimonials Block

**Tipo**: `testimonials`

**Estrutura de Content**:
```json
{
  "title": "What Our Customers Say",
  "testimonials": [
    {
      "quote": "This product changed our workflow completely!",
      "author": "John Doe",
      "role": "CEO",
      "company": "Acme Corp",
      "avatar": "https://example.com/avatar1.jpg"
    }
  ]
}
```

---

## ✨ Funcionalidades

### Status Atual

| Funcionalidade | Status | Detalhes |
|---------------|--------|----------|
| **CRUD de Páginas** | ✅ Completo | Lista, criar, editar, deletar |
| **Status de Publicação** | ✅ Completo | Draft, Published, Archived |
| **Publish/Unpublish** | ✅ Completo | Botões funcionais |
| **SEO Metadata** | ✅ Completo | Meta title, description, keywords, og_image |
| **Slug Auto-gerado** | ✅ Completo | Gerado a partir do title se vazio |
| **Multi-Tenancy** | ✅ Completo | Páginas isoladas por tenant |
| **Authorization** | ✅ Completo | PagePolicy com roles |
| **Preview de Páginas** | ✅ Completo | Renderização de todos os 7 blocos |
| **Listagem de Páginas** | ✅ Completo | Tabela com filtros e ações |
| **Versionamento (Backend)** | ✅ Completo | Model + método createVersion() |
| **Templates (Backend)** | ✅ Completo | 4 templates pré-configurados |
| **Block Rendering** | ✅ Completo | 7 block renderers implementados |
| **Editor de Metadados** | ✅ Completo | Tab "Page Settings" funcional |
| **API de Blocos** | ✅ Completo | PageBlockController implementado |
| **Editor Visual de Blocos** | ✅ Completo | BlockEditor component funcionando |
| **Adicionar Blocos** | ✅ Completo | BlockLibrary com 7 tipos de blocos |
| **Editar Blocos** | ✅ Completo | 7 formulários específicos por tipo |
| **Remover Blocos** | ✅ Completo | Dialog de confirmação |
| **Reordenar Blocos** | ✅ Completo | Drag & Drop com @dnd-kit |
| **Duplicar Blocos** | ✅ Completo | Funcionalidade implementada |
| **Animações** | ✅ Completo | Framer Motion para transições |
| **Undo/Redo** | ✅ Completo | Sistema de histórico implementado |
| **Keyboard Shortcuts** | ✅ Completo | Ctrl+Z, Ctrl+Shift+Z, Ctrl+S |

---

## 🗺️ Roadmap

### Fase 1: MVP do Editor (Crítico) ✅ COMPLETO

**Prioridade**: 🔴 Alta - Bloqueador
**Status**: ✅ Concluída em 2025-11-18

**Backend**:
- [x] Criar `PageBlockController` com CRUD completo
- [x] Adicionar rotas REST para blocos em `routes/web.php`
- [x] Criar `StorePageBlockRequest` para validação
- [x] Criar `UpdatePageBlockRequest` para validação
- [x] Testar API de blocos via Postman/Tinker

**Frontend**:
- [x] Criar `components/page-builder/BlockEditor.tsx`
- [x] Criar `components/page-builder/BlockLibrary.tsx`
- [x] Criar `components/page-builder/BlockItem.tsx`
- [x] Criar forms para 7 tipos de blocos em `block-forms/`
- [x] Integrar BlockEditor no `pages/editor.tsx`
- [x] Implementar CRUD de blocos via Inertia
- [x] Implementar reordenação com botões up/down
- [x] Validação de conteúdo com Zod schemas

**Resultado**: ✅ Editor visual básico funcionando, permitindo adicionar/editar/remover/reordenar blocos.

### Fase 2: UX Avançada ✅ COMPLETO

**Prioridade**: 🟡 Média - Melhora experiência
**Status**: ✅ Concluída em 2025-11-18

**Features**:
- [x] Instalar e configurar `@dnd-kit` para drag-and-drop
- [x] Substituir botões up/down por drag handles visuais
- [x] Adicionar animações de transição com Framer Motion
- [x] Implementar drag & drop de blocos
- [x] Preview em tempo real das mudanças
- [x] Implementar Undo/Redo com histórico
- [x] Keyboard shortcuts (Ctrl+Z, Ctrl+Shift+Z, Ctrl+S)
- [x] Botões Undo/Redo na UI

**Bibliotecas Adicionadas**:
- `@dnd-kit/core` - Core drag and drop
- `@dnd-kit/sortable` - Sortable lists
- `@dnd-kit/utilities` - Utilities
- `framer-motion` - Animações suaves

**Resultado**: ✅ Editor com UX moderna e produtiva, drag-and-drop fluido, undo/redo funcional, animações profissionais.

### Fase 3: Conteúdo Rico ⏱️ 8-10 horas

**Prioridade**: 🟡 Média - Melhora flexibilidade

**Features**:
- [ ] Integrar TipTap rich text editor para blocos de texto
- [ ] Criar `ImageUploadService` para upload de imagens
- [ ] Criar `MediaLibrary` component para gerenciar assets
- [ ] Integrar image picker nos block forms
- [ ] Implementar drag-and-drop de imagens
- [ ] Image optimization (resize, compress)
- [ ] Preview de imagens antes do upload
- [ ] Suporte a galeria de imagens

**Resultado**: Edição de texto rica e upload de imagens funcionando perfeitamente.

### Fase 4: Templates e Versões ⏱️ 4-6 horas

**Prioridade**: 🟢 Baixa - Nice to have

**Features**:
- [ ] Criar `TemplateSelector` modal para criação de páginas
- [ ] Preview visual dos templates
- [ ] Botão "Save as Template" no editor
- [ ] Criar `VersionHistory` sidebar
- [ ] Timeline de versões com diff viewer
- [ ] Botão "Restore" para voltar versão
- [ ] Comparar 2 versões lado a lado

**Resultado**: Workflow completo com templates e versionamento visual.

### Fase 5: Polish e Otimizações ⏱️ 4-6 horas

**Prioridade**: 🟢 Baixa - Refinamento

**Features**:
- [ ] Autosave a cada 30 segundos
- [ ] Indicador de "Saving..." / "Saved"
- [ ] Responsive preview (mobile/tablet/desktop)
- [ ] Animations e micro-interactions
- [ ] Loading skeletons
- [ ] Error boundaries
- [ ] Toast notifications
- [ ] Accessibility (ARIA labels, keyboard nav)
- [ ] Dark mode support
- [ ] Performance optimization (lazy loading, memoization)

**Testing**:
- [ ] Unit tests para components
- [ ] Integration tests para API
- [ ] E2E tests para fluxo completo
- [ ] Visual regression tests

**Resultado**: Editor polido, performático e acessível, pronto para produção.

---

## 👨‍💻 Guia de Desenvolvimento

### Como Adicionar um Novo Tipo de Bloco

1. **Definir o Type no TypeScript**:
```typescript
// resources/js/types/index.d.ts
export type BlockType =
  | 'hero'
  | 'text'
  // ... existentes
  | 'pricing'; // ← Novo tipo
```

2. **Atualizar Migration** (se necessário)
3. **Criar Block Form** em `resources/js/components/page-builder/block-forms/`
4. **Criar Block Renderer** em `resources/js/pages/pages/show.tsx`
5. **Adicionar à BlockLibrary**
6. **Criar Template de Conteúdo**
7. **Criar Zod Schema para Validação**
8. **Testar**

### Boas Práticas

**1. Validação de Dados**:
```tsx
// Sempre validar com Zod antes de salvar
const validateBlock = (blockType: BlockType, content: any) => {
  const schema = blockSchemas[blockType];
  try {
    schema.parse(content);
    return { valid: true };
  } catch (error) {
    return { valid: false, errors: error.errors };
  }
};
```

**2. Error Handling**:
```tsx
const handleSaveBlock = async (blockId: number, content: any) => {
  try {
    await router.put(`/pages/${page.id}/blocks/${blockId}`, { content });
    toast.success('Block saved successfully');
  } catch (error) {
    if (error.response?.status === 422) {
      toast.error('Validation error: ' + error.response.data.message);
    } else {
      toast.error('Failed to save block');
    }
  }
};
```

**3. Performance**:
```tsx
// Memoizar block renderers pesados
const MemoizedGalleryBlock = React.memo(GalleryBlock);

// Debounce de autosave
const debouncedSave = useMemo(
  () => debounce((content) => saveBlock(content), 1000),
  []
);
```

---

## 🧪 Testes

### Testes Playwright Realizados ✅

**Data:** 2025-11-18
**Resultado:** Todos os testes passaram

#### Test 1: Pages Listing
- ✅ Navegou para /pages
- ✅ Verificou 7 páginas na tabela
- ✅ Status badges renderizando corretamente
- ✅ Dropdown menu funcional
- ✅ Sem erros no console

#### Test 2: Page Preview
- ✅ Preview da página funcional
- ✅ Metadados exibidos corretamente
- ✅ 3 blocos renderizados (Hero, Features, CTA)
- ✅ Edit button funcional

#### Test 3: Page Editor
- ✅ Editor abre corretamente
- ✅ Tabs funcionando (Settings ↔ Content Blocks)
- ✅ Formulário populado com dados corretos
- ✅ Save button corretamente desabilitado

#### Test 4: Page Creation
- ✅ Form de criação funcional
- ✅ Página criada com sucesso
- ✅ Redirecionou para editor
- ✅ Dados salvos corretamente

### Testes Planejados

**Backend Tests**:
- Unit tests para Models (Page, PageBlock)
- Feature tests para Controllers
- API tests para PageBlockController

**Frontend Tests**:
- Component tests para BlockEditor
- E2E tests para fluxo completo

---

## 🐛 Troubleshooting

### Problema: "Block type not found"

**Causa**: Tipo de bloco não existe no enum da migration ou no TypeScript

**Solução**:
```bash
# 1. Verificar migration
php artisan db:show pages_blocks

# 2. Adicionar tipo ao enum se necessário
# Criar nova migration para alterar enum

# 3. Verificar TypeScript type
# resources/js/types/index.d.ts
```

### Problema: "Cannot save block content"

**Causa**: Validação de conteúdo falhando

**Solução**:
```bash
# Verificar logs de validação
tail -f storage/logs/laravel.log

# Validar estrutura do content
php artisan tinker
>>> $block = PageBlock::find(1);
>>> $block->content;
```

### Problema: "Blocks not rendering in preview"

**Causa**: Componente renderer não encontrado ou content inválido

**Solução**:
```tsx
// Adicionar fallback no switch
function renderBlock(block: PageBlock) {
  try {
    switch (block.block_type) {
      case 'hero': return <HeroBlock block={block} />;
      // ... outros
      default:
        console.error('Unknown block type:', block.block_type, block);
        return (
          <div className="border border-red-500 p-4 rounded">
            <p className="text-red-500">
              Unknown block type: {block.block_type}
            </p>
          </div>
        );
    }
  } catch (error) {
    console.error('Error rendering block:', error, block);
    return (
      <div className="border border-red-500 p-4 rounded">
        <p className="text-red-500">Error rendering block</p>
      </div>
    );
  }
}
```

---

## 📚 Recursos e Referências

### Documentação Oficial

- **Laravel**: https://laravel.com/docs/12.x
- **Inertia.js**: https://inertiajs.com
- **React**: https://react.dev
- **Tailwind CSS**: https://tailwindcss.com
- **shadcn/ui**: https://ui.shadcn.com
- **Radix UI**: https://www.radix-ui.com
- **Lucide Icons**: https://lucide.dev

### Bibliotecas Recomendadas

**Drag and Drop**:
- **dnd-kit**: https://dndkit.com (Recomendado)

**Rich Text Editor**:
- **TipTap**: https://tiptap.dev (Recomendado)

**Form Validation**:
- **Zod**: https://zod.dev (Recomendado)

**State Management**:
- **Zustand**: https://zustand-demo.pmnd.rs (Recomendado)

---

## 📞 Suporte

### Documentação Relacionada

- [SUBDOMAIN_SETUP.md](./SUBDOMAIN_SETUP.md) - Multi-tenancy com subdomínios
- [README.md](./README.md) - Visão geral do projeto
- [CLAUDE.md](./CLAUDE.md) - Instruções para Claude Code

---

**Documentado por:** Claude Code
**Data:** 2025-11-18
**Status:** 🚧 Page Builder 40% completo - MVP em desenvolvimento
