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
use App\Enums\PermissionAction;
use App\Enums\PermissionCategory;
use App\Enums\PlanFeature;
use App\Enums\PlanLimit;
use App\Enums\TenantConfigKey;
use App\Enums\TenantPermission;
use App\Enums\TenantRole;
use App\Http\Resources\Concerns\HasTypescriptType;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use ReflectionClass;

/**
 * Generate TypeScript types from PHP Enums (Single Source of Truth).
 *
 * This is the SINGLE command to generate all TypeScript types.
 * PHP Enums are the source of truth for:
 * - Union types and interfaces
 * - Runtime metadata maps
 * - Permission types
 * - Plan interfaces
 * - Translations
 *
 * Usage:
 *   sail artisan types:generate              # Generate everything
 *   sail artisan types:generate --fresh      # Clean and regenerate
 *
 * Generated files:
 *   - resources/js/types/enums.d.ts          # Union types + Option interfaces
 *   - resources/js/types/permissions.d.ts   # Permission type + Auth interface
 *   - resources/js/types/plan.d.ts          # Plan data interfaces
 *   - resources/js/types/resources.d.ts     # API Resource interfaces
 *   - resources/js/lib/enum-metadata.ts     # Runtime metadata maps
 *   - lang/{locale}.json                    # Translations (updated)
 */
class GenerateTypes extends Command
{
    protected $signature = 'types:generate
                            {--fresh : Clean generated files before regenerating}
                            {--force : Force write even if translations would be lost}';

    protected $description = 'Generate TypeScript types from PHP Enums (single source of truth)';

    /**
     * All enum configurations for type generation.
     *
     * @var array<string, array{class: class-string, interface: array<string, string>, metadata: callable}>
     */
    protected array $enums = [];

    public function __construct()
    {
        parent::__construct();
        $this->initializeEnums();
    }

    public function handle(): int
    {
        $this->info('');
        $this->info('  ╔══════════════════════════════════════════════════════════╗');
        $this->info('  ║  TypeScript Type Generator (Single Source of Truth)      ║');
        $this->info('  ╚══════════════════════════════════════════════════════════╝');
        $this->newLine();

        if ($this->option('fresh')) {
            $this->cleanGeneratedFiles();
        }

        // Generate all files
        $this->generateEnumTypes();
        $this->generateEnumMetadata();
        $this->generatePermissionTypes();
        $this->generatePlanTypes();
        $this->generateResourceTypes();
        $this->generateTranslations();

        $this->newLine();
        $this->displaySummary();

        return self::SUCCESS;
    }

