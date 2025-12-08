# User Sync Federation - Implementation Log

> **Status**: ✅ Completo (Todas as 6 fases implementadas)
> **Iniciado**: 2025-12-08
> **Branch**: `tenant-multi-db-user`
> **Documento de Arquitetura**: [USER-SYNC-FEDERATION-PLAN.md](./USER-SYNC-FEDERATION-PLAN.md)

---

## Resumo do Progresso

| Fase | Status | Data Conclusão |
|------|--------|----------------|
| Fase 1: Foundation | ✅ Completa | 2025-12-08 |
| Fase 2: Core Service Layer | ✅ Completa | 2025-12-08 |
| Fase 3: Jobs e Events | ✅ Completa | 2025-12-08 |
| Fase 4: API Controllers | ✅ Completa | 2025-12-08 |
| Fase 5: Frontend | ✅ Completa | 2025-12-08 |
| Fase 6: Testes | ✅ Completa | 2025-12-08 |

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

## Fase 2: Core Service Layer ✅

**Data**: 2025-12-08
**Objetivo**: Criar services com lógica de negócio

### Services Criados

| Arquivo | Descrição |
|---------|-----------|
| `app/Services/Central/FederationService.php` | Gestão de grupos de federação |
| `app/Services/Central/FederationSyncService.php` | Coordenação de sync entre tenants |
| `app/Services/Central/FederationAuditService.php` | Logging de operações |
| `app/Services/Central/FederationCacheService.php` | Cache e debounce |
| `app/Services/Tenant/FederationService.php` | Operações do contexto tenant |

### Exceptions Criadas

| Arquivo | Descrição |
|---------|-----------|
| `app/Exceptions/Central/FederationException.php` | Exceções de federação (central) |
| `app/Exceptions/Tenant/FederationException.php` | Herda do central (tenant-specific) |

### FederationService (Central)

**Responsabilidades**:
- Criar/editar/deletar grupos de federação
- Adicionar/remover tenants de grupos
- Gerenciar FederatedUsers no nível central
- Resolver conflitos

**Métodos principais**:
```php
createGroup(name, masterTenant, description, syncStrategy, settings)
updateGroup(group, data)
deleteGroup(group)
addTenantToGroup(group, tenant, settings)
removeTenantFromGroup(group, tenant)
getTenantGroup(tenant)
createFederatedUser(group, email, syncedData, masterTenant, masterTenantUserId)
updateFederatedUserData(federatedUser, newData, sourceTenant)
resolveConflict(conflict, resolvedValue, resolverId, resolution, notes)
getGroupStats(group)
getOverallStats()
```

### FederationSyncService

**Responsabilidades**:
- Sincronizar dados de usuários entre tenants
- Propagar mudanças de senha e 2FA
- Bulk sync para novos tenants
- Retry de syncs falhos

**Métodos principais**:
```php
syncUserToAllTenants(federatedUser, fields, excludeTenantId)
syncUserToTenant(federatedUser, tenant, fields)
syncPasswordToAllTenants(federatedUser, hashedPassword, sourceTenantId)
syncTwoFactorToAllTenants(federatedUser, twoFactorData, sourceTenantId)
syncAllUsersToNewTenant(group, tenant)
ensureUserExistsInTenant(federatedUser, tenant)
retryFailedSyncs(federatedUser)
getFailedSyncsForGroup(group)
validateSyncPermission(group, sourceTenant)
canTenantInitiateSync(group, tenant)
```

### FederationAuditService

**Responsabilidades**:
- Logging de todas operações via `FederationSyncLog`

**Métodos**:
```php
logGroupCreated(group, masterTenant)
logGroupUpdated(group, oldData, newData)
logGroupDeleted(group)
logTenantJoined(group, tenant)
logTenantLeft(group, tenant)
logUserCreated(group, federatedUser, sourceTenant)
logUserUpdated(group, federatedUser, sourceTenant, oldData, newData)
logUserDeleted(group, federatedUser, sourceTenant)
logPasswordChanged(group, federatedUser, sourceTenant)
logTwoFactorChanged(group, federatedUser, sourceTenant, enabled)
logConflictDetected(group, federatedUser, conflictingFields)
logConflictResolved(group, conflict, resolverId)
logSyncFailed(group, federatedUser, sourceTenant, targetTenant, errorMessage)
logSyncRetry(group, federatedUser, targetTenant, attemptNumber)
logUserSyncedToTenant(group, federatedUser, sourceTenant, targetTenant)
```

