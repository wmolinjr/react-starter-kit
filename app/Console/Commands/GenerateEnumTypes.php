<?php

namespace App\Console\Commands;

use App\Enums\AddonStatus;
use App\Enums\AddonType;
use App\Enums\BadgePreset;
use App\Enums\BillingPeriod;
use App\Enums\CentralPermission;
use App\Enums\FederatedUserLinkSyncStatus;
use App\Enums\FederatedUserStatus;
use App\Enums\FederationConflictStatus;
use App\Enums\FederationSyncStrategy;
use App\Enums\PlanFeature;
use App\Enums\PlanLimit;
use App\Enums\TenantConfigKey;
use App\Enums\TenantPermission;
use App\Enums\TenantRole;
use Illuminate\Console\Command;

/**
 * Generate TypeScript types for enums.
 *
 * Uses PHP enums as single source of truth to generate type-safe TypeScript interfaces.
 *
 * Usage:
 * - sail artisan enums:generate-types
 */
class GenerateEnumTypes extends Command
{
    protected $signature = 'enums:generate-types';

    protected $description = 'Generate TypeScript types from PHP enums';

    public function handle(): int
    {
        $this->info('Generating TypeScript types from enums...');

        // Generate types (.d.ts)
        $typescript = $this->generateTypeScript();
        $typesPath = resource_path('js/types/enums.d.ts');
        file_put_contents($typesPath, $typescript);
        $this->info("✅ Generated types: {$typesPath}");

        // Generate metadata (.ts)
        $metadata = $this->generateMetadata();
        $metadataPath = resource_path('js/lib/enum-metadata.ts');
        file_put_contents($metadataPath, $metadata);
        $this->info("✅ Generated metadata: {$metadataPath}");

        // Generate translations
        $this->generateTranslations();

        $this->newLine();

        // Display summary
        $this->displaySummary();

        return self::SUCCESS;
    }