    /**
     * Initialize enum configurations.
     */
    protected function initializeEnums(): void
    {
        $this->enums = [
            'AddonType' => [
                'class' => AddonType::class,
                'interface' => [
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
                ],
                'metadata' => fn ($case) => [
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
                ],
                'translations' => fn ($case, $locale) => $case->name()[$locale] ?? $case->name()['en'],
                'translation_key' => 'enums.addon_type',
            ],
            'AddonStatus' => [
                'class' => AddonStatus::class,
                'interface' => [
                    'value' => 'AddonStatus',
                    'label' => 'string',
                    'description' => 'string',
                    'icon' => 'string',
                    'color' => 'string',
                    'badge_variant' => "'default' | 'destructive' | 'secondary' | 'outline'",
                    'is_usable' => 'boolean',
                    'is_terminal' => 'boolean',
                ],
                'metadata' => fn ($case) => [
                    'value' => $case->value,
                    'label' => $case->name()['en'],
                    'description' => $case->description()['en'],
                    'icon' => $case->icon(),
                    'color' => $case->color(),
                    'badge_variant' => $case->badgeVariant(),
                    'is_usable' => $case->isUsable(),
                    'is_terminal' => $case->isTerminal(),
                ],
                'translations' => fn ($case, $locale) => $case->name()[$locale] ?? $case->name()['en'],
                'translation_key' => 'enums.addon_status',
            ],
            'BillingPeriod' => [
                'class' => BillingPeriod::class,
                'interface' => [
                    'value' => 'BillingPeriod',
                    'label' => 'string',
                    'description' => 'string',
                    'icon' => 'string',
                    'color' => 'string',
                    'badge_variant' => "'default' | 'destructive' | 'secondary' | 'outline'",
                    'is_recurring' => 'boolean',
                ],
                'metadata' => fn ($case) => [
                    'value' => $case->value,
                    'label' => $case->name()['en'],
                    'description' => $case->description()['en'],
                    'icon' => $case->icon(),
                    'color' => $case->color(),
                    'badge_variant' => $case->badgeVariant(),
                    'is_recurring' => $case->isRecurring(),
                ],
                'translations' => fn ($case, $locale) => $case->name()[$locale] ?? $case->name()['en'],
                'translation_key' => 'enums.billing_period',
            ],
            'PlanFeature' => [
                'class' => PlanFeature::class,
                'interface' => [
                    'value' => 'PlanFeature',
                    'label' => 'string',
                    'description' => 'string',
                    'icon' => 'string',
                    'color' => 'string',
                    'badge_variant' => "'default' | 'destructive' | 'secondary' | 'outline'",
                    'category' => 'string',
                    'permissions' => 'string[]',
                    'is_customizable' => 'boolean',
                ],
                'metadata' => fn ($case) => [
                    'value' => $case->value,
                    'label' => $case->name()['en'],
                    'description' => $case->description()['en'],
                    'icon' => $case->icon(),
                    'color' => $case->color(),
                    'badge_variant' => $case->badgeVariant(),
                    'category' => $case->category(),
                    'permissions' => $case->permissions(),
                    'is_customizable' => $case->isCustomizable(),
                ],
                'translations' => fn ($case, $locale) => $case->name()[$locale] ?? $case->name()['en'],
                'translation_key' => 'enums.plan_feature',
            ],
            'PlanLimit' => [
                'class' => PlanLimit::class,
                'interface' => [
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
                ],
                'metadata' => fn ($case) => [
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
                ],
                'translations' => fn ($case, $locale) => $case->name()[$locale] ?? $case->name()['en'],
                'translation_key' => 'enums.plan_limit',
            ],
            'TenantRole' => [
                'class' => TenantRole::class,
                'interface' => [
                    'value' => 'TenantRole',
                    'label' => 'string',
                    'description' => 'string',
                    'icon' => 'string',
                    'color' => 'string',
                    'badge_variant' => "'default' | 'destructive' | 'secondary' | 'outline'",
                    'is_system' => 'boolean',
                ],
                'metadata' => fn ($case) => [
                    'value' => $case->value,
                    'label' => $case->name()['en'],
                    'description' => $case->description()['en'],
                    'icon' => $case->icon(),
                    'color' => $case->color(),
                    'badge_variant' => $case->badgeVariant(),
                    'is_system' => $case->isSystemRole(),
                ],
                'translations' => fn ($case, $locale) => $case->name()[$locale] ?? $case->name()['en'],
                'translation_key' => 'enums.tenant_role',
            ],
            'FederatedUserStatus' => [
                'class' => FederatedUserStatus::class,
                'interface' => [
                    'value' => 'FederatedUserStatus',
                    'label' => 'string',
                    'description' => 'string',
                    'icon' => 'string',
                    'color' => 'string',
                    'badge_variant' => "'default' | 'destructive' | 'secondary' | 'outline'",
                    'can_sync' => 'boolean',
                    'is_pending' => 'boolean',
                ],
                'metadata' => fn ($case) => [
                    'value' => $case->value,
                    'label' => $case->name()['en'],
                    'description' => $case->description()['en'],
                    'icon' => $case->icon(),
                    'color' => $case->color(),
                    'badge_variant' => $case->badgeVariant(),
                    'can_sync' => $case->canSync(),
                    'is_pending' => $case->isPending(),
                ],
                'translations' => fn ($case, $locale) => $case->name()[$locale] ?? $case->name()['en'],
                'translation_key' => 'admin.federation.user_status',
            ],
            'FederatedUserLinkSyncStatus' => [
                'class' => FederatedUserLinkSyncStatus::class,
                'interface' => [
                    'value' => 'FederatedUserLinkSyncStatus',
                    'label' => 'string',
                    'description' => 'string',
                    'icon' => 'string',
                    'color' => 'string',
                    'badge_variant' => "'default' | 'destructive' | 'secondary' | 'outline'",
                    'needs_sync' => 'boolean',
                    'has_issue' => 'boolean',
                ],
                'metadata' => fn ($case) => [
                    'value' => $case->value,
                    'label' => $case->name()['en'],
                    'description' => $case->description()['en'],
                    'icon' => $case->icon(),
                    'color' => $case->color(),
                    'badge_variant' => $case->badgeVariant(),
                    'needs_sync' => $case->needsSync(),
                    'has_issue' => $case->hasIssue(),
                ],
                'translations' => fn ($case, $locale) => $case->name()[$locale] ?? $case->name()['en'],
                'translation_key' => 'admin.federation.link_status',
            ],
            'FederationConflictStatus' => [
                'class' => FederationConflictStatus::class,
                'interface' => [
                    'value' => 'FederationConflictStatus',
                    'label' => 'string',
                    'description' => 'string',
                    'icon' => 'string',
                    'color' => 'string',
                    'badge_variant' => "'default' | 'destructive' | 'secondary' | 'outline'",
                    'requires_action' => 'boolean',
                    'is_terminal' => 'boolean',
                ],
                'metadata' => fn ($case) => [
                    'value' => $case->value,
                    'label' => $case->name()['en'],
                    'description' => $case->description()['en'],
                    'icon' => $case->icon(),
                    'color' => $case->color(),
                    'badge_variant' => $case->badgeVariant(),
                    'requires_action' => $case->requiresAction(),
                    'is_terminal' => $case->isTerminal(),
                ],
                'translations' => fn ($case, $locale) => $case->name()[$locale] ?? $case->name()['en'],
                'translation_key' => 'admin.federation.conflict',
            ],
            'FederationSyncStrategy' => [
                'class' => FederationSyncStrategy::class,
                'interface' => [
                    'value' => 'FederationSyncStrategy',
                    'label' => 'string',
                    'description' => 'string',
                    'icon' => 'string',
                    'color' => 'string',
                    'badge_variant' => "'default' | 'destructive' | 'secondary' | 'outline'",
                    'creates_conflicts' => 'boolean',
                    'auto_resolves' => 'boolean',
                ],
                'metadata' => fn ($case) => [
                    'value' => $case->value,
                    'label' => $case->name()['en'],
                    'description' => $case->description()['en'],
                    'icon' => $case->icon(),
                    'color' => $case->color(),
                    'badge_variant' => $case->badgeVariant(),
                    'creates_conflicts' => $case->createsConflicts(),
                    'auto_resolves' => $case->autoResolves(),
                ],
                'translations' => fn ($case, $locale) => $case->name()[$locale] ?? $case->name()['en'],
                'translation_key' => 'admin.federation.sync_strategy',
            ],
            'CentralPermission' => [
                'class' => CentralPermission::class,
                'interface' => [
                    'value' => 'CentralPermission',
                    'label' => 'string',
                    'description' => 'string',
                    'icon' => 'string',
                    'color' => 'string',
                    'badge_variant' => "'default' | 'destructive' | 'secondary' | 'outline'",
                    'category' => 'string',
                    'action' => 'string',
                ],
                'metadata' => fn ($case) => [
                    'value' => $case->value,
                    'label' => $case->label('en'),
                    'description' => $case->translatedDescription('en'),
                    'icon' => $case->icon(),
                    'color' => $case->color(),
                    'badge_variant' => $case->badgeVariant(),
                    'category' => $case->category(),
                    'action' => $case->action(),
                ],
                'translations' => fn ($case, $locale) => $case->name()[$locale] ?? $case->name()['en'],
                'translation_key' => 'permissions.central',
            ],
            'TenantPermission' => [
                'class' => TenantPermission::class,
                'interface' => [
                    'value' => 'TenantPermission',
                    'label' => 'string',
                    'description' => 'string',
                    'icon' => 'string',
                    'color' => 'string',
                    'badge_variant' => "'default' | 'destructive' | 'secondary' | 'outline'",
                    'category' => 'string',
                    'action' => 'string',
                ],
                'metadata' => fn ($case) => [
                    'value' => $case->value,
                    'label' => $case->label('en'),
                    'description' => $case->translatedDescription('en'),
                    'icon' => $case->icon(),
                    'color' => $case->color(),
                    'badge_variant' => $case->badgeVariant(),
                    'category' => $case->category(),
                    'action' => $case->action(),
                ],
                'translations' => fn ($case, $locale) => $case->name()[$locale] ?? $case->name()['en'],
                'translation_key' => 'permissions.tenant',
            ],
            'PermissionCategory' => [
                'class' => PermissionCategory::class,
                'interface' => [
                    'value' => 'PermissionCategory',
                    'label' => 'string',
                    'icon' => 'string',
                    'color' => 'string',
                    'badge_variant' => "'default' | 'destructive' | 'secondary' | 'outline'",
                ],
                'metadata' => fn ($case) => [
                    'value' => $case->value,
                    'label' => $case->label('en'),
                    'icon' => $case->icon(),
                    'color' => $case->color(),
                    'badge_variant' => $case->badgeVariant(),
                ],
                'translations' => fn ($case, $locale) => $case->name()[$locale] ?? $case->name()['en'],
                'translation_key' => 'enums.permission_category',
            ],
            'PermissionAction' => [
                'class' => PermissionAction::class,
                'interface' => [
                    'value' => 'PermissionAction',
                    'label' => 'string',
                ],
                'metadata' => fn ($case) => [
                    'value' => $case->value,
                    'label' => $case->label('en'),
                ],
                'translations' => fn ($case, $locale) => $case->name()[$locale] ?? $case->name()['en'],
                'translation_key' => 'enums.permission_action',
            ],
            'BadgePreset' => [
                'class' => BadgePreset::class,
                'interface' => [
                    'value' => 'BadgePreset',
                    'label' => 'string',
                    'description' => 'string',
                    'icon' => 'string',
                    'color' => 'string',
                    'badge_variant' => "'default' | 'destructive' | 'secondary' | 'outline'",
                    'bg' => 'string',
                    'text' => 'string',
                    'border' => 'string',
                ],
                'metadata' => fn ($case) => [
                    'value' => $case->value,
                    'label' => $case->label('en'),
                    'description' => $case->translatedDescription('en'),
                    'icon' => $case->icon(),
                    'color' => $case->color(),
                    'badge_variant' => $case->badgeVariant(),
                    'bg' => $case->colorClasses()['bg'],
                    'text' => $case->colorClasses()['text'],
                    'border' => $case->colorClasses()['border'],
                ],
                'translations' => fn ($case, $locale) => $case->name()[$locale] ?? $case->name()['en'],
                'translation_key' => 'enums.badge_preset',
            ],
            'TenantConfigKey' => [
                'class' => TenantConfigKey::class,
                'interface' => [
                    'value' => 'TenantConfigKey',
                    'label' => 'string',
                    'description' => 'string',
                    'icon' => 'string',
                    'color' => 'string',
                    'badge_variant' => "'default' | 'destructive' | 'secondary' | 'outline'",
                    'category' => 'string',
                    'default_value' => 'string | number | null',
                ],
                'metadata' => fn ($case) => [
                    'value' => $case->value,
                    'label' => $case->label('en'),
                    'description' => $case->translatedDescription('en'),
                    'icon' => $case->icon(),
                    'color' => $case->color(),
                    'badge_variant' => $case->badgeVariant(),
                    'category' => $case->category(),
                    'default_value' => $case->defaultValue(),
                ],
                'translations' => fn ($case, $locale) => $case->name()[$locale] ?? $case->name()['en'],
                'translation_key' => 'enums.tenant_config_key',
            ],
        ];
    }

