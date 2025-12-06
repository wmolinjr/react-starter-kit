<?php

namespace App\Console\Commands;

use App\Enums\PlanFeature;
use App\Enums\PlanLimit;
use Illuminate\Console\Command;

/**
 * Generate TypeScript types for Plan features and limits.
 *
 * Uses PlanFeature and PlanLimit enums as single source of truth
 * to generate type-safe TypeScript interfaces.
 *
 * Usage:
 * - sail artisan plans:generate-types
 */
class GeneratePlanTypes extends Command
{
    protected $signature = 'plans:generate-types';

    protected $description = 'Generate TypeScript types from PlanFeature and PlanLimit enums';

    public function handle(): int
    {
        $this->info('Generating TypeScript types from PlanFeature and PlanLimit enums...');

        $typescript = $this->generateTypeScript();
        $outputPath = resource_path('js/types/plan.d.ts');

        file_put_contents($outputPath, $typescript);

        $this->info("✅ Generated: {$outputPath}");
        $this->newLine();

        // Display summary
        $this->displaySummary();

        return self::SUCCESS;
    }

    protected function generateTypeScript(): string
    {
        $features = PlanFeature::frontendFeatures();
        $limits = PlanLimit::values();

        $output = <<<'TS'
/**
 * Plan Types - Auto-generated from PlanFeature and PlanLimit enums
 *
 * DO NOT EDIT MANUALLY!
 * Run: sail artisan plans:generate-types
 *
 * Source of truth: app/Plans/Enums/PlanFeature.php, PlanLimit.php
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

        // PlanUsage interface (same as limits)
        $output .= "export interface PlanUsage {\n";
        foreach ($limits as $limit) {
            $output .= "    {$limit}: number;\n";
        }
        $output .= "}\n\n";

        // Feature keys type
        $featureKeys = implode("' | '", $features);
        $output .= "export type PlanFeatureKey = '{$featureKeys}';\n\n";

        // Limit keys type
        $limitKeys = implode("' | '", $limits);
        $output .= "export type PlanLimitKey = '{$limitKeys}';\n";

        return $output;
    }

    protected function displaySummary(): void
    {
        $features = PlanFeature::frontendFeatures();
        $limits = PlanLimit::values();

        $this->info('📊 Generated Types Summary:');
        $this->table(
            ['Type', 'Count', 'Keys'],
            [
                ['PlanFeatures', count($features), implode(', ', $features)],
                ['PlanLimits', count($limits), implode(', ', $limits)],
            ]
        );
    }
}