    protected function generateTypeScript(): string
    {
        $output = <<<'TS'
/**
 * Enum Types - Auto-generated from PHP Enums
 *
 * DO NOT EDIT MANUALLY!
 * Run: sail artisan enums:generate-types
 *
 * Source of truth:
 * - app/Enums/AddonType.php
 * - app/Enums/AddonStatus.php
 * - app/Enums/BillingPeriod.php
 * - app/Enums/PlanFeature.php
 * - app/Enums/PlanLimit.php
 * - app/Enums/TenantRole.php
 * - app/Enums/FederatedUserStatus.php
 * - app/Enums/FederatedUserLinkSyncStatus.php
 * - app/Enums/FederationConflictStatus.php
 * - app/Enums/FederationSyncStrategy.php
 * - app/Enums/CentralPermission.php
 * - app/Enums/TenantPermission.php
 * - app/Enums/BadgePreset.php
 * - app/Enums/TenantConfigKey.php
 */

TS;

        // AddonType
        $output .= $this->generateEnumType('AddonType', AddonType::cases());
        $output .= $this->generateEnumInterface('AddonTypeOption', [
            'value' => 'AddonType',
            'label' => 'string',
            'description' => 'string',
            'icon' => 'string',
            'color' => 'string',
            'badge_variant' => "'default' | 'destructive' | 'secondary' | 'outline'",
            'category' => 'string',
            'unit_label' => 'string',
            'is_metered' => 'boolean',
            'is_stackable' => 'boolean',
            'is_recurring' => 'boolean',
            'is_one_time' => 'boolean',
            'has_validity' => 'boolean',
        ]);

        // AddonStatus
        $output .= $this->generateEnumType('AddonStatus', AddonStatus::cases());
        $output .= $this->generateEnumInterface('AddonStatusOption', [
            'value' => 'AddonStatus',
            'label' => 'string',
            'description' => 'string',
            'icon' => 'string',
            'color' => 'string',
            'badge_variant' => "'default' | 'destructive' | 'secondary' | 'outline'",
            'is_usable' => 'boolean',
            'is_terminal' => 'boolean',
        ]);

        // BillingPeriod
        $output .= $this->generateEnumType('BillingPeriod', BillingPeriod::cases());
        $output .= $this->generateEnumInterface('BillingPeriodOption', [
            'value' => 'BillingPeriod',
            'label' => 'string',
            'description' => 'string',
            'icon' => 'string',
            'color' => 'string',
            'badge_variant' => "'default' | 'destructive' | 'secondary' | 'outline'",
            'is_recurring' => 'boolean',
        ]);

        // PlanFeature
        $output .= $this->generateEnumType('PlanFeature', PlanFeature::cases());
        $output .= $this->generateEnumInterface('PlanFeatureOption', [
            'value' => 'PlanFeature',
            'label' => 'string',
            'description' => 'string',
            'icon' => 'string',
            'color' => 'string',
            'badge_variant' => "'default' | 'destructive' | 'secondary' | 'outline'",
            'category' => 'string',
            'permissions' => 'string[]',
            'is_customizable' => 'boolean',
        ]);

        // PlanLimit
        $output .= $this->generateEnumType('PlanLimit', PlanLimit::cases());
        $output .= $this->generateEnumInterface('PlanLimitOption', [
            'value' => 'PlanLimit',
            'label' => 'string',
            'description' => 'string',
            'icon' => 'string',
            'color' => 'string',
            'badge_variant' => "'default' | 'destructive' | 'secondary' | 'outline'",
            'unit' => 'string',
            'unit_label' => 'string',
            'default_value' => 'number',
            'allows_unlimited' => 'boolean',
            'is_customizable' => 'boolean',
        ]);

        // TenantRole
        $output .= $this->generateEnumType('TenantRole', TenantRole::cases());
        $output .= $this->generateEnumInterface('TenantRoleOption', [
            'value' => 'TenantRole',
            'label' => 'string',
            'description' => 'string',
            'icon' => 'string',
            'color' => 'string',
            'badge_variant' => "'default' | 'destructive' | 'secondary' | 'outline'",
            'is_system' => 'boolean',
        ]);

        // FederatedUserStatus
        $output .= $this->generateEnumType('FederatedUserStatus', FederatedUserStatus::cases());
        $output .= $this->generateEnumInterface('FederatedUserStatusOption', [
            'value' => 'FederatedUserStatus',
            'label' => 'string',
            'description' => 'string',
            'icon' => 'string',
            'color' => 'string',
            'badge_variant' => "'default' | 'destructive' | 'secondary' | 'outline'",
            'can_sync' => 'boolean',
            'is_pending' => 'boolean',
        ]);

        // FederatedUserLinkSyncStatus
        $output .= $this->generateEnumType('FederatedUserLinkSyncStatus', FederatedUserLinkSyncStatus::cases());
        $output .= $this->generateEnumInterface('FederatedUserLinkSyncStatusOption', [
            'value' => 'FederatedUserLinkSyncStatus',
            'label' => 'string',
            'description' => 'string',
            'icon' => 'string',
            'color' => 'string',
            'badge_variant' => "'default' | 'destructive' | 'secondary' | 'outline'",
            'needs_sync' => 'boolean',
            'has_issue' => 'boolean',
        ]);

        // FederationConflictStatus
        $output .= $this->generateEnumType('FederationConflictStatus', FederationConflictStatus::cases());
        $output .= $this->generateEnumInterface('FederationConflictStatusOption', [
            'value' => 'FederationConflictStatus',
            'label' => 'string',
            'description' => 'string',
            'icon' => 'string',
            'color' => 'string',
            'badge_variant' => "'default' | 'destructive' | 'secondary' | 'outline'",
            'requires_action' => 'boolean',
            'is_terminal' => 'boolean',
        ]);

        // FederationSyncStrategy
        $output .= $this->generateEnumType('FederationSyncStrategy', FederationSyncStrategy::cases());
        $output .= $this->generateEnumInterface('FederationSyncStrategyOption', [
            'value' => 'FederationSyncStrategy',
            'label' => 'string',
            'description' => 'string',
            'icon' => 'string',
            'color' => 'string',
            'badge_variant' => "'default' | 'destructive' | 'secondary' | 'outline'",
            'creates_conflicts' => 'boolean',
            'auto_resolves' => 'boolean',
        ]);

        // CentralPermission
        $output .= $this->generateEnumType('CentralPermission', CentralPermission::cases());
        $output .= $this->generateEnumInterface('CentralPermissionOption', [
            'value' => 'CentralPermission',
            'label' => 'string',
            'description' => 'string',
            'icon' => 'string',
            'color' => 'string',
            'badge_variant' => "'default' | 'destructive' | 'secondary' | 'outline'",
            'category' => 'string',
            'action' => 'string',
        ]);

        // TenantPermission
        $output .= $this->generateEnumType('TenantPermission', TenantPermission::cases());
        $output .= $this->generateEnumInterface('TenantPermissionOption', [
            'value' => 'TenantPermission',
            'label' => 'string',
            'description' => 'string',
            'icon' => 'string',
            'color' => 'string',
            'badge_variant' => "'default' | 'destructive' | 'secondary' | 'outline'",
            'category' => 'string',
            'action' => 'string',
        ]);

        // BadgePreset
        $output .= $this->generateEnumType('BadgePreset', BadgePreset::cases());
        $output .= $this->generateEnumInterface('BadgePresetOption', [
            'value' => 'BadgePreset',
            'label' => 'string',
            'description' => 'string',
            'icon' => 'string',
            'color' => 'string',
            'badge_variant' => "'default' | 'destructive' | 'secondary' | 'outline'",
            'bg' => 'string',
            'text' => 'string',
            'border' => 'string',
        ]);

        // TenantConfigKey
        $output .= $this->generateEnumType('TenantConfigKey', TenantConfigKey::cases());
        $output .= $this->generateEnumInterface('TenantConfigKeyOption', [
            'value' => 'TenantConfigKey',
            'label' => 'string',
            'description' => 'string',
            'icon' => 'string',
            'color' => 'string',
            'badge_variant' => "'default' | 'destructive' | 'secondary' | 'outline'",
            'category' => 'string',
            'default_value' => 'string | number | null',
        ]);

        return $output;
    }

