<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Split monolithic translation files into nested namespace-based files.
 *
 * This command divides large JSON files (e.g., en.json with 2000+ keys) into smaller
 * files organized by namespace hierarchy (e.g., admin/federation.json, tenant/team.json).
 *
 * Structure:
 *   admin.federation.title → lang/pt_BR/admin/federation.json
 *   admin.users.create → lang/pt_BR/admin/users.json
 *   tenant.team.invite → lang/pt_BR/tenant/team.json
 *   common.save → lang/pt_BR/common.json (single-level stays flat)
 *
 * Usage:
 *   sail artisan i18n:split              # Split all locales
 *   sail artisan i18n:split --dry-run    # Preview without changes
 *   sail artisan i18n:split --locale=en  # Split specific locale
 *   sail artisan i18n:split --depth=1    # Only first level (admin.json, tenant.json)
 *   sail artisan i18n:split --depth=2    # Two levels (admin/users.json) [default]
 *
 * @see docs/I18N.md
 */
class SplitTranslations extends Command
{
    protected $signature = 'i18n:split
                            {--dry-run : Show what would be done without executing}
                            {--locale=* : Specific locales to process}
                            {--keep-original : Keep original files after split}
                            {--depth=2 : Namespace depth (1=flat, 2=nested)}';

    protected $description = 'Split monolithic translation files into nested namespace-based files';

    /**
     * Track statistics for summary.
     *
     * @var array<string, array<string, mixed>>
     */
    protected array $stats = [];

    public function handle(): int
    {
        $this->displayHeader();

        $locales = $this->option('locale') ?: config('app.locales', ['en', 'pt_BR', 'es']);
        $dryRun = $this->option('dry-run');
        $depth = (int) $this->option('depth');

        if ($dryRun) {
            $this->warn('  Running in DRY-RUN mode. No files will be modified.');
            $this->newLine();
        }

        $this->info("  Namespace depth: {$depth}");
        $this->newLine();

        foreach ($locales as $locale) {
            $this->processLocale($locale, $dryRun, $depth);
        }

        $this->displaySummary($dryRun);

        if ($dryRun) {
            $this->newLine();
            $this->info('  Run without --dry-run to apply changes.');
        }

        return self::SUCCESS;
    }

    protected function displayHeader(): void
    {
        $this->info('');
        $this->info('  ╔══════════════════════════════════════════════════════════╗');
        $this->info('  ║  Translation File Splitter (Nested Structure)            ║');
        $this->info('  ╚══════════════════════════════════════════════════════════╝');
        $this->newLine();
    }