    /**
     * Clean generated files before regenerating.
     */
    protected function cleanGeneratedFiles(): void
    {
        $files = [
            resource_path('js/types/enums.d.ts'),
            resource_path('js/types/permissions.d.ts'),
            resource_path('js/types/plan.d.ts'),
            resource_path('js/types/resources.d.ts'),
            resource_path('js/lib/enum-metadata.ts'),
        ];

        foreach ($files as $file) {
            if (File::exists($file)) {
                File::delete($file);
            }
        }

        $this->info('🧹 Cleaned generated files');
    }

    // =========================================================================
    // ENUM TYPES (enums.d.ts)
    // =========================================================================

    protected function generateEnumTypes(): void
    {
        $output = $this->getEnumTypesHeader();

        foreach ($this->enums as $name => $config) {
            $cases = $config['class']::cases();
            $output .= $this->generateUnionType($name, $cases);
            $output .= $this->generateInterface("{$name}Option", $config['interface']);
        }

        $path = resource_path('js/types/enums.d.ts');
        File::put($path, $output);
        $this->info("  ✓ Generated: resources/js/types/enums.d.ts");
    }

    protected function getEnumTypesHeader(): string
    {
        $enumList = collect($this->enums)->keys()->map(fn ($name) => " * - app/Enums/{$name}.php")->join("\n");

        return <<<TS
/**
 * Enum Types - Auto-generated from PHP Enums
 *
 * DO NOT EDIT MANUALLY!
 * Run: sail artisan types:generate
 *
 * Source of truth:
{$enumList}
 */

TS;
    }