    /**
     * Generate union type from enum cases.
     *
     * @param  array<\BackedEnum>  $cases
     */
    protected function generateEnumType(string $name, array $cases): string
    {
        $values = array_map(fn ($case) => "'{$case->value}'", $cases);

        return "export type {$name} = ".implode(' | ', $values).";\n\n";
    }

    /**
     * Generate interface from field definitions.
     *
     * @param  array<string, string>  $fields
     */
    protected function generateEnumInterface(string $name, array $fields): string
    {
        $output = "export interface {$name} {\n";
        foreach ($fields as $field => $type) {
            $output .= "    {$field}: {$type};\n";
        }
        $output .= "}\n\n";

        return $output;
    }

    protected function displaySummary(): void
    {
        $this->info('📊 Generated Types Summary:');
        $this->table(
            ['Enum', 'Values', 'Interface'],
            [
                ['AddonType', count(AddonType::cases()), 'AddonTypeOption'],
                ['AddonStatus', count(AddonStatus::cases()), 'AddonStatusOption'],
                ['BillingPeriod', count(BillingPeriod::cases()), 'BillingPeriodOption'],
                ['PlanFeature', count(PlanFeature::cases()), 'PlanFeatureOption'],
                ['PlanLimit', count(PlanLimit::cases()), 'PlanLimitOption'],
                ['TenantRole', count(TenantRole::cases()), 'TenantRoleOption'],
                ['FederatedUserStatus', count(FederatedUserStatus::cases()), 'FederatedUserStatusOption'],
                ['FederatedUserLinkSyncStatus', count(FederatedUserLinkSyncStatus::cases()), 'FederatedUserLinkSyncStatusOption'],
                ['FederationConflictStatus', count(FederationConflictStatus::cases()), 'FederationConflictStatusOption'],
                ['FederationSyncStrategy', count(FederationSyncStrategy::cases()), 'FederationSyncStrategyOption'],
                ['CentralPermission', count(CentralPermission::cases()), 'CentralPermissionOption'],
                ['TenantPermission', count(TenantPermission::cases()), 'TenantPermissionOption'],
                ['BadgePreset', count(BadgePreset::cases()), 'BadgePresetOption'],
                ['TenantConfigKey', count(TenantConfigKey::cases()), 'TenantConfigKeyOption'],
            ]
        );
    }

