# Enum System - Single Source of Truth

Este documento descreve a arquitetura do sistema de enums do projeto, que utiliza **PHP Enums como fonte única de verdade** para gerar automaticamente tipos TypeScript, metadados de UI e traduções.

## Visão Geral

```
┌─────────────────────────────────────────────────────────────────────┐
│                         PHP ENUMS                                   │
│                    (Single Source of Truth)                         │
│                                                                     │
│   app/Enums/                                                        │
│   ├── TenantPermission.php   (45 permissions)                       │
│   ├── CentralPermission.php  (38 permissions)                       │
│   ├── PlanFeature.php        (11 features)                          │
│   ├── PlanLimit.php          (8 limits)                             │
│   ├── TenantRole.php         (3 roles)                              │
│   ├── AddonType.php          (4 types)                              │
│   └── ... (14 enums total)                                          │
└─────────────────────────────────────────────────────────────────────┘
                                │
                                │ sail artisan types:generate
                                ▼
┌─────────────────────────────────────────────────────────────────────┐
│                    GENERATED TYPESCRIPT                             │
│                                                                     │
│   resources/js/types/                                               │
│   ├── enums.d.ts        (Union types + Option interfaces)           │
│   ├── permissions.d.ts  (Permission type + Auth interface)          │
│   └── plan.d.ts         (PlanFeatures/Limits/Usage interfaces)      │
│                                                                     │
│   resources/js/lib/                                                 │
│   └── enum-metadata.ts  (Runtime metadata maps + helpers)           │
└─────────────────────────────────────────────────────────────────────┘
                                │
                                ▼
┌─────────────────────────────────────────────────────────────────────┐
│                    CONSUMERS (Type-Safe)                            │
│                                                                     │
│   Hooks:                                                            │
│   ├── usePermissions()  → has('projects:view')                      │
│   ├── usePlan()         → hasFeature('customRoles')                 │
│   └── useCan()          → useCan('billing:manage')                  │
│                                                                     │
│   Components:                                                       │
│   ├── <Can permission="projects:create">                            │
│   ├── <StatusBadge status={user.federation_status} />               │
│   └── TENANT_ROLE['owner'].icon                                     │
└─────────────────────────────────────────────────────────────────────┘
```

## PHP Enums

### Lista Completa

| Enum | Cases | Propósito |
|------|-------|-----------|
| `TenantPermission` | 45 | Permissões no banco do tenant |
| `CentralPermission` | 38 | Permissões no banco central |
| `PlanFeature` | 11 | Features de planos (projects, customRoles, apiAccess, etc.) |
| `PlanLimit` | 8 | Limites de recursos (users, storage, apiCalls, etc.) |
| `TenantRole` | 3 | Roles do tenant (owner, admin, member) |
| `AddonType` | 4 | Tipos de addon (quota, feature, metered, credit) |
| `AddonStatus` | 5 | Estados de addon (active, expired, etc.) |
| `BillingPeriod` | 5 | Ciclos de cobrança (monthly, yearly, one_time, etc.) |
| `BadgePreset` | 12 | Badges de marketing (most_popular, best_value, etc.) |
| `FederatedUserStatus` | 4 | Estados de usuário federado |
| `FederatedUserLinkSyncStatus` | 5 | Estados de sync de link |
| `FederationConflictStatus` | 3 | Estados de conflito |
| `FederationSyncStrategy` | 3 | Estratégias de sync |
| `TenantConfigKey` | 7 | Chaves de configuração do tenant |

### Estrutura Padrão de um Enum

Todos os enums seguem uma estrutura consistente com métodos obrigatórios:

```php
<?php

namespace App\Enums;

enum MyEnum: string
{
    case OPTION_A = 'option_a';
    case OPTION_B = 'option_b';

    /**
     * Nome traduzível do enum.
     */
    public function name(): array
    {
        return match ($this) {
            self::OPTION_A => ['en' => 'Option A', 'pt_BR' => 'Opção A'],
            self::OPTION_B => ['en' => 'Option B', 'pt_BR' => 'Opção B'],
        };
    }

    /**
     * Descrição traduzível.
     */
    public function description(): array
    {
        return match ($this) {
            self::OPTION_A => ['en' => 'Description for A', 'pt_BR' => 'Descrição para A'],
            self::OPTION_B => ['en' => 'Description for B', 'pt_BR' => 'Descrição para B'],
        };
    }

    /**
     * Ícone Lucide para UI.
     */
    public function icon(): string
    {
        return match ($this) {
            self::OPTION_A => 'CircleCheck',
            self::OPTION_B => 'CircleX',
        };
    }

    /**
     * Cor Tailwind para UI.
     */
    public function color(): string
    {
        return match ($this) {
            self::OPTION_A => 'green',
            self::OPTION_B => 'red',
        };
    }

    /**
     * Variante do badge para UI.
     */
    public function badgeVariant(): string
    {
        return match ($this) {
            self::OPTION_A => 'default',
            self::OPTION_B => 'destructive',
        };
    }

    /**
     * Retorna todos os valores como array de strings.
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
```

### Métodos Específicos por Tipo

Além dos métodos padrão, alguns enums têm métodos específicos:

**PlanFeature:**
```php
public function permissions(): array     // Permissões que a feature habilita
public function isCustomizable(): bool   // Se pode ser customizado por plano
public static function frontendFeatures(): array  // Features excluindo 'base'
```

**PlanLimit:**
```php
public function unit(): string           // Unidade (users, MB, days)
public function defaultValue(): int      // Valor padrão
public function allowsUnlimited(): bool  // Se aceita -1 (ilimitado)
```

**TenantRole:**
```php
public function excludedPermissions(): array   // Permissões excluídas
public function excludedCategories(): array    // Categorias excluídas
public function filterPermissions(array $permissions): array
```

**AddonType:**
```php
public function isMetered(): bool    // Se é cobrado por uso
public function isStackable(): bool  // Se acumula quantidade
public function isRecurring(): bool  // Se é recorrente
```

## Comando de Geração

### `types:generate`

**Comando único** para gerar todos os tipos TypeScript do sistema.

```bash
sail artisan types:generate          # Gera tudo
sail artisan types:generate --fresh  # Limpa e regenera
```

**Arquivos Gerados:**

| Arquivo | Conteúdo |
|---------|----------|
| `resources/js/types/enums.d.ts` | Union types + interfaces Option para todos os 14 enums |
| `resources/js/types/permissions.d.ts` | Permission type (deduplicado) + Auth interface |
| `resources/js/types/plan.d.ts` | PlanFeatures, PlanLimits, PlanUsage interfaces |
| `resources/js/lib/enum-metadata.ts` | Mapas de metadados runtime + helper functions |
| `lang/en.json` | Traduções em inglês (atualizado) |
| `lang/pt_BR.json` | Traduções em português (atualizado) |

**Output do Comando:**
```
  ╔══════════════════════════════════════════════════════════╗
  ║  TypeScript Type Generator (Single Source of Truth)      ║
  ╚══════════════════════════════════════════════════════════╝

  ✓ Generated: resources/js/types/enums.d.ts
  ✓ Generated: resources/js/lib/enum-metadata.ts
  ✓ Generated: resources/js/types/permissions.d.ts
  ✓ Generated: resources/js/types/plan.d.ts
  ✓ Updated translations: lang/en.json
  ✓ Updated translations: lang/pt_BR.json

  ╔══════════════════════════════════════════════════════════╗
  ║  Summary                                                 ║
  ╚══════════════════════════════════════════════════════════╝

+-----------------------------+--------+-----------------------------------+
| Enum                        | Values | Interface                         |
+-----------------------------+--------+-----------------------------------+
| AddonType                   | 4      | AddonTypeOption                   |
| TenantPermission            | 45     | TenantPermissionOption            |
| ...                         | ...    | ...                               |
+-----------------------------+--------+-----------------------------------+
```

### Exemplos de Output

**`enums.d.ts`:**
```typescript
// Union type
export type TenantRole = 'owner' | 'admin' | 'member';

// Interface com metadados
export interface TenantRoleOption {
    value: TenantRole;
    label: string;
    description: string;
    icon: string;
    color: string;
    badge_variant: 'default' | 'destructive' | 'secondary' | 'outline';
    is_system: boolean;
}
```

**`enum-metadata.ts`:**
```typescript
export const TENANT_ROLE: Record<TenantRole, TenantRoleOption> = {
    owner: {
        value: 'owner',
        label: 'Owner',
        description: 'Full access to all features',
        icon: 'Crown',
        color: 'yellow',
        badge_variant: 'default',
        is_system: true,
    },
    // ...
};

export function getTenantRoleMeta(role: TenantRole): TenantRoleOption {
    return TENANT_ROLE[role];
}
```

