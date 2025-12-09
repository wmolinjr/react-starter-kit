# User Sync Federation - Plano de Arquitetura

> **Status**: Proposta de Arquitetura
> **Data**: Dezembro 2025
> **Versao**: 1.1
> **Prerequisito**: Opcao C implementada (usuarios apenas no banco do tenant)
> **Ambiente**: Desenvolvimento (sem dados legados, migrations diretas, seeders de teste)

---

## 1. Visao Geral

### 1.1 Conceito

User Sync Federation permite que **grupos de tenants** sincronizem dados de usuarios entre si, mantendo o isolamento de permissoes. Isso resolve o cenario onde uma organizacao possui multiplos tenants (filiais, marcas, produtos) e quer que funcionarios acessem todos com um unico cadastro.

### 1.2 Diagrama da Arquitetura

```
                              CENTRAL DATABASE
    ┌─────────────────────────────────────────────────────────────────────────────┐
    │                                                                             │
    │  ┌─────────────────────┐    ┌────────────────────────────────────────────┐  │
    │  │   federation_groups │    │          federated_users                   │  │
    │  ├─────────────────────┤    ├────────────────────────────────────────────┤  │
    │  │ id                  │───>│ id                                         │  │
    │  │ name                │    │ federation_group_id                        │  │
    │  │ master_tenant_id ───┼────│ global_email (unique per group)            │  │
    │  │ settings            │    │ synced_data (name, avatar, 2FA, etc.)      │  │
    │  │ sync_strategy       │    │ master_tenant_user_id                      │  │
    │  └─────────────────────┘    │ last_synced_at                             │  │
    │           │                 └───────────────┬────────────────────────────┘  │
    │           │                                 │                               │
    │  ┌────────┴───────────────┐   ┌─────────────┴─────────────┐                 │
    │  │federation_group_tenants│   │ federated_user_links      │                 │
    │  ├────────────────────────┤   ├───────────────────────────┤                 │
    │  │ federation_group_id    │   │ federated_user_id         │                 │
    │  │ tenant_id              │   │ tenant_id                 │                 │
    │  │ joined_at              │   │ tenant_user_id            │                 │
    │  │ sync_enabled           │   │ sync_status               │                 │
    │  └────────────────────────┘   │ last_synced_at            │                 │
    │                               └───────────────────────────┘                 │
    │                                                                             │
    └─────────────────────────────────────────────────────────────────────────────┘
                                          │
                                          │ Sync Jobs
                                          │ (assíncrono)
                    ┌─────────────────────┼─────────────────────┐
                    │                     │                     │
                    ▼                     ▼                     ▼
    ┌───────────────────────┐ ┌───────────────────────┐ ┌───────────────────────┐
    │   TENANT DB: ACME-SP  │ │   TENANT DB: ACME-RJ  │ │   TENANT DB: ACME-MG  │
    │   (Master Tenant)     │ │   (Member Tenant)     │ │   (Member Tenant)     │
    ├───────────────────────┤ ├───────────────────────┤ ├───────────────────────┤
    │ users                 │ │ users                 │ │ users                 │
    │ ├─ id: uuid-1         │ │ ├─ id: uuid-2         │ │ ├─ id: uuid-3         │
    │ ├─ email: j@acme.com  │ │ ├─ email: j@acme.com  │ │ ├─ email: j@acme.com  │
    │ ├─ name: "Joao Silva" │ │ ├─ name: "Joao Silva" │ │ ├─ name: "Joao Silva" │
    │ ├─ password: hash_x   │ │ ├─ password: hash_x   │ │ ├─ password: hash_x   │
    │ └─ federated_user_id  │ │ └─ federated_user_id  │ │ └─ federated_user_id  │
    │                       │ │                       │ │                       │
    │ roles (LOCAL)         │ │ roles (LOCAL)         │ │ roles (LOCAL)         │
    │ └─ owner              │ │ └─ member             │ │ └─ admin              │
    └───────────────────────┘ └───────────────────────┘ └───────────────────────┘
```

### 1.3 Conceitos-Chave

| Conceito | Descricao |
|----------|-----------|
| **Federation Group** | Grupo de tenants que sincronizam usuarios. Um tenant pode pertencer a apenas um grupo. |
| **Master Tenant** | Tenant "fonte da verdade" do grupo. Conflitos sao resolvidos com dados do master. |
| **Federated User** | Registro central que representa um usuario sincronizado entre tenants. |
| **Linked User** | Instancia local do usuario em cada tenant do grupo (com roles locais). |
| **Synced Data** | Dados que sao sincronizados: email, name, password, avatar, 2FA settings. |
| **Local Data** | Dados que NAO sao sincronizados: roles, permissions, settings de tenant. |

### 1.4 Cenarios de Uso

#### Cenario 1: Empresa Multi-Filial

```
ACME Corporation
├── ACME-SP (Master) - Matriz em Sao Paulo
├── ACME-RJ (Member) - Filial Rio de Janeiro
└── ACME-MG (Member) - Filial Minas Gerais

Funcionarios:
- Joao Silva: Dono em SP, Admin em RJ, Membro em MG
- Maria Santos: Admin em SP, Admin em RJ, sem acesso em MG
```

**Beneficios**:
- Funcionario altera senha em SP → propaga para RJ e MG
- RH cadastra novo funcionario em SP → aparece automaticamente nas filiais autorizadas
- Cada filial mantém controle sobre roles/permissions locais

#### Cenario 2: White-Label / Franquias

```
Setor3 Platform (Central Admin)
│
├── Federation Group: "Rede de Franquias XYZ"
│   ├── XYZ Franquia 01 (Master)
│   ├── XYZ Franquia 02
│   └── XYZ Franquia 03
│
├── Federation Group: "Holding ABC"
│   ├── Empresa ABC (Master)
│   └── Startup DEF (adquirida)
```

**Beneficios**:
- Cada grupo de franquias tem seu proprio federation
- Fusoes/aquisicoes podem unificar usuarios gradualmente
- Holding gerencia usuarios centralmente

---

## 2. Database Schema

### 2.1 Tabelas no Banco CENTRAL

#### federation_groups

```php
Schema::create('federation_groups', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->string('name');
    $table->text('description')->nullable();
    $table->foreignUuid('master_tenant_id')
        ->constrained('tenants')
        ->cascadeOnDelete();

    // Estrategia de resolucao de conflitos
    $table->enum('sync_strategy', [
        'master_wins',      // Master sempre prevalece
        'last_write_wins',  // Ultima alteracao prevalece
        'manual_review',    // Conflitos vao para fila de revisao
    ])->default('master_wins');

    // Configuracoes do grupo
    $table->json('settings')->nullable();
    // settings = {
    //   "sync_fields": ["name", "email", "password", "avatar", "two_factor"],
    //   "auto_create_on_login": true,
    //   "require_email_verification": false,
    //   "notification_email": "admin@acme.com"
    // }

    $table->boolean('is_active')->default(true);
    $table->timestamps();

    // Indices
    $table->index('master_tenant_id');
    $table->index('is_active');
});
```

#### federation_group_tenants (Pivot)

```php
Schema::create('federation_group_tenants', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignUuid('federation_group_id')
        ->constrained()
        ->cascadeOnDelete();
    $table->foreignUuid('tenant_id')
        ->constrained()
        ->cascadeOnDelete();

    // Status de participacao
    $table->boolean('sync_enabled')->default(true);
    $table->timestamp('joined_at');
    $table->timestamp('left_at')->nullable();

    // Configuracoes especificas do tenant no grupo
    $table->json('settings')->nullable();
    // settings = {
    //   "default_role": "member",
    //   "auto_accept_users": true,
    //   "require_approval": false
    // }

    $table->timestamps();

    // Constraints
    $table->unique(['federation_group_id', 'tenant_id']);
    $table->unique('tenant_id'); // Tenant so pode estar em um grupo

    // Indices
    $table->index('sync_enabled');
});
```

#### federated_users

```php
Schema::create('federated_users', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignUuid('federation_group_id')
        ->constrained()
        ->cascadeOnDelete();

    // Email canonico (fonte da verdade)
    $table->string('global_email');

    // Dados sincronizados (cache para performance)
    $table->json('synced_data');
    // synced_data = {
    //   "name": "Joao Silva",
    //   "avatar_url": "https://...",
    //   "locale": "pt_BR",
    //   "two_factor_enabled": true,
    //   "two_factor_secret": "encrypted...",
    //   "two_factor_recovery_codes": "encrypted...",
    //   "password_hash": "$2y$...",
    //   "password_changed_at": "2024-01-15T10:30:00Z"
    // }

    // Referencia ao usuario master
    $table->foreignUuid('master_tenant_id')
        ->constrained('tenants')
        ->cascadeOnDelete();
    $table->uuid('master_tenant_user_id'); // ID do user no banco do master

    // Controle de sync
    $table->timestamp('last_synced_at')->nullable();
    $table->string('last_sync_source')->nullable(); // tenant_id que originou ultimo sync
    $table->integer('sync_version')->default(1);

    // Status
    $table->enum('status', ['active', 'suspended', 'pending_review'])->default('active');

    $table->timestamps();
    $table->softDeletes();

    // Constraints
    $table->unique(['federation_group_id', 'global_email']);

    // Indices
    $table->index('global_email');
    $table->index('master_tenant_user_id');
    $table->index('status');
});
```

#### federated_user_links

```php
Schema::create('federated_user_links', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignUuid('federated_user_id')
        ->constrained()
        ->cascadeOnDelete();
    $table->foreignUuid('tenant_id')
        ->constrained()
        ->cascadeOnDelete();

    // ID do usuario no banco do tenant
    $table->uuid('tenant_user_id');

    // Status do link
    $table->enum('sync_status', [
        'synced',           // Sincronizado
        'pending_sync',     // Aguardando sync
        'sync_failed',      // Falhou (retry pendente)
        'conflict',         // Conflito detectado
        'disabled',         // Sync desabilitado manualmente
    ])->default('synced');

    // Controle de sync
    $table->timestamp('last_synced_at')->nullable();
    $table->integer('sync_attempts')->default(0);
    $table->text('last_sync_error')->nullable();

    // Metadata
    $table->json('metadata')->nullable();
    // metadata = {
    //   "created_via": "auto_sync",  // auto_sync, manual_link, import
    //   "original_role": "member",
    //   "notes": "..."
    // }

    $table->timestamps();

    // Constraints
    $table->unique(['federated_user_id', 'tenant_id']);
    $table->unique(['tenant_id', 'tenant_user_id']);

    // Indices
    $table->index('sync_status');
    $table->index('tenant_user_id');
});
```

