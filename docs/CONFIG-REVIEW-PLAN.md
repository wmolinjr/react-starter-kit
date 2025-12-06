# Config Review & TenantConfigBootstrapper Integration Plan

> **Status:** Fase 1 ✅ | Fase 2 Backend ✅ | Fase 2 Frontend ⏳
> **Data:** 2025-12-06
> **Prioridade:** Alta para bugs, Média para melhorias

## Sumário Executivo

Este documento apresenta uma análise completa de todos os arquivos de configuração do projeto, identificando problemas, avaliando oportunidades de integração com TenantConfigBootstrapper e propondo um plano de ação estruturado.

### Estado Atual

**TenantConfigBootstrapper Já Implementado:**
- Habilitado em `config/tenancy.php` (linha 139)
- Configurado em `TenancyServiceProvider.php` (linha 153)
- `TenantConfigKey` enum define mapeamentos para: locale, timezone, mail_from_address, mail_from_name, currency, currency_locale
- Testes existem em `tests/Feature/TenantConfigBootstrapperTest.php`
- Controller e Service de UI totalmente implementados

---

## Problemas Encontrados

### 1. `config/queue.php` - CRÍTICO

**Problema:** Database padrão para batching e failed jobs usa 'sqlite' ao invés de 'central'

```php
// Linha 106 - ERRADO
'batching' => [
    'database' => env('DB_CONNECTION', 'sqlite'),  // Deveria ser 'central'
    'table' => 'job_batches',
],

// Linha 125 - ERRADO
'failed' => [
    'driver' => env('QUEUE_FAILED_DRIVER', 'database-uuids'),
    'database' => env('DB_CONNECTION', 'sqlite'),  // Deveria ser 'central'
    'table' => 'failed_jobs',
],
```

**Correção:**
```php
'batching' => [
    'database' => env('DB_CONNECTION', 'central'),
    'table' => 'job_batches',
],

'failed' => [
    'driver' => env('QUEUE_FAILED_DRIVER', 'database-uuids'),
    'database' => env('DB_CONNECTION', 'central'),
    'table' => 'failed_jobs',
],
```

---

### 2. `config/pennant.php` - CRÍTICO

**Problemas:**
1. Model Tenant com namespace incorreto
2. Database connection não explícita

```php
// Linha 56 - ERRADO
'scope' => \App\Models\Tenant::class,  // Namespace incorreto

// Linha 39 - Deveria ser explícito
'connection' => null,  // Deveria ser 'central'
```

**Correção:**
```php
'stores' => [
    'database' => [
        'driver' => 'database',
        'connection' => 'central',  // Explicitamente central
        'table' => 'features',
    ],
],

'scope' => \App\Models\Central\Tenant::class,  // Namespace correto
```

---

### 3. `config/telescope.php` - MÉDIO

**Problema:** Storage connection não explícita para multi-tenancy

```php
// Linha 62-63 - Implícito
'storage' => [
    'database' => [
        'connection' => env('DB_CONNECTION', 'central'),
        'chunk' => 1000,
    ],
],
```

**Correção:**
```php
'storage' => [
    'database' => [
        'connection' => 'central',  // Sempre central para Telescope
        'chunk' => 1000,
    ],
],
```

---

### 4. `config/filesystems.php` - MÉDIO

**Problema:** Disks `tenant_uploads` e `tenant_s3` avaliam `tenancy()->initialized` no tempo de carregamento do config, não em runtime.

```php
// Linhas 64-84 - QUEBRADO
'tenant_uploads' => [
    'driver' => 'local',
    'root' => tenancy()->initialized
        ? storage_path('app/tenants/' . tenant()->id . '/uploads')
        : storage_path('app/central/uploads'),
    'visibility' => 'private',
],
```

**Correção:** Remover esses disks - `FilesystemTenancyBootstrapper` já lida com isolamento de paths automaticamente para discos `local` e `public`.

---

## Análise por Arquivo de Config

### Configs Bem Configurados (Sem Alterações Necessárias)

| Config | Status | Notas |
|--------|--------|-------|
| `session.php` | ✅ OK | Domain vazio, same_site=lax, driver=database |
| `cache.php` | ✅ OK | CacheTenancyBootstrapper lida com prefixing |
| `database.php` | ✅ OK | Connections central/tenant/testing corretos |
| `tenancy.php` | ✅ OK | Todos bootstrappers necessários habilitados |
| `fortify.php` | ✅ OK | Guard=tenant, middleware correto |
| `permission.php` | ✅ OK | Models em Shared/, cache configurado |
| `cashier.php` | ✅ OK | Integrado com TenantConfigBootstrapper |
| `activitylog.php` | ✅ OK | Model Tenant\Activity, connection respeitada |
| `media-library.php` | ✅ OK | TenantPathGenerator implementado |
| `cors.php` | ✅ OK | Patterns dinâmicos para subdomínios |
| `sanctum.php` | ✅ OK | Guard=['tenant'] |
| `inertia.php` | ✅ OK | Configuração padrão |
| `logging.php` | ✅ OK | FilesystemTenancyBootstrapper lida com paths |

### Configs Parcialmente Integrados

| Config | Status | Notas |
|--------|--------|-------|
| `app.php` | ⚠️ Parcial | locale/timezone integrados, timezone hardcoded |
| `mail.php` | ⚠️ Parcial | from.address/name integrados, SMTP futuro |
| `services.php` | ⚠️ Parcial | Slack webhook poderia ser per-tenant |