**`permissions.d.ts`:**
```typescript
// Union de todas as permissões (deduplicadas)
export type Permission =
    | 'addons:grant'
    | 'projects:view'
    | 'projects:create'
    // ... 72 permissões únicas

// Types por categoria
export type ProjectsPermission = 'projects:view' | 'projects:create' | ...;
export type BillingPermission = 'billing:view' | 'billing:manage' | ...;

// Interface de autenticação
export interface Auth<TUser> {
    user: TUser;
    permissions: Permission[];
    role: Role;
}
```

**`plan.d.ts`:**
```typescript
// Interface para features (exclui 'base' pois sempre é true)
export interface PlanFeatures {
    projects: boolean;
    customRoles: boolean;
    apiAccess: boolean;
    // ... 10 features
}

// Interface para limites
export interface PlanLimits {
    users: number;
    projects: number;
    storage: number;
    // ... 8 limits
}
```

## Arquivos TypeScript Gerados

### Diferença entre os Arquivos

| Arquivo | Propósito | Quando Usar |
|---------|-----------|-------------|
| `enums.d.ts` | Union types + interfaces com metadados | Para tipar valores de enum e acessar metadados |
| `permissions.d.ts` | Permission type deduplicado + Auth interface | Para checks de autorização |
| `plan.d.ts` | Interfaces de dados de plano | Para dados retornados pela API |
| `enum-metadata.ts` | Mapas runtime com metadados | Para acessar ícones, cores, labels em runtime |

### Relacionamento entre Tipos

```typescript
// enums.d.ts - Inclui 'base' (11 valores)
export type PlanFeature = 'base' | 'projects' | 'customRoles' | ...;

// plan.d.ts - Exclui 'base' (10 propriedades)
export interface PlanFeatures {
    projects: boolean;    // 'base' não está aqui
    customRoles: boolean;
    // ...
}

// Quando usar cada um:
import type { PlanFeature } from '@/types/enums';     // Para metadata lookup
import type { PlanFeatures } from '@/types/plan';    // Para dados da API
```

## Hooks Consumidores

### `usePermissions()`

Hook principal para verificação de permissões.

```typescript
import { usePermissions } from '@/hooks/shared/use-permissions';

function MyComponent() {
    const { has, hasAny, hasAll, all, role } = usePermissions();

    // Verificação única
    if (has('projects:create')) { }

    // Qualquer uma (OR)
    if (hasAny('projects:edit', 'projects:editOwn')) { }

    // Todas (AND)
    if (hasAll('billing:view', 'billing:manage')) { }

    // Metadados do role
    if (role.isOwner) { }
    if (role.isAdmin) { }

    // Lista todas as permissões
    const permissions = all();
}
```

### `useCan()`

Shorthand para verificação única.

```typescript
import { useCan } from '@/hooks/shared/use-permissions';

function MyComponent() {
    const canCreate = useCan('projects:create');

    if (canCreate) {
        // ...
    }
}
```

### `usePlan()`

Hook para verificar features e limites do plano.

```typescript
import { usePlan } from '@/hooks/tenant/use-plan';

function MyComponent() {
    const {
        hasFeature,
        getLimit,
        hasReachedLimit,
        canAdd,
        getUsagePercentage,
        isUnlimited,
    } = usePlan();

    // Verificar feature
    if (hasFeature('customRoles')) { }

    // Verificar limite
    const maxUsers = getLimit('users');
    const usagePercent = getUsagePercentage('users');

    // Verificar se atingiu limite
    if (hasReachedLimit('projects')) { }

    // Verificar se pode adicionar mais
    if (canAdd('users', 5)) { }

    // Verificar se é ilimitado
    if (isUnlimited('storage')) { }
}
```

## Componentes Consumidores

### `<Can>` - Permission Guard

Componente para renderização condicional baseada em permissões.

```tsx
import { Can } from '@/components/shared/auth/can';

// Permissão única
<Can permission="projects:create">
    <CreateProjectButton />
</Can>

// Qualquer uma (OR)
<Can anyPermission={['projects:edit', 'projects:editOwn']}>
    <EditProjectButton />
</Can>

// Todas (AND)
<Can allPermissions={['billing:view', 'billing:manage']}>
    <BillingSettings />
</Can>

// Com fallback
<Can permission="billing:manage" fallback={<UpgradePrompt />}>
    <BillingDashboard />
</Can>
```

