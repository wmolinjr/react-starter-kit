# Enum Standardization Plan

## Objetivo

Padronizar todos os 14 enums em `app/Enums` para seguir o padrão estabelecido, onde o PHP enum é a **única fonte de verdade** para:
- Valores do enum
- Traduções (en, pt_BR)
- Metadata (icon, color, badgeVariant)
- Lógica de negócio

## Padrão de Referência

**Arquivo**: `app/Enums/FederatedUserStatus.php`

### Métodos Obrigatórios

| Método | Descrição |
|--------|-----------|
| `name(): array` | Traduções `['en' => '...', 'pt_BR' => '...']` |
| `description(): array` | Descrições traduzidas |
| `icon(): string` | Nome do ícone Lucide |
| `color(): string` | Cor semântica (green, red, yellow, blue, gray, purple) |
| `badgeVariant(): string` | Variant do Badge (default, destructive, secondary, outline) |
| `label(): string` | Retorna `name()[app()->getLocale()]` |
| `translatedDescription(): string` | Retorna `description()[app()->getLocale()]` |
| `values(): array` | Array de todos os valores |
| `options(): array` | Array para selects/dropdowns |
| `toFrontend(): array` | Metadata completo para frontend |
| `toFrontendArray(): array` | Array de todos os casos para frontend |
| `toFrontendMap(): array` | Map indexado por value |

---

## Status dos Enums

### Totalmente Conformes (100%)
- ✅ `FederatedUserStatus` - Referência
- ✅ `FederatedUserLinkSyncStatus`
- ✅ `FederationConflictStatus`
- ✅ `FederationSyncStrategy` - (Fase 1 completa)
- ✅ `AddonStatus` - (Fase 1 completa)
- ✅ `AddonType` - (Fase 1 completa)
- ✅ `BillingPeriod` - (Fase 1 completa)
- ✅ `PlanFeature` - (Fase 2 completa)
- ✅ `PlanLimit` - (Fase 2 completa)
- ✅ `TenantRole` - (Fase 2 completa)
- ✅ `CentralPermission` - (Fase 3 completa)
- ✅ `TenantPermission` - (Fase 3 completa)
- ✅ `BadgePreset` - (Fase 3 completa)
- ✅ `TenantConfigKey` - (Fase 3 completa)

### Todos os 14 enums estão agora padronizados!

---

## Plano de Implementação

### Fase 1: Quick Fixes (Pequenas Adições)

#### 1.1 FederationSyncStrategy
```php
// Adicionar método
public function badgeVariant(): string
{
    return match ($this) {
        self::MasterWins => 'default',
        self::LastWriteWins => 'secondary',
        self::ManualReview => 'outline',
    };
}
```

#### 1.2 AddonStatus
```php
// Adicionar métodos
public function badgeVariant(): string
{
    return match ($this) {
        self::Active => 'default',
        self::Pending => 'secondary',
        self::Expired => 'outline',
        self::Cancelled => 'destructive',
        self::Suspended => 'destructive',
    };
}

public static function toFrontendMap(): array
{
    return collect(self::cases())
        ->mapWithKeys(fn ($case) => [$case->value => $case->toFrontend()])
        ->toArray();
}
```

#### 1.3 AddonType
```php
// Adicionar mesmos métodos que AddonStatus
```

#### 1.4 BillingPeriod
```php
// Adicionar mesmos métodos que AddonStatus
```

---

### Fase 2: Medium Refactoring

#### 2.1 PlanFeature
Adicionar:
- `color(): string` - Por categoria (billing=green, team=blue, etc.)
- `badgeVariant(): string`
- `label(): string` - Alias para `translatedName()`
- `options(): array`
- `toFrontendMap(): array`

#### 2.2 PlanLimit
Adicionar mesmos métodos que PlanFeature.

#### 2.3 TenantRole
Refatorar:
- Renomear `displayName()` → `name()`
- Adicionar `icon(): string`
- Adicionar `color(): string`
- Adicionar `badgeVariant(): string`
- Adicionar `toFrontend()`, `toFrontendMap()`

---

### Fase 3: Major Refactoring

#### 3.1 CentralPermission / TenantPermission
```php
// Adicionar tradução por categoria
public function name(): array
{
    $category = $this->category();
    $action = $this->action();

    return [
        'en' => ucfirst($category) . ': ' . ucfirst($action),
        'pt_BR' => $this->translateCategory($category) . ': ' . $this->translateAction($action),
    ];
}

public function icon(): string
{
    return match ($this->category()) {
        'tenants' => 'Building2',
        'plans' => 'CreditCard',
        'users' => 'Users',
        'roles' => 'Shield',
        'system' => 'Settings',
        'federation' => 'Network',
        default => 'Circle',
    };
}

public function color(): string
{
    return match ($this->category()) {
        'tenants' => 'blue',
        'plans' => 'green',
        'users' => 'purple',
        'roles' => 'yellow',
        'system' => 'gray',
        'federation' => 'cyan',
        default => 'gray',
    };
}
```