#### federation_sync_log

```php
Schema::create('federation_sync_logs', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignUuid('federation_group_id')
        ->constrained()
        ->cascadeOnDelete();
    $table->foreignUuid('federated_user_id')
        ->nullable()
        ->constrained()
        ->nullOnDelete();

    // Operacao
    $table->enum('operation', [
        'user_created',
        'user_updated',
        'password_changed',
        'two_factor_enabled',
        'two_factor_disabled',
        'user_deleted',
        'conflict_detected',
        'conflict_resolved',
        'sync_failed',
        'link_created',
        'link_removed',
    ]);

    // Contexto
    $table->foreignUuid('source_tenant_id')
        ->nullable()
        ->constrained('tenants')
        ->nullOnDelete();
    $table->foreignUuid('target_tenant_id')
        ->nullable()
        ->constrained('tenants')
        ->nullOnDelete();
    $table->uuid('actor_user_id')->nullable(); // Quem fez a alteracao

    // Dados da operacao
    $table->json('old_data')->nullable();
    $table->json('new_data')->nullable();
    $table->text('error_message')->nullable();

    // Resultado
    $table->enum('status', ['success', 'failed', 'pending'])->default('pending');

    $table->timestamp('created_at');

    // Indices
    $table->index(['federation_group_id', 'created_at']);
    $table->index('federated_user_id');
    $table->index('operation');
});
```

#### federation_conflicts

```php
Schema::create('federation_conflicts', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignUuid('federated_user_id')
        ->constrained()
        ->cascadeOnDelete();

    // Dados conflitantes
    $table->string('field'); // name, email, password, etc.
    $table->json('values'); // {tenant_id_1: "value1", tenant_id_2: "value2"}

    // Resolucao
    $table->enum('status', ['pending', 'resolved', 'ignored'])->default('pending');
    $table->uuid('resolved_by')->nullable(); // Central admin que resolveu
    $table->string('resolution')->nullable(); // Valor escolhido
    $table->timestamp('resolved_at')->nullable();

    $table->timestamps();

    // Indices
    $table->index(['federated_user_id', 'status']);
});
```

### 2.2 Alteracoes nos Bancos de TENANT

**NENHUMA alteracao de schema necessaria!**

O modelo `Tenant\User` ganha apenas um campo virtual/accessor:

```php
// app/Models/Tenant/User.php

/**
 * Get the federated user ID from central database.
 * This is a READ-ONLY lookup, not a column in tenant DB.
 */
public function getFederatedUserId(): ?string
{
    // Busca no banco central se este user esta federado
    return \DB::connection('central')
        ->table('federated_user_links')
        ->where('tenant_id', tenant()->id)
        ->where('tenant_user_id', $this->id)
        ->value('federated_user_id');
}

public function isFederated(): bool
{
    return $this->getFederatedUserId() !== null;
}
```

### 2.3 Diagrama ER Completo

```
┌─────────────────────┐
│     tenants         │
├─────────────────────┤
│ id (PK)             │
│ name                │
│ ...                 │
└──────────┬──────────┘
           │
           │ 1:N
           │
┌──────────┴──────────┐          ┌─────────────────────┐
│ federation_groups   │          │ federation_group_   │
├─────────────────────┤  1:N     │      tenants        │
│ id (PK)             │◄─────────├─────────────────────┤
│ name                │          │ id (PK)             │
│ master_tenant_id(FK)├──────────│ federation_group_id │
│ sync_strategy       │          │ tenant_id (FK,UQ)   │
│ settings            │          │ sync_enabled        │
│ is_active           │          │ joined_at           │
└──────────┬──────────┘          └─────────────────────┘
           │
           │ 1:N
           │
┌──────────┴──────────┐          ┌─────────────────────┐
│  federated_users    │          │ federated_user_     │
├─────────────────────┤  1:N     │      links          │
│ id (PK)             │◄─────────├─────────────────────┤
│ federation_group_id │          │ id (PK)             │
│ global_email (UQ)   │          │ federated_user_id   │
│ synced_data (JSON)  │          │ tenant_id (FK)      │
│ master_tenant_id    │          │ tenant_user_id      │
│ master_tenant_user_ │          │ sync_status         │
│    id               │          │ last_synced_at      │
│ last_synced_at      │          └─────────────────────┘
│ sync_version        │
│ status              │
└──────────┬──────────┘
           │
           │ 1:N
           │
┌──────────┴──────────┐          ┌─────────────────────┐
│ federation_sync_    │          │ federation_         │
│      logs           │          │    conflicts        │
├─────────────────────┤          ├─────────────────────┤
│ id (PK)             │          │ id (PK)             │
│ federation_group_id │          │ federated_user_id   │
│ federated_user_id   │          │ field               │
│ operation           │          │ values (JSON)       │
│ source_tenant_id    │          │ status              │
│ target_tenant_id    │          │ resolved_by         │
│ old_data / new_data │          │ resolution          │
│ status              │          └─────────────────────┘
└─────────────────────┘
```

---

## 3. Fluxos Detalhados

### 3.1 Criar Federation Group

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                    FLUXO: CRIAR FEDERATION GROUP                             │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                             │
│  1. Owner do tenant ACME-SP acessa Settings > Federation                    │
│     │                                                                       │
│     v                                                                       │
│  2. Clica em "Create Federation Group"                                      │
│     │                                                                       │
│     v                                                                       │
│  3. Formulario:                                                             │
│     - Nome: "ACME Corporation"                                              │
│     - Descricao: "Grupo de filiais ACME"                                    │
│     - Estrategia: "master_wins"                                             │
│     - [x] Este tenant sera o Master                                         │
│     │                                                                       │
│     v                                                                       │
│  4. FederationGroupService::create()                                        │
│     ├─ Verifica: tenant nao esta em outro grupo                             │
│     ├─ Verifica: owner tem permissao 'federation:manage'                    │
│     ├─ Cria FederationGroup com master_tenant_id = ACME-SP                  │
│     ├─ Cria FederationGroupTenant (ACME-SP, joined_at=now)                  │
│     └─ Dispara FederationGroupCreated event                                 │
│         │                                                                   │
│         v                                                                   │
│  5. IndexExistingUsersJob (opcional)                                        │
│     ├─ Itera users de ACME-SP                                               │
│     └─ Para cada user: cria FederatedUser inicial                           │
│                                                                             │
└─────────────────────────────────────────────────────────────────────────────┘
```

### 3.2 Tenant Join/Leave Grupo

#### Join

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                    FLUXO: TENANT JOIN GRUPO                                  │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                             │
│  1. Master (ACME-SP) convida tenant ACME-RJ                                 │
│     ├─ Gera invite_token com expiracao                                      │
│     └─ Envia email para owner de ACME-RJ                                    │
│         │                                                                   │
│         v                                                                   │
│  2. Owner de ACME-RJ recebe convite                                         │
│     ├─ Acessa link: /federation/accept/{token}                              │
│     └─ Confirma participacao                                                │
│         │                                                                   │
│         v                                                                   │
│  3. FederationService::acceptInvite()                                       │
│     ├─ Valida token e expiracao                                             │
│     ├─ Verifica ACME-RJ nao esta em outro grupo                             │
│     ├─ Cria FederationGroupTenant (ACME-RJ)                                 │
│     └─ Dispara TenantJoinedFederation event                                 │
│         │                                                                   │
│         v                                                                   │
│  4. SyncExistingUsersJob                                                    │
│     ├─ Para cada user em ACME-RJ:                                           │
│     │   ├─ Busca FederatedUser por email no grupo                           │
│     │   ├─ Se existe: cria FederatedUserLink                                │
│     │   └─ Se nao existe: cria FederatedUser + Link                         │
│     │       │                                                               │
│     │       v                                                               │
│     └─ Sincroniza dados: ACME-SP (master) → ACME-RJ                         │
│                                                                             │
└─────────────────────────────────────────────────────────────────────────────┘
```

#### Leave

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                    FLUXO: TENANT LEAVE GRUPO                                 │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                             │
│  1. Owner de ACME-MG decide sair do grupo                                   │
│     │                                                                       │
│     v                                                                       │
│  2. FederationService::leave()                                              │
│     ├─ Verifica: nao e o master (master nao pode sair)                      │
│     ├─ Marca FederationGroupTenant.left_at = now                            │
│     ├─ Marca FederationGroupTenant.sync_enabled = false                     │
│     └─ Dispara TenantLeftFederation event                                   │
│         │                                                                   │
│         v                                                                   │
│  3. CleanupLinksJob                                                         │
│     ├─ Remove FederatedUserLinks de ACME-MG                                 │
│     └─ NAO deleta users de ACME-MG (ficam como usuarios locais)             │
│                                                                             │
│  Resultado:                                                                  │
│  - Usuarios de ACME-MG continuam existindo (isolados)                       │
│  - Alteracoes em ACME-MG NAO propagam mais                                  │
│  - ACME-MG pode criar novo grupo ou entrar em outro                         │
│                                                                             │
└─────────────────────────────────────────────────────────────────────────────┘
```

### 3.3 Usuario Criado em Tenant do Grupo

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                    FLUXO: CRIAR USUARIO NO GRUPO                             │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                             │
│  1. Admin em ACME-SP cria usuario maria@acme.com                            │
│     │                                                                       │
│     v                                                                       │
│  2. TenantUserObserver::created()                                           │
│     ├─ Verifica: tenant ACME-SP esta em federation group?                   │
│     │   └─ Sim: dispara UserCreatedInFederatedTenant event                  │
│         │                                                                   │
│         v                                                                   │
│  3. CreateFederatedUserJob                                                  │
│     ├─ Busca FederatedUser por email no grupo                               │
│     ├─ Se existe (usuario ja em outro tenant do grupo):                     │
│     │   └─ Cria apenas FederatedUserLink para ACME-SP                       │
│     ├─ Se nao existe:                                                       │
│     │   ├─ Cria FederatedUser com synced_data                               │
│     │   └─ Cria FederatedUserLink para ACME-SP                              │
│         │                                                                   │
│         v                                                                   │
│  4. SyncUserToFederatedTenantsJob                                           │
│     ├─ Se "auto_create_on_sync" habilitado no grupo:                        │
│     │   ├─ Para cada tenant do grupo (exceto origem):                       │
│     │   │   ├─ Verifica: settings.auto_accept_users?                        │
│     │   │   │   └─ Sim: cria User no banco do tenant                        │
│     │   │   │       └─ Cria FederatedUserLink                               │
│     │   │   └─ Nao: marca como pending_approval                             │
│     │       │                                                               │
│     │       v                                                               │
│     └─ Loga operacao em federation_sync_logs                                │
│                                                                             │
│  Resultado:                                                                  │
│  - maria@acme.com existe em ACME-SP, ACME-RJ, ACME-MG                       │
│  - Cada tenant define role local (owner, admin, member, etc.)               │
│                                                                             │
└─────────────────────────────────────────────────────────────────────────────┘
```

