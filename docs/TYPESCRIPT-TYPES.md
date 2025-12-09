# TypeScript Types Architecture

Este documento descreve a arquitetura de tipos TypeScript do projeto, incluindo convenções, padrões e melhores práticas.

## Visão Geral

O projeto usa uma abordagem **Single Source of Truth** onde os tipos TypeScript são gerados automaticamente a partir do backend PHP:

```
PHP Enums + Resources → types:generate → TypeScript Types
```

### Arquivos de Tipos Gerados

| Arquivo | Fonte | Conteúdo |
|---------|-------|----------|
| `enums.d.ts` | PHP Enums | Union types e interfaces de metadados |
| `resources.d.ts` | PHP Resources | Interfaces de API Resources |
| `permissions.d.ts` | Permission Enums | Tipos de permissões |
| `plan.d.ts` | PlanFeature/PlanLimit | Interfaces de planos |

### Arquivos de Tipos Manuais

| Arquivo | Conteúdo |
|---------|----------|
| `index.d.ts` | Tipos globais (User, PageProps, etc.) |
| `common.d.ts` | Tipos compartilhados não-Resource |
| `pagination.d.ts` | Tipos de paginação Inertia |

## Comando de Geração

```bash
# Gerar todos os tipos
sail artisan types:generate

# Limpar e regenerar
sail artisan types:generate --fresh
```

## Hierarquia de Tipos

### 1. Tipos Gerados de Enums (`enums.d.ts`)

Para cada PHP Enum, são gerados:
- **Union Type**: valores possíveis do enum
- **Option Interface**: metadados completos

```typescript
// Union type
export type AddonType = 'quota' | 'feature' | 'metered' | 'credit';

// Option interface (metadados)
export interface AddonTypeOption {
    value: AddonType;
    label: string;
    description: string;
    icon: string;
    color: string;
    badge_variant: 'default' | 'destructive' | 'secondary' | 'outline';
    is_stackable: boolean;
    is_recurring: boolean;
    // ...
}
```

**Quando usar:**
- `AddonType` - Para tipar valores de campos
- `AddonTypeOption` - Para renderizar UI com metadados

### 2. Tipos Gerados de Resources (`resources.d.ts`)

Para cada PHP Resource com `HasTypescriptType` trait:

```php
// PHP Resource
class ProjectResource extends BaseResource
{
    use HasTypescriptType;

    public static function typescriptSchema(): array
    {
        return [
            'id' => 'string',
            'name' => 'string',
            'status' => 'string',
            'user' => 'UserSummaryResource | null',
        ];
    }
}
```

```typescript
// TypeScript gerado
export interface ProjectResource {
    id: string;
    name: string;
    status: string;
    user: UserSummaryResource | null;
}
```

### 3. Tipos Manuais (`common.d.ts`)

Para tipos que:
- Não têm Resource PHP correspondente
- São estruturas aninhadas em Resources
- São específicos de páginas individuais

```typescript
// common.d.ts
export interface FederationGroupShowStats {
    total_users: number;
    active_syncs: number;
    pending_conflicts: number;
    failed_syncs: number;
}
```

## Padrões de Uso

### Importando Tipos

```typescript
// ✅ CORRETO: Importar de @/types (re-exporta tudo)
import type {
    ProjectResource,
    UserResource,
    AddonType,
    BreadcrumbItem
} from '@/types';

// ✅ CORRETO: Importar tipos específicos de enums
import type { AddonTypeOption } from '@/types/enums';

// ✅ CORRETO: Importar tipos manuais de common
import type { TeamStats } from '@/types/common';

// ❌ EVITAR: Importar diretamente de resources.d.ts
import type { ProjectResource } from '@/types/resources';
```

### Estendendo Tipos de Resources

Quando o controller retorna campos adicionais não presentes no Resource base:

```typescript
// Resource base gerado
interface FederationConflictResource {
    id: string;
    field_name: string;
    status: FederationConflictStatus;
    // ...
}

// Extensão para página específica
interface ConflictWithSourceTenant extends FederationConflictResource {
    source_value: string;
    target_value: string;
    source_tenant: { id: string; name: string };
}

// Uso
interface Props {
    conflicts: ConflictWithSourceTenant[];
}
```

### Tipos de Props de Página

```typescript
// Padrão para páginas Inertia
interface Props {
    // Dados do Resource
    project: ProjectResource;

    // Dados paginados
    projects: InertiaPaginatedResponse<ProjectResource>;

    // Dados estendidos
    stats: CentralDashboardStatsResource;

    // Opções de formulário
    categories: CategoryOptionResource[];
}

function ProjectIndex({ project, projects, stats, categories }: Props) {
    // ...
}
```

## Convenções de Nomenclatura

### Resources PHP → TypeScript