#### 3.2 BadgePreset
Migrar traduções de `lang/*/badges.php` para o enum:
```php
public function name(): array
{
    return match ($this) {
        self::Owner => ['en' => 'Owner', 'pt_BR' => 'Proprietário'],
        self::Admin => ['en' => 'Admin', 'pt_BR' => 'Administrador'],
        self::Member => ['en' => 'Member', 'pt_BR' => 'Membro'],
        // ...
    };
}
```

#### 3.3 TenantConfigKey
Migrar traduções e adicionar todos os métodos padrão.

---

### Fase 4: Atualizar GenerateEnumTypes

#### 4.1 Adicionar imports
```php
use App\Enums\BadgePreset;
use App\Enums\TenantRole;
use App\Enums\TenantConfigKey;
use App\Enums\CentralPermission;
use App\Enums\TenantPermission;
use App\Enums\PlanFeature;
use App\Enums\PlanLimit;
```

#### 4.2 Gerar TypeScript types para cada novo enum

#### 4.3 Gerar metadata para cada novo enum

#### 4.4 Gerar traduções para cada novo enum
Atualizar `generateTranslations()` para incluir:
```php
$enumTranslations = [
    // Existentes
    'admin.federation.user_status' => ...,
    'admin.federation.link_status' => ...,
    'admin.federation.conflict' => ...,
    'admin.federation.sync_strategy' => ...,

    // Novos
    'enums.addon_type' => ...,
    'enums.addon_status' => ...,
    'enums.billing_period' => ...,
    'enums.badge_preset' => ...,
    'enums.tenant_role' => ...,
    'enums.tenant_config_key' => ...,
    'enums.plan_feature' => ...,
    'enums.plan_limit' => ...,
    'permissions.central' => ...,  // Ou agrupar por categoria
    'permissions.tenant' => ...,
];
```

---

## Arquivos a Modificar

| Arquivo | Ação |
|---------|------|
| `app/Enums/FederationSyncStrategy.php` | Adicionar `badgeVariant()` |
| `app/Enums/AddonStatus.php` | Adicionar `badgeVariant()`, `toFrontendMap()` |
| `app/Enums/AddonType.php` | Adicionar `badgeVariant()`, `toFrontendMap()` |
| `app/Enums/BillingPeriod.php` | Adicionar `badgeVariant()`, `toFrontendMap()` |
| `app/Enums/PlanFeature.php` | Adicionar `color()`, `badgeVariant()`, `options()`, `toFrontendMap()` |
| `app/Enums/PlanLimit.php` | Adicionar `color()`, `badgeVariant()`, `options()`, `toFrontendMap()` |
| `app/Enums/TenantRole.php` | Refatorar completamente |
| `app/Enums/CentralPermission.php` | Adicionar todos os métodos |
| `app/Enums/TenantPermission.php` | Adicionar todos os métodos |
| `app/Enums/BadgePreset.php` | Refatorar completamente |
| `app/Enums/TenantConfigKey.php` | Refatorar completamente |
| `app/Console/Commands/GenerateEnumTypes.php` | Adicionar 7 enums |
| `lang/en.json` | Remover traduções migradas |
| `lang/pt_BR.json` | Remover traduções migradas |

---

## Ordem de Execução Recomendada

1. **Fase 1** - 4 enums, ~30 min cada = 2h
2. **Fase 2** - 3 enums, ~1h cada = 3h
3. **Fase 3** - 4 enums, ~2h cada = 8h
4. **Fase 4** - GenerateEnumTypes = 2h

**Total estimado**: ~15h de trabalho

---

## Validação

Após cada fase:
1. Rodar `sail artisan enums:generate-types`
2. Verificar `resources/js/types/enums.d.ts`
3. Verificar `resources/js/lib/enum-metadata.ts`
4. Verificar `lang/en.json` e `lang/pt_BR.json`
5. Rodar `sail npm run types` para validar TypeScript
6. Testar no browser que traduções aparecem corretamente

---

## Benefícios

- **Single Source of Truth**: PHP enum define tudo
- **Type Safety**: TypeScript gerado automaticamente
- **i18n**: Traduções vêm do enum, não de arquivos separados
- **Consistência**: Todos os enums seguem o mesmo padrão
- **Manutenibilidade**: Mudar tradução = mudar só o enum + rodar comando