### 3.4 Usuario Atualiza Perfil

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                    FLUXO: ATUALIZAR PERFIL                                   │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                             │
│  1. Joao em ACME-RJ altera nome para "Joao P. Silva"                        │
│     │                                                                       │
│     v                                                                       │
│  2. TenantUserObserver::updated()                                           │
│     ├─ Verifica campos alterados: ['name']                                  │
│     ├─ Campo 'name' esta em synced_fields? Sim                              │
│     └─ Dispara UserUpdatedInFederatedTenant event                           │
│         │                                                                   │
│         v                                                                   │
│  3. PropagateUserUpdateJob                                                  │
│     ├─ Busca FederatedUser por link                                         │
│     ├─ Verifica sync_strategy:                                              │
│     │   │                                                                   │
│     │   ├─ "master_wins":                                                   │
│     │   │   ├─ Se origem == master: propaga para todos                      │
│     │   │   └─ Se origem != master: NAO propaga (reverte localmente)        │
│     │   │                                                                   │
│     │   ├─ "last_write_wins":                                               │
│     │   │   └─ Propaga para todos, atualiza sync_version                    │
│     │   │                                                                   │
│     │   └─ "manual_review":                                                 │
│     │       └─ Cria FederationConflict para revisao                         │
│     │                                                                       │
│     └─ Para cada tenant com link:                                           │
│         └─ UpdateLinkedUserJob                                              │
│             ├─ Conecta no banco do tenant                                   │
│             ├─ Atualiza User.name                                           │
│             └─ Atualiza FederatedUserLink.last_synced_at                    │
│                                                                             │
└─────────────────────────────────────────────────────────────────────────────┘
```

### 3.5 Usuario Altera Senha

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                    FLUXO: ALTERAR SENHA                                      │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                             │
│  1. Maria em ACME-SP altera senha                                           │
│     │                                                                       │
│     v                                                                       │
│  2. PasswordChangedListener (Fortify event)                                 │
│     └─ Dispara UserPasswordChangedInFederatedTenant event                   │
│         │                                                                   │
│         v                                                                   │
│  3. PropagatePasswordChangeJob (ALTA PRIORIDADE)                            │
│     ├─ Busca FederatedUser                                                  │
│     ├─ Atualiza synced_data.password_hash                                   │
│     ├─ Atualiza synced_data.password_changed_at                             │
│     │                                                                       │
│     └─ Para cada tenant com link (paralelo):                                │
│         └─ UpdateUserPasswordJob                                            │
│             ├─ Conecta no banco do tenant                                   │
│             ├─ Atualiza User.password (mesmo hash)                          │
│             ├─ Opcional: revoga sessions ativas                             │
│             └─ Loga: 'password_changed' em sync_log                         │
│                                                                             │
│  Seguranca:                                                                  │
│  - Hash propagado (nao a senha em texto)                                    │
│  - Sessions podem ser revogadas por config                                  │
│  - Audit trail completo                                                     │
│                                                                             │
└─────────────────────────────────────────────────────────────────────────────┘
```

### 3.6 Resolucao de Conflitos

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                    FLUXO: DETECTAR E RESOLVER CONFLITO                       │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                             │
│  CENARIO: sync_strategy = "last_write_wins" e 2 updates simultaneos         │
│                                                                             │
│  T1: Joao em ACME-SP altera nome para "Joao Silva"                          │
│  T2: Joao em ACME-RJ altera nome para "Joao Santos"                         │
│     (ambos antes do sync propagar)                                          │
│     │                                                                       │
│     v                                                                       │
│  1. Ambos updates disparam PropagateUserUpdateJob                           │
│     │                                                                       │
│     v                                                                       │
│  2. Primeiro job (ACME-SP) processa:                                        │
│     ├─ sync_version = 1 → 2                                                 │
│     └─ synced_data.name = "Joao Silva"                                      │
│     │                                                                       │
│     v                                                                       │
│  3. Segundo job (ACME-RJ) processa:                                         │
│     ├─ Verifica sync_version esperado = 1                                   │
│     ├─ sync_version atual = 2 (ja foi alterado!)                            │
│     └─ CONFLITO DETECTADO                                                   │
│         │                                                                   │
│         v                                                                   │
│  4. ConflictResolutionService::handle()                                     │
│     │                                                                       │
│     ├─ Se "last_write_wins":                                                │
│     │   ├─ Compara timestamps                                               │
│     │   └─ Mais recente vence: "Joao Santos" (ACME-RJ)                      │
│     │                                                                       │
│     ├─ Se "master_wins":                                                    │
│     │   └─ Valor do master: "Joao Silva" (ACME-SP e master)                 │
│     │                                                                       │
│     └─ Se "manual_review":                                                  │
│         ├─ Cria FederationConflict                                          │
│         ├─ Notifica administradores do grupo                                │
│         └─ Mantem valor do master temporariamente                           │
│             │                                                               │
│             v                                                               │
│  5. Se manual_review → Admin resolve:                                       │
│     ├─ Acessa /admin/federation/conflicts                                   │
│     ├─ Seleciona valor ou digita novo                                       │
│     ├─ ConflictResolutionService::resolve()                                 │
│     └─ Propaga valor final para todos os tenants                            │
│                                                                             │
└─────────────────────────────────────────────────────────────────────────────┘
```

### 3.7 Login Cross-Tenant

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                    FLUXO: LOGIN CROSS-TENANT                                 │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                             │
│  CENARIO: Usuario federado loga em tenant que ainda nao tem conta local     │
│                                                                             │
│  1. joao@acme.com tenta login em acme-mg.test                               │
│     (existe em ACME-SP e ACME-RJ, mas NAO em ACME-MG ainda)                 │
│     │                                                                       │
│     v                                                                       │
│  2. FederatedLoginController::authenticate()                                │
│     ├─ Busca User local por email: NAO ENCONTRADO                           │
│     ├─ Verifica: tenant ACME-MG esta em federation group? SIM               │
│     └─ Busca FederatedUser por email no grupo: ENCONTRADO                   │
│         │                                                                   │
│         v                                                                   │
│  3. Verifica settings do grupo:                                             │
│     └─ auto_create_on_login: true                                           │
│         │                                                                   │
│         v                                                                   │
│  4. CreateUserFromFederatedDataJob (sync)                                   │
│     ├─ Cria User em ACME-MG com dados de FederatedUser.synced_data          │
│     ├─ Atribui role padrao (settings.default_role = "member")               │
│     ├─ Cria FederatedUserLink                                               │
│     └─ Retorna User recem-criado                                            │
│         │                                                                   │
│         v                                                                   │
│  5. Valida senha contra synced_data.password_hash                           │
│     └─ Se valida: Auth::login($user)                                        │
│         │                                                                   │
│         v                                                                   │
│  6. Redirect para dashboard                                                 │
│                                                                             │
│  Resultado:                                                                  │
│  - Joao agora tem conta em ACME-MG                                          │
│  - Role inicial: member (pode ser promovido depois)                         │
│  - Proximos logins nao precisam criar conta                                 │
│                                                                             │
└─────────────────────────────────────────────────────────────────────────────┘
```

---

## 4. Models e Relacionamentos

### 4.1 FederationGroup Model

```php
<?php

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * Federation Group - Grupo de tenants com usuarios sincronizados.
 *
 * @property string $id UUID
 * @property string $name
 * @property string|null $description
 * @property string $master_tenant_id
 * @property string $sync_strategy master_wins|last_write_wins|manual_review
 * @property array|null $settings
 * @property bool $is_active
 */
class FederationGroup extends Model
{
    use CentralConnection, HasUuids;

    protected $fillable = [
        'name',
        'description',
        'master_tenant_id',
        'sync_strategy',
        'settings',
        'is_active',
    ];

    protected $casts = [
        'settings' => 'array',
        'is_active' => 'boolean',
    ];

    // ==========================================
    // Relationships
    // ==========================================

    /**
     * Tenant master do grupo (fonte da verdade).
     */
    public function masterTenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'master_tenant_id');
    }

    /**
     * Todos os tenants do grupo (incluindo master).
     */
    public function tenants(): BelongsToMany
    {
        return $this->belongsToMany(Tenant::class, 'federation_group_tenants')
            ->withPivot(['sync_enabled', 'joined_at', 'left_at', 'settings'])
            ->withTimestamps();
    }

    /**
     * Tenants ativos no grupo.
     */
    public function activeTenants(): BelongsToMany
    {
        return $this->tenants()
            ->wherePivot('sync_enabled', true)
            ->wherePivotNull('left_at');
    }

    /**
     * Usuarios federados do grupo.
     */
    public function federatedUsers(): HasMany
    {
        return $this->hasMany(FederatedUser::class);
    }

    /**
     * Logs de sincronizacao.
     */
    public function syncLogs(): HasMany
    {
        return $this->hasMany(FederationSyncLog::class);
    }

    // ==========================================
    // Accessors & Helpers
    // ==========================================

    /**
     * Campos que serao sincronizados.
     */
    public function getSyncFieldsAttribute(): array
    {
        return $this->settings['sync_fields'] ?? [
            'name',
            'email',
            'password',
            'avatar',
            'two_factor',
            'locale',
        ];
    }

    /**
     * Verifica se tenant e o master.
     */
    public function isMaster(Tenant|string $tenant): bool
    {
        $tenantId = $tenant instanceof Tenant ? $tenant->id : $tenant;
        return $this->master_tenant_id === $tenantId;
    }

    /**
     * Verifica se tenant pode sair do grupo.
     */
    public function canLeave(Tenant|string $tenant): bool
    {
        return !$this->isMaster($tenant);
    }

    /**
     * Obtem configuracao especifica de um tenant no grupo.
     */
    public function getTenantSettings(Tenant|string $tenant): array
    {
        $tenantId = $tenant instanceof Tenant ? $tenant->id : $tenant;

        $pivot = $this->tenants()
            ->where('tenants.id', $tenantId)
            ->first()?->pivot;

        return $pivot?->settings ?? [];
    }
}
```

