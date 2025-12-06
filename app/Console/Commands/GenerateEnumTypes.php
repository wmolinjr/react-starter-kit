<?php

namespace App\Console\Commands;

use App\Enums\AddonStatus;
use App\Enums\AddonType;
use App\Enums\BillingPeriod;
use Illuminate\Console\Command;

/**
 * Generate TypeScript types for Addon enums.
 *
 * Uses AddonType, AddonStatus, and BillingPeriod enums as single source of truth
 * to generate type-safe TypeScript interfaces.
 *
 * Usage:
 * - sail artisan enums:generate-types
 */
class GenerateEnumTypes extends Command
{
    protected $signature = 'enums:generate-types';

    protected $description = 'Generate TypeScript types from AddonType, AddonStatus, and BillingPeriod enums';

    public function handle(): int
    {
        $this->info('Generating TypeScript types from Addon enums...');

        $typescript = $this->generateTypeScript();
        $outputPath = resource_path('js/types/enums.d.ts');

        file_put_contents($outputPath, $typescript);

        $this->info("✅ Generated: {$outputPath}");
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
            ]
        );
    }
}