### Status Badges com Metadados

```tsx
import { FEDERATED_USER_STATUS } from '@/lib/enum-metadata';
import type { FederatedUserStatus } from '@/types/enums';

function UserStatusBadge({ status }: { status: FederatedUserStatus }) {
    const meta = FEDERATED_USER_STATUS[status];

    return (
        <Badge variant={meta.badge_variant}>
            <Icon name={meta.icon} className={`text-${meta.color}-500`} />
            {meta.label}
        </Badge>
    );
}
```

### Usando Metadados em Formulários

```tsx
import { TENANT_ROLE, getTenantRoleMeta } from '@/lib/enum-metadata';
import type { TenantRole } from '@/types/enums';

function RoleSelect({ value, onChange }: Props) {
    return (
        <Select value={value} onValueChange={onChange}>
            {Object.entries(TENANT_ROLE).map(([key, meta]) => (
                <SelectItem key={key} value={key}>
                    <Icon name={meta.icon} />
                    {meta.label}
                </SelectItem>
            ))}
        </Select>
    );
}
```

## Como Adicionar um Novo Enum

### Passo 1: Criar o PHP Enum

```php
// app/Enums/MyNewEnum.php
<?php

namespace App\Enums;

enum MyNewEnum: string
{
    case OPTION_A = 'option_a';
    case OPTION_B = 'option_b';

    public function name(): array
    {
        return match ($this) {
            self::OPTION_A => ['en' => 'Option A', 'pt_BR' => 'Opção A'],
            self::OPTION_B => ['en' => 'Option B', 'pt_BR' => 'Opção B'],
        };
    }

    public function description(): array
    {
        return match ($this) {
            self::OPTION_A => ['en' => 'Description A', 'pt_BR' => 'Descrição A'],
            self::OPTION_B => ['en' => 'Description B', 'pt_BR' => 'Descrição B'],
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::OPTION_A => 'Check',
            self::OPTION_B => 'X',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::OPTION_A => 'green',
            self::OPTION_B => 'red',
        };
    }

    public function badgeVariant(): string
    {
        return match ($this) {
            self::OPTION_A => 'default',
            self::OPTION_B => 'destructive',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
```

### Passo 2: Adicionar ao Comando de Geração

Editar `app/Console/Commands/GenerateTypes.php`, adicionando na propriedade `$enums` do método `initializeEnums()`:

```php
'MyNewEnum' => [
    'class' => MyNewEnum::class,
    'interface' => [
        'value' => 'MyNewEnum',
        'label' => 'string',
        'description' => 'string',
        'icon' => 'string',
        'color' => 'string',
        'badge_variant' => "'default' | 'destructive' | 'secondary' | 'outline'",
        // ... campos específicos do enum
    ],
    'metadata' => fn ($case) => [
        'value' => $case->value,
        'label' => $case->name()['en'],
        'description' => $case->description()['en'],
        'icon' => $case->icon(),
        'color' => $case->color(),
        'badge_variant' => $case->badgeVariant(),
        // ... campos específicos
    ],
    'translations' => fn ($case, $locale) => $case->name()[$locale] ?? $case->name()['en'],
    'translation_key' => 'enums.my_new_enum',
],
```

### Passo 3: Regenerar Tipos

```bash
sail artisan types:generate
```

### Passo 4: Usar no Frontend

```typescript
// Importar tipo
import type { MyNewEnum } from '@/types/enums';

// Importar metadados
import { MY_NEW_ENUM, getMyNewEnumMeta } from '@/lib/enum-metadata';

// Usar
const meta = MY_NEW_ENUM['option_a'];
console.log(meta.icon); // 'Check'
```

## Como Adicionar uma Nova Permissão

### Passo 1: Adicionar ao PHP Enum

```php
// app/Enums/TenantPermission.php (ou CentralPermission.php)

case PROJECTS_ARCHIVE = 'projects:archive';

// Adicionar descrição
public function description(): array
{
    return match ($this) {
        // ... outras
        self::PROJECTS_ARCHIVE => [
            'en' => 'Archive projects',
            'pt_BR' => 'Arquivar projetos',
        ],
    };
}

// Adicionar categoria
public function category(): string
{
    return match ($this) {
        // ... outras
        self::PROJECTS_ARCHIVE => 'projects',
    };
}
```

