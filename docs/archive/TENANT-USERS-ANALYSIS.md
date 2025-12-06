# Análise: Migração de Usuários para Bancos de Tenant

> **Status**: Documento de Análise para Decisão
> **Data**: Dezembro 2025
> **Versão Tenancy**: Stancl/Tenancy v4

---

## 1. Contexto Atual

### 1.1 Arquitetura Existente

```
┌─────────────────────────────────────────────────────────────────────┐
│                     CENTRAL DATABASE (laravel)                       │
├─────────────────────────────────────────────────────────────────────┤
│  ✅ users                    │  ✅ tenants                          │
│  ✅ tenant_user (pivot)      │  ✅ domains                          │
│  ✅ plans, addons            │  ✅ subscriptions                    │
│  ✅ personal_access_tokens   │  ✅ password_reset_tokens            │
│  ✅ sessions                 │  ✅ telescope_entries                │
│  ✅ impersonation_tokens     │                                      │
└─────────────────────────────────────────────────────────────────────┘
                                    │
                ┌───────────────────┼───────────────────┐
                ▼                   ▼                   ▼
┌─────────────────────┐  ┌─────────────────────┐  ┌─────────────────────┐
│ TENANT DB 1         │  │ TENANT DB 2         │  │ TENANT DB N         │
│ (tenant_xxx)        │  │ (tenant_yyy)        │  │ (tenant_zzz)        │
├─────────────────────┤  ├─────────────────────┤  ├─────────────────────┤
│ ❌ users (ausente)  │  │ ❌ users (ausente)  │  │ ❌ users (ausente)  │
│ ✅ roles            │  │ ✅ roles            │  │ ✅ roles            │
│ ✅ permissions      │  │ ✅ permissions      │  │ ✅ permissions      │
│ ✅ model_has_roles  │  │ ✅ model_has_roles  │  │ ✅ model_has_roles  │
│ ✅ projects         │  │ ✅ projects         │  │ ✅ projects         │
│ ✅ activity_log     │  │ ✅ activity_log     │  │ ✅ activity_log     │
│ ✅ media            │  │ ✅ media            │  │ ✅ media            │
└─────────────────────┘  └─────────────────────┘  └─────────────────────┘
```

### 1.2 Problemas da Arquitetura Atual

#### 1.2.1 Cross-Database Queries (Workarounds)

O `User` model usa `CentralConnection`, mas `roles` e `permissions` estão no banco do tenant:

```php
// app/Models/User.php - WORKAROUND atual
public function roles(): MorphToMany
{
    // Temporariamente remove connection override para usar tenant
    $originalConnection = $this->connection;

    if (tenancy()->initialized) {
        $this->connection = null; // Usa default (tenant)
    }

    $relation = $this->morphToMany(/*...*/);

    $this->connection = $originalConnection; // Restaura

    return $relation;
}
```

**Problemas**:
- Código frágil e difícil de manter
- Potencial para bugs em edge cases
- Não funciona bem com eager loading
- Performance ruim (não pode fazer joins nativos)

#### 1.2.2 Verificação de Acesso Complexa

```php
// app/Http/Middleware/VerifyTenantAccess.php
protected function handleRegularUser(Request $request, Closure $next): Response
{
    $user = $request->user();

    // Depende do SpatiePermissionsBootstrapper ter mudado o contexto
    // Se falhar, usuário pode ter acesso indevido ou ser bloqueado incorretamente
    if ($user->getRoleNames()->isEmpty()) {
        abort(403, __('You do not have access to this tenant.'));
    }

    return $next($request);
}
```

#### 1.2.3 Seamless Login / Impersonation

O sistema atual requer tokens complexos porque usuários estão em banco diferente:

```php
// routes/tenant.php - Seamless login
Route::get('/auth/seamless/{token}', function (string $token) {
    // Busca token em central
    $impersonationToken = ImpersonationToken::where('token', $token)->first();

    // Busca user em central
    $user = User::find($impersonationToken->user_id);

    // Login em contexto de tenant
    Auth::login($user);

    // Limpa flag de impersonation
    session()->forget('tenancy_impersonating');
});
```

#### 1.2.4 Activity Log e Audit