    protected function generateUnionType(string $name, array $cases): string
    {
        $values = array_map(fn ($case) => "'{$case->value}'", $cases);

        return "export type {$name} = ".implode(' | ', $values).";\n\n";
    }

    protected function generateInterface(string $name, array $fields): string
    {
        $output = "export interface {$name} {\n";
        foreach ($fields as $field => $type) {
            $output .= "    {$field}: {$type};\n";
        }
        $output .= "}\n\n";

        return $output;
    }

    // =========================================================================
    // ENUM METADATA (enum-metadata.ts)
    // =========================================================================

    protected function generateEnumMetadata(): void
    {
        $output = $this->getMetadataHeader();

        // Generate metadata maps
        foreach ($this->enums as $name => $config) {
            $cases = $config['class']::cases();
            $constName = $this->toConstName($name);
            $output .= $this->generateMetadataMap($constName, $name, "{$name}Option", $cases, $config['metadata']);
        }

        // Generate helper functions
        $output .= $this->generateHelperFunctions();

        $path = resource_path('js/lib/enum-metadata.ts');
        File::put($path, $output);
        $this->info("  ✓ Generated: resources/js/lib/enum-metadata.ts");
    }

    protected function getMetadataHeader(): string
    {
        $imports = collect($this->enums)->keys()->flatMap(fn ($name) => [$name, "{$name}Option"])->join(",\n    ");

        return <<<TS
/**
 * Enum Metadata - Auto-generated from PHP Enums
 *
 * DO NOT EDIT MANUALLY!
 * Run: sail artisan types:generate
 *
 * Contains the actual metadata (icon, color, label, etc.) for each enum value.
 */

import type {
    {$imports},
} from '@/types/enums';

TS;
    }