### Passo 2: Sincronizar

```bash
# Sincroniza banco + regenera tipos
sail artisan permissions:sync
```

### Passo 3: Usar

```tsx
// TypeScript já reconhece a nova permissão
<Can permission="projects:archive">
    <ArchiveButton />
</Can>

// Hook também funciona
const canArchive = useCan('projects:archive');
```

## Convenções de Nomenclatura

### Permissões

Formato: `resource:action`

| Categoria | Exemplos |
|-----------|----------|
| projects | `projects:view`, `projects:create`, `projects:edit`, `projects:delete`, `projects:archive` |
| team | `team:view`, `team:invite`, `team:remove`, `team:manageRoles` |
| billing | `billing:view`, `billing:manage`, `billing:invoices` |
| settings | `settings:view`, `settings:edit`, `settings:danger` |
| roles | `roles:view`, `roles:create`, `roles:edit`, `roles:delete` |

### Ações Comuns

| Ação | Significado |
|------|-------------|
| `view` | Visualizar listagem/detalhes |
| `create` | Criar novo registro |
| `edit` | Editar registro existente |
| `delete` | Remover registro |
| `manage` | Acesso completo de gerenciamento |
| `export` | Exportar dados |
| `archive` | Arquivar (soft delete) |

### Cores (Tailwind)

| Cor | Uso Típico |
|-----|------------|
| `green` | Sucesso, ativo, permitido |
| `red` | Erro, inativo, negado |
| `yellow` | Aviso, pendente, owner |
| `blue` | Informação, neutro |
| `purple` | Premium, especial |
| `gray` | Desabilitado, secundário |

### Badge Variants

| Variant | Uso |
|---------|-----|
| `default` | Estado padrão/positivo |
| `destructive` | Erro/perigo |
| `secondary` | Secundário/neutro |
| `outline` | Baixa ênfase |

## Integração com i18n

Os enums geram automaticamente chaves de tradução:

```php
// PHP Enum
public function name(): array
{
    return match ($this) {
        self::OWNER => ['en' => 'Owner', 'pt_BR' => 'Proprietário'],
    };
}
```

```json
// lang/en/enums/tenant.json (auto-gerado)
{
    "enums.tenant.role.owner": "Owner"
}

// lang/pt_BR/enums/tenant.json (auto-gerado)
{
    "enums.tenant.role.owner": "Proprietário"
}
```

```tsx
// Uso no frontend
import { useLaravelReactI18n } from 'laravel-react-i18n';

function RoleBadge({ role }: { role: TenantRole }) {
    const { t } = useLaravelReactI18n();

    return <Badge>{t(`enums.tenant.role.${role}`)}</Badge>;
}
```

## Troubleshooting

### Tipos não atualizando

```bash
# Regenerar todos os tipos
sail artisan types:generate

# Regenerar do zero (limpa arquivos antes)
sail artisan types:generate --fresh

# Verificar TypeScript
sail npm run types
```

### Permissão não reconhecida no TypeScript

1. Verificar se foi adicionada ao enum PHP
2. Rodar `sail artisan types:generate`
3. Verificar `permissions.d.ts` foi atualizado

### Metadados não disponíveis em runtime

1. Verificar import: `import { ENUM_NAME } from '@/lib/enum-metadata'`
2. Não confundir com types: `import type { EnumName } from '@/types/enums'`

## Arquivos de Referência

| Arquivo | Localização |
|---------|-------------|
| PHP Enums | `app/Enums/*.php` |
| Comando Unificado | `app/Console/Commands/GenerateTypes.php` |
| Types - Enums | `resources/js/types/enums.d.ts` |
| Types - Permissions | `resources/js/types/permissions.d.ts` |
| Types - Plans | `resources/js/types/plan.d.ts` |
| Metadata Runtime | `resources/js/lib/enum-metadata.ts` |
| Hook - Permissions | `resources/js/hooks/shared/use-permissions.ts` |
| Hook - Plan | `resources/js/hooks/tenant/use-plan.ts` |
| Component - Can | `resources/js/components/shared/auth/can.tsx` |
