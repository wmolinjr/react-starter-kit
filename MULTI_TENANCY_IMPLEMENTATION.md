# Multi-Tenancy Implementation - Laravel + React Starter Kit

**Status**: Fase 1 Completa ✅ | Fase 2 Completa ✅

Este documento detalha a implementação do sistema completo de gestão de workspaces multi-tenant similar ao Tenancy for Laravel SaaS boilerplate.

---

## Fase 1: Sistema de Múltiplos Domínios Customizados ✅

**Objetivo**: Permitir que cada workspace tenha múltiplos domínios customizados com verificação DNS.

### Backend Implementado

#### 1. Database Schema

**Migration**: `database/migrations/2025_11_19_000000_create_domains_table.php`

```sql
CREATE TABLE domains (
    id BIGINT PRIMARY KEY,
    tenant_id BIGINT NOT NULL,
    domain VARCHAR(255) UNIQUE,
    is_primary BOOLEAN DEFAULT false,
    verification_status ENUM('pending', 'verified', 'failed') DEFAULT 'pending',
    verification_token VARCHAR(255),
    verified_at TIMESTAMP NULL,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
);
```

**Migração de Dados**: Domínios existentes em `tenants.domain` foram migrados para a tabela `domains` com status `verified` e `is_primary = true`.

#### 2. Models

**Domain Model** (`app/Models/Domain.php`):
- **Relationships**: `tenant()`
- **Scopes**: `verified()`, `primary()`, `pending()`
- **Methods**:
  - `generateVerificationToken()`: Gera token único para DNS
  - `markAsVerified()`: Marca domínio como verificado
  - `markAsFailed()`: Marca verificação como falha
  - `setPrimary()`: Define como domínio primário (atômico)
  - `isVerified()`, `isPending()`, `hasFailed()`: Status helpers

**Tenant Model** (`app/Models/Tenant.php`) - Atualizações:
- **New Relationships**:
  - `domains()`: HasMany - Todos os domínios do tenant
  - `primaryDomain()`: HasOne - Domínio primário ativo
- **New Methods**:
  - `addDomain(string $domain, bool $isPrimary = false)`: Adiciona domínio com token

#### 3. Controllers

**DomainController** (`app/Http/Controllers/DomainController.php`):

| Method | Route | Descrição |
|--------|-------|-----------|
| `index($slug)` | `GET /tenants/{slug}/domains` | Lista domínios do tenant |
| `store($slug)` | `POST /tenants/{slug}/domains` | Adiciona novo domínio |
| `update($slug, $domain)` | `PATCH /tenants/{slug}/domains/{domain}` | Define como primário |
| `verify($slug, $domain)` | `POST /tenants/{slug}/domains/{domain}/verify` | Verifica via DNS |
| `destroy($slug, $domain)` | `DELETE /tenants/{slug}/domains/{domain}` | Remove domínio |

**Validações**:
- FQDN válido (regex para domínios completos)
- Domínio único (não pode ser usado por outro tenant)
- Não pode usar domínio base da aplicação
- Apenas domínios verificados podem ser primários
- Não pode deletar último domínio primário

#### 4. Services

**DomainVerificationService** (`app/Services/DomainVerificationService.php`):

```php
/**
 * Verifica propriedade do domínio via DNS TXT record
 *
 * @param Domain $domain
 * @return bool
 */
public function verifyDomain(Domain $domain): bool
{
    // 1. Busca TXT record: _tenant-verify.{domain}
    // 2. Compara com verification_token
    // 3. Auto-verifica localhost em desenvolvimento
    // 4. Marca como verified ou failed
}

/**
 * Retorna instruções de configuração DNS
 *
 * @param Domain $domain
 * @return array
 */
public function getVerificationInstructions(Domain $domain): array
{
    return [
        'record_type' => 'TXT',
        'host' => "_tenant-verify.{$domain->domain}",
        'value' => $domain->verification_token,
        'ttl' => 3600
    ];
}
```

#### 5. Middleware Updates

**IdentifyTenantByDomain** (`app/Http/Middleware/IdentifyTenantByDomain.php`):