    protected function generateMetadataMap(string $constName, string $enumType, string $optionType, array $cases, callable $mapper): string
    {
        $output = "export const {$constName}: Record<{$enumType}, {$optionType}> = {\n";

        foreach ($cases as $case) {
            $data = $mapper($case);
            $output .= "    '{$case->value}': ".$this->phpToTypescript($data).",\n";
        }

        $output .= "};\n\n";

        return $output;
    }

    protected function generateHelperFunctions(): string
    {
        $functions = [];

        foreach ($this->enums as $name => $config) {
            $constName = $this->toConstName($name);
            $paramName = lcfirst($name);

            // Determine appropriate parameter name based on enum name
            $paramLabel = match (true) {
                str_contains($name, 'Status') => 'status',
                str_contains($name, 'Strategy') => 'strategy',
                str_contains($name, 'Permission') => 'permission',
                str_contains($name, 'Role') => 'role',
                str_contains($name, 'Type') => 'type',
                str_contains($name, 'Period') => 'period',
                str_contains($name, 'Feature') => 'feature',
                str_contains($name, 'Limit') => 'limit',
                str_contains($name, 'Preset') => 'preset',
                str_contains($name, 'Key') => 'key',
                default => 'value',
            };

            $functions[] = <<<TS
/**
 * Get metadata for a {$name} value.
 */
export function get{$name}Meta({$paramLabel}: {$name}): {$name}Option {
    return {$constName}[{$paramLabel}];
}
TS;
        }

        return "\n".implode("\n\n", $functions)."\n";
    }

    protected function toConstName(string $name): string
    {
        // Convert PascalCase to SCREAMING_SNAKE_CASE
        return strtoupper(preg_replace('/(?<!^)[A-Z]/', '_$0', $name));
    }

    // =========================================================================
    // PERMISSION TYPES (permissions.d.ts)
    // =========================================================================

    protected function generatePermissionTypes(): void
    {
        $timestamp = now()->toDateTimeString();

        // Get permissions from enums
        $tenantPermissions = collect(TenantPermission::cases())->map(fn ($p) => [
            'name' => $p->value,
            'description' => $p->description()['en'] ?? '',
            'category' => $p->category(),
            'source' => 'tenant',
        ]);

        $centralPermissions = collect(CentralPermission::cases())->map(fn ($p) => [
            'name' => $p->value,
            'description' => $p->description()['en'] ?? '',
            'category' => $p->category(),
            'source' => 'central',
        ]);

        // Merge and deduplicate
        $allPermissions = $tenantPermissions
            ->merge($centralPermissions)
            ->unique('name')
            ->values()
            ->toArray();

        $categories = collect($allPermissions)->pluck('category')->unique()->sort()->values()->toArray();
        $actions = collect($allPermissions)
            ->map(fn ($p) => explode(':', $p['name'])[1] ?? '')
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->toArray();

        $output = $this->generatePermissionTypesContent($allPermissions, $categories, $actions, $timestamp);

        $path = resource_path('js/types/permissions.d.ts');
        File::put($path, $output);
        $this->info("  ✓ Generated: resources/js/types/permissions.d.ts");
    }

