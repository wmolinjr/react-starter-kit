# Stancl/Tenancy Features

Este projeto usa **Stancl/Tenancy for Laravel v4** com estratégia de **multi-database** (cada tenant tem seu próprio banco).

## Features Habilitadas

**Configuração:** `config/tenancy.php:169-176`

```php
'features' => [
    Stancl\Tenancy\Features\UserImpersonation::class,
    Stancl\Tenancy\Features\TelescopeTags::class,
    Stancl\Tenancy\Features\CrossDomainRedirect::class,
],
```

### 1. UserImpersonation ⭐⭐⭐⭐⭐

**O que faz:** Sistema de impersonation seguro com tokens single-use e time-limited (60s TTL).

**Por que usar:**
- Essencial para suporte admin e debugging
- Tokens descartáveis (consumidos após uso)
- Sessões isoladas por domínio (segurança)
- Audit trail via `UserImpersonation::isImpersonating()`

**Implementação (Tenancy v4 Native):**
- Controller: `app/Http/Controllers/Central/Admin/ImpersonationController.php`
- Rota de consumo: `routes/tenant.php` (`/impersonate/{token}`)
- Middleware unificado: `VerifyTenantAccess` verifica `UserImpersonation::isImpersonating()` e bloqueia rotas sensíveis via `config('tenancy.impersonation.blocked_routes')`

**Uso:**
```php
// Admin cria token
$token = tenancy()->impersonate($tenant, (string) $userId, '/dashboard');

// Redireciona para tenant domain
$domain = $tenant->primaryDomain()->domain;
return Inertia::location("{$protocol}://{$domain}/impersonate/{$token->token}");

// Token consumido via UserImpersonation::makeResponse($token)
// Verificar impersonation: UserImpersonation::isImpersonating()
// Parar impersonation: UserImpersonation::stopImpersonating()
```

### 2. TelescopeTags ⭐⭐⭐⭐⭐

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

### 3. CrossDomainRedirect ⭐⭐⭐⭐

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

### 4. TenantConfigBootstrapper ⭐⭐⭐⭐

**O que faz:** Mapeia atributos do tenant para valores do Laravel `config()` durante boot.

**Por que usar:**
- Permite configurações por tenant (locale, timezone, moeda, mail)
- Integração transparente com packages que leem de `config()`
- Configurado via `TenantConfigKey` enum

**Implementação:**

```php
// config/tenancy.php - bootstrappers
Bootstrappers\TenantConfigBootstrapper::class,

// TenancyServiceProvider.php
TenantConfigBootstrapper::$storageToConfigMap = TenantConfigKey::toStorageConfigMap();

// TenantConfigKey enum define o mapeamento:
// tenant.settings['config.locale'] -> config('app.locale')
// tenant.settings['config.timezone'] -> config('app.timezone')
// tenant.settings['config.mail_from_address'] -> config('mail.from.address')
```

**Chaves disponíveis:**
- `locale` → `app.locale`
- `timezone` → `app.timezone`
- `currency` / `currency_locale` → uso em formatadores
- `mail_from_address` / `mail_from_name` → `mail.from.*`

### 5. Universal Routes - Via Middleware Flag ⭐⭐⭐⭐

**O que faz:** Permite rotas funcionarem em ambos os contextos (central + tenant).

**Por que usar:**
- Rotas de configurações (`/settings/*`) funcionam em ambos os contextos
- Central Admin e Tenant Users compartilham mesma interface de perfil/senha/2FA
- Sem duplicação de código/rotas

**Como funciona (Stancl v4):**

No v4, Universal Routes NÃO é uma feature separada - é um middleware flag:

```php
// routes/shared.php
Route::middleware(['web', InitializeTenancyByDomain::class, 'universal', 'auth'])
    ->prefix('settings')
    ->name('shared.settings.')
    ->group(function () {
        Route::get('profile', [ProfileController::class, 'edit']);
        // ...
    });
```

**Fluxo:**
1. `InitializeTenancyByDomain` detecta a flag `'universal'` na rota
2. Chama `requestHasTenant()` para verificar se domínio é tenant
3. Se domínio está em `central_domains` → NÃO inicializa tenancy
4. Se domínio é tenant → INICIALIZA tenancy

**Importante - Ordem do middleware:**
```php
// ✅ CORRETO (InitializeTenancyByDomain ANTES de 'universal')
['web', InitializeTenancyByDomain::class, 'universal', 'auth']

// ❌ ERRADO
['web', 'universal', InitializeTenancyByDomain::class, 'auth']
```

@see https://v4.tenancyforlaravel.com/universal-routes

---

## Features Desabilitadas (e Por Quê)

### ❌ ViteBundler - Incompatível

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

## Documentação Oficial

- **Stancl/Tenancy v4:** https://v4.tenancyforlaravel.com/
- **Features:** https://v4.tenancyforlaravel.com/features/overview