    protected function processLocale(string $locale, bool $dryRun, int $depth): void
    {
        // Try to find source - either monolithic file or existing split structure
        $monolithicFile = lang_path("{$locale}.json");
        $backupFile = lang_path("{$locale}.json.bak");
        $targetDir = lang_path($locale);

        // Determine source
        $translations = [];

        if (File::exists($monolithicFile)) {
            $content = File::get($monolithicFile);
            $translations = json_decode($content, true) ?? [];
            $this->info("  Processing locale: {$locale} (from {$locale}.json)");
        } elseif (File::exists($backupFile)) {
            $content = File::get($backupFile);
            $translations = json_decode($content, true) ?? [];
            $this->info("  Processing locale: {$locale} (from {$locale}.json.bak)");
        } elseif (File::isDirectory($targetDir)) {
            // Collect from existing split structure
            $translations = $this->collectFromDirectory($targetDir);
            $this->info("  Processing locale: {$locale} (from existing split structure)");
        } else {
            $this->warn("  Skipping {$locale}: no source found");

            return;
        }

        if (empty($translations)) {
            $this->error("  Error: No translations found for {$locale}");

            return;
        }

        // Clean target directory if exists
        if (! $dryRun && File::isDirectory($targetDir)) {
            File::deleteDirectory($targetDir);
        }

        if (! $dryRun) {
            File::makeDirectory($targetDir, 0755, true);
        }

        // Group by namespace hierarchy
        $grouped = $this->groupByNamespace($translations, $depth);

        $this->stats[$locale] = [
            'total_keys' => count($translations),
            'files_created' => 0,
            'folders_created' => [],
            'files' => [],
        ];

        // Process each file path
        foreach ($grouped as $relativePath => $keys) {
            $targetFile = "{$targetDir}/{$relativePath}";
            $count = count($keys);

            $this->stats[$locale]['files'][$relativePath] = $count;
            $this->stats[$locale]['files_created']++;

            // Track folders
            $folder = dirname($relativePath);
            if ($folder !== '.' && ! in_array($folder, $this->stats[$locale]['folders_created'])) {
                $this->stats[$locale]['folders_created'][] = $folder;
            }

            if ($dryRun) {
                $this->line("    [DRY-RUN] Would create: {$relativePath} ({$count} keys)");
            } else {
                // Create subdirectory if needed
                $dir = dirname($targetFile);
                if (! File::isDirectory($dir)) {
                    File::makeDirectory($dir, 0755, true);
                }

                ksort($keys);
                $json = json_encode($keys, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                File::put($targetFile, $json."\n");
                $this->line("    ✓ Created: {$relativePath} ({$count} keys)");
            }
        }

        // Handle original file
        if (! $dryRun && File::exists($monolithicFile) && ! $this->option('keep-original')) {
            if (! File::exists($backupFile)) {
                File::copy($monolithicFile, $backupFile);
            }
            File::delete($monolithicFile);
            $this->line("    📦 Backup: {$locale}.json → {$locale}.json.bak");
        }

        $this->newLine();
    }

    /**
     * Collect all translations from a directory structure.
     *
     * @return array<string, string>
     */
    protected function collectFromDirectory(string $dir): array
    {
        $translations = [];
        $files = $this->getJsonFilesRecursively($dir);

        foreach ($files as $file) {
            $content = json_decode(File::get($file), true) ?? [];
            $translations = array_merge($translations, $content);
        }

        return $translations;
    }

    /**
     * Get all JSON files recursively from a directory.
     *
     * @return array<string>
     */
    protected function getJsonFilesRecursively(string $dir): array
    {
        $files = [];

        foreach (File::allFiles($dir) as $file) {
            if ($file->getExtension() === 'json') {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }

    /**
     * Group translation keys by namespace hierarchy.
     *
     * @return array<string, array<string, string>>
     */
    protected function groupByNamespace(array $translations, int $depth): array
    {
        $grouped = [];

        foreach ($translations as $key => $value) {
            $parts = explode('.', $key);
            $path = $this->getFilePath($parts, $depth);
            $grouped[$path][$key] = $value;
        }

        // Sort by path
        ksort($grouped);

        return $grouped;
    }

    /**
     * Determine the file path for a translation key.
     *
     * Examples (depth=2):
     *   admin.federation.title → admin/federation.json
     *   admin.users.create → admin/users.json
     *   common.save → common.json
     *   validation.required → validation.json
     *
     * @param  array<string>  $parts  Key parts (split by .)
     */
    protected function getFilePath(array $parts, int $depth): string
    {
        $count = count($parts);

        if ($depth === 1 || $count <= 2) {
            // Single level: admin.json, tenant.json
            return $parts[0].'.json';
        }

        if ($depth >= 2 && $count >= 3) {
            // Two levels: admin/federation.json
            return $parts[0].'/'.$parts[1].'.json';
        }

        // Fallback to first level
        return $parts[0].'.json';
    }

    protected function displaySummary(bool $dryRun): void
    {
        $this->info('  ╔══════════════════════════════════════════════════════════╗');
        $this->info('  ║  Summary                                                 ║');
        $this->info('  ╚══════════════════════════════════════════════════════════╝');
        $this->newLine();

        foreach ($this->stats as $locale => $data) {
            $this->line("  {$locale}:");
            $this->line("    Total keys: {$data['total_keys']}");
            $this->line("    Files created: {$data['files_created']}");

            if (! empty($data['folders_created'])) {
                $this->line('    Folders: '.implode(', ', $data['folders_created']));
            }

            // Top 10 largest files
            arsort($data['files']);
            $top = array_slice($data['files'], 0, 10, true);

            $this->line('    Largest files:');
            foreach ($top as $path => $count) {
                $percentage = round(($count / $data['total_keys']) * 100, 1);
                $this->line("      - {$path}: {$count} keys ({$percentage}%)");
            }

            $this->newLine();
        }

        if (! $dryRun) {
            $this->info('  Next steps:');
            $this->line('    1. Run: sail artisan types:generate');
            $this->line('    2. Test: sail npm run build && sail npm run dev');
        }
    }
}
