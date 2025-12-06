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

## Features Desabilitadas (e Por Quê)

### ⚠️ TenantConfig - Considerar Futuro

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

### ❌ UniversalRoutes - Não Necessário

**O que faz:** Permite rotas funcionarem em ambos os domínios (central + tenant).

**Por que NÃO usar:**
- Arquitetura atual separa intencionalmente domínios
- Nenhuma rota precisa funcionar em ambos
- Adiciona complexidade sem benefício
- Security model depende de isolamento estrito

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