### 4.2 FederatedUser Model

```php
<?php

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * Federated User - Usuario sincronizado entre tenants de um grupo.
 *
 * @property string $id UUID
 * @property string $federation_group_id
 * @property string $global_email
 * @property array $synced_data
 * @property string $master_tenant_id
 * @property string $master_tenant_user_id
 * @property \Carbon\Carbon|null $last_synced_at
 * @property string|null $last_sync_source
 * @property int $sync_version
 * @property string $status active|suspended|pending_review
 */
class FederatedUser extends Model
{
    use CentralConnection, HasUuids, SoftDeletes;

    protected $fillable = [
        'federation_group_id',
        'global_email',
        'synced_data',
        'master_tenant_id',
        'master_tenant_user_id',
        'last_synced_at',
        'last_sync_source',
        'sync_version',
        'status',
    ];

    protected $casts = [
        'synced_data' => 'array',
        'last_synced_at' => 'datetime',
        'sync_version' => 'integer',
    ];

    // ==========================================
    // Relationships
    // ==========================================

    /**
     * Grupo de federacao.
     */
    public function federationGroup(): BelongsTo
    {
        return $this->belongsTo(FederationGroup::class);
    }

    /**
     * Tenant master.
     */
    public function masterTenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'master_tenant_id');
    }

    /**
     * Links com usuarios locais nos tenants.
     */
    public function links(): HasMany
    {
        return $this->hasMany(FederatedUserLink::class);
    }

    /**
     * Conflitos pendentes.
     */
    public function conflicts(): HasMany
    {
        return $this->hasMany(FederationConflict::class);
    }

    // ==========================================
    // Accessors
    // ==========================================

    /**
     * Nome sincronizado.
     */
    public function getNameAttribute(): ?string
    {
        return $this->synced_data['name'] ?? null;
    }

    /**
     * Hash da senha.
     */
    public function getPasswordHashAttribute(): ?string
    {
        return $this->synced_data['password_hash'] ?? null;
    }

    /**
     * 2FA habilitado?
     */
    public function getTwoFactorEnabledAttribute(): bool
    {
        return $this->synced_data['two_factor_enabled'] ?? false;
    }

    // ==========================================
    // Methods
    // ==========================================

    /**
     * Atualiza dados sincronizados.
     */
    public function updateSyncedData(array $data, string $sourceTenanId): void
    {
        $this->synced_data = array_merge($this->synced_data ?? [], $data);
        $this->last_synced_at = now();
        $this->last_sync_source = $sourceTenanId;
        $this->increment('sync_version');
        $this->save();
    }

    /**
     * Obtem link para um tenant especifico.
     */
    public function getLinkForTenant(string $tenantId): ?FederatedUserLink
    {
        return $this->links()->where('tenant_id', $tenantId)->first();
    }

    /**
     * Verifica se tem link ativo com tenant.
     */
    public function hasLinkWith(string $tenantId): bool
    {
        return $this->links()
            ->where('tenant_id', $tenantId)
            ->where('sync_status', '!=', 'disabled')
            ->exists();
    }

    /**
     * Obtem todos os tenant_user_ids vinculados.
     */
    public function getAllLinkedUserIds(): array
    {
        return $this->links()
            ->where('sync_status', 'synced')
            ->pluck('tenant_user_id', 'tenant_id')
            ->toArray();
    }
}
```

### 4.3 FederatedUserLink Model

```php
<?php

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * Federated User Link - Vincula FederatedUser ao User local de cada tenant.
 *
 * @property string $id UUID
 * @property string $federated_user_id
 * @property string $tenant_id
 * @property string $tenant_user_id UUID do user no banco do tenant
 * @property string $sync_status synced|pending_sync|sync_failed|conflict|disabled
 * @property \Carbon\Carbon|null $last_synced_at
 * @property int $sync_attempts
 * @property string|null $last_sync_error
 * @property array|null $metadata
 */
class FederatedUserLink extends Model
{
    use CentralConnection, HasUuids;

    protected $fillable = [
        'federated_user_id',
        'tenant_id',
        'tenant_user_id',
        'sync_status',
        'last_synced_at',
        'sync_attempts',
        'last_sync_error',
        'metadata',
    ];

    protected $casts = [
        'last_synced_at' => 'datetime',
        'sync_attempts' => 'integer',
        'metadata' => 'array',
    ];

    // ==========================================
    // Relationships
    // ==========================================

    /**
     * Usuario federado.
     */
    public function federatedUser(): BelongsTo
    {
        return $this->belongsTo(FederatedUser::class);
    }

    /**
     * Tenant.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    // ==========================================
    // Scopes
    // ==========================================

    /**
     * Links sincronizados.
     */
    public function scopeSynced($query)
    {
        return $query->where('sync_status', 'synced');
    }

    /**
     * Links com erro.
     */
    public function scopeFailed($query)
    {
        return $query->where('sync_status', 'sync_failed');
    }

    /**
     * Links pendentes.
     */
    public function scopePending($query)
    {
        return $query->where('sync_status', 'pending_sync');
    }

    // ==========================================
    // Methods
    // ==========================================

    /**
     * Marca como sincronizado.
     */
    public function markSynced(): void
    {
        $this->update([
            'sync_status' => 'synced',
            'last_synced_at' => now(),
            'sync_attempts' => 0,
            'last_sync_error' => null,
        ]);
    }

    /**
     * Marca como falha.
     */
    public function markFailed(string $error): void
    {
        $this->update([
            'sync_status' => 'sync_failed',
            'sync_attempts' => $this->sync_attempts + 1,
            'last_sync_error' => $error,
        ]);
    }

    /**
     * Obtem o User local do tenant.
     * Precisa inicializar tenancy antes de chamar.
     */
    public function getTenantUser(): ?\App\Models\Tenant\User
    {
        return $this->tenant->run(function () {
            return \App\Models\Tenant\User::find($this->tenant_user_id);
        });
    }
}
```

### 4.4 Trait para Tenant\User

```php
<?php

namespace App\Models\Concerns;

use App\Models\Central\FederatedUser;
use App\Models\Central\FederatedUserLink;
use Illuminate\Support\Facades\DB;

/**
 * Trait para adicionar funcionalidades de federation ao User do tenant.
 *
 * @mixin \App\Models\Tenant\User
 */
trait HasFederation
{
    /**
     * Boot do trait.
     */
    public static function bootHasFederation(): void
    {
        // Observer para propagar alteracoes
        static::updated(function ($user) {
            if ($user->isFederated()) {
                event(new \App\Events\Federation\UserUpdatedInFederatedTenant($user));
            }
        });

        static::deleted(function ($user) {
            if ($user->isFederated()) {
                event(new \App\Events\Federation\UserDeletedInFederatedTenant($user));
            }
        });
    }

    /**
     * Verifica se o usuario esta federado.
     */
    public function isFederated(): bool
    {
        return $this->getFederatedUserLink() !== null;
    }

    /**
     * Obtem o link de federacao.
     */
    public function getFederatedUserLink(): ?FederatedUserLink
    {
        return DB::connection('central')
            ->table('federated_user_links')
            ->where('tenant_id', tenant()->id)
            ->where('tenant_user_id', $this->id)
            ->first();
    }

    /**
     * Obtem o FederatedUser.
     */
    public function getFederatedUser(): ?FederatedUser
    {
        $link = $this->getFederatedUserLink();

        if (!$link) {
            return null;
        }

        return FederatedUser::find($link->federated_user_id);
    }

    /**
     * Obtem IDs de usuarios vinculados em outros tenants.
     * Retorna: ['tenant_id' => 'user_id', ...]
     */
    public function getLinkedUsersInOtherTenants(): array
    {
        $federatedUser = $this->getFederatedUser();

        if (!$federatedUser) {
            return [];
        }

        return $federatedUser->links()
            ->where('tenant_id', '!=', tenant()->id)
            ->where('sync_status', 'synced')
            ->pluck('tenant_user_id', 'tenant_id')
            ->toArray();
    }

    /**
     * Verifica se este e o usuario master da federacao.
     */
    public function isFederationMaster(): bool
    {
        $federatedUser = $this->getFederatedUser();

        if (!$federatedUser) {
            return false;
        }

        return $federatedUser->master_tenant_id === tenant()->id
            && $federatedUser->master_tenant_user_id === $this->id;
    }
}
```

---

## 5. Jobs e Events

### 5.1 Events

```php
<?php
// app/Events/Federation/

namespace App\Events\Federation;

use App\Models\Central\FederationGroup;
use App\Models\Central\FederatedUser;
use App\Models\Central\Tenant;
use App\Models\Tenant\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

// Grupo criado
class FederationGroupCreated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public FederationGroup $group
    ) {}
}

// Tenant entrou no grupo
class TenantJoinedFederation
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public FederationGroup $group,
        public Tenant $tenant
    ) {}
}

// Tenant saiu do grupo
class TenantLeftFederation
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public FederationGroup $group,
        public Tenant $tenant
    ) {}
}

// Usuario criado em tenant federado
class UserCreatedInFederatedTenant
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public User $user,
        public string $tenantId
    ) {}
}

// Usuario atualizado em tenant federado
class UserUpdatedInFederatedTenant
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public User $user,
        public array $changedFields = []
    ) {}
}

// Usuario deletado em tenant federado
class UserDeletedInFederatedTenant
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public string $userId,
        public string $email,
        public string $tenantId
    ) {}
}

// Senha alterada
class UserPasswordChangedInFederatedTenant
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public User $user,
        public string $newPasswordHash
    ) {}
}

// 2FA habilitado/desabilitado
class UserTwoFactorChangedInFederatedTenant
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public User $user,
        public bool $enabled,
        public ?string $secret = null,
        public ?array $recoveryCodes = null
    ) {}
}

// Conflito detectado
class FederationConflictDetected
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public FederatedUser $federatedUser,
        public string $field,
        public array $conflictingValues
    ) {}
}

// Sync falhou
class FederationSyncFailed
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public FederatedUser $federatedUser,
        public string $targetTenantId,
        public string $operation,
        public string $error
    ) {}
}
```