### FederationCacheService

**Responsabilidades**:
- Cache de grupos e tenants (TTL 1 hora)
- Debounce de operações de sync

**Cache keys**:
- `federation:tenant_group:{tenantId}` - Grupo do tenant
- `federation:group_tenants:{groupId}` - Tenants do grupo
- `federation:user_links:{federatedUserId}` - Links do usuário
- `federation:user_email:{hash}` - Usuário por email/grupo

**Métodos de cache**:
```php
getTenantGroup(tenantId)
invalidateTenant(tenantId)
getGroupTenantIds(groupId)
invalidateGroup(groupId)
getUserLinkedTenantIds(federatedUserId)
invalidateUserLinks(federatedUserId)
getUserByEmailInGroup(email, groupId)
invalidateUserByEmail(email, groupId)
invalidateAllForGroup(group)
clearAll()
```

**Métodos de debounce**:
```php
shouldSync(federatedUserId, field, debounceSeconds = 5)
syncCompleted(federatedUserId, field)
```

### FederationService (Tenant)

**Responsabilidades**:
- Operações de federação no contexto do tenant
- Federar/desfederar usuários locais
- Sincronizar dados com federação
- Auto-criar usuários no login

**Métodos principais**:
```php
getCurrentGroup()
isFederated()
isMaster()
getMembership()
getFederatedUsers()
getLocalOnlyUsers()
isUserFederated(user)
getUserFederationInfo(user)
federateUser(user)
unfederateUser(user)
syncUserToFederation(user)
syncPasswordToFederation(user, hashedPassword)
syncTwoFactorToFederation(user)
applyFederationDataToUser(user)
findOrCreateFromFederation(email)
createLocalUserFromFederation(federatedUser)
getStats()
```

### Modificações em Models

| Model | Modificação |
|-------|-------------|
| `FederatedUser` | Adicionado `syncedLinks()` scope, ajustado `activeLinks()` para excluir disabled |
| `FederatedUserLink` | Adicionado `CREATED_VIA_BULK_SYNC`, `incrementSyncAttempts()` |

### Verificação

```bash
# Migrations e seeders funcionando
./vendor/bin/sail artisan migrate:fresh --seed
# ✅ Federation seeded successfully!
```

---

## Fase 3: Jobs e Events ✅

**Data**: 2025-12-08
**Objetivo**: Criar jobs e events para sync assíncrono

### Events Criados

| Arquivo | Descrição |
|---------|-----------|
| `app/Events/Central/Federation/FederatedUserCreated.php` | Novo usuário federado criado |
| `app/Events/Central/Federation/FederatedUserUpdated.php` | Dados de perfil atualizados |
| `app/Events/Central/Federation/FederatedUserPasswordChanged.php` | Senha alterada |
| `app/Events/Central/Federation/FederatedUserTwoFactorChanged.php` | 2FA habilitado/desabilitado |
| `app/Events/Central/Federation/TenantJoinedFederation.php` | Tenant entrou no grupo |
| `app/Events/Central/Federation/TenantLeftFederation.php` | Tenant saiu do grupo |

### Jobs Criados

| Arquivo | Descrição |
|---------|-----------|
| `app/Jobs/Central/Federation/SyncUserToFederatedTenantsJob.php` | Sync perfil para todos tenants |
| `app/Jobs/Central/Federation/PropagatePasswordChangeJob.php` | Propaga mudança de senha |
| `app/Jobs/Central/Federation/PropagateTwoFactorChangeJob.php` | Propaga mudança de 2FA |
| `app/Jobs/Central/Federation/SyncAllUsersToTenantJob.php` | Bulk sync para novo tenant |
| `app/Jobs/Central/Federation/RetryFailedSyncsJob.php` | Retry de syncs falhos |

