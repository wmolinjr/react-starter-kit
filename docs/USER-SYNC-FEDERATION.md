# User Sync Federation

> **Status**: ✅ Implementado
> **Versão**: 1.0.0
> **Última Atualização**: 2025-12-08

## Visão Geral

O **User Sync Federation** permite que usuários sejam sincronizados automaticamente entre múltiplos tenants em uma arquitetura multi-database. Isso é ideal para empresas com múltiplas filiais onde funcionários precisam acessar diferentes sistemas com as mesmas credenciais.

### Casos de Uso

- **Empresa Multi-filial**: Funcionários da matriz acessam sistemas das filiais
- **Franquias**: Gerentes regionais com acesso a múltiplas unidades
- **Grupos Empresariais**: Holdings com subsidiárias que compartilham equipe
- **SSO Simplificado**: Single Sign-On sem infraestrutura SAML/OIDC externa

### Características Principais

| Feature | Descrição |
|---------|-----------|
| **Multi-Database** | Cada tenant mantém banco isolado (LGPD/HIPAA compliant) |
| **Master Tenant** | Um tenant é a "fonte da verdade" para resolução de conflitos |
| **Sync Strategies** | 3 estratégias de resolução: master_wins, last_write_wins, manual_review |
| **Selective Sync** | Escolha quais campos sincronizar (senha, perfil, 2FA, roles) |
| **Auto-Create** | Usuários são criados automaticamente no primeiro login |
| **Conflict Resolution** | Interface para resolver conflitos manualmente |

---

## Arquitetura

### Diagrama de Componentes

```
┌─────────────────────────────────────────────────────────────────────┐
│                        CENTRAL DATABASE                              │
├─────────────────────────────────────────────────────────────────────┤
│  federation_groups          │  federated_users                       │
│  ├── id (UUID)              │  ├── id (UUID)                         │
│  ├── name                   │  ├── federation_group_id               │
│  ├── master_tenant_id       │  ├── global_email                      │
│  ├── sync_strategy          │  ├── synced_data (JSON)                │
│  └── settings (JSON)        │  ├── master_tenant_id                  │
│                             │  └── status                            │
├─────────────────────────────┼────────────────────────────────────────┤
│  federation_group_tenants   │  federated_user_links                  │
│  ├── federation_group_id    │  ├── federated_user_id                 │
│  ├── tenant_id              │  ├── tenant_id                         │
│  ├── sync_enabled           │  ├── tenant_user_id                    │
│  └── settings (JSON)        │  └── sync_status                       │
├─────────────────────────────┼────────────────────────────────────────┤
│  federation_sync_logs       │  federation_conflicts                  │
│  ├── Audit trail            │  ├── Conflitos pendentes               │
│  └── de operações           │  └── para manual_review                │
└─────────────────────────────┴────────────────────────────────────────┘

┌─────────────────────┐  ┌─────────────────────┐  ┌─────────────────────┐
│   TENANT 1 (Master) │  │     TENANT 2        │  │     TENANT 3        │
│   tenant_master     │  │   tenant_branch1    │  │   tenant_branch2    │
├─────────────────────┤  ├─────────────────────┤  ├─────────────────────┤
│  users              │  │  users              │  │  users              │
│  ├── id             │  │  ├── id             │  │  ├── id             │
│  ├── email          │  │  ├── email          │  │  ├── email          │
│  ├── password       │  │  ├── password       │  │  ├── password       │
│  ├── federated_     │  │  ├── federated_     │  │  ├── federated_     │
│  │   user_id ──────────────────────────────────────────────┐         │
│  └── roles (local)  │  │  └── roles (local)  │  │  └── roles (local)  │
└─────────────────────┘  └─────────────────────┘  └─────────────────────┘
                                    │
                         ┌──────────┴──────────┐
                         │   FederatedUser     │
                         │   (Central DB)      │
                         │   Fonte da Verdade  │
                         └─────────────────────┘
```

### Fluxo de Dados

```
1. Usuário altera senha no Tenant 1 (Master)
   │
   ▼
2. Observer detecta alteração em Tenant\User
   │
   ▼
3. TenantFederationService::syncPasswordToFederation()
   │
   ▼
4. FederatedUser.synced_data atualizado no Central DB
   │
   ▼
5. SyncFederatedUserJob dispatched
   │
   ▼
6. Para cada tenant no grupo:
   ├── Tenant 2: User.password atualizado
   └── Tenant 3: User.password atualizado
```

---

## Models

### Central Database Models