**Prioridade de Identificação**:
1. ✅ **Custom Domain** (verified) - Consulta `domains` table primeiro
2. ✅ **Subdomain** - Extrai subdomain e consulta `tenants.subdomain`
3. ✅ **Legacy Domain** - Fallback para `tenants.domain` (compatibilidade)

```php
// Priority 1: Custom verified domains
$domain = Domain::where('domain', $host)
    ->verified()
    ->with('tenant')
    ->first();

if ($domain && $domain->tenant->isActive()) {
    app()->instance('tenant', $domain->tenant);
    return $next($request);
}

// Priority 2: Subdomain
$subdomain = $this->extractSubdomain($host);
if ($subdomain) {
    $tenant = Tenant::where('subdomain', $subdomain)
        ->active()
        ->first();

    if ($tenant) {
        app()->instance('tenant', $tenant);
        return $next($request);
    }
}

// 404 if not found
abort(404, 'Tenant not found');
```

**HandleInertiaRequests** (`app/Http/Middleware/HandleInertiaRequests.php`):
- ✅ Compartilha `tenant.domains` com todas as páginas Inertia
- ✅ Eager loading ordenado (primário primeiro, depois por data)

#### 6. Routes

```php
// routes/web.php
Route::middleware(['auth', 'verified'])->group(function () {
    Route::prefix('tenants/{slug}')->name('tenants.')->group(function () {
        Route::get('domains', [DomainController::class, 'index'])->name('domains.index');
        Route::post('domains', [DomainController::class, 'store'])->name('domains.store');
        Route::patch('domains/{domain}', [DomainController::class, 'update'])->name('domains.update');
        Route::post('domains/{domain}/verify', [DomainController::class, 'verify'])->name('domains.verify');
        Route::delete('domains/{domain}', [DomainController::class, 'destroy'])->name('domains.destroy');
    });
});
```

### Frontend Implementado

#### 1. TypeScript Types

**Domain Interfaces** (`resources/js/types/index.d.ts`):

```typescript
export type DomainVerificationStatus = 'pending' | 'verified' | 'failed';

export interface Domain {
  id: number;
  tenant_id: number;
  domain: string;
  is_primary: boolean;
  verification_status: DomainVerificationStatus;
  verification_token: string | null;
  verified_at: string | null;
  created_at: string;
  updated_at: string;
}

export interface Tenant {
  // ... campos existentes
  domains?: Domain[];
}
```

#### 2. Components

**DomainStatusBadge** (`resources/js/components/domains/domain-status-badge.tsx`):
- Badge colorido com ícones Lucide
- `pending`: Amber + Clock icon
- `verified`: Green + CheckCircle2 icon
- `failed`: Red + XCircle icon

**AddDomainDialog** (`resources/js/components/domains/add-domain-dialog.tsx`):
- Dialog modal com form
- Validação de FQDN em tempo real
- Usa `useForm()` do Inertia
- Submit para `tenants.domains.store()`
- Auto-fecha e reseta em sucesso

**DomainVerificationInstructions** (`resources/js/components/domains/domain-verification-instructions.tsx`):
- Exibe instruções DNS formatadas
- Copy-to-clipboard para token
- Botão "Verify Domain" com loading state
- Dicas sobre propagação DNS

#### 3. Pages

**Domains Settings Page** (`resources/js/pages/tenants/settings/domains.tsx`):

**Layout**:
- Header com título, descrição e botão "Add Custom Domain"
- Tabela de domínios:
  - Colunas: Domain, Status, Date Added, Actions
  - Badge "Primary" para domínio primário
  - Status badge colorido
- Empty state quando sem domínios
- Painel de instruções (para domínios pending selecionados)

**Actions Menu** (por domínio):
- "Set as Primary" - Apenas verified e não-primary
- "View Instructions" - Apenas pending
- "Verify Now" - Apenas pending
- "Delete" - Disabled se for único primary

**Funcionalidades**:
- ✅ Listar todos os domínios
- ✅ Adicionar novo domínio
- ✅ Ver status de verificação
- ✅ Copiar token de verificação
- ✅ Verificar domínio (DNS check)
- ✅ Definir domínio primário
- ✅ Deletar domínio

### Testing & Validation

#### Manual Testing Checklist