| PHP Resource | TypeScript Interface |
|--------------|---------------------|
| `ProjectResource` | `ProjectResource` |
| `ProjectDetailResource` | `ProjectDetailResource` |
| `ProjectEditResource` | `ProjectEditResource` |
| `UserSummaryResource` | `UserSummaryResource` |

### Sufixos de Resource

| Sufixo | Uso |
|--------|-----|
| `Resource` | Listagem (campos básicos) |
| `DetailResource` | Página show (com relacionamentos) |
| `EditResource` | Formulário de edição |
| `SummaryResource` | Dropdown/seleção (mínimo) |

### Campos Padrão

Os Resources seguem convenções de nomenclatura:

| Campo PHP | Campo TypeScript | Descrição |
|-----------|------------------|-----------|
| `value` | `value` | Identificador único (enum) |
| `label` | `label` | Nome legível para UI |
| `description` | `description` | Descrição longa |
| `icon` | `icon` | Nome do ícone Lucide |
| `color` | `color` | Nome da cor (blue, green, etc.) |

## Criando Novos Resources com Tipos

### 1. Criar o Resource PHP

```php
<?php

namespace App\Http\Resources\Central;

use App\Http\Resources\BaseResource;
use App\Http\Resources\Concerns\HasTypescriptType;

class MyNewResource extends BaseResource
{
    use HasTypescriptType;

    public static function typescriptSchema(): array
    {
        return [
            'id' => 'string',
            'name' => 'string',
            'count' => 'number',
            'is_active' => 'boolean',
            'metadata' => 'Record<string, unknown> | null',
            'items' => 'string[]',
            'status' => 'MyStatus', // Referência a outro tipo
        ];
    }

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'count' => $this->count,
            'is_active' => $this->is_active,
            'metadata' => $this->metadata,
            'items' => $this->items,
            'status' => $this->status,
        ];
    }
}
```

### 2. Regenerar Tipos

```bash
sail artisan types:generate
```

### 3. Usar no Frontend

```typescript
import type { MyNewResource } from '@/types';

interface Props {
    data: MyNewResource;
}
```

## Tipos Suportados no Schema

| TypeScript | Exemplo |
|------------|---------|
| `string` | `'id' => 'string'` |
| `number` | `'count' => 'number'` |
| `boolean` | `'active' => 'boolean'` |
| `null` | `'value' => 'null'` |
| Nullable | `'name' => 'string \| null'` |
| Array | `'items' => 'string[]'` |
| Object | `'data' => 'Record<string, unknown>'` |
| Reference | `'user' => 'UserResource'` |
| Union | `'status' => "'active' \| 'inactive'"` |
| Enum | `'type' => 'AddonType'` |

## Boas Práticas

### DO ✅

```typescript
// Usar tipos gerados
import type { ProjectResource } from '@/types';

// Estender quando necessário
interface ProjectWithExtra extends ProjectResource {
    extra_field: string;
}

// Usar union types de enums
const status: AddonStatus = 'active';

// Usar Option interfaces para UI
const options: AddonTypeOption[] = Object.values(ADDON_TYPE);
```

### DON'T ❌

```typescript
// Não criar tipos duplicados
interface Project {  // ❌ Já existe ProjectResource
    id: string;
    name: string;
}

// Não usar any
const data: any = response;  // ❌

// Não ignorar tipos nullable
const name = user.name.toUpperCase();  // ❌ name pode ser null
const name = user.name?.toUpperCase() ?? '';  // ✅
```

## Tratando Campos Opcionais

### Arrays Opcionais

```typescript
// ❌ ERRO: 'links' is possibly undefined
user.links.filter(...)

// ✅ CORRETO: Usar nullish coalescing
const links = user.links ?? [];
links.filter(...)
```

### Objetos Opcionais

```typescript
// ❌ ERRO: Cannot read property of undefined
user.profile.avatar

// ✅ CORRETO: Optional chaining
user.profile?.avatar

// ✅ CORRETO: Com fallback
user.profile?.avatar ?? '/default-avatar.png'
```

## Debugging de Tipos

### Verificar tipos gerados

```bash
# Ver tipos gerados
cat resources/js/types/resources.d.ts | grep "interface ProjectResource" -A 20

# Verificar erros de tipo
sail npm run types
```

### Erros comuns

| Erro | Causa | Solução |
|------|-------|---------|
| `Cannot find name 'X'` | Tipo não importado | Adicionar import |
| `Property 'x' does not exist` | Campo renomeado | Verificar schema do Resource |
| `Type 'X' is not assignable` | Tipo incompatível | Verificar nullability |

## Referências

- [HasTypescriptType Trait](../app/Http/Resources/Concerns/HasTypescriptType.php)
- [GenerateTypes Command](../app/Console/Commands/GenerateTypes.php)
- [API Resources Guide](./API-RESOURCES.md)
- [Enums Single Source of Truth](./ENUMS-SINGLE-SOURCE-OF-TRUTH.md)
