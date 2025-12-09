<?php

namespace App\Console\Commands;

use App\Enums\AddonStatus;
use App\Enums\AddonType;
use App\Enums\BillingPeriod;
use App\Enums\FederatedUserLinkSyncStatus;
use App\Enums\FederatedUserStatus;
use App\Enums\FederationConflictStatus;
use App\Enums\FederationSyncStrategy;
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
 * - app/Enums/FederatedUserStatus.php
 * - app/Enums/FederatedUserLinkSyncStatus.php
 * - app/Enums/FederationConflictStatus.php
 * - app/Enums/FederationSyncStrategy.php
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
            'category' => 'string',
            'unit' => 'string',
            'unit_label' => 'string',
            'is_metered' => 'boolean',
            'is_stackable' => 'boolean',
            'limit_key' => 'string | null',
            'increases_limit' => 'boolean',
        ]);

        // AddonStatus
        $output .= $this->generateEnumType('AddonStatus', AddonStatus::cases());
        $output .= $this->generateEnumInterface('AddonStatusOption', [
            'value' => 'AddonStatus',
            'label' => 'string',
            'description' => 'string',
            'icon' => 'string',
            'color' => 'string',
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
            'is_recurring' => 'boolean',
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
            'creates_conflicts' => 'boolean',
            'auto_resolves' => 'boolean',
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
                ['FederatedUserStatus', count(FederatedUserStatus::cases()), 'FederatedUserStatusOption'],
                ['FederatedUserLinkSyncStatus', count(FederatedUserLinkSyncStatus::cases()), 'FederatedUserLinkSyncStatusOption'],
                ['FederationConflictStatus', count(FederationConflictStatus::cases()), 'FederationConflictStatusOption'],
                ['FederationSyncStrategy', count(FederationSyncStrategy::cases()), 'FederationSyncStrategyOption'],
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
    FederatedUserStatus,
    FederatedUserStatusOption,
    FederatedUserLinkSyncStatus,
    FederatedUserLinkSyncStatusOption,
    FederationConflictStatus,
    FederationConflictStatusOption,
    FederationSyncStrategy,
    FederationSyncStrategyOption,
} from '@/types/enums';

TS;

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
                'creates_conflicts' => $case->createsConflicts(),
                'auto_resolves' => $case->autoResolves(),
            ]
        );

        // Helper functions
        $output .= <<<'TS'

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
export function getFederationSyncStrategyMeta(status: FederationSyncStrategy): FederationSyncStrategyOption {
    return FEDERATION_SYNC_STRATEGY[status];
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
     * Convert PHP array to TypeScript object literal.
     *
     * @param  array<string, mixed>  $data
     */
    protected function phpToTypescript(array $data): string
    {
        $pairs = [];
        foreach ($data as $key => $value) {
            $tsValue = match (true) {
                is_bool($value) => $value ? 'true' : 'false',
                is_null($value) => 'null',
                is_string($value) => "'{$value}'",
                is_array($value) => $this->phpToTypescript($value),
                default => (string) $value,
            };
            $pairs[] = "{$key}: {$tsValue}";
        }

        return '{ '.implode(', ', $pairs).' }';
    }
}