### 5.2 Jobs

```php
<?php
// app/Jobs/Federation/

namespace App\Jobs\Federation;

use App\Models\Central\FederatedUser;
use App\Models\Central\FederatedUserLink;
use App\Models\Central\FederationGroup;
use App\Models\Central\Tenant;
use App\Models\Tenant\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Sincroniza usuario para todos os tenants do grupo.
 */
class SyncUserToFederatedTenantsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60;

    public function __construct(
        public FederatedUser $federatedUser,
        public array $fieldsToSync = [],
        public ?string $excludeTenantId = null
    ) {}

    public function handle(): void
    {
        $group = $this->federatedUser->federationGroup;

        if (!$group->is_active) {
            Log::info('Federation group is inactive, skipping sync', [
                'group_id' => $group->id,
                'user_id' => $this->federatedUser->id,
            ]);
            return;
        }

        // Obtem tenants ativos do grupo
        $tenantIds = $group->activeTenants()
            ->when($this->excludeTenantId, fn($q) => $q->where('tenants.id', '!=', $this->excludeTenantId))
            ->pluck('tenants.id');

        foreach ($tenantIds as $tenantId) {
            UpdateLinkedUserJob::dispatch(
                $this->federatedUser,
                $tenantId,
                $this->fieldsToSync
            );
        }
    }
}

/**
 * Atualiza usuario em um tenant especifico.
 */
class UpdateLinkedUserJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 30;

    public function __construct(
        public FederatedUser $federatedUser,
        public string $targetTenantId,
        public array $fieldsToSync = []
    ) {}

    public function handle(): void
    {
        $link = $this->federatedUser->getLinkForTenant($this->targetTenantId);

        if (!$link) {
            Log::warning('No link found for tenant', [
                'federated_user_id' => $this->federatedUser->id,
                'tenant_id' => $this->targetTenantId,
            ]);
            return;
        }

        if ($link->sync_status === 'disabled') {
            Log::info('Sync disabled for this link', [
                'link_id' => $link->id,
            ]);
            return;
        }

        try {
            $tenant = Tenant::find($this->targetTenantId);

            $tenant->run(function () use ($link) {
                $user = User::find($link->tenant_user_id);

                if (!$user) {
                    throw new \Exception("User not found: {$link->tenant_user_id}");
                }

                // Atualiza campos sincronizados
                $syncedData = $this->federatedUser->synced_data;
                $fieldsToUpdate = $this->fieldsToSync ?: array_keys($syncedData);

                $updateData = [];
                foreach ($fieldsToUpdate as $field) {
                    if (isset($syncedData[$field])) {
                        $dbField = $this->mapFieldToColumn($field);
                        $updateData[$dbField] = $syncedData[$field];
                    }
                }

                if (!empty($updateData)) {
                    // Desabilita observer temporariamente para evitar loop
                    User::withoutEvents(function () use ($user, $updateData) {
                        $user->update($updateData);
                    });
                }
            });

            $link->markSynced();

            Log::info('User synced successfully', [
                'federated_user_id' => $this->federatedUser->id,
                'tenant_id' => $this->targetTenantId,
                'fields' => $this->fieldsToSync,
            ]);

        } catch (\Exception $e) {
            $link->markFailed($e->getMessage());

            Log::error('Failed to sync user', [
                'federated_user_id' => $this->federatedUser->id,
                'tenant_id' => $this->targetTenantId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Mapeia campo de synced_data para coluna do banco.
     */
    private function mapFieldToColumn(string $field): string
    {
        return match ($field) {
            'password_hash' => 'password',
            'two_factor_secret' => 'two_factor_secret',
            'two_factor_recovery_codes' => 'two_factor_recovery_codes',
            default => $field,
        };
    }
}

/**
 * Cria usuario em tenant a partir de FederatedUser.
 */
class CreateUserInTenantJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public FederatedUser $federatedUser,
        public string $targetTenantId,
        public string $defaultRole = 'member'
    ) {}

    public function handle(): void
    {
        // Verifica se ja existe link
        if ($this->federatedUser->hasLinkWith($this->targetTenantId)) {
            Log::info('Link already exists', [
                'federated_user_id' => $this->federatedUser->id,
                'tenant_id' => $this->targetTenantId,
            ]);
            return;
        }

        $tenant = Tenant::find($this->targetTenantId);

        $userId = $tenant->run(function () {
            $syncedData = $this->federatedUser->synced_data;

            $user = User::create([
                'name' => $syncedData['name'],
                'email' => $this->federatedUser->global_email,
                'password' => $syncedData['password_hash'],
                'locale' => $syncedData['locale'] ?? config('app.locale'),
                'email_verified_at' => now(), // Federado ja e verificado
            ]);

            // Atribui role padrao
            $user->assignRole($this->defaultRole);

            // Configura 2FA se existir
            if ($syncedData['two_factor_enabled'] ?? false) {
                $user->forceFill([
                    'two_factor_secret' => $syncedData['two_factor_secret'],
                    'two_factor_recovery_codes' => $syncedData['two_factor_recovery_codes'],
                    'two_factor_confirmed_at' => now(),
                ])->save();
            }

            return $user->id;
        });

        // Cria link
        FederatedUserLink::create([
            'federated_user_id' => $this->federatedUser->id,
            'tenant_id' => $this->targetTenantId,
            'tenant_user_id' => $userId,
            'sync_status' => 'synced',
            'last_synced_at' => now(),
            'metadata' => [
                'created_via' => 'auto_sync',
                'original_role' => $this->defaultRole,
            ],
        ]);

        Log::info('User created in tenant via federation', [
            'federated_user_id' => $this->federatedUser->id,
            'tenant_id' => $this->targetTenantId,
            'tenant_user_id' => $userId,
        ]);
    }
}

/**
 * Propaga alteracao de senha.
 */
class PropagatePasswordChangeJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public string $queue = 'high'; // Alta prioridade

    public function __construct(
        public FederatedUser $federatedUser,
        public string $newPasswordHash,
        public string $sourceTenantId
    ) {}

    public function handle(): void
    {
        // Atualiza FederatedUser
        $this->federatedUser->updateSyncedData([
            'password_hash' => $this->newPasswordHash,
            'password_changed_at' => now()->toIso8601String(),
        ], $this->sourceTenantId);

        // Propaga para outros tenants
        SyncUserToFederatedTenantsJob::dispatch(
            $this->federatedUser,
            ['password_hash'],
            $this->sourceTenantId
        );
    }
}
```

---

## 6. API/Controllers

### 6.1 Estrutura de Controllers

```
app/Http/Controllers/
├── Central/Admin/Federation/
│   ├── FederationGroupController.php      # CRUD de grupos (super admin)
│   └── FederationConflictController.php   # Gerenciar conflitos
└── Tenant/Admin/Federation/
    ├── FederationSettingsController.php   # Config do tenant no grupo
    └── FederatedUserController.php        # Listar usuarios federados
```

### 6.2 FederationGroupController (Central)

```php
<?php

namespace App\Http\Controllers\Central\Admin\Federation;

use App\Http\Controllers\Controller;
use App\Http\Requests\Central\Federation\StoreFederationGroupRequest;
use App\Http\Requests\Central\Federation\UpdateFederationGroupRequest;
use App\Http\Requests\Central\Federation\InviteTenantRequest;
use App\Http\Resources\Central\FederationGroupResource;
use App\Models\Central\FederationGroup;
use App\Models\Central\Tenant;
use App\Services\Central\FederationService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class FederationGroupController extends Controller
{
    public function __construct(
        protected FederationService $federationService
    ) {}

    /**
     * Lista todos os grupos de federation.
     */
    public function index(): Response
    {
        $groups = FederationGroup::with(['masterTenant', 'tenants'])
            ->orderBy('name')
            ->paginate(20);

        return Inertia::render('central/admin/federation/index', [
            'groups' => FederationGroupResource::collection($groups),
        ]);
    }

    /**
     * Formulario de criacao.
     */
    public function create(): Response
    {
        $availableTenants = Tenant::doesntHave('federationGroup')
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('central/admin/federation/create', [
            'availableTenants' => $availableTenants,
        ]);
    }

    /**
     * Cria novo grupo.
     */
    public function store(StoreFederationGroupRequest $request): RedirectResponse
    {
        $group = $this->federationService->createGroup(
            $request->validated()
        );

        return redirect()
            ->route('central.admin.federation.show', $group)
            ->with('success', __('Federation group created successfully.'));
    }

    /**
     * Detalhes do grupo.
     */
    public function show(FederationGroup $group): Response
    {
        $group->load([
            'masterTenant',
            'tenants',
            'federatedUsers' => fn($q) => $q->latest()->take(10),
            'syncLogs' => fn($q) => $q->latest()->take(20),
        ]);

        return Inertia::render('central/admin/federation/show', [
            'group' => new FederationGroupResource($group),
            'stats' => $this->federationService->getGroupStats($group),
        ]);
    }

    /**
     * Atualiza grupo.
     */
    public function update(UpdateFederationGroupRequest $request, FederationGroup $group): RedirectResponse
    {
        $this->federationService->updateGroup($group, $request->validated());

        return back()->with('success', __('Federation group updated successfully.'));
    }

    /**
     * Deleta grupo.
     */
    public function destroy(FederationGroup $group): RedirectResponse
    {
        $this->federationService->deleteGroup($group);

        return redirect()
            ->route('central.admin.federation.index')
            ->with('success', __('Federation group deleted successfully.'));
    }

    /**
     * Convida tenant para o grupo.
     */
    public function inviteTenant(InviteTenantRequest $request, FederationGroup $group): RedirectResponse
    {
        $this->federationService->inviteTenant(
            $group,
            Tenant::findOrFail($request->tenant_id),
            $request->validated()
        );

        return back()->with('success', __('Invitation sent successfully.'));
    }

    /**
     * Remove tenant do grupo.
     */
    public function removeTenant(FederationGroup $group, Tenant $tenant): RedirectResponse
    {
        if ($group->isMaster($tenant)) {
            return back()->with('error', __('Cannot remove master tenant from group.'));
        }

        $this->federationService->removeTenant($group, $tenant);

        return back()->with('success', __('Tenant removed from federation group.'));
    }
}
```