    /**
     * Generate TypeScript metadata file with actual data from enums.
     */
    protected function generateMetadata(): string
    {
        $output = <<<'TS'
/**
 * Enum Metadata - Auto-generated from PHP Enums
 *
 * DO NOT EDIT MANUALLY!
 * Run: sail artisan enums:generate-types
 *
 * Contains the actual metadata (icon, color, label, etc.) for each enum value.
 */

import type {
    AddonType,
    AddonTypeOption,
    AddonStatus,
    AddonStatusOption,
    BillingPeriod,
    BillingPeriodOption,
    PlanFeature,
    PlanFeatureOption,
    PlanLimit,
    PlanLimitOption,
    TenantRole,
    TenantRoleOption,
    FederatedUserStatus,
    FederatedUserStatusOption,
    FederatedUserLinkSyncStatus,
    FederatedUserLinkSyncStatusOption,
    FederationConflictStatus,
    FederationConflictStatusOption,
    FederationSyncStrategy,
    FederationSyncStrategyOption,
    CentralPermission,
    CentralPermissionOption,
    TenantPermission,
    TenantPermissionOption,
    BadgePreset,
    BadgePresetOption,
    TenantConfigKey,
    TenantConfigKeyOption,
} from '@/types/enums';

TS;

        // AddonType metadata
        $output .= $this->generateEnumMetadataMap(
            'ADDON_TYPE',
            'AddonType',
            'AddonTypeOption',
            AddonType::cases(),
            fn ($case) => [
                'value' => $case->value,
                'label' => $case->name()['en'],
                'description' => $case->description()['en'],
                'icon' => $case->icon(),
                'color' => $case->color(),
                'badge_variant' => $case->badgeVariant(),
                'category' => $case->category(),
                'unit_label' => $case->unitLabel()['en'],
                'is_metered' => $case->isMetered(),
                'is_stackable' => $case->isStackable(),
                'is_recurring' => $case->isRecurring(),
                'is_one_time' => $case->isOneTime(),
                'has_validity' => $case->hasValidity(),
            ]
        );

        // AddonStatus metadata
        $output .= $this->generateEnumMetadataMap(
            'ADDON_STATUS',
            'AddonStatus',
            'AddonStatusOption',
            AddonStatus::cases(),
            fn ($case) => [
                'value' => $case->value,
                'label' => $case->name()['en'],
                'description' => $case->description()['en'],
                'icon' => $case->icon(),
                'color' => $case->color(),
                'badge_variant' => $case->badgeVariant(),
                'is_usable' => $case->isUsable(),
                'is_terminal' => $case->isTerminal(),
            ]
        );

        // BillingPeriod metadata
        $output .= $this->generateEnumMetadataMap(
            'BILLING_PERIOD',
            'BillingPeriod',
            'BillingPeriodOption',
            BillingPeriod::cases(),
            fn ($case) => [
                'value' => $case->value,
                'label' => $case->name()['en'],
                'description' => $case->description()['en'],
                'icon' => $case->icon(),
                'color' => $case->color(),
                'badge_variant' => $case->badgeVariant(),
                'is_recurring' => $case->isRecurring(),
            ]
        );

        // PlanFeature metadata
        $output .= $this->generateEnumMetadataMap(
            'PLAN_FEATURE',
            'PlanFeature',
            'PlanFeatureOption',
            PlanFeature::cases(),
            fn ($case) => [
                'value' => $case->value,
                'label' => $case->name()['en'],
                'description' => $case->description()['en'],
                'icon' => $case->icon(),
                'color' => $case->color(),
                'badge_variant' => $case->badgeVariant(),
                'category' => $case->category(),
                'permissions' => $case->permissions(),
                'is_customizable' => $case->isCustomizable(),
            ]
        );

        // PlanLimit metadata
        $output .= $this->generateEnumMetadataMap(
            'PLAN_LIMIT',
            'PlanLimit',
            'PlanLimitOption',
            PlanLimit::cases(),
            fn ($case) => [
                'value' => $case->value,
                'label' => $case->name()['en'],
                'description' => $case->description()['en'],
                'icon' => $case->icon(),
                'color' => $case->color(),
                'badge_variant' => $case->badgeVariant(),
                'unit' => $case->unit(),
                'unit_label' => $case->unitLabel()['en'],
                'default_value' => $case->defaultValue(),
                'allows_unlimited' => $case->allowsUnlimited(),
                'is_customizable' => $case->isCustomizable(),
            ]
        );

        // TenantRole metadata
        $output .= $this->generateEnumMetadataMap(
            'TENANT_ROLE',
            'TenantRole',
            'TenantRoleOption',
            TenantRole::cases(),
            fn ($case) => [
                'value' => $case->value,
                'label' => $case->name()['en'],
                'description' => $case->description()['en'],
                'icon' => $case->icon(),
                'color' => $case->color(),
                'badge_variant' => $case->badgeVariant(),
                'is_system' => $case->isSystemRole(),
            ]
        );

        // FederatedUserStatus metadata
        $output .= $this->generateEnumMetadataMap(
            'FEDERATED_USER_STATUS',
            'FederatedUserStatus',
            'FederatedUserStatusOption',
            FederatedUserStatus::cases(),
            fn ($case) => [
                'value' => $case->value,
                'label' => $case->name()['en'],
                'description' => $case->description()['en'],
                'icon' => $case->icon(),
                'color' => $case->color(),
                'badge_variant' => $case->badgeVariant(),
                'can_sync' => $case->canSync(),
                'is_pending' => $case->isPending(),
            ]
        );

        // FederatedUserLinkSyncStatus metadata
        $output .= $this->generateEnumMetadataMap(
            'FEDERATED_USER_LINK_SYNC_STATUS',
            'FederatedUserLinkSyncStatus',
            'FederatedUserLinkSyncStatusOption',
            FederatedUserLinkSyncStatus::cases(),
            fn ($case) => [
                'value' => $case->value,
                'label' => $case->name()['en'],
                'description' => $case->description()['en'],
                'icon' => $case->icon(),
                'color' => $case->color(),
                'badge_variant' => $case->badgeVariant(),
                'needs_sync' => $case->needsSync(),
                'has_issue' => $case->hasIssue(),
            ]
        );

        // FederationConflictStatus metadata
        $output .= $this->generateEnumMetadataMap(
            'FEDERATION_CONFLICT_STATUS',
            'FederationConflictStatus',
            'FederationConflictStatusOption',
            FederationConflictStatus::cases(),
            fn ($case) => [
                'value' => $case->value,
                'label' => $case->name()['en'],
                'description' => $case->description()['en'],
                'icon' => $case->icon(),
                'color' => $case->color(),
                'badge_variant' => $case->badgeVariant(),
                'requires_action' => $case->requiresAction(),
                'is_terminal' => $case->isTerminal(),
            ]
        );

        // FederationSyncStrategy metadata
        $output .= $this->generateEnumMetadataMap(
            'FEDERATION_SYNC_STRATEGY',
            'FederationSyncStrategy',
            'FederationSyncStrategyOption',
            FederationSyncStrategy::cases(),
            fn ($case) => [
                'value' => $case->value,
                'label' => $case->name()['en'],
                'description' => $case->description()['en'],
                'icon' => $case->icon(),
                'color' => $case->color(),
                'badge_variant' => $case->badgeVariant(),
                'creates_conflicts' => $case->createsConflicts(),
                'auto_resolves' => $case->autoResolves(),
            ]
        );

        // CentralPermission metadata
        $output .= $this->generateEnumMetadataMap(
            'CENTRAL_PERMISSION',
            'CentralPermission',
            'CentralPermissionOption',
            CentralPermission::cases(),
            fn ($case) => [
                'value' => $case->value,
                'label' => $case->label('en'),
                'description' => $case->translatedDescription('en'),
                'icon' => $case->icon(),
                'color' => $case->color(),
                'badge_variant' => $case->badgeVariant(),
                'category' => $case->category(),
                'action' => $case->action(),
            ]
        );

        // TenantPermission metadata
        $output .= $this->generateEnumMetadataMap(
            'TENANT_PERMISSION',
            'TenantPermission',
            'TenantPermissionOption',
            TenantPermission::cases(),
            fn ($case) => [
                'value' => $case->value,
                'label' => $case->label('en'),
                'description' => $case->translatedDescription('en'),
                'icon' => $case->icon(),
                'color' => $case->color(),
                'badge_variant' => $case->badgeVariant(),
                'category' => $case->category(),
                'action' => $case->action(),
            ]
        );

        // BadgePreset metadata
        $output .= $this->generateEnumMetadataMap(
            'BADGE_PRESET',
            'BadgePreset',
            'BadgePresetOption',
            BadgePreset::cases(),
            fn ($case) => [
                'value' => $case->value,
                'label' => $case->label('en'),
                'description' => $case->translatedDescription('en'),
                'icon' => $case->icon(),
                'color' => $case->color(),
                'badge_variant' => $case->badgeVariant(),
                'bg' => $case->colorClasses()['bg'],
                'text' => $case->colorClasses()['text'],
                'border' => $case->colorClasses()['border'],
            ]
        );

        // TenantConfigKey metadata
        $output .= $this->generateEnumMetadataMap(
            'TENANT_CONFIG_KEY',
            'TenantConfigKey',
            'TenantConfigKeyOption',
            TenantConfigKey::cases(),
            fn ($case) => [
                'value' => $case->value,
                'label' => $case->label('en'),
                'description' => $case->translatedDescription('en'),
                'icon' => $case->icon(),
                'color' => $case->color(),
                'badge_variant' => $case->badgeVariant(),
                'category' => $case->category(),
                'default_value' => $case->defaultValue(),
            ]
        );

        // Helper functions
        $output .= <<<'TS'

/**
 * Get metadata for an AddonType value.
 */
export function getAddonTypeMeta(type: AddonType): AddonTypeOption {
    return ADDON_TYPE[type];
}

/**
 * Get metadata for an AddonStatus value.
 */
export function getAddonStatusMeta(status: AddonStatus): AddonStatusOption {
    return ADDON_STATUS[status];
}

/**
 * Get metadata for a BillingPeriod value.
 */
export function getBillingPeriodMeta(period: BillingPeriod): BillingPeriodOption {
    return BILLING_PERIOD[period];
}

/**
 * Get metadata for a PlanFeature value.
 */
export function getPlanFeatureMeta(feature: PlanFeature): PlanFeatureOption {
    return PLAN_FEATURE[feature];
}

/**
 * Get metadata for a PlanLimit value.
 */
export function getPlanLimitMeta(limit: PlanLimit): PlanLimitOption {
    return PLAN_LIMIT[limit];
}

/**
 * Get metadata for a TenantRole value.
 */
export function getTenantRoleMeta(role: TenantRole): TenantRoleOption {
    return TENANT_ROLE[role];
}

/**
 * Get metadata for a FederatedUserStatus value.
 */
export function getFederatedUserStatusMeta(status: FederatedUserStatus): FederatedUserStatusOption {
    return FEDERATED_USER_STATUS[status];
}

/**
 * Get metadata for a FederatedUserLinkSyncStatus value.
 */
export function getFederatedUserLinkSyncStatusMeta(status: FederatedUserLinkSyncStatus): FederatedUserLinkSyncStatusOption {
    return FEDERATED_USER_LINK_SYNC_STATUS[status];
}

/**
 * Get metadata for a FederationConflictStatus value.
 */
export function getFederationConflictStatusMeta(status: FederationConflictStatus): FederationConflictStatusOption {
    return FEDERATION_CONFLICT_STATUS[status];
}

/**
 * Get metadata for a FederationSyncStrategy value.
 */
export function getFederationSyncStrategyMeta(strategy: FederationSyncStrategy): FederationSyncStrategyOption {
    return FEDERATION_SYNC_STRATEGY[strategy];
}

/**
 * Get metadata for a CentralPermission value.
 */
export function getCentralPermissionMeta(permission: CentralPermission): CentralPermissionOption {
    return CENTRAL_PERMISSION[permission];
}

/**
 * Get metadata for a TenantPermission value.
 */
export function getTenantPermissionMeta(permission: TenantPermission): TenantPermissionOption {
    return TENANT_PERMISSION[permission];
}

/**
 * Get metadata for a BadgePreset value.
 */
export function getBadgePresetMeta(preset: BadgePreset): BadgePresetOption {
    return BADGE_PRESET[preset];
}

/**
 * Get metadata for a TenantConfigKey value.
 */
export function getTenantConfigKeyMeta(key: TenantConfigKey): TenantConfigKeyOption {
    return TENANT_CONFIG_KEY[key];
}
TS;

        return $output;
    }