```php
// Activity log registra user_id, mas user está em outro banco
// Não é possível fazer join direto para relatórios
```

---

## 2. Solução Proposta: Resource Syncing (Stancl v4)

### 2.1 O que é Resource Syncing?

Feature nativa do Stancl/Tenancy v4 que permite sincronizar recursos entre bancos central e tenant.

**Conceito**:
- **SyncMaster** (Central): Modelo "master" no banco central
- **Syncable** (Tenant): Modelo "espelho" em cada banco de tenant
- **global_id**: Identificador único que associa os dois

### 2.2 Arquitetura Proposta

```
┌─────────────────────────────────────────────────────────────────────┐
│                     CENTRAL DATABASE (laravel)                       │
├─────────────────────────────────────────────────────────────────────┤
│  ✅ users (CentralUser)      │  ✅ tenant_resources (pivot)         │
│     - id (UUID)              │     - tenant_id                      │
│     - global_id (UUID)       │     - resource_type                  │
│     - name                   │     - resource_id (global_id)        │
│     - email                  │                                      │
│     - password               │                                      │
│     - email_verified_at      │                                      │
│  ✅ tenants, domains         │  ✅ plans, addons                    │
└─────────────────────────────────────────────────────────────────────┘
                                    │
           Sync automático via ─────┼───── ResourceSyncing Events
                                    │
                ┌───────────────────┼───────────────────┐
                ▼                   ▼                   ▼
┌─────────────────────┐  ┌─────────────────────┐  ┌─────────────────────┐
│ TENANT DB 1         │  │ TENANT DB 2         │  │ TENANT DB N         │
├─────────────────────┤  ├─────────────────────┤  ├─────────────────────┤
│ ✅ users (TenantUser)│ │ ✅ users            │  │ ✅ users            │
│    - id (local)     │  │    - global_id      │  │    - global_id      │
│    - global_id      │  │    - name (synced)  │  │    - name (synced)  │
│    - name (synced)  │  │    - email (synced) │  │    - email (synced) │
│    - email (synced) │  │    - password(sync) │  │    - password(sync) │
│    - password(sync) │  │    - tenant_role    │  │    - tenant_role    │
│    - tenant_role    │  │    - custom_fields  │  │    - custom_fields  │
│ ✅ roles            │  │ ✅ roles            │  │ ✅ roles            │
│ ✅ model_has_roles  │  │ ✅ model_has_roles  │  │ ✅ model_has_roles  │
│ ✅ projects         │  │ ✅ projects         │  │ ✅ projects         │
│ ✅ activity_log     │  │ ✅ activity_log     │  │ ✅ activity_log     │
└─────────────────────┘  └─────────────────────┘  └─────────────────────┘
```

### 2.3 Implementação Resource Syncing

#### 2.3.1 CentralUser Model (SyncMaster)

```php
<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Stancl\Tenancy\Database\Concerns\CentralConnection;
use Stancl\Tenancy\Contracts\SyncMaster;
use Stancl\Tenancy\Database\Concerns\ResourceSyncing;

class CentralUser extends Authenticatable implements SyncMaster
{
    use CentralConnection, ResourceSyncing;

    protected $table = 'users';

    protected $fillable = [
        'global_id',
        'name',
        'email',
        'password',
        'email_verified_at',
        'locale',
    ];

    /**
     * Modelo do tenant que será sincronizado
     */
    public function getTenantModelName(): string
    {
        return TenantUser::class;
    }

    /**
     * Nome do modelo central (si mesmo)
     */
    public function getCentralModelName(): string
    {
        return static::class;
    }

    /**
     * Atributos que serão sincronizados automaticamente
     */
    public function getSyncedAttributeNames(): array
    {
        return [
            'global_id',
            'name',
            'email',
            'password',
            'email_verified_at',
            'locale',
        ];
    }

    /**
     * Relacionamento com tenants (quais tenants este user pertence)
     */
    public function tenants(): MorphToMany
    {
        return $this->morphToMany(
            Tenant::class,
            'resource',
            'tenant_resources',
            'resource_id',
            'tenant_id',
            'global_id',
            'id'
        );
    }
}
```

#### 2.3.2 TenantUser Model (Syncable)