### 6.3 FederationSettingsController (Tenant)

```php
<?php

namespace App\Http\Controllers\Tenant\Admin\Federation;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Federation\AcceptInvitationRequest;
use App\Http\Requests\Tenant\Federation\UpdateFederationSettingsRequest;
use App\Http\Resources\Tenant\FederationSettingsResource;
use App\Models\Central\FederationGroup;
use App\Services\Tenant\FederationService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class FederationSettingsController extends Controller
{
    public function __construct(
        protected FederationService $federationService
    ) {}

    /**
     * Pagina de configuracoes de federation do tenant.
     */
    public function index(): Response
    {
        $tenant = tenant();
        $group = $this->federationService->getTenantFederationGroup($tenant);
        $pendingInvitations = $this->federationService->getPendingInvitations($tenant);

        return Inertia::render('tenant/admin/settings/federation', [
            'federation' => $group ? new FederationSettingsResource($group, $tenant) : null,
            'pendingInvitations' => $pendingInvitations,
            'canCreateGroup' => !$group,
            'isMaster' => $group?->isMaster($tenant) ?? false,
        ]);
    }

    /**
     * Aceita convite para grupo.
     */
    public function acceptInvitation(AcceptInvitationRequest $request): RedirectResponse
    {
        $this->federationService->acceptInvitation(
            tenant(),
            $request->token
        );

        return back()->with('success', __('Successfully joined federation group.'));
    }

    /**
     * Rejeita convite.
     */
    public function rejectInvitation(string $token): RedirectResponse
    {
        $this->federationService->rejectInvitation(tenant(), $token);

        return back()->with('success', __('Invitation rejected.'));
    }

    /**
     * Atualiza configuracoes do tenant no grupo.
     */
    public function update(UpdateFederationSettingsRequest $request): RedirectResponse
    {
        $this->federationService->updateTenantSettings(
            tenant(),
            $request->validated()
        );

        return back()->with('success', __('Federation settings updated.'));
    }

    /**
     * Sai do grupo.
     */
    public function leave(): RedirectResponse
    {
        $group = $this->federationService->getTenantFederationGroup(tenant());

        if (!$group) {
            return back()->with('error', __('Tenant is not in a federation group.'));
        }

        if ($group->isMaster(tenant())) {
            return back()->with('error', __('Master tenant cannot leave the group. Transfer ownership first.'));
        }

        $this->federationService->leaveFederation(tenant());

        return back()->with('success', __('Successfully left federation group.'));
    }
}
```

### 6.4 Form Requests

```php
<?php
// app/Http/Requests/Central/Federation/

namespace App\Http\Requests\Central\Federation;

use Illuminate\Foundation\Http\FormRequest;

class StoreFederationGroupRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'master_tenant_id' => [
                'required',
                'uuid',
                'exists:tenants,id',
                // Tenant nao pode estar em outro grupo
                function ($attribute, $value, $fail) {
                    $exists = \DB::table('federation_group_tenants')
                        ->where('tenant_id', $value)
                        ->whereNull('left_at')
                        ->exists();

                    if ($exists) {
                        $fail(__('This tenant is already in a federation group.'));
                    }
                },
            ],
            'sync_strategy' => ['required', 'in:master_wins,last_write_wins,manual_review'],
            'settings' => ['nullable', 'array'],
            'settings.sync_fields' => ['nullable', 'array'],
            'settings.sync_fields.*' => ['string', 'in:name,email,password,avatar,two_factor,locale'],
            'settings.auto_create_on_login' => ['nullable', 'boolean'],
            'settings.notification_email' => ['nullable', 'email'],
        ];
    }
}

class UpdateFederationGroupRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'sync_strategy' => ['sometimes', 'in:master_wins,last_write_wins,manual_review'],
            'settings' => ['nullable', 'array'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}

class InviteTenantRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'tenant_id' => [
                'required',
                'uuid',
                'exists:tenants,id',
                function ($attribute, $value, $fail) {
                    $exists = \DB::table('federation_group_tenants')
                        ->where('tenant_id', $value)
                        ->whereNull('left_at')
                        ->exists();

                    if ($exists) {
                        $fail(__('This tenant is already in a federation group.'));
                    }
                },
            ],
            'default_role' => ['nullable', 'string', 'in:owner,admin,member'],
            'auto_accept_users' => ['nullable', 'boolean'],
        ];
    }
}
```

---

## 7. Frontend (React/Inertia)

### 7.1 Estrutura de Paginas

```
resources/js/pages/
├── central/admin/federation/
│   ├── index.tsx              # Lista de grupos
│   ├── create.tsx             # Criar grupo
│   ├── show.tsx               # Detalhes do grupo
│   └── conflicts.tsx          # Gerenciar conflitos
└── tenant/admin/settings/
    └── federation.tsx         # Config do tenant
```

### 7.2 Pagina de Federation Settings (Tenant)

```tsx
// resources/js/pages/tenant/admin/settings/federation.tsx

import { Head } from '@inertiajs/react';
import { AppLayout } from '@/layouts/app-layout';
import { SettingsLayout } from '@/layouts/settings/layout';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Switch } from '@/components/ui/switch';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Users, Link2, Shield, Clock, AlertTriangle } from 'lucide-react';
import { useForm } from '@inertiajs/react';

interface FederationSettings {
    group: {
        id: string;
        name: string;
        masterTenant: { id: string; name: string };
        syncStrategy: 'master_wins' | 'last_write_wins' | 'manual_review';
        tenantCount: number;
        userCount: number;
    } | null;
    tenantSettings: {
        syncEnabled: boolean;
        defaultRole: string;
        autoAcceptUsers: boolean;
    };
}

interface Props {
    federation: FederationSettings | null;
    pendingInvitations: Array<{
        id: string;
        groupName: string;
        invitedBy: string;
        expiresAt: string;
        token: string;
    }>;
    canCreateGroup: boolean;
    isMaster: boolean;
}

export default function FederationSettingsPage({
    federation,
    pendingInvitations,
    canCreateGroup,
    isMaster,
}: Props) {
    const { post, processing } = useForm();

    const handleAcceptInvitation = (token: string) => {
        post(route('tenant.admin.federation.accept'), {
            data: { token },
        });
    };

    const handleLeave = () => {
        if (confirm('Are you sure you want to leave this federation group?')) {
            post(route('tenant.admin.federation.leave'));
        }
    };

    return (
        <AppLayout>
            <Head title="Federation Settings" />

            <SettingsLayout>
                <div className="space-y-6">
                    {/* Header */}
                    <div>
                        <h2 className="text-2xl font-bold tracking-tight">
                            User Federation
                        </h2>
                        <p className="text-muted-foreground">
                            Synchronize users across multiple tenants in your organization.
                        </p>
                    </div>

                    {/* Pending Invitations */}
                    {pendingInvitations.length > 0 && (
                        <Alert>
                            <AlertTriangle className="h-4 w-4" />
                            <AlertDescription>
                                You have {pendingInvitations.length} pending federation invitation(s).
                            </AlertDescription>
                        </Alert>
                    )}

                    {pendingInvitations.map((invitation) => (
                        <Card key={invitation.id}>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <Link2 className="h-5 w-5" />
                                    Invitation to {invitation.groupName}
                                </CardTitle>
                                <CardDescription>
                                    Invited by {invitation.invitedBy}
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="flex gap-2">
                                <Button
                                    onClick={() => handleAcceptInvitation(invitation.token)}
                                    disabled={processing}
                                >
                                    Accept
                                </Button>
                                <Button variant="outline">
                                    Decline
                                </Button>
                            </CardContent>
                        </Card>
                    ))}

                    {/* Current Federation */}
                    {federation?.group ? (
                        <Card>
                            <CardHeader>
                                <div className="flex items-center justify-between">
                                    <div>
                                        <CardTitle className="flex items-center gap-2">
                                            <Users className="h-5 w-5" />
                                            {federation.group.name}
                                        </CardTitle>
                                        <CardDescription>
                                            {isMaster ? 'Master Tenant' : 'Member Tenant'}
                                        </CardDescription>
                                    </div>
                                    <Badge variant={isMaster ? 'default' : 'secondary'}>
                                        {isMaster ? 'Master' : 'Member'}
                                    </Badge>
                                </div>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                {/* Stats */}
                                <div className="grid grid-cols-3 gap-4">
                                    <div className="text-center">
                                        <div className="text-2xl font-bold">
                                            {federation.group.tenantCount}
                                        </div>
                                        <div className="text-sm text-muted-foreground">
                                            Tenants
                                        </div>
                                    </div>
                                    <div className="text-center">
                                        <div className="text-2xl font-bold">
                                            {federation.group.userCount}
                                        </div>
                                        <div className="text-sm text-muted-foreground">
                                            Federated Users
                                        </div>
                                    </div>
                                    <div className="text-center">
                                        <div className="text-2xl font-bold capitalize">
                                            {federation.group.syncStrategy.replace('_', ' ')}
                                        </div>
                                        <div className="text-sm text-muted-foreground">
                                            Sync Strategy
                                        </div>
                                    </div>
                                </div>

                                {/* Settings */}
                                <div className="border-t pt-4 space-y-4">
                                    <div className="flex items-center justify-between">
                                        <div>
                                            <div className="font-medium">Sync Enabled</div>
                                            <div className="text-sm text-muted-foreground">
                                                Receive user updates from other tenants
                                            </div>
                                        </div>
                                        <Switch
                                            checked={federation.tenantSettings.syncEnabled}
                                            disabled={isMaster}
                                        />
                                    </div>

                                    <div className="flex items-center justify-between">
                                        <div>
                                            <div className="font-medium">Auto-Accept Users</div>
                                            <div className="text-sm text-muted-foreground">
                                                Automatically create accounts for federated users
                                            </div>
                                        </div>
                                        <Switch
                                            checked={federation.tenantSettings.autoAcceptUsers}
                                        />
                                    </div>
                                </div>

                                {/* Leave Button */}
                                {!isMaster && (
                                    <div className="border-t pt-4">
                                        <Button
                                            variant="destructive"
                                            onClick={handleLeave}
                                            disabled={processing}
                                        >
                                            Leave Federation
                                        </Button>
                                    </div>
                                )}
                            </CardContent>
                        </Card>
                    ) : (
                        <Card>
                            <CardContent className="py-12 text-center">
                                <Users className="h-12 w-12 mx-auto text-muted-foreground mb-4" />
                                <h3 className="text-lg font-medium mb-2">
                                    No Federation Group
                                </h3>
                                <p className="text-muted-foreground mb-4">
                                    This tenant is not part of any federation group.
                                    {canCreateGroup && ' You can create one or wait for an invitation.'}
                                </p>
                                {canCreateGroup && (
                                    <Button>
                                        Create Federation Group
                                    </Button>
                                )}
                            </CardContent>
                        </Card>
                    )}

                    {/* Federated User Indicator Example */}
                    {federation?.group && (
                        <Card>
                            <CardHeader>
                                <CardTitle>Federated User Badge</CardTitle>
                                <CardDescription>
                                    This badge appears on user profiles that are synchronized
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <div className="flex items-center gap-2 p-3 bg-muted rounded-lg">
                                    <div className="h-10 w-10 rounded-full bg-primary/10 flex items-center justify-center">
                                        JS
                                    </div>
                                    <div>
                                        <div className="font-medium flex items-center gap-2">
                                            John Smith
                                            <Badge variant="outline" className="text-xs">
                                                <Link2 className="h-3 w-3 mr-1" />
                                                Federated
                                            </Badge>
                                        </div>
                                        <div className="text-sm text-muted-foreground">
                                            john@acme.com
                                        </div>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    )}
                </div>
            </SettingsLayout>
        </AppLayout>
    );
}
```

