# Stancl/Tenancy v4 - Plano de Melhorias

Este documento detalha as melhorias do Stancl/Tenancy v4 que podemos implementar no projeto atual.

## Status Atual

**Versao**: Stancl/Tenancy v4 (dev-master)
**Modo**: Multi-database tenancy (PostgreSQL)
**Arquitetura**: Domain-based identification

### Recursos v4 Ja Implementados

- [x] FortifyRouteBootstrapper - Redirects tenant-aware para Fortify
- [x] DatabaseSessionBootstrapper - Sessoes isoladas por tenant
- [x] RouteMode enum - Configurado como CENTRAL por default
- [x] Early identification middleware - `InitializeTenancyByDomain` prepended
- [x] Middleware priority - Tenancy antes de StartSession
- [x] PreventAccessFromUnwantedDomains - Substituindo PreventAccessFromCentralDomains
- [x] Shared routes - Rotas `/settings/*` funcionam em ambos contextos
- [x] UserImpersonation - Feature habilitada
- [x] TelescopeTags - Feature habilitada
- [x] CrossDomainRedirect - Feature habilitada

---

## Melhorias Recomendadas

### 1. ScopeSessions Middleware (Alta Prioridade)

**Descricao**: Middleware que previne session hijacking entre tenants.

**Beneficio**: Seguranca adicional - garante que uma sessao criada em um tenant nao pode ser usada em outro.

**Status Atual**: Nao implementado (usamos apenas isolamento via Redis prefix).

**Implementacao**:

```php
// bootstrap/app.php - Adicionar ao middleware group tenant
$middleware->alias([
    'scope.sessions' => \Stancl\Tenancy\Middleware\ScopeSessions::class,
]);
```

```php
// routes/tenant.php - Adicionar apos InitializeTenancyByDomain
Route::middleware([
    'web',
    InitializeTenancyByDomain::class,
    PreventAccessFromUnwantedDomains::class,
    'scope.sessions', // <-- Adicionar
])->name('tenant.')->group(function () {
    // ...
});
```

**Configuracao Opcional**:
```php
// AppServiceProvider.php
use Stancl\Tenancy\Middleware\ScopeSessions;

ScopeSessions::$onFail = function ($request) {
    // Limpar sessao e redirecionar para login
    $request->session()->invalidate();
    return redirect()->route('login')
        ->with('error', 'Sua sessao expirou. Faca login novamente.');
};
```

**Prioridade**: Alta
**Esforco**: Baixo (1-2 horas)

---

### 2. Pending Tenants Pool (Media Prioridade)

**Descricao**: Pool de tenants pre-criados para onboarding instantaneo.

**Beneficio**: Novo tenant signup sem espera - database ja existe.

**Status Atual**: Configuracao existe (`tenancy.pending.count = 5`) mas nao esta sendo usado.

**Implementacao**:

1. **Adicionar migration para pending_since**:
```php
// database/migrations/xxxx_add_pending_since_to_tenants.php
Schema::table('tenants', function (Blueprint $table) {
    $table->unsignedBigInteger('pending_since')->nullable()->index();
});
```

2. **Atualizar Tenant Model**:
```php
// app/Models/Tenant.php
use Stancl\Tenancy\Database\Concerns\HasPendingState;

class Tenant extends Model implements TenantWithDatabase
{
    use HasPendingState; // <-- Adicionar
    // ...
}
```

3. **Criar comando para manter o pool**:
```bash
# Adicionar ao scheduler
sail artisan tenants:pending-create --count=5
```

4. **Modificar registro de tenant**:
```php
// Servico de criacao de tenant
public function createTenant(array $data): Tenant
{
    // Tentar pegar um pending tenant
    $tenant = Tenant::claimPending();

    if ($tenant) {
        // Atualizar com dados reais
        $tenant->update([
            'name' => $data['name'],
            'slug' => $data['slug'],
        ]);
        return $tenant;
    }

    // Fallback: criar novo (sync)
    return Tenant::create($data);
}
```

5. **Limpar pending tenants antigos**:
```bash
# Scheduler: limpar pending > 7 dias
sail artisan tenants:pending-clear --older-than-days=7
```