```php
<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Stancl\Tenancy\Contracts\Syncable;
use Stancl\Tenancy\Database\Concerns\ResourceSyncing;
use Spatie\Permission\Traits\HasRoles;

class TenantUser extends Authenticatable implements Syncable
{
    use ResourceSyncing, HasRoles;

    protected $table = 'users';

    protected $fillable = [
        'global_id',
        'name',
        'email',
        'password',
        'email_verified_at',
        'locale',
        // Campos específicos do tenant
        'department',
        'employee_id',
        'custom_settings',
    ];

    /**
     * Nome do modelo central
     */
    public function getCentralModelName(): string
    {
        return CentralUser::class;
    }

    /**
     * Atributos sincronizados com central
     */
    public function getSyncedAttributeNames(): array
    {
        return [
            'global_id',
            'name',
            'email',
            'password',
            'email_verified_at',
            'locale',
        ];
    }

    /**
     * Campos extras ao criar central a partir do tenant
     */
    public function getCreationAttributes(): array
    {
        return [
            'global_id',
            'name',
            'email',
            'password',
            'email_verified_at' => now(),
        ];
    }

    /**
     * Roles agora são LOCAIS (mesmo banco)
     * Sem workarounds de connection switching!
     */
    // HasRoles trait funciona nativamente
}
```

#### 2.3.3 Migration para Tenant Users

```php
// database/migrations/tenant/0001_01_01_000000_create_users_table.php

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('global_id')->unique(); // Link com central
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->string('locale')->nullable();

            // Campos específicos do tenant
            $table->string('department')->nullable();
            $table->string('employee_id')->nullable();
            $table->json('custom_settings')->nullable();

            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();

            $table->index('global_id');
        });
    }
};
```

#### 2.3.4 Configuração de Eventos

```php
// app/Providers/TenancyServiceProvider.php

public function events(): array
{
    return [
        // ... outros eventos ...

        // Resource Syncing Events
        ResourceSyncing\Events\SyncedResourceSaved::class => [
            ResourceSyncing\Listeners\UpdateOrCreateSyncedResource::class,
        ],
        ResourceSyncing\Events\SyncMasterDeleted::class => [
            ResourceSyncing\Listeners\DeleteResourcesInTenants::class,
        ],
        ResourceSyncing\Events\CentralResourceAttachedToTenant::class => [
            ResourceSyncing\Listeners\CreateTenantResource::class,
        ],
        ResourceSyncing\Events\CentralResourceDetachedFromTenant::class => [
            ResourceSyncing\Listeners\DeleteResourceInTenants::class,
        ],
    ];
}
```

---

## 3. Comparação de Abordagens

### 3.1 Opção A: Manter Arquitetura Atual (Não Recomendado)

| Aspecto | Avaliação |
|---------|-----------|
| **Complexidade** | 🔴 Alta - workarounds de connection switching |
| **Manutenção** | 🔴 Difícil - código frágil |
| **Performance** | 🟡 Média - sem joins nativos |
| **LGPD/HIPAA** | 🟡 Parcial - dados de user em banco central |
| **Esforço** | 🟢 Zero - já implementado |

### 3.2 Opção B: Resource Syncing (Recomendado)

| Aspecto | Avaliação |
|---------|-----------|
| **Complexidade** | 🟢 Baixa - padrão nativo do v4 |
| **Manutenção** | 🟢 Fácil - código limpo |
| **Performance** | 🟢 Alta - joins nativos no tenant |
| **LGPD/HIPAA** | 🟢 Total - dados isolados por tenant |
| **Esforço** | 🟡 Médio - migração necessária |

### 3.3 Opção C: Usuários Apenas em Tenant (Máximo Isolamento)

| Aspecto | Avaliação |
|---------|-----------|
| **Complexidade** | 🟡 Média - sem sincronização |
| **Manutenção** | 🟢 Fácil - arquitetura simples |
| **Performance** | 🟢 Alta - tudo local |
| **LGPD/HIPAA** | 🟢 Total - isolamento máximo |
| **Esforço** | 🔴 Alto - repensar toda autenticação |

---

## 4. Fluxos com Resource Syncing