### 7.3 Componente de Badge Federado

```tsx
// resources/js/components/federated-user-badge.tsx

import { Badge } from '@/components/ui/badge';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { Link2 } from 'lucide-react';

interface FederatedUserBadgeProps {
    groupName?: string;
    syncedAt?: string;
    isMasterUser?: boolean;
}

export function FederatedUserBadge({
    groupName,
    syncedAt,
    isMasterUser,
}: FederatedUserBadgeProps) {
    return (
        <TooltipProvider>
            <Tooltip>
                <TooltipTrigger>
                    <Badge
                        variant={isMasterUser ? 'default' : 'outline'}
                        className="text-xs cursor-help"
                    >
                        <Link2 className="h-3 w-3 mr-1" />
                        {isMasterUser ? 'Master' : 'Federated'}
                    </Badge>
                </TooltipTrigger>
                <TooltipContent>
                    <div className="text-sm">
                        <div className="font-medium">
                            {isMasterUser
                                ? 'Master User (Source of Truth)'
                                : 'Federated User'}
                        </div>
                        {groupName && (
                            <div className="text-muted-foreground">
                                Group: {groupName}
                            </div>
                        )}
                        {syncedAt && (
                            <div className="text-muted-foreground">
                                Last synced: {syncedAt}
                            </div>
                        )}
                    </div>
                </TooltipContent>
            </Tooltip>
        </TooltipProvider>
    );
}
```

---

## 8. Seguranca

### 8.1 Permissoes Necessarias

```php
// app/Enums/TenantPermission.php (adicionar)

case FEDERATION_VIEW = 'federation:view';
case FEDERATION_MANAGE = 'federation:manage';

// app/Enums/CentralPermission.php (adicionar)

case FEDERATION_GROUPS_VIEW = 'federation:groups:view';
case FEDERATION_GROUPS_CREATE = 'federation:groups:create';
case FEDERATION_GROUPS_EDIT = 'federation:groups:edit';
case FEDERATION_GROUPS_DELETE = 'federation:groups:delete';
case FEDERATION_CONFLICTS_RESOLVE = 'federation:conflicts:resolve';
```

### 8.2 Audit Log

```php
<?php

namespace App\Services\Central;

use App\Models\Central\FederationSyncLog;

class FederationAuditService
{
    public function log(
        string $groupId,
        string $operation,
        ?string $federatedUserId = null,
        ?string $sourceTenantId = null,
        ?string $targetTenantId = null,
        ?string $actorUserId = null,
        ?array $oldData = null,
        ?array $newData = null,
        string $status = 'success',
        ?string $errorMessage = null
    ): FederationSyncLog {
        return FederationSyncLog::create([
            'federation_group_id' => $groupId,
            'federated_user_id' => $federatedUserId,
            'operation' => $operation,
            'source_tenant_id' => $sourceTenantId,
            'target_tenant_id' => $targetTenantId,
            'actor_user_id' => $actorUserId,
            'old_data' => $oldData,
            'new_data' => $newData,
            'status' => $status,
            'error_message' => $errorMessage,
        ]);
    }
}
```

### 8.3 Checklist de Seguranca

- [ ] Apenas owners podem criar/gerenciar federation groups
- [ ] Master tenant nao pode ser removido do grupo
- [ ] Senhas sao sincronizadas como hash (nunca em texto)
- [ ] 2FA secrets sao criptografados no synced_data
- [ ] Tokens de convite expiram em 7 dias
- [ ] Audit trail completo de todas as operacoes de sync
- [ ] Rate limiting em operacoes de sync (max 100/minuto)
- [ ] Validacao de email antes de criar usuario federado
- [ ] Roles NAO sao sincronizados (isolamento de permissoes)

---

## 9. Migrations

### 9.1 Lista Completa

```bash
# Ordem de execucao
database/migrations/
├── 2025_12_10_000001_create_federation_groups_table.php
├── 2025_12_10_000002_create_federation_group_tenants_table.php
├── 2025_12_10_000003_create_federated_users_table.php
├── 2025_12_10_000004_create_federated_user_links_table.php
├── 2025_12_10_000005_create_federation_sync_logs_table.php
├── 2025_12_10_000006_create_federation_conflicts_table.php
└── 2025_12_10_000007_create_federation_invitations_table.php
```

### 9.2 Migration: federation_invitations

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('federation_invitations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('federation_group_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->foreignUuid('tenant_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->foreignUuid('invited_by')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->string('token', 64)->unique();
            $table->json('settings')->nullable();

            $table->enum('status', ['pending', 'accepted', 'rejected', 'expired'])
                ->default('pending');

            $table->timestamp('expires_at');
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index('token');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('federation_invitations');
    }
};
```

---

## 10. Testes

### 10.1 Testes PHPUnit

```php
<?php
// tests/Feature/Federation/FederationGroupTest.php

namespace Tests\Feature\Federation;

use App\Models\Central\FederationGroup;
use App\Models\Central\Tenant;
use App\Models\Central\User as CentralUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FederationGroupTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_federation_group(): void
    {
        $admin = CentralUser::factory()->create(['is_super_admin' => true]);
        $tenant = Tenant::factory()->create();

        $response = $this->actingAs($admin, 'central')
            ->post(route('central.admin.federation.store'), [
                'name' => 'ACME Group',
                'master_tenant_id' => $tenant->id,
                'sync_strategy' => 'master_wins',
            ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('federation_groups', [
            'name' => 'ACME Group',
            'master_tenant_id' => $tenant->id,
        ]);
    }

    public function test_tenant_can_only_be_in_one_group(): void
    {
        $admin = CentralUser::factory()->create(['is_super_admin' => true]);
        $tenant = Tenant::factory()->create();

        // Cria primeiro grupo
        FederationGroup::factory()->create([
            'master_tenant_id' => $tenant->id,
        ]);

        // Tenta criar segundo grupo com mesmo tenant
        $response = $this->actingAs($admin, 'central')
            ->post(route('central.admin.federation.store'), [
                'name' => 'Another Group',
                'master_tenant_id' => $tenant->id,
                'sync_strategy' => 'master_wins',
            ]);

        $response->assertSessionHasErrors('master_tenant_id');
    }

    public function test_master_tenant_cannot_leave_group(): void
    {
        $group = FederationGroup::factory()
            ->has(Tenant::factory(), 'masterTenant')
            ->create();

        $tenant = $group->masterTenant;

        $this->assertFalse($group->canLeave($tenant));
    }
}
```

### 10.2 Testes de Sincronizacao

```php
<?php
// tests/Feature/Federation/UserSyncTest.php

namespace Tests\Feature\Federation;

use App\Jobs\Federation\SyncUserToFederatedTenantsJob;
use App\Models\Central\FederatedUser;
use App\Models\Central\FederatedUserLink;
use App\Models\Central\FederationGroup;
use App\Models\Central\Tenant;
use App\Models\Tenant\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class UserSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_update_propagates_to_federated_tenants(): void
    {
        Queue::fake();

        // Setup
        $group = FederationGroup::factory()->create(['sync_strategy' => 'master_wins']);
        $masterTenant = $group->masterTenant;
        $memberTenant = Tenant::factory()->create();

        $group->tenants()->attach($memberTenant, [
            'sync_enabled' => true,
            'joined_at' => now(),
        ]);

        // Cria usuario no master
        $federatedUser = FederatedUser::factory()->create([
            'federation_group_id' => $group->id,
            'master_tenant_id' => $masterTenant->id,
            'global_email' => 'john@acme.com',
            'synced_data' => ['name' => 'John Doe', 'password_hash' => 'hash'],
        ]);

        // Cria link com member tenant
        FederatedUserLink::factory()->create([
            'federated_user_id' => $federatedUser->id,
            'tenant_id' => $memberTenant->id,
            'tenant_user_id' => 'user-uuid-123',
            'sync_status' => 'synced',
        ]);

        // Dispara job de sync
        SyncUserToFederatedTenantsJob::dispatch($federatedUser, ['name']);

        Queue::assertPushed(SyncUserToFederatedTenantsJob::class);
    }

    public function test_password_change_syncs_immediately(): void
    {
        Queue::fake();

        $group = FederationGroup::factory()->create();
        $federatedUser = FederatedUser::factory()->create([
            'federation_group_id' => $group->id,
        ]);

        // Simula alteracao de senha
        event(new \App\Events\Federation\UserPasswordChangedInFederatedTenant(
            $federatedUser,
            'new_hash_123',
            $group->master_tenant_id
        ));

        Queue::assertPushed(\App\Jobs\Federation\PropagatePasswordChangeJob::class);
    }
}
```

### 10.3 Testes E2E Playwright

```typescript
// tests/Browser/federation.spec.ts