**Prioridade**: Media
**Esforco**: Medio (4-6 horas)

---

### 3. Parallel Migrations (Alta Prioridade)

**Descricao**: Migracoes em paralelo para multiplos tenants.

**Beneficio**: Speedup proporcional ao numero de CPU cores.

**Status Atual**: Disponivel mas nao documentado no projeto.

**Uso**:
```bash
# Migrar todos os tenants em paralelo (4 processos)
sail artisan tenants:migrate -p 4

# Com --skip-failing para continuar se um tenant falhar
sail artisan tenants:migrate -p 4 --skip-failing

# Seed em paralelo
sail artisan tenants:seed -p 4
```

**Adicionar ao CLAUDE.md**:
```markdown
### Parallel Tenant Commands

# Migrar tenants em paralelo (4 processos)
sail artisan tenants:migrate -p 4

# Rollback em paralelo
sail artisan tenants:rollback -p 4

# Seed em paralelo
sail artisan tenants:seed -p 4
```

**Prioridade**: Alta
**Esforco**: Muito Baixo (apenas documentacao)

---

### 4. MailConfigBootstrapper (Baixa Prioridade)

**Descricao**: Credenciais de email por tenant.

**Beneficio**: Cada tenant pode ter seu proprio SMTP/mailer.

**Status Atual**: Nao implementado (usamos mailer global).

**Implementacao** (se necessario no futuro):

1. **Adicionar ao bootstrappers**:
```php
// config/tenancy.php
'bootstrappers' => [
    // ...
    Bootstrappers\MailConfigBootstrapper::class,
],
```

2. **Armazenar credenciais no tenant**:
```php
// Tenant data (JSON)
$tenant->update([
    'mail_mailer' => 'smtp',
    'mail_host' => 'smtp.mailgun.org',
    'mail_port' => 587,
    'mail_username' => 'tenant-specific',
    'mail_password' => encrypt('secret'),
    'mail_from_address' => 'hello@tenant.com',
    'mail_from_name' => $tenant->name,
]);
```

**Prioridade**: Baixa (feature para Enterprise tier)
**Esforco**: Medio (3-4 horas)

---

### 5. BroadcastChannelPrefixBootstrapper (Media Prioridade)

**Descricao**: Prefixo de canais de broadcast por tenant.

**Beneficio**: Eventos real-time isolados por tenant.

**Status Atual**: Nao implementado.

**Implementacao**:

```php
// config/tenancy.php
'bootstrappers' => [
    // ...
    Bootstrappers\BroadcastChannelPrefixBootstrapper::class,
],
```

**Prioridade**: Media (necessario quando implementar real-time features)
**Esforco**: Baixo (1 hora)

---

### 6. ScoutPrefixBootstrapper (Baixa Prioridade)

**Descricao**: Prefixo de indices de busca por tenant.

**Beneficio**: Busca full-text isolada por tenant (Meilisearch/Algolia).

**Status Atual**: Nao implementado (nao usamos Scout ainda).

**Implementacao** (quando necessario):

```php
// config/tenancy.php
'bootstrappers' => [
    // ...
    Bootstrappers\Integrations\ScoutPrefixBootstrapper::class,
],
```

**Prioridade**: Baixa
**Esforco**: Baixo

---

### 7. DisallowSqliteAttach Feature (Seguranca)

**Descricao**: Previne ATTACH database attack em SQLite.

**Beneficio**: Seguranca adicional para ambientes de teste.

**Status Atual**: Comentado no config.

**Implementacao**:
```php
// config/tenancy.php
'features' => [
    // ...
    Stancl\Tenancy\Features\DisallowSqliteAttach::class, // <-- Descomentar
],
```

**Prioridade**: Baixa (so relevante se usar SQLite)
**Esforco**: Muito Baixo

---

### 8. Tenant Resolver Caching (Alta Prioridade)

**Descricao**: Cache de resolucao de tenant por dominio.

**Beneficio**: Performance - evita query no banco para cada request.

**Status Atual**: Desabilitado (`cache => false`).

**Implementacao**:

```php
// config/tenancy.php
'identification' => [
    'resolvers' => [
        Resolvers\DomainTenantResolver::class => [
            'cache' => true,          // <-- Habilitar
            'cache_ttl' => 3600,      // 1 hora
            'cache_store' => 'redis', // Usar Redis
        ],
    ],
],
```

**Importante**: Invalidar cache quando dominio muda:
```php
// Observer ou evento
public function updated(Domain $domain)
{
    Cache::tags(['tenancy'])->forget("tenant_by_domain_{$domain->domain}");
}
```

**Prioridade**: Alta
**Esforco**: Baixo (1-2 horas)

---

### 9. InitializeTenancyByOriginHeader (SPA Support)

**Descricao**: Identificacao de tenant via header Origin para SPAs.

**Beneficio**: Suporte a SPA separado consumindo API do tenant.

**Status Atual**: Disponivel mas nao utilizado.

**Implementacao** (quando necessario para SPA):

```php
// routes/api.php
Route::middleware([
    'api',
    InitializeTenancyByOriginHeader::class,
])->group(function () {
    // API routes
});
```

**Prioridade**: Baixa (futuro: SPA separado)
**Esforco**: Baixo

---

### 10. Route Mode Improvements

**Descricao**: Usar RouteMode enum para declarar rotas.

**Status Atual**: Usando `default_route_mode => RouteMode::CENTRAL` mas nao declarando explicitamente.

**Implementacao** (opcional, para clareza):

```php
// routes/tenant.php
use Stancl\Tenancy\Enums\RouteMode;

Route::middleware([...])->routeMode(RouteMode::TENANT)->group(function () {
    // Rotas tenant
});
```

**Prioridade**: Baixa (apenas organizacional)
**Esforco**: Muito Baixo

---

### 11. tenant:tinker Command

**Descricao**: Tinker no contexto de um tenant especifico.

**Beneficio**: Debug e exploracao de dados do tenant.

**Status Atual**: Ja disponivel via v4.

**Uso**:
```bash
# Tinker no tenant1
sail artisan tenant:tinker tenant1

# Usar ID do tenant
sail artisan tenant:tinker <uuid>
```

**Adicionar ao CLAUDE.md**:
```markdown
### Tenant Tinker

# Debug no contexto de um tenant
sail artisan tenant:tinker <tenant-id>

# Exemplo
sail artisan tenant:tinker 01HQ...
>>> App\Models\Project::count()
=> 42
```

**Prioridade**: Baixa (apenas documentacao)
**Esforco**: Muito Baixo

---

## Plano de Implementacao

### Fase 1: Quick Wins (Esta Semana)

1. **Documentar parallel migrations** - 15 min
2. **Documentar tenant:tinker** - 15 min
3. **Habilitar tenant resolver caching** - 2 horas
4. **Implementar ScopeSessions middleware** - 2 horas

### Fase 2: Performance (Proxima Semana)

5. **Testar parallel migrations em producao** - 1 hora
6. **Benchmark resolver caching** - 1 hora
7. **Monitorar hit rate do cache** - Ongoing

### Fase 3: Features Avancadas (Futuro)

8. **Pending Tenants Pool** - 4-6 horas
9. **BroadcastChannelPrefixBootstrapper** - 1 hora
10. **MailConfigBootstrapper** (se necessario) - 3-4 horas

---

## Verificacao de Compatibilidade

### Testes Existentes

Todos os 327 testes devem continuar passando apos cada implementacao.

```bash
sail artisan test
```

### Checklist Pre-Deploy

- [ ] ScopeSessions middleware testado em tenant domain
- [ ] Cache de resolver nao causa stale data
- [ ] Parallel migrations funcionam com PostgreSQL
- [ ] Pending tenants pool mantido pelo scheduler

---

## Referencias

- [Stancl/Tenancy v4 Docs](https://v4.tenancyforlaravel.com)
- [GitHub Repository](https://github.com/stancl/tenancy)
- [Upgrade Guide v3 -> v4](https://v4.tenancyforlaravel.com/version-4)

---

*Documento gerado em: Dezembro 2024*
*Projeto: React Starter Kit (Multi-tenant SaaS)*