| Model | Tabela | Descrição |
|-------|--------|-----------|
| `FederationGroup` | `federation_groups` | Grupo de tenants que sincronizam usuários |
| `FederationGroupTenant` | `federation_group_tenants` | Pivot: membership de tenant no grupo |
| `FederatedUser` | `federated_users` | Registro central do usuário sincronizado |
| `FederatedUserLink` | `federated_user_links` | Link entre FederatedUser e user local |
| `FederationSyncLog` | `federation_sync_logs` | Audit trail de operações |
| `FederationConflict` | `federation_conflicts` | Conflitos para resolução manual |

### Tenant Model Modifications

O model `App\Models\Tenant\User` inclui o trait `HasFederation`:

```php
use App\Models\Tenant\Traits\HasFederation;

class User extends Authenticatable
{
    use HasFederation;

    // Campo adicionado via migration
    protected $fillable = [
        // ...
        'federated_user_id', // UUID, nullable
    ];
}
```

---

## Services

### Central Services

```
app/Services/Central/
├── FederationService.php       # Gerenciamento de grupos e usuários
├── FederationSyncService.php   # Sincronização entre tenants
├── FederationAuditService.php  # Logging de operações
└── FederationCacheService.php  # Cache de grupos e links
```

### Tenant Services

```
app/Services/Tenant/
└── FederationService.php       # Operações do lado do tenant
```

---

## Sync Strategies

### 1. Master Wins (Padrão)

```php
FederationGroup::STRATEGY_MASTER_WINS
```

- Apenas o tenant master pode alterar dados sincronizados
- Alterações em tenants secundários são ignoradas
- **Melhor para**: Hierarquia clara (matriz → filiais)

### 2. Last Write Wins

```php
FederationGroup::STRATEGY_LAST_WRITE_WINS
```

- Qualquer tenant pode alterar dados sincronizados
- A última alteração sempre vence
- **Melhor para**: Tenants com igual autonomia

### 3. Manual Review

```php
FederationGroup::STRATEGY_MANUAL_REVIEW
```

- Alterações conflitantes criam um `FederationConflict`
- Admin central resolve manualmente
- **Melhor para**: Dados críticos que requerem supervisão

---

## Dados Sincronizados

### Campos Sincronizáveis

| Campo | Sync por Padrão | Configurável |
|-------|-----------------|--------------|
| `name` | ✅ Sim | ✅ |
| `email` | ✅ Sim | ✅ |
| `password` | ✅ Sim | ✅ |
| `locale` | ✅ Sim | ✅ |
| `two_factor_secret` | ✅ Sim | ✅ |
| `two_factor_recovery_codes` | ✅ Sim | ✅ |
| `two_factor_confirmed_at` | ✅ Sim | ✅ |
| `roles` | ❌ Não | ✅ |

### Dados NÃO Sincronizados (Sempre Locais)

- Roles e Permissions (cada tenant tem sua própria estrutura)
- Dados de perfil específicos do tenant
- Relacionamentos com outros models do tenant

---

## API Reference

### Controllers

#### Central Admin

```
App\Http\Controllers\Central\Admin\
├── FederationGroupController   # CRUD de grupos
└── FederationConflictController # Gerenciar conflitos
```

#### Tenant Admin

```
App\Http\Controllers\Tenant\Admin\
└── FederationController        # Operações do tenant
```

### Routes

#### Central Admin Routes (`central.admin.federation.*`)

| Method | URI | Name | Descrição |
|--------|-----|------|-----------|
| GET | `/admin/federation` | `index` | Listar grupos |
| GET | `/admin/federation/create` | `create` | Form criar grupo |
| POST | `/admin/federation` | `store` | Criar grupo |
| GET | `/admin/federation/{group}` | `show` | Ver grupo |
| GET | `/admin/federation/{group}/edit` | `edit` | Form editar |
| PUT | `/admin/federation/{group}` | `update` | Atualizar grupo |
| DELETE | `/admin/federation/{group}` | `destroy` | Deletar grupo |
| POST | `/admin/federation/{group}/tenants` | `tenants.add` | Adicionar tenant |
| DELETE | `/admin/federation/{group}/tenants/{tenant}` | `tenants.remove` | Remover tenant |
| GET | `/admin/federation/{group}/users/{user}` | `users.show` | Ver usuário |
| POST | `/admin/federation/{group}/users/{user}/sync` | `users.sync` | Sync usuário |
| POST | `/admin/federation/{group}/retry-sync` | `retry-sync` | Retry syncs falhos |
| GET | `/admin/federation/{group}/conflicts` | `conflicts.index` | Listar conflitos |
| GET | `/admin/federation/{group}/conflicts/{conflict}` | `conflicts.show` | Ver conflito |
| POST | `/admin/federation/{group}/conflicts/{conflict}/resolve` | `conflicts.resolve` | Resolver |
| POST | `/admin/federation/{group}/conflicts/{conflict}/dismiss` | `conflicts.dismiss` | Ignorar |