import { test, expect } from '@playwright/test';

test.describe('User Sync Federation', () => {
    test('owner can create federation group', async ({ page }) => {
        // Login como owner do tenant
        await page.goto('http://tenant1.test/login');
        await page.fill('input[name="email"]', 'john@acme.com');
        await page.fill('input[name="password"]', 'password');
        await page.click('button[type="submit"]');

        // Navega para settings > federation
        await page.goto('http://tenant1.test/admin/settings/federation');

        // Verifica que pode criar grupo
        await expect(page.locator('text=Create Federation Group')).toBeVisible();
    });

    test('federated user badge appears on profile', async ({ page }) => {
        // Login em tenant federado
        await page.goto('http://tenant2.test/login');
        await page.fill('input[name="email"]', 'maria@acme.com');
        await page.fill('input[name="password"]', 'password');
        await page.click('button[type="submit"]');

        // Navega para team
        await page.goto('http://tenant2.test/admin/team');

        // Verifica badge de usuario federado
        await expect(page.locator('[data-testid="federated-badge"]')).toBeVisible();
    });

    test('password change syncs across tenants', async ({ page, context }) => {
        // Login no master tenant
        await page.goto('http://tenant1.test/login');
        await page.fill('input[name="email"]', 'john@acme.com');
        await page.fill('input[name="password"]', 'password');
        await page.click('button[type="submit"]');

        // Altera senha
        await page.goto('http://tenant1.test/admin/settings/password');
        await page.fill('input[name="current_password"]', 'password');
        await page.fill('input[name="password"]', 'newpassword123');
        await page.fill('input[name="password_confirmation"]', 'newpassword123');
        await page.click('button[type="submit"]');

        // Aguarda sync
        await page.waitForTimeout(2000);

        // Abre nova aba para tenant2
        const page2 = await context.newPage();
        await page2.goto('http://tenant2.test/login');
        await page2.fill('input[name="email"]', 'john@acme.com');
        await page2.fill('input[name="password"]', 'newpassword123');
        await page2.click('button[type="submit"]');

        // Verifica login bem sucedido
        await expect(page2).toHaveURL(/dashboard/);
    });
});
```

---

## 11. Consideracoes de Performance

### 11.1 Queue Jobs para Sync Assincrono

```php
// config/queue.php - adicionar queue dedicada

'connections' => [
    'redis' => [
        // ...
    ],
],

// Queue dedicada para federation
'federation' => [
    'driver' => 'redis',
    'connection' => 'queue',
    'queue' => 'federation',
    'retry_after' => 90,
    'block_for' => null,
],
```

```bash
# Worker dedicado para federation jobs
sail artisan queue:work --queue=federation,high,default
```

### 11.2 Debounce de Updates Frequentes

```php
<?php

namespace App\Services\Central;

use Illuminate\Support\Facades\Cache;

class FederationDebounceService
{
    /**
     * Debounce updates do mesmo usuario.
     * Se multiplos updates vierem em 5 segundos, processa apenas o ultimo.
     */
    public function shouldSync(string $federatedUserId, string $field): bool
    {
        $key = "federation:debounce:{$federatedUserId}:{$field}";

        if (Cache::has($key)) {
            // Update recente, ignora este
            return false;
        }

        // Marca como "em processamento" por 5 segundos
        Cache::put($key, true, 5);

        return true;
    }
}
```

### 11.3 Cache de Federation Group Membership

```php
<?php

namespace App\Services\Central;

use App\Models\Central\FederationGroup;
use App\Models\Central\Tenant;
use Illuminate\Support\Facades\Cache;

class FederationCacheService
{
    private const CACHE_TTL = 3600; // 1 hora

    /**
     * Obtem grupo do tenant (com cache).
     */
    public function getTenantGroup(string $tenantId): ?FederationGroup
    {
        return Cache::remember(
            "tenant:{$tenantId}:federation_group",
            self::CACHE_TTL,
            fn() => FederationGroup::whereHas('tenants', fn($q) =>
                $q->where('tenants.id', $tenantId)
                  ->whereNull('left_at')
            )->first()
        );
    }

    /**
     * Invalida cache do tenant.
     */
    public function invalidateTenant(string $tenantId): void
    {
        Cache::forget("tenant:{$tenantId}:federation_group");
    }

    /**
     * Obtem IDs de tenants do grupo (com cache).
     */
    public function getGroupTenantIds(string $groupId): array
    {
        return Cache::remember(
            "federation_group:{$groupId}:tenant_ids",
            self::CACHE_TTL,
            fn() => FederationGroup::find($groupId)
                ?->activeTenants()
                ->pluck('tenants.id')
                ->toArray() ?? []
        );
    }
}
```

### 11.4 Batch Processing para Sync Inicial

```php
<?php

namespace App\Jobs\Federation;

use App\Models\Central\FederationGroup;
use App\Models\Central\Tenant;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Bus;

/**
 * Indexa usuarios existentes quando tenant entra no grupo.
 * Usa batching para processar em chunks.
 */
class IndexExistingUsersJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public FederationGroup $group,
        public Tenant $tenant
    ) {}

    public function handle(): void
    {
        $jobs = [];

        // Busca usuarios do tenant em chunks
        $this->tenant->run(function () use (&$jobs) {
            \App\Models\Tenant\User::chunk(100, function ($users) use (&$jobs) {
                foreach ($users as $user) {
                    $jobs[] = new CreateFederatedUserFromExistingJob(
                        $this->group,
                        $this->tenant,
                        $user->id,
                        $user->email,
                        $user->only(['name', 'password', 'locale'])
                    );
                }
            });
        });

        // Processa em batch
        if (!empty($jobs)) {
            Bus::batch($jobs)
                ->name("federation-index-{$this->tenant->id}")
                ->onQueue('federation')
                ->dispatch();
        }
    }
}
```

---

## 12. Plano de Implementacao

> **NOTA**: Estamos em ambiente de desenvolvimento. Nao ha dados legados.
> - Migrations sao editadas diretamente (sem criar novas migrations de alteracao)
> - Seeders de teste sao criados/atualizados
> - Comando de reset: `sail artisan migrate:fresh --seed`

### Fase 1: Foundation (1-2 dias)

- [ ] Editar/criar migrations centrais para federation
- [ ] Editar migration tenant para adicionar `federated_user_id` em users
- [ ] Criar models: FederationGroup, FederatedUser, FederatedUserLink
- [ ] Criar models: FederationSyncLog, FederationConflict
- [ ] Adicionar trait HasFederation ao Tenant\User
- [ ] Adicionar permissoes aos enums (CentralPermission, TenantPermission)
- [ ] Criar FederationSeeder com dados de teste

### Fase 2: Core Service Layer (2-3 dias)

- [ ] Criar FederationService (Central)
- [ ] Criar FederationService (Tenant)
- [ ] Criar FederationAuditService
- [ ] Criar FederationCacheService
- [ ] Criar FederationDebounceService

### Fase 3: Jobs e Events (2-3 dias)

- [ ] Criar Events de federation
- [ ] Criar SyncUserToFederatedTenantsJob
- [ ] Criar UpdateLinkedUserJob
- [ ] Criar CreateUserInTenantJob
- [ ] Criar PropagatePasswordChangeJob
- [ ] Criar IndexExistingUsersJob
- [ ] Configurar queue dedicada

### Fase 4: API Controllers (2 dias)

- [ ] Criar FederationGroupController (Central)
- [ ] Criar FederationConflictController (Central)
- [ ] Criar FederationSettingsController (Tenant)
- [ ] Criar FederatedUserController (Tenant)
- [ ] Criar Form Requests

### Fase 5: Frontend (2-3 dias)

- [ ] Criar pagina central/admin/federation/index
- [ ] Criar pagina central/admin/federation/show
- [ ] Criar pagina tenant/admin/settings/federation
- [ ] Criar componente FederatedUserBadge
- [ ] Integrar badge na pagina de Team

### Fase 6: Testes (2 dias)

- [ ] Escrever testes PHPUnit para services
- [ ] Escrever testes de integracao para jobs
- [ ] Escrever testes E2E Playwright
- [ ] Atualizar CLAUDE.md com nova feature

---

## 13. Estimativa de Complexidade

| Fase | Complexidade | Estimativa | Riscos |
|------|-------------|------------|--------|
| Foundation | Media | 1-2 dias | Baixo |
| Core Services | Alta | 2-3 dias | Medio (logica de sync) |
| Jobs/Events | Alta | 2-3 dias | Medio (race conditions) |
| API Controllers | Media | 2 dias | Baixo |
| Frontend | Media | 2-3 dias | Baixo |
| Testes | Media | 2 dias | Baixo |

**Total Estimado**: 11-16 dias de desenvolvimento

**Simplificacoes (ambiente dev)**:
- Sem migrations de alteracao (edita direto)
- Sem scripts de migracao de dados
- Seeders criam cenarios de teste prontos
- `sail artisan migrate:fresh --seed` recria tudo

**Principais Riscos**:
1. Race conditions em updates simultaneos → Mitigado por sync_version e locking
2. Performance com muitos tenants → Mitigado por queue e batching
3. Complexidade de resolucao de conflitos → Mitigado por estrategia "master_wins" como padrao

---

## 14. Referencias

- [Stancl/Tenancy v4 - Multi-Database Tenancy](https://v4.tenancyforlaravel.com/multi-database-tenancy)
- [Laravel Queues - Job Batching](https://laravel.com/docs/12.x/queues#job-batching)
- [Eventual Consistency Patterns](https://docs.microsoft.com/en-us/azure/architecture/patterns/eventual-consistency)
- [CQRS Pattern](https://docs.microsoft.com/en-us/azure/architecture/patterns/cqrs)

---

## Changelog

| Versao | Data | Alteracoes |
|--------|------|------------|
| 1.0 | 2025-12-08 | Documento inicial |
| 1.1 | 2025-12-08 | Ajuste para ambiente dev (migrations diretas, seeders) |