- [x] Adicionar domínio com FQDN válido
- [x] Validação rejeita domínios inválidos
- [x] Validação rejeita domínio base da app
- [x] Token de verificação é gerado
- [x] Instruções DNS são exibidas
- [x] Verificação DNS funciona (localhost auto-verifica)
- [x] Badge de status atualiza corretamente
- [x] Definir domínio como primário funciona
- [x] Não pode deletar único domínio primário
- [x] Middleware identifica por custom domain
- [x] Middleware faz fallback para subdomain
- [x] Build frontend sem erros
- [x] TypeScript sem erros
- [x] Rotas Wayfinder geradas

### Arquivos Criados/Modificados

**Backend (9 arquivos)**:
- ✅ `database/migrations/2025_11_19_000000_create_domains_table.php`
- ✅ `app/Models/Domain.php`
- ✅ `app/Http/Controllers/DomainController.php`
- ✅ `app/Services/DomainVerificationService.php`
- ✅ `app/Models/Tenant.php` (modificado)
- ✅ `app/Http/Middleware/IdentifyTenantByDomain.php` (modificado)
- ✅ `app/Http/Middleware/HandleInertiaRequests.php` (modificado)
- ✅ `routes/web.php` (modificado)

**Frontend (5 arquivos)**:
- ✅ `resources/js/types/index.d.ts` (modificado)
- ✅ `resources/js/components/domains/domain-status-badge.tsx`
- ✅ `resources/js/components/domains/add-domain-dialog.tsx`
- ✅ `resources/js/components/domains/domain-verification-instructions.tsx`
- ✅ `resources/js/pages/tenants/settings/domains.tsx`

**Build**:
- ✅ `public/build/assets/domains-B0kOm5rY.js` (15.53 kB / 5.59 kB gzipped)

### Próximas Melhorias (Futuras)

- [ ] Artisan command para verificar domínios pending automaticamente
- [ ] Notificação por email quando domínio é verificado
- [ ] Webhook para notificar verificação
- [ ] Support para wildcard domains (*.example.com)
- [ ] Cloudflare API integration para auto-verificação

---

## Fase 2: Sistema de Branding ✅

**Objetivo**: Permitir customização visual de cada workspace (logo, favicon, cores, descrição).

### Backend Implementado

#### 1. Database Schema

**Migration**: `database/migrations/2025_11_19_113223_add_branding_to_tenants_table.php`

```sql
ALTER TABLE tenants ADD COLUMN description TEXT NULL;
ALTER TABLE tenants ADD COLUMN logo VARCHAR(255) NULL;
ALTER TABLE tenants ADD COLUMN favicon VARCHAR(255) NULL;
ALTER TABLE tenants ADD COLUMN primary_color VARCHAR(7) NULL;
```

Campos adicionados após `settings` column na tabela `tenants`.

#### 2. Model Updates

**Tenant Model** (`app/Models/Tenant.php`) - Atualizações:

**Fillable Fields**:
- `description` - Descrição do workspace (max 500 caracteres)
- `logo` - Caminho do arquivo de logo
- `favicon` - Caminho do arquivo de favicon
- `primary_color` - Cor primária em HEX (#RRGGBB)

**Accessors**:
- `getLogoUrlAttribute()`: Retorna URL completa do logo usando `asset('storage/' . $logo)`
- `getFaviconUrlAttribute()`: Retorna URL completa do favicon

**Methods**:
- `updateBranding(array $data)`: Atualiza campos de branding do tenant

#### 3. Controller

**TenantController** (`app/Http/Controllers/TenantController.php`):

**Method**: `updateBranding(Request $request, string $slug)`

**Validações**:
- `description`: nullable, string, max:500
- `logo`: nullable, image, max:2048KB, mimes:png,jpg,jpeg,svg
- `favicon`: nullable, image, max:512KB, mimes:ico,png
- `primary_color`: nullable, regex:/^#[0-9A-F]{6}$/i

**File Handling**:
- Uploads salvos em `storage/app/public/tenants/{slug}/`
- Deleta arquivo antigo antes de salvar novo
- Usa `Storage::disk('public')` para persistência

**Response Pattern**:
```php
return back()->with([
    'flash' => [
        'data' => $tenant->fresh()->append(['logo_url', 'favicon_url']),
        'message' => 'Branding updated successfully',
    ],
]);
```

#### 4. Routes

```php
// routes/web.php
Route::prefix('tenants/{slug}')->name('tenants.')->group(function () {
    // ... existing routes
    Route::put('branding', [TenantController::class, 'updateBranding'])->name('branding.update');
});
```

### Frontend Implementado

#### 1. TypeScript Types

**Tenant Interface** (`resources/js/types/index.d.ts`) - Campos adicionados:

```typescript
export interface Tenant {
  // ... existing fields
  description?: string | null;
  logo?: string | null;
  favicon?: string | null;
  primary_color?: string | null;
  logo_url?: string | null;    // accessor
  favicon_url?: string | null;  // accessor
}
```

#### 2. Components

**LogoUploader** (`resources/js/components/branding/logo-uploader.tsx`):

**Features**:
- Drag-and-drop zone com feedback visual
- Suporte para PNG, JPG, SVG
- Validação de tamanho de arquivo (configurável)
- Preview de imagem atual
- Botão "Remove" para deletar
- Estados de erro inline
- Aceita configuração de `maxSize` e `accept`

**Props**:
```typescript
interface LogoUploaderProps {
    label: string;
    description?: string;
    currentUrl?: string | null;
    onFileChange: (file: File | null) => void;
    maxSize?: number; // em MB
    accept?: string;
}
```

**ColorPicker** (`resources/js/components/branding/color-picker.tsx`):

**Features**:
- Input visual tipo `color` nativo do browser
- Input de texto para código HEX
- Validação em tempo real (regex `/^#[0-9A-F]{6}$/i`)
- Preview swatch da cor selecionada
- Quick colors presets (6 cores pré-definidas)
- Sincronização bidirecional entre inputs
- Auto-correção: adiciona # se omitido
- Reverte para última cor válida em blur

**BrandingPreview** (`resources/js/components/branding/branding-preview.tsx`):

**Features**:
- Preview de logo com dimensionamento correto
- Card mostrando cor primária com nome do workspace
- Visualização de descrição
- Exemplo de uso (card com logo + botão estilizado)
- Mostra código HEX da cor
- Fallback visual quando sem logo

**Props**:
```typescript
interface BrandingPreviewProps {
    name: string;
    logo?: string | null;
    primaryColor?: string | null;
    description?: string | null;
}
```

#### 3. Page

**Branding Settings Page** (`resources/js/pages/tenants/settings/branding.tsx`):

**Layout**:
- Grid 2 colunas (formulário | preview)
- Preview sticky na direita
- Breadcrumbs navigation
- Header com botão "Back"

**Sections**:
1. **Description Card**:
   - Textarea com contador de caracteres (0/500)
   - Validação de limite inline

2. **Logo Card**:
   - LogoUploader component
   - Max 2MB, PNG/JPG/SVG
   - Recomendação: 512x512px

3. **Favicon Card**:
   - LogoUploader component
   - Max 512KB, ICO/PNG
   - Recomendação: 32x32px ou 16x16px

4. **Primary Color Card**:
   - ColorPicker component
   - HEX color format
   - Quick presets disponíveis

**Form Handling**:
- Usa `useForm()` do Inertia
- FormData para multipart upload
- `router.post()` com `forceFormData: true` (Inertia v2)
- Preview ao vivo enquanto edita
- Loading state durante submit
- Error handling inline por campo

**Submit Flow**:
```typescript
const formData = new FormData();
formData.append('description', data.description);
formData.append('primary_color', data.primary_color);
if (logoFile) formData.append('logo', logoFile);
if (faviconFile) formData.append('favicon', faviconFile);

router.post(tenants.branding.update({ slug }).url, formData, {
    forceFormData: true,
    preserveScroll: true,
});
```

### Testing & Validation

#### Manual Testing Checklist

- [x] Migração executada sem erros
- [x] Campos adicionados na tabela tenants
- [x] Upload de logo funciona (PNG, JPG, SVG)
- [x] Upload de favicon funciona (ICO, PNG)
- [x] Validação de tamanho de arquivo (2MB logo, 512KB favicon)
- [x] Validação de cor HEX (formato #RRGGBB)
- [x] Preview ao vivo atualiza enquanto edita
- [x] Arquivos antigos são deletados antes de salvar novos
- [x] Accessors retornam URLs corretas
- [x] Storage symlink funciona
- [x] TypeScript compila sem erros
- [x] Build frontend sem erros (20.39 kB / 7.14 kB gzipped)
- [x] Wayfinder routes geradas corretamente

### Arquivos Criados/Modificados

**Backend (5 arquivos)**:
- ✅ `database/migrations/2025_11_19_113223_add_branding_to_tenants_table.php`
- ✅ `app/Models/Tenant.php` (modificado - fillable, accessors, updateBranding)
- ✅ `app/Http/Controllers/TenantController.php` (modificado - updateBranding method)
- ✅ `routes/web.php` (modificado - branding route)

**Frontend (5 arquivos)**:
- ✅ `resources/js/types/index.d.ts` (modificado - Tenant interface)
- ✅ `resources/js/components/branding/logo-uploader.tsx`
- ✅ `resources/js/components/branding/color-picker.tsx`
- ✅ `resources/js/components/branding/branding-preview.tsx`
- ✅ `resources/js/pages/tenants/settings/branding.tsx`

**Build Assets**:
- ✅ `public/build/assets/branding-D5xbYsY_.js` (20.39 kB / 7.14 kB gzipped)
- ✅ `resources/js/routes/tenants/branding/index.ts` (Wayfinder gerado)

### Próximas Melhorias (Futuras)

- [ ] Adicionar crop/resize de imagens antes do upload
- [ ] Suporte para múltiplos logos (light/dark theme)
- [ ] Gradient color picker para gradientes
- [ ] Font customization (Google Fonts integration)
- [ ] Gerar favicon automaticamente do logo
- [ ] Validação de contraste de cor (acessibilidade)

**Status**: Fase 2 Completa ✅

---

## Fase 3: Member Management & Invitations (Planejado)

- Sistema de convites por email
- Gestão de membros (roles, permissões)
- Pending invitations list

## Fase 4: Advanced Actions (Planejado)

- Transfer ownership
- Archive/restore workspace
- Danger zone (delete workspace)

## Fase 5: Settings Layout with Tabs (Planejado)

- Criar layout com tabs
- Organizar todas as settings em abas
- General | Domains | Branding | Members | Danger Zone

---

## Arquitetura Multi-Tenant

### Identificação de Tenant

```
Request: https://workspace.example.com/dashboard

1. IdentifyTenantByDomain middleware:
   ├─ Check: domains.domain = 'workspace.example.com' (verified)
   ├─ Found? Use domain->tenant
   ├─ Not found? Extract subdomain 'workspace'
   ├─ Check: tenants.subdomain = 'workspace'
   └─ Not found? 404

2. EnsureTenantAccess middleware:
   ├─ Check: user->tenants->contains($tenant)
   ├─ Yes? Update user->current_tenant_id
   └─ No? 403 Forbidden

3. App container: app('tenant') = $tenant

4. All queries: scoped by tenant_id (via BelongsToTenant trait)
```

### Domínios Suportados

**Subdomain (Padrão)**:
- `{slug}.localhost` (desenvolvimento)
- `{slug}.app.com` (produção)

**Custom Domains (Ilimitados)**:
- `workspace.example.com` ✅ Verificado via DNS
- `www.company.com` ✅ Verificado via DNS
- `app.startup.io` ⏳ Pending verification

**Primary Domain**:
- Apenas 1 domínio pode ser primário
- Usado para URLs canônicas
- SEO: canonical tags apontam para primary

---

## Tecnologias Utilizadas

- **Backend**: Laravel 12, PostgreSQL, Spatie MediaLibrary
- **Frontend**: React 19, TypeScript, Inertia.js v2, Tailwind CSS 4
- **UI**: shadcn/ui, Lucide icons, Radix UI
- **Build**: Vite 7, Laravel Wayfinder (type-safe routes)
- **DNS**: PHP `dns_get_record()` para verificação TXT

---

**Última Atualização**: 2025-11-19
**Autor**: Claude Code (Anthropic)
**Versão**: 1.0.0