**Características dos Jobs**:
- Queue: `federation` (dedicada)
- Tries: 3 com backoff exponencial (30s, 60s, 120s)
- Tags para Telescope (federation, grupo, usuário)
- Logging detalhado de sucesso/falha

### Listeners Criados

| Arquivo | Evento | Ação |
|---------|--------|------|
| `SyncNewFederatedUser.php` | `FederatedUserCreated` | Dispatch `SyncUserToFederatedTenantsJob` |
| `SyncUpdatedFederatedUser.php` | `FederatedUserUpdated` | Dispatch `SyncUserToFederatedTenantsJob` |
| `PropagatePasswordChange.php` | `FederatedUserPasswordChanged` | Dispatch `PropagatePasswordChangeJob` |
| `PropagateTwoFactorChange.php` | `FederatedUserTwoFactorChanged` | Dispatch `PropagateTwoFactorChangeJob` |
| `SyncUsersToNewTenant.php` | `TenantJoinedFederation` | Dispatch `SyncAllUsersToTenantJob` |

### Event Registration

Eventos registrados em `app/Providers/AppServiceProvider.php`:

```php
// Federation Event Listeners
Event::listen(FederatedUserCreated::class, SyncNewFederatedUser::class);
Event::listen(FederatedUserUpdated::class, SyncUpdatedFederatedUser::class);
Event::listen(FederatedUserPasswordChanged::class, PropagatePasswordChange::class);
Event::listen(FederatedUserTwoFactorChanged::class, PropagateTwoFactorChange::class);
Event::listen(TenantJoinedFederation::class, SyncUsersToNewTenant::class);
```

### Fluxo de Sync

```
1. Usuário altera senha em tenant1
   ↓
2. TenantUserObserver detecta mudança
   ↓
3. FederationService (Tenant) verifica se é federado
   ↓
4. Dispara FederatedUserPasswordChanged event
   ↓
5. PropagatePasswordChange listener captura
   ↓
6. PropagatePasswordChangeJob entra na fila 'federation'
   ↓
7. Worker processa job, sincroniza com tenant2, tenant3...
   ↓
8. FederationSyncLog registra resultado
```

### Verificação

```bash
# Migrations e seeders funcionando
./vendor/bin/sail artisan migrate:fresh --seed
# ✅ Federation seeded successfully!
```

---

## Fase 4: API Controllers ✅

**Data**: 2025-12-08
**Objetivo**: Criar controllers e rotas

### Form Requests Criados

| Arquivo | Descrição |
|---------|-----------|
| `app/Http/Requests/Central/StoreFederationGroupRequest.php` | Criar grupo |
| `app/Http/Requests/Central/UpdateFederationGroupRequest.php` | Atualizar grupo |
| `app/Http/Requests/Central/AddTenantToGroupRequest.php` | Adicionar tenant |
| `app/Http/Requests/Central/ResolveConflictRequest.php` | Resolver conflito |
| `app/Http/Requests/Tenant/FederateUserRequest.php` | Federar usuário local |

### API Resources Criados

| Arquivo | Descrição |
|---------|-----------|
| `app/Http/Resources/Central/FederationGroupResource.php` | Listagem de grupos |
| `app/Http/Resources/Central/FederationGroupDetailResource.php` | Detalhes do grupo |
| `app/Http/Resources/Central/FederatedUserResource.php` | Listagem de usuários |
| `app/Http/Resources/Central/FederatedUserDetailResource.php` | Detalhes do usuário |
| `app/Http/Resources/Central/FederationConflictResource.php` | Conflitos |
| `app/Http/Resources/Tenant/FederationInfoResource.php` | Info do tenant |

### Controllers Criados

| Arquivo | Descrição |
|---------|-----------|
| `app/Http/Controllers/Central/Admin/FederationGroupController.php` | CRUD grupos + sync |
| `app/Http/Controllers/Central/Admin/FederationConflictController.php` | Gerenciar conflitos |
| `app/Http/Controllers/Tenant/Admin/FederationController.php` | Operações tenant |

### Rotas Central (`central.admin.federation.*`)