    /**
     * Generate a typed metadata map constant.
     *
     * @param  array<\BackedEnum>  $cases
     */
    protected function generateEnumMetadataMap(
        string $constName,
        string $enumType,
        string $optionType,
        array $cases,
        callable $mapper
    ): string {
        $output = "export const {$constName}: Record<{$enumType}, {$optionType}> = {\n";

        foreach ($cases as $case) {
            $data = $mapper($case);
            $output .= "    '{$case->value}': ".$this->phpToTypescript($data).",\n";
        }

        $output .= "};\n\n";

        return $output;
    }

    /**
     * Convert PHP array to TypeScript literal (object or array).
     *
     * @param  array<string|int, mixed>  $data
     */
    protected function phpToTypescript(array $data): string
    {
        // Check if it's a list (sequential numeric keys starting from 0)
        if (array_is_list($data)) {
            $items = [];
            foreach ($data as $value) {
                $items[] = $this->valueToTypescript($value);
            }

            return '['.implode(', ', $items).']';
        }

        // It's an associative array - convert to object
        $pairs = [];
        foreach ($data as $key => $value) {
            $pairs[] = "{$key}: ".$this->valueToTypescript($value);
        }

        return '{ '.implode(', ', $pairs).' }';
    }

    /**
     * Convert a single PHP value to TypeScript literal.
     */
    protected function valueToTypescript(mixed $value): string
    {
        return match (true) {
            is_bool($value) => $value ? 'true' : 'false',
            is_null($value) => 'null',
            is_string($value) => "'{$value}'",
            is_array($value) => $this->phpToTypescript($value),
            default => (string) $value,
        };
    }