---

## TenantConfigBootstrapper - Estado Atual

### Mapeamentos Implementados (TenantConfigKey enum)

| Setting | Laravel Config Key | Status |
|---------|-------------------|--------|
| `locale` | `app.locale` | ✅ Implementado |
| `timezone` | `app.timezone` | ✅ Implementado |
| `mail_from_address` | `mail.from.address` | ✅ Implementado |
| `mail_from_name` | `mail.from.name` | ✅ Implementado |
| `currency` | `cashier.currency` | ✅ Implementado |
| `currency_locale` | `cashier.currency_locale` | ✅ Implementado |

### Adições Propostas

| Setting | Laravel Config Key | Use Case | Prioridade |
|---------|-------------------|----------|------------|
| `app_name` | `app.name` | White-label branding | Média |
| `date_format` | `app.date_format` (novo) | Formatação regional | Baixa |
| `smtp_host` | `mail.mailers.smtp.host` | Enterprise SMTP | Baixa |
| `smtp_port` | `mail.mailers.smtp.port` | Enterprise SMTP | Baixa |
| `smtp_username` | `mail.mailers.smtp.username` | Enterprise SMTP | Baixa |
| `smtp_password` | `mail.mailers.smtp.password` | Enterprise SMTP | Baixa |

---

## Plano de Ação

### Fase 1: Bug Fixes (Alta Prioridade)

| # | Tarefa | Arquivo | Linhas |
|---|--------|---------|--------|
| 1 | Corrigir database batching/failed para 'central' | `config/queue.php` | 106, 125 |
| 2 | Corrigir namespace Tenant e connection | `config/pennant.php` | 39, 56 |
| 3 | Usar connection 'central' explícita | `config/telescope.php` | 62 |
| 4 | Remover disks tenant_uploads/tenant_s3 quebrados | `config/filesystems.php` | 64-84 |

### Fase 2: Melhorias (Média Prioridade)

| # | Tarefa | Arquivo |
|---|--------|---------|
| 5 | Adicionar `APP_TIMEZONE` ao `.env.example` | `.env.example` |
| 6 | Adicionar `app_name` ao TenantConfigKey | `app/Enums/TenantConfigKey.php` |
| 7 | Atualizar migration tenant_settings para app_name | Migration |
| 8 | Atualizar UI de configurações do tenant | Frontend |

### Fase 3: Features Enterprise (Baixa Prioridade - Futuro)

| # | Tarefa | Descrição |
|---|--------|-----------|
| 9 | Criar MailTenancyBootstrapper | SMTP customizado por tenant |
| 10 | Adicionar date/time format ao TenantConfigKey | Preferências regionais |

---

## Checklist de Implementação

### Fase 1: Bug Fixes ✅ CONCLUÍDO (2025-12-06)
- [x] `config/queue.php` - Alterar 'sqlite' para 'central' em batching.database
- [x] `config/queue.php` - Alterar 'sqlite' para 'central' em failed.database
- [x] `config/pennant.php` - Adicionar 'connection' => 'central' em stores.database
- [x] `config/pennant.php` - Corrigir scope para `\App\Models\Central\Tenant::class`
- [x] `config/telescope.php` - Alterar connection para 'central' explícito
- [x] `config/filesystems.php` - Remover disk 'tenant_uploads'
- [x] `config/filesystems.php` - Remover disk 'tenant_s3'
- [x] `app/Models/Tenant/Project.php` - Atualizar useDisk de 'tenant_uploads' para 'public'
- [x] `tests/Feature/MediaLibraryQueueTenancyTest.php` - Atualizar Storage::fake para 'public'
- [x] `docs/MEDIALIBRARY.md` - Atualizar documentação para disk 'public'
- [x] Rodar `sail artisan config:clear`
- [x] Rodar testes para verificar não quebrou nada (420 passed)

### Fase 2: Melhorias (Parcial - 2025-12-06)
- [x] Adicionar `APP_TIMEZONE=UTC` ao `.env.example`
- [x] Adicionar case `app_name` ao enum `TenantConfigKey`
- [x] Atualizar mapeamento (automático via `toStorageConfigMap()`)
- [x] Adicionar traduções `tenant.config.app_name` em en.json e pt_BR.json
- [x] Corrigir namespaces de Exceptions em PlanService e TeamService
- [ ] Atualizar UI de configurações do tenant para incluir app_name (Frontend)
- [ ] Criar campo app_name no formulário de configurações

### Fase 3: Enterprise (Futuro)
- [ ] Documentar feature SMTP customizado
- [ ] Implementar MailTenancyBootstrapper quando necessário

---

## Referências

- [Tenancy v4 - TenantConfigBootstrapper](https://v4.tenancyforlaravel.com/bootstrappers/tenant-config)
- [Tenancy v4 - Bootstrappers](https://v4.tenancyforlaravel.com/bootstrappers)
- [Tenancy v4 - MailTenancyBootstrapper](https://v4.tenancyforlaravel.com/misc)
- `docs/TENANT-CONFIG-PLAN.md` - Plano original de configurações por tenant
- `docs/SESSION-SECURITY.md` - Documentação de segurança de sessões