#### Tenant Admin Routes (`tenant.admin.settings.federation.*`)

| Method | URI | Name | Descrição |
|--------|-----|------|-----------|
| GET | `/admin/tenant-settings/federation` | `index` | Status federação |
| GET | `/admin/tenant-settings/federation/users/{user}` | `show` | Info usuário |
| POST | `/admin/tenant-settings/federation/users/federate` | `users.federate` | Federar usuário |
| DELETE | `/admin/tenant-settings/federation/users/{user}/unfederate` | `users.unfederate` | Desfederar |
| POST | `/admin/tenant-settings/federation/users/{user}/sync` | `users.sync` | Sync manual |

---

## Permissions

### Central Permissions

```php
// app/Enums/CentralPermission.php
case FEDERATION_VIEW = 'federation:view';
case FEDERATION_CREATE = 'federation:create';
case FEDERATION_EDIT = 'federation:edit';
case FEDERATION_DELETE = 'federation:delete';
case FEDERATION_MANAGE_CONFLICTS = 'federation:manageConflicts';
```

### Tenant Permissions

```php
// app/Enums/TenantPermission.php
case FEDERATION_VIEW = 'federation:view';
case FEDERATION_MANAGE = 'federation:manage';
case FEDERATION_INVITE = 'federation:invite';
case FEDERATION_LEAVE = 'federation:leave';
```

---

## Usage Examples

### Criar um Grupo de Federação

```php
use App\Services\Central\FederationService;
use App\Models\Central\Tenant;
use App\Models\Central\FederationGroup;

$service = app(FederationService::class);

$masterTenant = Tenant::find($masterId);

$group = $service->createGroup(
    name: 'Empresa ABC - Federação',
    masterTenant: $masterTenant,
    description: 'Sincroniza usuários entre matriz e filiais',
    syncStrategy: FederationGroup::STRATEGY_MASTER_WINS,
    settings: [
        'sync_fields' => ['name', 'email', 'password', 'locale'],
        'auto_create_on_login' => true,
    ]
);
```

### Adicionar Tenant ao Grupo

```php
$branchTenant = Tenant::find($branchId);

$service->addTenantToGroup($group, $branchTenant, [
    'default_role' => 'member',
    'auto_accept_users' => true,
]);
```

### Federar um Usuário Existente (do Tenant)

```php
use App\Services\Tenant\FederationService;

// No contexto do tenant
$tenantService = app(FederationService::class);

$localUser = User::find($userId);
$federatedUser = $tenantService->federateUser($localUser);
```

### Verificar se Usuário é Federado

```php
// No model Tenant\User (via HasFederation trait)
if ($user->isFederated()) {
    $group = $user->getFederationGroup();
    $isMaster = $user->isMasterUser();
}
```

### Sincronizar Alteração de Senha

```php
// Automático via Observer, ou manual:
$tenantService->syncPasswordToFederation($user, $hashedPassword);
```

### Resolver um Conflito

```php
use App\Services\Central\FederationService;
use App\Models\Central\FederationConflict;

$service = app(FederationService::class);

$conflict = FederationConflict::find($conflictId);

$service->resolveConflict(
    conflict: $conflict,
    resolvedValue: 'John Doe',
    resolverId: auth()->id(),
    resolution: FederationConflict::RESOLUTION_MANUAL,
    notes: 'Valor correto confirmado com RH'
);
```

---

## Frontend Components

### Páginas Central Admin

| Componente | Rota | Descrição |
|------------|------|-----------|
| `central/admin/federation/index.tsx` | `/admin/federation` | Grid de grupos |
| `central/admin/federation/show.tsx` | `/admin/federation/{id}` | Detalhes com tabs |
| `central/admin/federation/create.tsx` | `/admin/federation/create` | Criar grupo |
| `central/admin/federation/edit.tsx` | `/admin/federation/{id}/edit` | Editar grupo |
| `central/admin/federation/conflicts.tsx` | `/admin/federation/{id}/conflicts` | Conflitos |

### Páginas Tenant Admin

| Componente | Rota | Descrição |
|------------|------|-----------|
| `tenant/admin/settings/federation.tsx` | `/admin/tenant-settings/federation` | Status e ações |