    /**
     * Generate translations from enums into lang JSON files.
     *
     * Updates the translation files with enum labels and descriptions.
     * PHP enums are the single source of truth for translations.
     */
    protected function generateTranslations(): void
    {
        $locales = ['en', 'pt_BR'];

        // Define enum translations to generate
        $enumTranslations = [
            // Phase 1 enums
            'enums.addon_type' => $this->getEnumTranslations(
                AddonType::cases(),
                fn ($case, $locale) => $case->name()[$locale] ?? $case->name()['en']
            ),
            'enums.addon_status' => $this->getEnumTranslations(
                AddonStatus::cases(),
                fn ($case, $locale) => $case->name()[$locale] ?? $case->name()['en']
            ),
            'enums.billing_period' => $this->getEnumTranslations(
                BillingPeriod::cases(),
                fn ($case, $locale) => $case->name()[$locale] ?? $case->name()['en']
            ),
            // Phase 2 enums
            'enums.plan_feature' => $this->getEnumTranslations(
                PlanFeature::cases(),
                fn ($case, $locale) => $case->name()[$locale] ?? $case->name()['en']
            ),
            'enums.plan_limit' => $this->getEnumTranslations(
                PlanLimit::cases(),
                fn ($case, $locale) => $case->name()[$locale] ?? $case->name()['en']
            ),
            'enums.tenant_role' => $this->getEnumTranslations(
                TenantRole::cases(),
                fn ($case, $locale) => $case->name()[$locale] ?? $case->name()['en']
            ),
            // Federation enums
            'admin.federation.user_status' => $this->getEnumTranslations(
                FederatedUserStatus::cases(),
                fn ($case, $locale) => $case->name()[$locale] ?? $case->name()['en']
            ),
            'admin.federation.link_status' => $this->getEnumTranslations(
                FederatedUserLinkSyncStatus::cases(),
                fn ($case, $locale) => $case->name()[$locale] ?? $case->name()['en']
            ),
            'admin.federation.conflict' => $this->getEnumTranslations(
                FederationConflictStatus::cases(),
                fn ($case, $locale) => $case->name()[$locale] ?? $case->name()['en']
            ),
            'admin.federation.sync_strategy' => $this->getEnumTranslations(
                FederationSyncStrategy::cases(),
                fn ($case, $locale) => $case->name()[$locale] ?? $case->name()['en']
            ),
            // Phase 3 enums
            'permissions.central' => $this->getEnumTranslations(
                CentralPermission::cases(),
                fn ($case, $locale) => $case->name()[$locale] ?? $case->name()['en']
            ),
            'permissions.tenant' => $this->getEnumTranslations(
                TenantPermission::cases(),
                fn ($case, $locale) => $case->name()[$locale] ?? $case->name()['en']
            ),
            'enums.badge_preset' => $this->getEnumTranslations(
                BadgePreset::cases(),
                fn ($case, $locale) => $case->name()[$locale] ?? $case->name()['en']
            ),
            'enums.tenant_config_key' => $this->getEnumTranslations(
                TenantConfigKey::cases(),
                fn ($case, $locale) => $case->name()[$locale] ?? $case->name()['en']
            ),
        ];

        foreach ($locales as $locale) {
            $this->updateTranslationFile($locale, $enumTranslations);
        }
    }