### 4.1 Registro de Novo Usuário (via Central)

```
┌─────────────┐     ┌─────────────────────────────────────────────────┐
│  Registro   │────▶│ 1. CentralUser::create(['email' => '...'])     │
│  (central)  │     │ 2. SyncedResourceSaved event fired             │
└─────────────┘     │ 3. Para cada tenant associado:                 │
                    │    - TenantUser::create() no banco do tenant   │
                    │    - Atributos synced copiados automaticamente │
                    └─────────────────────────────────────────────────┘
```

### 4.2 Login em Tenant

```
┌─────────────┐     ┌─────────────────────────────────────────────────┐
│   Login     │────▶│ 1. Request chega em tenant1.localhost          │
│  (tenant)   │     │ 2. Tenancy inicializado (DatabaseBootstrapper) │
└─────────────┘     │ 3. Auth::attempt() usa TenantUser local        │
                    │ 4. Roles/Permissions no MESMO banco            │
                    │ 5. Zero workarounds necessários!               │
                    └─────────────────────────────────────────────────┘
```

### 4.3 Atualização de Perfil

```
┌─────────────┐     ┌─────────────────────────────────────────────────┐
│  Update     │────▶│ 1. TenantUser->update(['name' => 'Novo Nome']) │
│  (tenant)   │     │ 2. SyncedResourceSaved event fired             │
└─────────────┘     │ 3. CentralUser atualizado automaticamente      │
                    │ 4. Outros tenants do mesmo user atualizados    │
                    └─────────────────────────────────────────────────┘
```

### 4.4 Convite para Tenant

```
┌─────────────┐     ┌─────────────────────────────────────────────────┐
│  Convite    │────▶│ 1. Buscar CentralUser por email                │
│  (tenant)   │     │ 2. Se não existe: criar CentralUser            │
└─────────────┘     │ 3. Associar ao tenant via tenant_resources     │
                    │ 4. CentralResourceAttachedToTenant event       │
                    │ 5. TenantUser criado automaticamente           │
                    │ 6. Atribuir role local (owner/admin/member)    │
                    └─────────────────────────────────────────────────┘
```

---

## 5. Benefícios da Migração

### 5.1 Código Mais Limpo

**Antes** (workaround):
```php
public function roles(): MorphToMany
{
    $originalConnection = $this->connection;
    if (tenancy()->initialized) {
        $this->connection = null;
    }
    $relation = $this->morphToMany(/*...*/);
    $this->connection = $originalConnection;
    return $relation;
}
```

**Depois** (nativo):
```php
// Nenhum código especial necessário!
// HasRoles trait funciona nativamente porque user e roles
// estão no mesmo banco (tenant)
```

### 5.2 Performance Melhor

**Antes**:
```sql
-- Duas queries separadas (bancos diferentes)
SELECT * FROM central.users WHERE id = ?;
SELECT * FROM tenant_xxx.model_has_roles WHERE model_id = ?;
```

**Depois**:
```sql
-- Uma query com JOIN (mesmo banco)
SELECT users.*, roles.name as role_name
FROM users
JOIN model_has_roles ON users.id = model_has_roles.model_id
JOIN roles ON model_has_roles.role_id = roles.id
WHERE users.id = ?;
```

### 5.3 Activity Log Completo

**Antes**:
```php
// Activity log registra user_id, mas user está em central
// Relatórios complexos requerem cross-database queries
```

**Depois**:
```php
// User está no mesmo banco que activity_log
// JOIN direto para relatórios
Activity::with('causer')->get(); // Funciona nativamente!
```

### 5.4 Compliance LGPD/HIPAA

- **Isolamento Total**: Todos os dados do usuário (incluindo PII) estão no banco do tenant
- **Right to Erasure**: Deletar tenant database = deletar todos os dados do usuário naquele contexto
- **Data Portability**: Export de dados do usuário direto do banco do tenant
- **Audit Trail**: Activity log completo com user data local

---

## 6. Plano de Migração

### Fase 1: Preparação (1-2 dias)

- [ ] Criar migration para `users` table em tenant
- [ ] Criar migration para `tenant_resources` pivot em central
- [ ] Configurar eventos de Resource Syncing
- [ ] Criar modelos `CentralUser` e `TenantUser`