### Componentes Compartilhados

| Componente | Descrição |
|------------|-----------|
| `components/shared/federated-user-badge.tsx` | Badge indicando usuário federado |
| `federation/components/federation-group-form.tsx` | Formulário reutilizável |

---

## Jobs e Events

### Jobs

```
app/Jobs/Central/Federation/
├── SyncFederatedUserJob.php       # Sync usuário para todos tenants
├── SyncPasswordToTenantsJob.php   # Sync específico de senha
├── SyncTwoFactorToTenantsJob.php  # Sync de 2FA
├── RetryFailedSyncsJob.php        # Retry de syncs falhos
└── SyncAllUsersToNewTenantJob.php # Sync bulk ao adicionar tenant
```

### Events

```
app/Events/Central/Federation/
├── FederatedUserCreated.php
├── FederatedUserUpdated.php
├── FederationGroupCreated.php
├── FederationGroupUpdated.php
├── TenantJoinedFederation.php
├── TenantLeftFederation.php
├── FederationSyncCompleted.php
└── FederationConflictDetected.php
```

---

## Testing

### Rodar Testes

```bash
# Todos os testes de federação
sail artisan test --filter=Federation

# Testes específicos
sail artisan test --filter=FederationGroupTest
sail artisan test --filter=FederatedUserSyncTest
sail artisan test --filter=FederationPasswordSyncTest
sail artisan test --filter=TenantFederationTest
sail artisan test --filter=FederationConflictTest
```

### Arquivos de Teste

```
tests/Feature/Central/
├── FederationGroupTest.php         # CRUD grupos (~25 testes)
├── FederatedUserSyncTest.php       # Sync usuários (~20 testes)
├── FederationPasswordSyncTest.php  # Sync senha/2FA (~18 testes)
└── FederationConflictTest.php      # Conflitos (~25 testes)

tests/Feature/Tenant/
└── TenantFederationTest.php        # Operações tenant (~20 testes)
```

---

## Troubleshooting

### Usuário não sincroniza

1. Verificar se tenant está no grupo: `$group->hasTenant($tenant)`
2. Verificar se sync está habilitado: `$membership->sync_enabled`
3. Verificar se grupo está ativo: `$group->is_active`
4. Verificar sync strategy permite alteração

### Conflito não aparece

- Conflitos só são criados com strategy `manual_review`
- Com `master_wins`, alterações de não-master são ignoradas silenciosamente

### Password não sincroniza

1. Verificar se `sync_password` está nas settings
2. Verificar se é o tenant master (com `master_wins`)
3. Verificar logs: `FederationSyncLog::where('operation', 'password_sync')`

### Auto-create não funciona

1. Verificar setting: `$group->shouldAutoCreateOnLogin()`
2. Verificar se email existe em FederatedUser
3. Verificar se tenant tem role padrão configurado

---

## Migrations

### Rodar Migrations

```bash
# Central database
sail artisan migrate

# Todas as tenant databases
sail artisan tenants:migrate
```

### Arquivos

```
database/migrations/
└── 2025_12_08_000001_create_federation_tables.php  # Central tables

database/migrations/tenant/
└── 2025_01_01_000000_create_users_table.php        # Modificado: +federated_user_id
```

---

## Security Considerations

### Isolamento de Dados

- Cada tenant mantém banco de dados separado
- FederatedUser no central apenas referencia IDs locais
- Dados sensíveis (password hash) nunca expostos via API

### Permissions

- Todas as rotas protegidas por permissions específicas
- Central admin gerencia grupos, tenant admin gerencia usuários locais
- Usuário master não pode ser desfederado pelo tenant

### Audit Trail

- Todas operações logadas em `federation_sync_logs`
- Conflitos mantêm histórico de valores e resoluções
- Timestamps de última sincronização por link

---

## Related Documentation

- [USER-SYNC-FEDERATION-PLAN.md](./USER-SYNC-FEDERATION-PLAN.md) - Documento de arquitetura original
- [USER-SYNC-FEDERATION-IMPLEMENTATION-LOG.md](./USER-SYNC-FEDERATION-IMPLEMENTATION-LOG.md) - Log de implementação
- [MULTI-DATABASE-MIGRATION-PLAN.md](./MULTI-DATABASE-MIGRATION-PLAN.md) - Arquitetura multi-database
- [PERMISSIONS.md](./PERMISSIONS.md) - Sistema de permissions
- [SESSION-SECURITY.md](./SESSION-SECURITY.md) - Segurança de sessão multi-tenant