    protected function generatePermissionTypesContent(array $permissions, array $categories, array $actions, string $timestamp): string
    {
        // Build Permission union type with JSDoc comments
        $permissionUnion = collect($permissions)
            ->map(fn ($p) => "  | '{$p['name']}'  // {$p['description']}")
            ->join("\n");

        // Build category-specific permission types
        $categoryTypes = collect($categories)
            ->map(function ($category) use ($permissions) {
                $categoryPerms = collect($permissions)
                    ->filter(fn ($p) => $p['category'] === $category)
                    ->map(fn ($p) => "  | '{$p['name']}'  // {$p['description']}")
                    ->join("\n");

                if (empty($categoryPerms)) {
                    return '';
                }

                $capitalizedCategory = ucfirst($category);

                return <<<TS
/**
 * {$capitalizedCategory} permissions
 */
export type {$capitalizedCategory}Permission =
{$categoryPerms};
TS;
            })
            ->filter()
            ->join("\n\n");

        // Build categories union
        $categoriesUnion = collect($categories)
            ->map(fn ($c) => "  | '{$c}'")
            ->join("\n");

        // Build actions union
        $actionsUnion = collect($actions)
            ->map(fn ($a) => "  | '{$a}'")
            ->join("\n");

        return <<<TS
/**
 * Auto-generated TypeScript types for Laravel permissions
 *
 * DO NOT EDIT THIS FILE MANUALLY
 * Generated at: {$timestamp}
 *
 * Source: TenantPermission and CentralPermission enums (single source of truth)
 *
 * To regenerate, run: sail artisan types:generate
 */

/**
 * All available permissions in the system
 * Format: <category>:<action>
 *
 * Note: Some permissions exist in both tenant and central contexts
 * (e.g., roles:view). The TypeScript type is deduplicated.
 */
export type Permission =
{$permissionUnion};

{$categoryTypes}

/**
 * Permission categories available in the system
 */
export type PermissionCategory =
{$categoriesUnion};

/**
 * Permission actions available in the system
 */
export type PermissionAction =
{$actionsUnion};

/**
 * Role metadata (for UI display only - NOT for authorization)
 */
export interface Role {
  /** Role name (owner, admin, member) */
  name: string | null;

  /** Is user owner of tenant? (for UI badges/display) */
  isOwner: boolean;

  /** Is user admin? (for UI badges/display) */
  isAdmin: boolean;

  /** Is user admin or owner? (for UI badges/display) */
  isAdminOrOwner: boolean;

  /** Is user a Super Admin? (global platform admin - for UI badges/display) */
  isSuperAdmin: boolean;
}

/**
 * Authentication state with permissions
 * Note: User type should be imported from your app's types (e.g., @/types)
 */
export interface Auth<TUser = unknown> {
  /** Current authenticated user */
  user: TUser | null;

  /**
   * Array of permission names the user HAS (not all with booleans)
   * Use with: permissions.includes('projects:view')
   * Or better: usePermissions().has('projects:view')
   */
  permissions: Permission[];

  /**
   * Role metadata (for UI only - badges, display names, etc)
   * WARNING: Do NOT use for authorization! Use permissions instead.
   */
  role: Role | null;
}

TS;
    }

    // =========================================================================
    // PLAN TYPES (plan.d.ts)
    // =========================================================================

    protected function generatePlanTypes(): void
    {
        $features = PlanFeature::frontendFeatures();
        $limits = PlanLimit::values();

        $output = <<<'TS'
/**
 * Plan Types - Auto-generated from PlanFeature and PlanLimit enums
 *
 * DO NOT EDIT MANUALLY!
 * Run: sail artisan types:generate
 *
 * Source of truth: app/Enums/PlanFeature.php, PlanLimit.php
 */

TS;

        // PlanFeatures interface
        $output .= "export interface PlanFeatures {\n";
        foreach ($features as $feature) {
            $output .= "    {$feature}: boolean;\n";
        }
        $output .= "}\n\n";

        // PlanLimits interface
        $output .= "export interface PlanLimits {\n";
        foreach ($limits as $limit) {
            $output .= "    {$limit}: number;\n";
        }
        $output .= "}\n\n";

        // PlanUsage interface
        $output .= "export interface PlanUsage {\n";
        foreach ($limits as $limit) {
            $output .= "    {$limit}: number;\n";
        }
        $output .= "}\n\n";

        // Documentation note
        $output .= <<<'TS'
/**
 * Note: For union types with all enum values (including 'base'),
 * use PlanFeature and PlanLimit from '@/types/enums'.
 *
 * The interfaces above (PlanFeatures, PlanLimits, PlanUsage) represent
 * the actual data structure returned by the API, where 'base' is always true
 * and thus excluded from the interface.
 */

TS;

        $path = resource_path('js/types/plan.d.ts');
        File::put($path, $output);
        $this->info("  ✓ Generated: resources/js/types/plan.d.ts");
    }

