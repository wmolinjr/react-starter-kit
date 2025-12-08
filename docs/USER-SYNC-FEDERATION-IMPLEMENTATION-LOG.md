# User Sync Federation - Implementation Log

> **Status**: Em Desenvolvimento
> **Iniciado**: 2025-12-08
> **Branch**: `tenant-multi-db-user`
> **Documento de Arquitetura**: [USER-SYNC-FEDERATION-PLAN.md](./USER-SYNC-FEDERATION-PLAN.md)

---

## Resumo do Progresso

| Fase | Status | Data Conclusão |
|------|--------|----------------|
| Fase 1: Foundation | ✅ Completa | 2025-12-08 |
| Fase 2: Core Service Layer | ⏳ Pendente | - |
| Fase 3: Jobs e Events | ⏳ Pendente | - |
| Fase 4: API Controllers | ⏳ Pendente | - |
| Fase 5: Frontend | ⏳ Pendente | - |
| Fase 6: Testes | ⏳ Pendente | - |

---

## Fase 1: Foundation ✅

**Data**: 2025-12-08
**Objetivo**: Criar estrutura base (migrations, models, traits, permissions, seeders)

### Arquivos Criados

#### Migrations

| Arquivo | Descrição |
|---------|-----------|
| `database/migrations/2025_12_08_000001_create_federation_tables.php` | 6 tabelas no banco central |

**Tabelas criadas**:
- `federation_groups` - Grupos de tenants que sincronizam usuários
- `federation_group_tenants` - Pivot: quais tenants pertencem a cada grupo
- `federated_users` - Registro central de usuários sincronizados
- `federated_user_links` - Links entre FederatedUser e users locais dos tenants
- `federation_sync_logs` - Audit trail de todas operações de sync
- `federation_conflicts` - Conflitos pendentes de resolução

#### Migration de Tenant Modificada

| Arquivo | Modificação |
|---------|-------------|
| `database/migrations/tenant/2025_01_01_000000_create_users_table.php` | Adicionado campo `federated_user_id` (UUID, nullable, indexed) |

### Models Criados

| Arquivo | Descrição |
|---------|-----------|
| `app/Models/Central/FederationGroup.php` | Grupo de federação com master tenant |
| `app/Models/Central/FederationGroupTenant.php` | Pivot model com UUID e casting |
| `app/Models/Central/FederatedUser.php` | Usuário federado (fonte da verdade) |
| `app/Models/Central/FederatedUserLink.php` | Link entre central e tenant user |
| `app/Models/Central/FederationSyncLog.php` | Log de operações de sync |
| `app/Models/Central/FederationConflict.php` | Conflitos de dados |

**Características dos models**:
- Todos usam `HasUuids` trait
- Todos usam `CentralConnection` trait
- Campos JSON com casting para array
- Métodos helper para operações comuns
- Scopes para queries frequentes

### Trait Criado

| Arquivo | Descrição |
|---------|-----------|
| `app/Models/Tenant/Traits/HasFederation.php` | Métodos de federação para Tenant\User |

**Métodos do trait**:
- `isFederated()` - Verifica se usuário é federado
- `getFederatedUser()` - Obtém FederatedUser do banco central
- `getFederationGroup()` - Obtém grupo de federação
- `getFederationLink()` - Obtém link para o tenant atual
- `isMasterUser()` - Verifica se é o usuário master
- `toFederationSyncData()` - Prepara dados para sync
- `applyFederationSyncData()` - Aplica dados sincronizados
- Scopes: `federated()`, `notFederated()`, `inFederationGroup()`

### Model Modificado

| Arquivo | Modificação |
|---------|-------------|
| `app/Models/Tenant/User.php` | Adicionado `use HasFederation` trait e property docblock |

### Permissions Adicionadas

#### CentralPermission.php (+5 permissions)

```php
case FEDERATION_VIEW = 'federation:view';
case FEDERATION_CREATE = 'federation:create';
case FEDERATION_EDIT = 'federation:edit';
case FEDERATION_DELETE = 'federation:delete';
case FEDERATION_MANAGE_CONFLICTS = 'federation:manageConflicts';
```

#### TenantPermission.php (+4 permissions)

```php
case FEDERATION_VIEW = 'federation:view';
case FEDERATION_MANAGE = 'federation:manage';
case FEDERATION_INVITE = 'federation:invite';
case FEDERATION_LEAVE = 'federation:leave';
```

### Seeders

| Arquivo | Descrição |
|---------|-----------|
| `database/seeders/FederationSeeder.php` | Cria dados de teste para federação |
| `database/seeders/DatabaseSeeder.php` | Modificado para incluir FederationSeeder |

**Dados de teste criados**:

```
Federation Group: "ACME Corporation"
├── Master: tenant1.test (Acme Corporation)
│   ├── john@acme.com (owner, LOCAL)
│   └── shared@acme.com (admin, FEDERADO)
│
└── Member: tenant2.test (Startup Inc)
    ├── jane@startup.com (owner, LOCAL)
    └── shared@acme.com (member, FEDERADO)

Usuário Federado:
- Email: shared@acme.com
- Senha: password
- Existe em tenant1.test E tenant2.test
- Mesma senha em ambos (sincronizada)
- Roles diferentes por tenant (admin vs member)
```

### Verificação

```bash
# Migrations executadas com sucesso
./vendor/bin/sail artisan migrate:fresh --seed

# Verificação via tinker
Federation Group: ACME Corporation
Master Tenant: Acme Corporation
Active Tenants: Acme Corporation, Startup Inc

Federated User: shared@acme.com
Status: active
Links count: 2
  - Acme Corporation (status: synced)
  - Startup Inc (status: synced)

# Testes de tenant passando (115 passed)
./vendor/bin/sail artisan test --filter=Tenant
```