    /**
     * Get translations for enum cases.
     *
     * @param  array<\BackedEnum>  $cases
     * @return array<string, callable>
     */
    protected function getEnumTranslations(array $cases, callable $labelGetter): array
    {
        $translations = [];
        foreach ($cases as $case) {
            $translations[$case->value] = $labelGetter;
        }

        return ['cases' => $cases, 'getter' => $labelGetter];
    }

    /**
     * Update a translation file with enum translations.
     *
     * @param  array<string, array>  $enumTranslations
     */
    protected function updateTranslationFile(string $locale, array $enumTranslations): void
    {
        $filePath = lang_path("{$locale}.json");

        // Read existing translations
        $translations = [];
        if (file_exists($filePath)) {
            $content = file_get_contents($filePath);
            $translations = json_decode($content, true) ?? [];
        }

        // Update enum translations
        foreach ($enumTranslations as $prefix => $config) {
            $cases = $config['cases'];
            $getter = $config['getter'];

            foreach ($cases as $case) {
                $key = "{$prefix}.{$case->value}";
                $translations[$key] = $getter($case, $locale);
            }
        }

        // Sort translations alphabetically
        ksort($translations);

        // Write back
        $json = json_encode($translations, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        file_put_contents($filePath, $json."\n");

        $this->info("✅ Updated translations: {$filePath}");
    }
}