```
GET    /admin/federation                                index
POST   /admin/federation                                store
GET    /admin/federation/create                         create
GET    /admin/federation/{group}                        show
PUT    /admin/federation/{group}                        update
DELETE /admin/federation/{group}                        destroy
GET    /admin/federation/{group}/edit                   edit
POST   /admin/federation/{group}/tenants                tenants.add
DELETE /admin/federation/{group}/tenants/{tenant}       tenants.remove
GET    /admin/federation/{group}/users/{user}           users.show
POST   /admin/federation/{group}/users/{user}/sync      users.sync
POST   /admin/federation/{group}/retry-sync             retry-sync
GET    /admin/federation/{group}/conflicts              conflicts.index
GET    /admin/federation/{group}/conflicts/{conflict}   conflicts.show
POST   /admin/federation/{group}/conflicts/{conflict}/resolve  conflicts.resolve
POST   /admin/federation/{group}/conflicts/{conflict}/dismiss  conflicts.dismiss
```

### Rotas Tenant (`tenant.admin.settings.federation.*`)

```
GET    /admin/tenant-settings/federation                        index
GET    /admin/tenant-settings/federation/users/{user}           show
POST   /admin/tenant-settings/federation/users/federate         users.federate
DELETE /admin/tenant-settings/federation/users/{user}/unfederate users.unfederate
POST   /admin/tenant-settings/federation/users/{user}/sync      users.sync
```

### Verificação

```bash
# Rotas listadas corretamente
./vendor/bin/sail artisan route:list --name=federation
# 21 rotas encontradas
```

---

## Fase 5: Frontend ✅

**Data**: 2025-12-08
**Objetivo**: Criar páginas React/Inertia

### Páginas Criadas (Central Admin)

| Arquivo | Descrição |
|---------|-----------|
| `resources/js/pages/central/admin/federation/index.tsx` | Listagem de grupos de federação |
| `resources/js/pages/central/admin/federation/show.tsx` | Detalhes do grupo com tabs (tenants, users, conflicts) |
| `resources/js/pages/central/admin/federation/create.tsx` | Criar novo grupo |
| `resources/js/pages/central/admin/federation/edit.tsx` | Editar grupo existente |
| `resources/js/pages/central/admin/federation/conflicts.tsx` | Gerenciar conflitos de dados |
| `resources/js/pages/central/admin/federation/components/federation-group-form.tsx` | Formulário reutilizável |

### Páginas Criadas (Tenant Admin)

| Arquivo | Descrição |
|---------|-----------|
| `resources/js/pages/tenant/admin/settings/federation.tsx` | Status da federação no tenant |

### Componentes Criados

| Arquivo | Descrição |
|---------|-----------|
| `resources/js/components/shared/federated-user-badge.tsx` | Badge indicando usuário federado |

### Traduções Adicionadas

| Arquivo | Chaves Adicionadas |
|---------|-------------------|
| `lang/en.json` | ~150 chaves para `admin.federation.*`, `tenant.federation.*`, `flash.federation.*`, `components.federated_badge.*` |

### Funcionalidades das Páginas

**Central Admin - Index**:
- Grid de cards com grupos de federação
- Badges para sync strategy (master_wins, last_write_wins, manual_review)
- Contagem de tenants e usuários federados
- Botões para view, edit, delete

**Central Admin - Show**:
- Stats cards (tenants, users, sync strategy, conflicts)
- Tabs: Tenants, Users, Conflicts
- Adicionar/remover tenants
- Sync individual de usuários
- Retry de syncs falhos

**Central Admin - Create/Edit**:
- Formulário com campos: name, description, master_tenant, sync_strategy
- Toggle settings: sync_password, sync_profile, sync_two_factor, sync_roles
- Radio buttons para sync strategy com descrições

**Central Admin - Conflicts**:
- Lista de conflitos pendentes
- Dialog para resolver conflitos (source, target, custom value)
- Histórico de conflitos resolvidos

**Tenant Admin - Federation**:
- Status se tenant está ou não federado
- Informações do grupo e membership
- Lista de usuários federados e locais
- Federar/desfederar usuários locais
- Sync manual de usuários

---

## Fase 6: Testes ✅

**Data**: 2025-12-08
**Objetivo**: Cobertura de testes para todas as funcionalidades de federação

### Testes Criados (Central Admin)