    // =========================================================================
    // RESOURCE TYPES (resources.d.ts)
    // =========================================================================

    protected function generateResourceTypes(): void
    {
        $resources = $this->discoverResources();

        if (empty($resources)) {
            $this->info('  ⚠ No Resources with HasTypescriptType trait found');

            return;
        }

        $output = $this->getResourceTypesHeader();

        // Group resources by context
        $grouped = [
            'central' => [],
            'tenant' => [],
            'shared' => [],
        ];

        foreach ($resources as $resource) {
            $context = $resource['context'] ?? 'shared';
            $grouped[$context][] = $resource;
        }

        // Generate interfaces for each context
        foreach ($grouped as $context => $contextResources) {
            if (empty($contextResources)) {
                continue;
            }

            $contextLabel = ucfirst($context);
            $output .= "// =============================================================================\n";
            $output .= "// {$contextLabel} Resources\n";
            $output .= "// =============================================================================\n\n";

            foreach ($contextResources as $resource) {
                $output .= $this->generateResourceInterface($resource);
            }
        }

        $path = resource_path('js/types/resources.d.ts');
        File::put($path, $output);
        $this->info('  ✓ Generated: resources/js/types/resources.d.ts ('.count($resources).' interfaces)');
    }

    /**
     * Discover all Resources that use HasTypescriptType trait.
     */
    protected function discoverResources(): array
    {
        $resources = [];
        $directories = [
            app_path('Http/Resources/Central'),
            app_path('Http/Resources/Tenant'),
            app_path('Http/Resources/Shared'),
            app_path('Http/Resources'), // For base/misc resources
        ];

        foreach ($directories as $directory) {
            if (! File::isDirectory($directory)) {
                continue;
            }

            $files = File::glob($directory.'/*.php');

            foreach ($files as $file) {
                $className = $this->getClassNameFromFile($file);

                if ($className === null) {
                    continue;
                }

                // Check if class uses HasTypescriptType trait
                if (! $this->usesTypescriptTrait($className)) {
                    continue;
                }

                $resources[] = [
                    'class' => $className,
                    'name' => $className::typescriptName(),
                    'schema' => $className::typescriptSchema(),
                    'context' => $className::typescriptContext(),
                ];
            }
        }

        return $resources;
    }

    /**
     * Get fully qualified class name from file path.
     */
    protected function getClassNameFromFile(string $filePath): ?string
    {
        $content = File::get($filePath);

        // Extract namespace
        if (! preg_match('/namespace\s+([^;]+);/', $content, $namespaceMatch)) {
            return null;
        }

        // Extract class name
        if (! preg_match('/class\s+(\w+)/', $content, $classMatch)) {
            return null;
        }

        return $namespaceMatch[1].'\\'.$classMatch[1];
    }

    /**
     * Check if a class uses the HasTypescriptType trait.
     */
    protected function usesTypescriptTrait(string $className): bool
    {
        if (! class_exists($className)) {
            return false;
        }

        $reflection = new ReflectionClass($className);

        // Check if class is abstract
        if ($reflection->isAbstract()) {
            return false;
        }

        // Check for trait usage (including parent classes)
        return in_array(HasTypescriptType::class, class_uses_recursive($className), true);
    }

    /**
     * Generate TypeScript interface for a Resource.
     */
    protected function generateResourceInterface(array $resource): string
    {
        $name = $resource['name'];
        $schema = $resource['schema'];

        $output = "export interface {$name} {\n";

        foreach ($schema as $property => $type) {
            $output .= "    {$property}: {$type};\n";
        }

        $output .= "}\n\n";

        return $output;
    }

    /**
     * Get header for resources.d.ts file.
     */
    protected function getResourceTypesHeader(): string
    {
        return <<<'TS'
/**
 * API Resource Types - Auto-generated from PHP Resources
 *
 * DO NOT EDIT MANUALLY!
 * Run: sail artisan types:generate
 *
 * Resources using the HasTypescriptType trait are automatically discovered
 * and their TypeScript interfaces are generated here.
 *
 * Source of truth: app/Http/Resources/ (with HasTypescriptType trait)
 */

// Import enums
import type {
    BillingPeriod,
    BadgePreset,
    TenantRole,
    FederationSyncStrategy,
    FederatedUserStatus,
    FederationConflictStatus,
} from './enums';

// Import plan types
import type { PlanFeatures, PlanLimits, PlanUsage } from './plan';

// Import common types
import type {
    Translations,
    ProjectAttachment,
    ProjectImage,
    ActivityCauser,
    ActivityProperties,
    TenantPlanSummary,
    TenantUser,
    InvitedByUser,
    AddonSummary,
    FederationGroupTenant,
    TenantFederationGroup,
    FederationGroupStats,
    FederatedUserSyncedData,
    FederatedUserLink,
} from './common';


TS;
    }