### Notas Técnicas

1. **Pivot com UUID**: O Laravel não gera UUID automaticamente em `attach()`. Solução: criar `FederationGroupTenant` como Pivot model com `HasUuids` trait.

2. **JSON em Pivot**: Campos JSON no pivot precisam de `json_encode()` manual no `attach()` ou usar o Pivot model.

3. **CentralConnection**: Todos os models de federação usam `CentralConnection` trait para garantir queries no banco central.

---

## Fase 2: Core Service Layer ⏳

**Status**: Pendente
**Objetivo**: Criar services com lógica de negócio

### Planejado

- [ ] `app/Services/Central/FederationService.php` - Gestão de grupos
- [ ] `app/Services/Tenant/FederationService.php` - Operações do tenant
- [ ] `app/Services/Central/FederationAuditService.php` - Logging
- [ ] `app/Services/Central/FederationCacheService.php` - Cache
- [ ] `app/Services/Central/FederationDebounceService.php` - Debounce de updates

---

## Fase 3: Jobs e Events ⏳

**Status**: Pendente
**Objetivo**: Criar jobs e events para sync assíncrono

### Planejado

- [ ] Events de federação (UserCreated, UserUpdated, PasswordChanged, etc.)
- [ ] `SyncUserToFederatedTenantsJob`
- [ ] `UpdateLinkedUserJob`
- [ ] `CreateUserInTenantJob`
- [ ] `PropagatePasswordChangeJob`
- [ ] `IndexExistingUsersJob`
- [ ] Configurar queue `federation`

---

## Fase 4: API Controllers ⏳

**Status**: Pendente
**Objetivo**: Criar controllers e rotas

### Planejado

- [ ] `FederationGroupController` (Central Admin)
- [ ] `FederationConflictController` (Central Admin)
- [ ] `FederationSettingsController` (Tenant Admin)
- [ ] `FederatedUserController` (Tenant Admin)
- [ ] Form Requests
- [ ] Rotas em `routes/central.php` e `routes/tenant.php`

---

## Fase 5: Frontend ⏳

**Status**: Pendente
**Objetivo**: Criar páginas React/Inertia

### Planejado

- [ ] `resources/js/pages/central/admin/federation/index.tsx`
- [ ] `resources/js/pages/central/admin/federation/show.tsx`
- [ ] `resources/js/pages/tenant/admin/settings/federation.tsx`
- [ ] Componente `FederatedUserBadge`
- [ ] Integrar badge na página de Team

---

## Fase 6: Testes ⏳

**Status**: Pendente
**Objetivo**: Cobertura de testes

### Planejado

- [ ] `tests/Feature/FederationGroupTest.php`
- [ ] `tests/Feature/FederatedUserSyncTest.php`
- [ ] `tests/Feature/FederationPasswordSyncTest.php`
- [ ] `tests/Browser/federation.spec.ts` (Playwright)

---

## Decisões de Design

### 1. UUID em Pivot Tables

**Decisão**: Usar UUID como PK em todas as tabelas, incluindo pivots.

**Motivo**: Consistência com arquitetura existente (DATABASE-IDS.md).

**Implementação**: Criar Pivot model customizado com `HasUuids` trait.

### 2. Sync Strategy Padrão

**Decisão**: `master_wins` como estratégia padrão de resolução de conflitos.

**Motivo**: Simplicidade e previsibilidade para o cenário principal (empresa multi-filial).

### 3. Dados Sincronizados vs Locais

**Sincronizados**:
- name, email, password, locale
- two_factor_secret, two_factor_recovery_codes, two_factor_confirmed_at

**Locais (por tenant)**:
- roles, permissions
- department, employee_id
- custom_settings

**Motivo**: Cada filial pode ter estrutura organizacional diferente.

### 4. federated_user_id no Tenant

**Decisão**: Armazenar `federated_user_id` diretamente na tabela `users` do tenant.

**Motivo**: Performance - evita query ao banco central para verificar se usuário é federado.

---

## Problemas Encontrados e Soluções

### 1. Pivot UUID não gerado automaticamente

**Problema**: `$group->tenants()->attach()` não gera UUID para a PK.

**Solução**: Criar `FederationGroupTenant` extends `Pivot` com `HasUuids` trait.

```php
// Antes (não funciona)
$group->tenants()->attach($tenantId, ['settings' => [...]]);

// Depois (funciona)
FederationGroupTenant::create([
    'federation_group_id' => $group->id,
    'tenant_id' => $tenantId,
    'settings' => [...],
]);
```

### 2. JSON em campos de Pivot

**Problema**: `attach()` não faz cast de arrays para JSON.

**Solução**: Usar Pivot model com `$casts` definidos.

---

## Comandos Úteis

```bash
# Reset completo do banco
./vendor/bin/sail artisan migrate:fresh --seed

# Verificar federação no tinker
./vendor/bin/sail artisan tinker
>>> FederationGroup::with('tenants', 'federatedUsers')->first()

# Verificar usuário federado em tenant
./vendor/bin/sail artisan tinker
>>> Tenant::where('slug', 'acme')->first()->run(fn() => User::where('email', 'shared@acme.com')->first()->isFederated())

# Rodar testes de tenant
./vendor/bin/sail artisan test --filter=Tenant

# Sync de permissions (após adicionar novas)
./vendor/bin/sail artisan permissions:sync
```

---

## Changelog

| Data | Fase | Alterações |
|------|------|------------|
| 2025-12-08 | 1 | Foundation completa - migrations, models, traits, permissions, seeders |