### Fase 2: Implementação (2-3 dias)

- [ ] Atualizar `TenancyServiceProvider` com eventos
- [ ] Migrar `tenant_user` pivot para `tenant_resources`
- [ ] Atualizar seeders para criar TenantUsers
- [ ] Atualizar auth guards para usar TenantUser

### Fase 3: Refatoração (2-3 dias)

- [ ] Remover workarounds de connection switching do User model
- [ ] Atualizar controllers para usar TenantUser
- [ ] Atualizar middleware `VerifyTenantAccess`
- [ ] Atualizar Fortify/Sanctum configuration

### Fase 4: Testes (1-2 dias)

- [ ] Testes de registro e login
- [ ] Testes de sincronização de atributos
- [ ] Testes de convite e remoção
- [ ] Testes de roles e permissions
- [ ] Testes E2E com Playwright

### Fase 5: Migração de Dados (se aplicável)

- [ ] Script para popular TenantUsers a partir de CentralUsers existentes
- [ ] Validação de integridade de dados
- [ ] Rollback plan

---

## 7. Questões para Decisão

### 7.1 Autenticação

**Pergunta**: Como será o fluxo de login?
**Login sempre no tenant**: User digita email/senha em `tenant1.localhost`, autentica contra `TenantUser`


### 7.2 Cadastro de Novos Usuários

**Pergunta**: Onde usuários novos se cadastram?

**Opções**:
1. **Central only**: Cadastro em `setor3.app`, depois convite para tenants
2. **Tenant only**: Cadastro direto em `tenant1.localhost` (cria central + tenant user)
3. **Híbrido**: Ambas as opções

**Recomendação**: Opção 3 (Híbrido) - flexibilidade máxima.

### 7.3 Campos Específicos por Tenant

**Pergunta**: Tenants podem ter campos customizados de usuário?

**Exemplo**:
- Tenant A: `department`, `employee_id`
- Tenant B: `team`, `manager_id`

**Resposta**: SIM! Com Resource Syncing, cada tenant pode ter seus próprios campos no `TenantUser` que NÃO são sincronizados com central.

### 7.4 Sincronização Bidirecional

**Pergunta**: Alterações no tenant devem propagar para central?

**Cenário**:
- User muda nome em `tenant1.localhost`
- Central e `tenant2.localhost` devem atualizar?

**Recomendação**: SIM - Resource Syncing suporta sincronização bidirecional nativamente.

---

## 8. Riscos e Mitigações

| Risco | Probabilidade | Impacto | Mitigação |
|-------|---------------|---------|-----------|
| Bugs na sincronização | Média | Alto | Testes extensivos, eventos de fallback |
| Performance de sync | Baixa | Médio | Queued jobs para sync em background |
| Dados inconsistentes | Baixa | Alto | Validação de integridade, logs detalhados |
| Rollback complexo | Média | Alto | Manter pivot `tenant_user` até migração completa |

---

## 9. Recomendação Final

**Recomendo a Opção B (Resource Syncing)** pelos seguintes motivos:

1. **Padrão oficial do Stancl v4** - não é gambiarra
2. **Código mais limpo** - remove workarounds existentes
3. **Performance melhor** - queries locais no tenant
4. **Compliance total** - dados isolados por tenant
5. **Flexibilidade** - campos customizados por tenant
6. **Esforço moderado** - não requer reescrever autenticação

### Próximos Passos

1. **Aprovar** este documento de análise
2. **Criar branch** `feature/tenant-users`
3. **Implementar** em fases conforme Seção 6
4. **Testar** extensivamente antes de merge
5. **Documentar** mudanças em CLAUDE.md

---

## Referências

- [Stancl/Tenancy v4 - Resource Syncing](https://v4.tenancyforlaravel.com/resource-syncing)
- [Stancl/Tenancy v4 - Getting Started](https://v4.tenancyforlaravel.com/getting-started)
- [Stancl/Tenancy v4 - Multi-Database Tenancy](https://v4.tenancyforlaravel.com/multi-database-tenancy)
- [Spatie Laravel Permission](https://spatie.be/docs/laravel-permission)