| Arquivo | Descrição | Nº Testes |
|---------|-----------|-----------|
| `tests/Feature/Central/FederationGroupTest.php` | CRUD de grupos de federação | ~25 |
| `tests/Feature/Central/FederatedUserSyncTest.php` | Sincronização de usuários federados | ~20 |
| `tests/Feature/Central/FederationPasswordSyncTest.php` | Sincronização de senha e 2FA | ~18 |
| `tests/Feature/Central/FederationConflictTest.php` | Gerenciamento de conflitos | ~25 |

### Testes Criados (Tenant)

| Arquivo | Descrição | Nº Testes |
|---------|-----------|-----------|
| `tests/Feature/Tenant/TenantFederationTest.php` | Operações de federação do tenant | ~20 |

### Cobertura de Testes

**FederationGroupTest**:
- ✅ Index page rendering e permissions
- ✅ Create page e validation
- ✅ Store group com estratégias diferentes
- ✅ Show page details
- ✅ Edit e Update group
- ✅ Delete group
- ✅ Add/Remove tenants
- ✅ Tenant não pode ser adicionado em múltiplos grupos
- ✅ Master tenant não pode ser removido
- ✅ Sync strategies (master_wins, last_write_wins, manual_review)
- ✅ Custom settings e sync fields
- ✅ Active scope

**FederatedUserSyncTest**:
- ✅ Criar FederatedUser com synced_data
- ✅ Normalização de email (lowercase)
- ✅ Criar e gerenciar links para tenants
- ✅ Atualizar synced_data (single e multiple fields)
- ✅ Link status (synced, pending, failed, disabled)
- ✅ shouldRetry() logic
- ✅ Find user by email (case insensitive)
- ✅ Find user by tenant user ID
- ✅ Active/suspended status scopes
- ✅ Link scopes (synced, pending, failed)

**FederationPasswordSyncTest**:
- ✅ Password hash storage e update
- ✅ Password sync service
- ✅ Exclude source tenant from sync
- ✅ password_changed_at timestamp
- ✅ Sync version increment
- ✅ Master wins strategy validation
- ✅ Last write wins strategy
- ✅ Manual review strategy
- ✅ Validation (inactive group, non-member, sync disabled)
- ✅ 2FA sync (enable/disable)

**TenantFederationTest**:
- ✅ Federation page rendering
- ✅ Correct status display (is_federated, is_master)
- ✅ Permission requirements
- ✅ getCurrentGroup() e isFederated()
- ✅ isMaster() e getMembership()
- ✅ Federate local user
- ✅ Unfederate user
- ✅ Cannot unfederate master user
- ✅ Get federated/local-only users
- ✅ Sync user applies federation data
- ✅ getUserFederationInfo()
- ✅ Statistics
- ✅ Auto-create on login
- ✅ Sync to federation (master only with master_wins)

**FederationConflictTest**:
- ✅ Conflict model CRUD
- ✅ Add conflicting values from multiple tenants
- ✅ Track involved tenants
- ✅ Resolve conflict with value
- ✅ Dismiss conflict
- ✅ isPending() e isResolved()
- ✅ Scopes (pending, resolved, forField, forUser)
- ✅ findOrCreatePending()
- ✅ Controller: index, show, resolve, dismiss
- ✅ Permission requirements
- ✅ Service: create conflict, get pending, resolve

### Verificação

```bash
# Rodar todos os testes de federação
sail artisan test --filter=Federation

# Rodar testes específicos
sail artisan test --filter=FederationGroupTest
sail artisan test --filter=FederatedUserSyncTest
sail artisan test --filter=FederationPasswordSyncTest
sail artisan test --filter=TenantFederationTest
sail artisan test --filter=FederationConflictTest
```

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
| 2025-12-08 | 2 | Core Service Layer - 5 services + 2 exceptions criados |
| 2025-12-08 | 3 | Jobs e Events - 6 events, 5 jobs, 5 listeners criados |
| 2025-12-08 | 4 | API Controllers - 3 controllers, 5 form requests, 6 resources, 21 routes |
| 2025-12-08 | 5 | Frontend - 7 páginas React, 1 componente, ~150 traduções |
