# Session Security & Multi-Tenancy

Este projeto usa **multi-tenancy com isolamento de sessões** para segurança máxima entre tenants.

## ⚠️ CONFIGURAÇÃO CRÍTICA: SESSION_DOMAIN

### Para PRODUÇÃO (obrigatório)

```env
SESSION_DOMAIN=              # VAZIO = Isolamento por domínio exato
SESSION_SAME_SITE=lax        # Proteção CSRF + permite impersonation
SESSION_SECURE_COOKIE=true   # HTTPS obrigatório
```

### Para DESENVOLVIMENTO (opcional)

```env
SESSION_DOMAIN=.localhost    # Facilita testes locais
SESSION_SAME_SITE=lax
```

## Implicações de Segurança

| Configuração | Comportamento | Segurança | Produção |
|--------------|---------------|-----------|----------|
| `SESSION_DOMAIN=null` | Cookies isolados por domínio exato | ✅ **Seguro** - Tenants isolados | ✅ **Recomendado** |
| `SESSION_DOMAIN=.yourdomain.com` | Cookies compartilhados entre subdomains | ❌ **Inseguro** - Session leakage | ❌ **Nunca usar** |
| `SESSION_SAME_SITE=strict` | Máxima proteção CSRF | ✅ Seguro (pode quebrar flows) | ⚠️ Requer testes |
| `SESSION_SAME_SITE=lax` | Proteção CSRF + permite GET redirects | ✅ **Recomendado** | ✅ **Ideal** |
| `SESSION_SAME_SITE=none` | Permite cross-site requests | ❌ **Muito inseguro** | ❌ **Nunca usar** |

## ❌ Vulnerabilidades de SESSION_DOMAIN=.yourdomain.com

1. **Session Fixation**: Atacante em `tenant1.yourdomain.com` pode fixar sessão em `tenant2.yourdomain.com`
2. **Cross-Tenant Cookie Leakage**: Sessões vazam entre tenants diferentes
3. **Session Hijacking via XSS**: XSS em qualquer subdomain compromete todos os outros
4. **CSRF Cross-Domain**: Ataques CSRF facilitados entre subdomains

## ✅ Sistema de Impersonation Seguro

O impersonation **NÃO DEPENDE** de cookies compartilhados. Funciona perfeitamente com `SESSION_DOMAIN=null`:

### Fluxo Seguro

1. Admin em `admin.yourdomain.com` cria token de impersonation
2. Redireciona para `tenant1.yourdomain.com/impersonate/{token}`
3. Token validado no tenant domain
4. **NOVA sessão criada** exclusivamente em `tenant1.yourdomain.com`
5. Cookies **NÃO compartilhados** entre admin e tenant

### Implementação

`app/Http/Controllers/ImpersonationController.php:41`:

```php
// Cria nova sessão isolada no domínio do tenant
auth()->login($impersonationToken->user, true);
session()->regenerate(); // Sessão independente
```

### Segurança do Token

- ✅ Single-use (consumido após primeiro uso)
- ✅ Time-limited (expira em 5 minutos)
- ✅ Tenant-scoped (validado contra tenant atual)
- ✅ Audit trail (registra quem impersonou quem)

## Redis Session Scoping

### Arquitetura Multi-Database Redis

**Estratégia**: Múltiplas databases Redis para separação de concerns.

```env
REDIS_DB=0          # Default: Sessions + Direct Redis calls (tenant-prefixed)
REDIS_CACHE_DB=1    # Cache: Application cache (tenant-tagged)
REDIS_QUEUE_DB=2    # Queue: Background jobs (NO tenant prefix)
```

### Session Scoping via CacheTenancyBootstrapper

**Configuração** (`config/tenancy.php:103-106`):

```php
'cache' => [
    'tag_base' => 'tenant',
    'scope_sessions' => true,  // ✅ Automatic session scoping for cache-based drivers
],
```

**Como Funciona**:

1. `CacheTenancyBootstrapper` detecta que `SESSION_DRIVER=redis` é cache-based
2. Com `scope_sessions => true`, aplica **tenant tagging** automático nas sessions
3. Sessions são isoladas por tenant usando cache tags: `tenant:{tenant_id}`
4. Garante que `tenant1` nunca acesse sessions de `tenant2`

**Referência**: [Session Scoping - Tenancy for Laravel v4](https://v4.tenancyforlaravel.com/session-scoping)

### Redis Prefix Scoping via RedisTenancyBootstrapper

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

### ⚠️ CRITICAL: Queue Connection Isolation

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
],
```

## Fluxo Completo de Session Isolation

### Tenant Initialization

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

### Session Read/Write

**Tenant 1**:
```php
// User in tenant1.yourdomain.com
session()->put('user_id', 123);

// Redis key (DB 0): tenant_1:laravel_session:abc123
// Cache tag: tenant:1
// Result: Isolated to Tenant 1 only
```

**Tenant 2**:
```php
// User in tenant2.yourdomain.com
session()->put('user_id', 456);

// Redis key (DB 0): tenant_2:laravel_session:xyz789
// Cache tag: tenant:2
// Result: Completely isolated from Tenant 1
```

## Checklist de Segurança para Produção

Ver arquivo `.env.production.example` com configuração completa e comentada.

### Sessões
- [ ] `SESSION_DOMAIN` vazio (isolamento por domínio)
- [ ] `SESSION_SAME_SITE=lax` ou `strict`
- [ ] `SESSION_SECURE_COOKIE=true` (HTTPS)
- [ ] `SESSION_DRIVER=redis` (performance + escalabilidade)

### Multi-Tenancy
- [ ] Route model binding verifica `tenant_id` (`bootstrap/app.php:68`)
- [ ] Middleware `VerifyTenantAccess` em todas as rotas tenant
- [ ] Models usam `BelongsToTenant` trait
- [ ] Storage isolado por tenant (`tenants/{tenant_id}/...`)

### Impersonation
- [ ] Tokens single-use e time-limited
- [ ] Audit trail habilitado
- [ ] `VerifyTenantAccess` bloqueia rotas sensíveis durante impersonation (via config)
- [ ] Rota `/impersonate/{token}` apenas em tenant domains

### Redis Session Scoping
- [ ] `SESSION_DRIVER=redis` configurado
- [ ] `tenancy.cache.scope_sessions=true` habilitado
- [ ] `RedisTenancyBootstrapper` habilitado
- [ ] Redis connections separadas (default, cache, queue)
- [ ] Queue connection NÃO em `prefixed_connections`

## Benefícios da Arquitetura

### Performance
- Redis mais rápido que database sessions
- Databases separadas evitam key conflicts
- Cache tags eficientes (O(1) lookups)

### Security
- Isolamento automático por tenant (via prefix + tags)
- Impossível cross-tenant session access
- Queue jobs não "vazam" entre tenants

### Scalability
- Redis cluster-ready
- Databases independentes podem ser movidas para Redis instances separados
- Horizontal scaling sem mudanças de código

### Maintainability
- Configuração declarativa (via config files)
- Zero código customizado para session scoping
- Stancl Tenancy gerencia automaticamente