    // =========================================================================
    // TRANSLATIONS
    // =========================================================================

    protected function generateTranslations(): void
    {
        $locales = ['en', 'pt_BR'];

        foreach ($locales as $locale) {
            $this->updateTranslationFile($locale);
        }
    }

    protected function updateTranslationFile(string $locale): void
    {
        $filePath = lang_path("{$locale}.json");

        // Read existing translations
        $existingTranslations = [];
        if (File::exists($filePath)) {
            $content = File::get($filePath);
            $existingTranslations = json_decode($content, true) ?? [];
        }

        $existingCount = count($existingTranslations);

        // Start with existing translations (preserves all hardcoded keys)
        $translations = $existingTranslations;

        // Collect all enum translation keys that will be generated
        $enumKeys = [];

        // Update enum translations (only overwrites enum-generated keys)
        foreach ($this->enums as $name => $config) {
            $cases = $config['class']::cases();
            $getter = $config['translations'];
            $prefix = $config['translation_key'];

            foreach ($cases as $case) {
                $key = "{$prefix}.{$case->value}";
                $enumKeys[] = $key;
                $translations[$key] = $getter($case, $locale);
            }
        }

        // Sort translations
        ksort($translations);

        $newCount = count($translations);

        // Safety check: ABORT if significant translation loss detected
        // This happens when translations were accidentally deleted before running this command
        $minimumExpected = [
            'en' => 1200,     // English should have at least 1200 keys
            'pt_BR' => 1200,  // Portuguese should have at least 1200 keys
        ];

        $minExpected = $minimumExpected[$locale] ?? 200;

        if ($newCount < $minExpected && ! $this->option('force')) {
            $this->error("  ✗ ABORTED: lang/{$locale}.json has only {$newCount} translations (expected at least {$minExpected})");
            $this->error("    This indicates missing hardcoded translations!");
            $this->error("    The file was NOT modified to prevent data loss.");
            $this->error("");
            $this->error("    To fix this, restore translations from git:");
            $this->error("      git checkout HEAD -- lang/{$locale}.json");
            $this->error("");
            $this->error("    Or use --force to write anyway (NOT RECOMMENDED):");
            $this->error("      sail artisan types:generate --force");
            $this->newLine();

            return; // Do NOT write the file
        }

        // Write back
        $json = json_encode($translations, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        File::put($filePath, $json."\n");

        $this->info("  ✓ Updated translations: lang/{$locale}.json ({$newCount} keys, " . count($enumKeys) . " enum-generated)");
    }

    // =========================================================================
    // UTILITIES
    // =========================================================================

    protected function phpToTypescript(array $data): string
    {
        if (array_is_list($data)) {
            $items = array_map(fn ($v) => $this->valueToTypescript($v), $data);

            return '['.implode(', ', $items).']';
        }

        $pairs = [];
        foreach ($data as $key => $value) {
            $pairs[] = "{$key}: ".$this->valueToTypescript($value);
        }

        return '{ '.implode(', ', $pairs).' }';
    }

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

    // =========================================================================
    // SUMMARY
    // =========================================================================

    protected function displaySummary(): void
    {
        $this->info('  ╔══════════════════════════════════════════════════════════╗');
        $this->info('  ║  Summary                                                 ║');
        $this->info('  ╚══════════════════════════════════════════════════════════╝');
        $this->newLine();

        $rows = [];
        foreach ($this->enums as $name => $config) {
            $cases = $config['class']::cases();
            $rows[] = [$name, count($cases), "{$name}Option"];
        }

        $this->table(['Enum', 'Values', 'Interface'], $rows);

        $this->newLine();
        $this->info('  Generated files:');
        $this->line('    • resources/js/types/enums.d.ts');
        $this->line('    • resources/js/types/permissions.d.ts');
        $this->line('    • resources/js/types/plan.d.ts');
        $this->line('    • resources/js/types/resources.d.ts');
        $this->line('    • resources/js/lib/enum-metadata.ts');
        $this->line('    • lang/en.json (updated)');
        $this->line('    • lang/pt_BR.json (updated)');
        $this->newLine();
    }
}
