# Plano de Reestruturação do Sistema de i18n

## Objetivo

Reestruturar o sistema de internacionalização para:
1. Dividir traduções por namespace (arquivos menores e organizados)
2. Gerar tipos TypeScript para autocomplete de chaves
3. Detectar chaves órfãs (não utilizadas no código)
4. Integrar com o comando `types:generate` existente
5. Manter compatibilidade total com SSR

## Estado Atual

```
lang/
├── en.json         (1996 linhas, 120KB)
├── es.json         (1718 linhas, 108KB)
├── pt_BR.json      (1996 linhas, 128KB)
└── pt_BR/          (arquivos PHP do Laravel)
    ├── auth.php
    ├── passwords.php
    ├── pagination.php
    └── validation.php
```

**Distribuição de namespaces:**
- `admin.*` - 482 chaves (24%)
- `tenant.*` - 415 chaves (21%)
- `billing.*` - 260 chaves (13%)
- `customer.*` - 143 chaves (7%)
- `enums.*` - 93 chaves (5%) ← Geradas pelo `types:generate`
- Outros 19 namespaces

## Nova Estrutura

```
lang/
├── en/
│   ├── admin.json          (482 chaves)
│   ├── tenant.json         (415 chaves)
│   ├── billing.json        (260 chaves)
│   ├── customer.json       (143 chaves)
│   ├── common.json         (73 chaves)
│   ├── auth.json           (52 chaves)
│   ├── settings.json       (46 chaves)
│   ├── checkout.json       (51 chaves)
│   ├── flash.json          (87 chaves)
│   ├── sidebar.json        (36 chaves)
│   ├── roles.json          (42 chaves)
│   ├── permissions.json    (84 chaves)
│   ├── enums.json          (93 chaves) ← Gerado automaticamente
│   └── misc.json           (restante)
├── pt_BR/
│   └── (mesma estrutura)
├── es/
│   └── (mesma estrutura)
└── pt_BR.php/              (arquivos PHP do Laravel - mantidos)
    ├── auth.php
    ├── passwords.php
    ├── pagination.php
    └── validation.php
```

## Implementação

### Fase 1: Script de Migração

Criar comando Artisan para dividir os arquivos existentes.

**Arquivo:** `app/Console/Commands/SplitTranslations.php`

```php
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class SplitTranslations extends Command
{
    protected $signature = 'i18n:split
                            {--dry-run : Mostrar o que seria feito sem executar}
                            {--locale=* : Locales específicos para processar}';

    protected $description = 'Divide arquivos de tradução monolíticos por namespace';

    /**
     * Namespaces e seus prefixos.
     */
    protected array $namespaces = [
        'admin' => ['admin.'],
        'tenant' => ['tenant.'],
        'billing' => ['billing.'],
        'customer' => ['customer.'],
        'common' => ['common.'],
        'auth' => ['auth.'],
        'settings' => ['settings.'],
        'checkout' => ['checkout.'],
        'flash' => ['flash.'],
        'sidebar' => ['sidebar.'],
        'roles' => ['roles.'],
        'permissions' => ['permissions.'],
        'enums' => ['enums.'],
        'payment_settings' => ['payment_settings.'],
        'impersonation' => ['impersonation.'],
        'breadcrumbs' => ['breadcrumbs.'],
        'badges' => ['badges.'],
        'navigation' => ['navigation.'],
        'central' => ['central.'],
        'placeholders' => ['placeholders.'],
        'validation' => ['validation.'],
        'icons' => ['icons.'],
        'components' => ['components.'],
        'user_menu' => ['user_menu.'],
        'colors' => ['colors.'],
    ];

    public function handle(): int
    {
        $locales = $this->option('locale') ?: config('app.locales', ['en', 'pt_BR', 'es']);
        $dryRun = $this->option('dry-run');

        foreach ($locales as $locale) {
            $this->processLocale($locale, $dryRun);
        }

        if ($dryRun) {
            $this->warn('Dry run completo. Nenhum arquivo foi modificado.');
            $this->info('Execute sem --dry-run para aplicar as mudanças.');
        }

        return self::SUCCESS;
    }

    protected function processLocale(string $locale, bool $dryRun): void
    {
        $sourceFile = lang_path("{$locale}.json");

        if (!File::exists($sourceFile)) {
            $this->warn("Arquivo não encontrado: {$sourceFile}");
            return;
        }

        $this->info("Processando locale: {$locale}");

        $translations = json_decode(File::get($sourceFile), true);
        $targetDir = lang_path($locale);

        if (!$dryRun && !File::isDirectory($targetDir)) {
            File::makeDirectory($targetDir, 0755, true);
        }

        $grouped = $this->groupByNamespace($translations);

        foreach ($grouped as $namespace => $keys) {
            $targetFile = "{$targetDir}/{$namespace}.json";
            $count = count($keys);

            if ($dryRun) {
                $this->line("  [DRY-RUN] Criaria: {$namespace}.json ({$count} chaves)");
            } else {
                ksort($keys);
                $json = json_encode($keys, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                File::put($targetFile, $json . "\n");
                $this->line("  ✓ Criado: {$namespace}.json ({$count} chaves)");
            }
        }

        // Backup do arquivo original
        if (!$dryRun) {
            $backupFile = lang_path("{$locale}.json.bak");
            File::copy($sourceFile, $backupFile);
            $this->info("  📦 Backup criado: {$locale}.json.bak");
        }
    }

    protected function groupByNamespace(array $translations): array
    {
        $grouped = [];
        $misc = [];

        foreach ($translations as $key => $value) {
            $placed = false;

            foreach ($this->namespaces as $namespace => $prefixes) {
                foreach ($prefixes as $prefix) {
                    if (str_starts_with($key, $prefix)) {
                        $grouped[$namespace][$key] = $value;
                        $placed = true;
                        break 2;
                    }
                }
            }

            if (!$placed) {
                $misc[$key] = $value;
            }
        }

        if (!empty($misc)) {
            $grouped['misc'] = $misc;
        }

        return $grouped;
    }
}
```

### Fase 2: Atualizar Configuração do Vite

**Arquivo:** `vite.config.ts`

```typescript
import { wayfinder } from '@laravel/vite-plugin-wayfinder';
import tailwindcss from '@tailwindcss/vite';
import react from '@vitejs/plugin-react';
import laravel from 'laravel-vite-plugin';
import i18n from 'laravel-react-i18n/vite';
import { defineConfig } from 'vite';
import path from 'path';

export default defineConfig({
    server: {
        host: '0.0.0.0',
        port: 5173,
        strictPort: true,
        hmr: {
            host: 'localhost',
        },
        cors: {
            origin: true,
        },
    },
    resolve: {
        alias: {
            react: path.resolve('./node_modules/react'),
            'react-dom': path.resolve('./node_modules/react-dom'),
        },
    },
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.tsx'],
            ssr: 'resources/js/ssr.tsx',
            refresh: true,
        }),
        react({
            babel: {
                plugins: ['babel-plugin-react-compiler'],
            },
        }),
        tailwindcss(),
        wayfinder({
            formVariants: true,
        }),
        i18n({
            langDirname: 'lang',
            // Habilita geração de tipos TypeScript
            // NOTA: Usaremos nosso próprio comando para ter mais controle
        }),
    ],
    esbuild: {
        jsx: 'automatic',
    },
});
```

### Fase 3: Atualizar Provider do React

**Arquivo:** `resources/js/app.tsx` (mudança no glob)

```typescript
// ANTES:
files={import.meta.glob('/lang/*.json', { eager: true })}

// DEPOIS:
files={import.meta.glob('/lang/**/*.json', { eager: true })}
```

**Arquivo:** `resources/js/ssr.tsx` (mesma mudança)

```typescript
// ANTES:
files={import.meta.glob('/lang/*.json', { eager: true })}

// DEPOIS:
files={import.meta.glob('/lang/**/*.json', { eager: true })}
```

### Fase 4: Estender GenerateTypes para i18n

Adicionar geração de tipos de tradução ao comando existente.

**Modificações em:** `app/Console/Commands/GenerateTypes.php`

```php
// Adicionar ao método handle() após generateTranslations():
$this->generateTranslationTypes();
$this->checkOrphanedKeys();

// Novo método para gerar tipos TypeScript
protected function generateTranslationTypes(): void
{
    $locales = config('app.locales', ['en', 'pt_BR', 'es']);
    $baseLocale = config('app.fallback_locale', 'en');

    // Coletar todas as chaves do locale base
    $allKeys = $this->collectTranslationKeys($baseLocale);

    // Gerar arquivo de tipos
    $output = $this->getTranslationTypesHeader();
    $output .= $this->generateTranslationKeyUnion($allKeys);
    $output .= $this->generateNamespaceTypes($allKeys);

    $path = resource_path('js/types/translations.d.ts');
    File::put($path, $output);

    $this->info('  ✓ Generated: resources/js/types/translations.d.ts (' . count($allKeys) . ' keys)');
}

protected function collectTranslationKeys(string $locale): array
{
    $keys = [];
    $dir = lang_path($locale);

    if (!File::isDirectory($dir)) {
        // Fallback para arquivo único (compatibilidade)
        $file = lang_path("{$locale}.json");
        if (File::exists($file)) {
            $translations = json_decode(File::get($file), true) ?? [];
            return array_keys($translations);
        }
        return [];
    }

    $files = File::glob("{$dir}/*.json");

    foreach ($files as $file) {
        $translations = json_decode(File::get($file), true) ?? [];
        $keys = array_merge($keys, array_keys($translations));
    }

    sort($keys);
    return array_unique($keys);
}

protected function getTranslationTypesHeader(): string
{
    return <<<'TS'
/**
 * Translation Key Types - Auto-generated from translation files
 *
 * DO NOT EDIT MANUALLY!
 * Run: sail artisan types:generate
 *
 * Source of truth: lang/{locale}/*.json files
 *
 * Usage:
 *   const { t } = useLaravelReactI18n();
 *   t('admin.users.title'); // ← TypeScript validates this key
 */

import 'laravel-react-i18n';

declare module 'laravel-react-i18n' {
    interface TranslationKeys {

TS;
}

protected function generateTranslationKeyUnion(array $keys): string
{
    $output = '';

    // Agrupar por namespace
    $namespaces = [];
    foreach ($keys as $key) {
        $parts = explode('.', $key);
        $namespace = $parts[0];
        $namespaces[$namespace][] = $key;
    }

    // Gerar union type para cada namespace
    foreach ($namespaces as $namespace => $nsKeys) {
        $capitalizedNs = ucfirst($namespace);
        $keyList = array_map(fn($k) => "            | '{$k}'", $nsKeys);
        $output .= "        /** {$capitalizedNs} translation keys */\n";
        $output .= "        {$namespace}:\n" . implode("\n", $keyList) . ";\n\n";
    }

    $output .= "    }\n";
    $output .= "}\n\n";

    return $output;
}

protected function generateNamespaceTypes(array $keys): string
{
    // Gerar tipo union geral
    $allKeysUnion = array_map(fn($k) => "    | '{$k}'", $keys);

    $output = <<<TS
/**
 * All available translation keys.
 * Use with: t(key: TranslationKey)
 */
export type TranslationKey =
TS;

    $output .= "\n" . implode("\n", $allKeysUnion) . ";\n\n";

    // Gerar namespaces como tipos separados
    $namespaces = [];
    foreach ($keys as $key) {
        $parts = explode('.', $key);
        $namespace = $parts[0];
        if (!isset($namespaces[$namespace])) {
            $namespaces[$namespace] = [];
        }
        $namespaces[$namespace][] = $key;
    }

    foreach ($namespaces as $namespace => $nsKeys) {
        $capitalizedNs = ucfirst($namespace);
        $nsKeysUnion = array_map(fn($k) => "    | '{$k}'", $nsKeys);

        $output .= "/**\n * {$capitalizedNs} namespace keys ({count($nsKeys)} keys)\n */\n";
        $output .= "export type {$capitalizedNs}TranslationKey =\n";
        $output .= implode("\n", $nsKeysUnion) . ";\n\n";
    }

    return $output;
}
```

### Fase 5: Comando de Detecção de Órfãos

**Arquivo:** `app/Console/Commands/CheckOrphanedTranslations.php`

```php
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Finder\Finder;

class CheckOrphanedTranslations extends Command
{
    protected $signature = 'i18n:check-orphans
                            {--fix : Remove chaves órfãs automaticamente}
                            {--namespace=* : Verificar apenas namespaces específicos}
                            {--ignore=* : Ignorar chaves específicas}';

    protected $description = 'Detecta chaves de tradução não utilizadas no código';

    /**
     * Padrões para encontrar uso de traduções no código.
     */
    protected array $patterns = [
        // t('key'), t("key")
        '/\bt\s*\(\s*[\'"]([^\'"]+)[\'"]\s*(?:,|\))/',
        // tChoice('key', ...)
        '/\btChoice\s*\(\s*[\'"]([^\'"]+)[\'"]\s*,/',
        // trans('key'), __('key')
        '/\b(?:trans|__)\s*\(\s*[\'"]([^\'"]+)[\'"]\s*(?:,|\))/',
        // Lang::get('key')
        '/Lang::get\s*\(\s*[\'"]([^\'"]+)[\'"]\s*(?:,|\))/',
    ];

    /**
     * Chaves que são geradas dinamicamente (não aparecem literalmente no código).
     */
    protected array $dynamicKeyPatterns = [
        'enums.*',           // Geradas pelo types:generate
        'permissions.*',     // Geradas pelo types:generate
        'validation.*',      // Usadas pelo Laravel internamente
        'flash.*',           // Usadas via backend
    ];

    public function handle(): int
    {
        $this->info('');
        $this->info('  ╔══════════════════════════════════════════════════════════╗');
        $this->info('  ║  Translation Orphan Checker                              ║');
        $this->info('  ╚══════════════════════════════════════════════════════════╝');
        $this->newLine();

        // 1. Coletar todas as chaves definidas
        $definedKeys = $this->collectDefinedKeys();
        $this->info("  📚 Chaves definidas: " . count($definedKeys));

        // 2. Coletar todas as chaves usadas no código
        $usedKeys = $this->collectUsedKeys();
        $this->info("  🔍 Chaves encontradas no código: " . count($usedKeys));

        // 3. Encontrar órfãs
        $orphans = $this->findOrphans($definedKeys, $usedKeys);

        // 4. Filtrar chaves dinâmicas
        $orphans = $this->filterDynamicKeys($orphans);

        $this->newLine();

        if (empty($orphans)) {
            $this->info('  ✅ Nenhuma chave órfã encontrada!');
            return self::SUCCESS;
        }

        $this->warn("  ⚠️  Encontradas " . count($orphans) . " chaves potencialmente órfãs:");
        $this->newLine();

        // Agrupar por namespace
        $grouped = $this->groupByNamespace($orphans);

        foreach ($grouped as $namespace => $keys) {
            $this->line("  [{$namespace}] " . count($keys) . " chaves:");
            foreach (array_slice($keys, 0, 5) as $key) {
                $this->line("    - {$key}");
            }
            if (count($keys) > 5) {
                $this->line("    ... e mais " . (count($keys) - 5) . " chaves");
            }
            $this->newLine();
        }

        // Opção de fix
        if ($this->option('fix')) {
            $this->removeOrphanedKeys($orphans);
        } else {
            $this->info('  💡 Execute com --fix para remover as chaves órfãs');
        }

        return self::SUCCESS;
    }

    protected function collectDefinedKeys(): array
    {
        $keys = [];
        $locales = config('app.locales', ['en', 'pt_BR', 'es']);
        $baseLocale = config('app.fallback_locale', 'en');

        $dir = lang_path($baseLocale);

        if (File::isDirectory($dir)) {
            $files = File::glob("{$dir}/*.json");
            foreach ($files as $file) {
                $translations = json_decode(File::get($file), true) ?? [];
                $keys = array_merge($keys, array_keys($translations));
            }
        } else {
            // Fallback para arquivo único
            $file = lang_path("{$baseLocale}.json");
            if (File::exists($file)) {
                $translations = json_decode(File::get($file), true) ?? [];
                $keys = array_keys($translations);
            }
        }

        return array_unique($keys);
    }

    protected function collectUsedKeys(): array
    {
        $usedKeys = [];

        $finder = new Finder();
        $finder->files()
            ->in([
                resource_path('js'),
                app_path(),
            ])
            ->name(['*.tsx', '*.ts', '*.php', '*.blade.php'])
            ->exclude(['vendor', 'node_modules']);

        foreach ($finder as $file) {
            $content = $file->getContents();

            foreach ($this->patterns as $pattern) {
                if (preg_match_all($pattern, $content, $matches)) {
                    $usedKeys = array_merge($usedKeys, $matches[1]);
                }
            }
        }

        return array_unique($usedKeys);
    }

    protected function findOrphans(array $defined, array $used): array
    {
        return array_diff($defined, $used);
    }

    protected function filterDynamicKeys(array $orphans): array
    {
        return array_filter($orphans, function ($key) {
            foreach ($this->dynamicKeyPatterns as $pattern) {
                $regex = '/^' . str_replace(['*', '.'], ['.*', '\.'], $pattern) . '$/';
                if (preg_match($regex, $key)) {
                    return false;
                }
            }
            return true;
        });
    }

    protected function groupByNamespace(array $keys): array
    {
        $grouped = [];
        foreach ($keys as $key) {
            $parts = explode('.', $key);
            $namespace = $parts[0];
            $grouped[$namespace][] = $key;
        }
        ksort($grouped);
        return $grouped;
    }

    protected function removeOrphanedKeys(array $orphans): void
    {
        if (!$this->confirm('Remover ' . count($orphans) . ' chaves órfãs de todos os locales?')) {
            return;
        }

        $locales = config('app.locales', ['en', 'pt_BR', 'es']);

        foreach ($locales as $locale) {
            $dir = lang_path($locale);

            if (!File::isDirectory($dir)) {
                continue;
            }

            $files = File::glob("{$dir}/*.json");

            foreach ($files as $file) {
                $translations = json_decode(File::get($file), true) ?? [];
                $originalCount = count($translations);

                foreach ($orphans as $orphan) {
                    unset($translations[$orphan]);
                }

                $newCount = count($translations);
                $removed = $originalCount - $newCount;

                if ($removed > 0) {
                    ksort($translations);
                    $json = json_encode($translations, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                    File::put($file, $json . "\n");
                    $this->line("  ✓ {$locale}/" . basename($file) . ": removidas {$removed} chaves");
                }
            }
        }

        $this->newLine();
        $this->info('  ✅ Chaves órfãs removidas com sucesso!');
        $this->info('  💡 Execute "sail artisan types:generate" para atualizar os tipos TypeScript');
    }
}
```

### Fase 6: Atualizar GenerateTypes para Trabalhar com Nova Estrutura

**Modificar:** `generateTranslations()` em `GenerateTypes.php`

```php
protected function generateTranslations(): void
{
    $locales = config('app.locales', ['en', 'pt_BR', 'es']);

    foreach ($locales as $locale) {
        $this->updateTranslationFiles($locale);
    }
}

protected function updateTranslationFiles(string $locale): void
{
    $dir = lang_path($locale);

    // Se diretório existe, usar nova estrutura
    if (File::isDirectory($dir)) {
        $this->updateNamespacedTranslations($locale, $dir);
        return;
    }

    // Fallback para arquivo único (compatibilidade durante migração)
    $this->updateTranslationFile($locale);
}

protected function updateNamespacedTranslations(string $locale, string $dir): void
{
    // Agrupar traduções de enums por arquivo de destino
    $enumsByFile = [];

    foreach ($this->enums as $name => $config) {
        $cases = $config['class']::cases();
        $getter = $config['translations'];
        $prefix = $config['translation_key'];

        // Determinar arquivo de destino baseado no prefixo
        $namespace = explode('.', $prefix)[0];
        $targetFile = "{$dir}/{$namespace}.json";

        foreach ($cases as $case) {
            $key = "{$prefix}.{$case->value}";
            $enumsByFile[$targetFile][$key] = $getter($case, $locale, $key);
        }
    }

    // Atualizar cada arquivo
    $totalEnumKeys = 0;

    foreach ($enumsByFile as $targetFile => $enumTranslations) {
        $existingTranslations = [];

        if (File::exists($targetFile)) {
            $existingTranslations = json_decode(File::get($targetFile), true) ?? [];
        }

        // Merge: existentes + novas de enum (enum sobrescreve)
        $translations = array_merge($existingTranslations, $enumTranslations);
        ksort($translations);

        $json = json_encode($translations, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        File::put($targetFile, $json . "\n");

        $totalEnumKeys += count($enumTranslations);
    }

    $this->info("  ✓ Updated translations: lang/{$locale}/ ({$totalEnumKeys} enum-generated keys)");
}
```

### Fase 7: Atualizar index.d.ts

**Arquivo:** `resources/js/types/index.d.ts`

Adicionar export:
```typescript
// Re-export all auto-generated types
export * from './permissions';
export * from './plan';
export * from './enums';
export * from './resources';
export * from './pagination';
export * from './common';
export * from './billing';
export * from './translations';  // ← Adicionar
```

---

## Comandos Disponíveis Após Implementação

```bash
# Dividir arquivos de tradução (executar uma vez)
sail artisan i18n:split
sail artisan i18n:split --dry-run  # Preview

# Regenerar tipos (inclui traduções)
sail artisan types:generate

# Verificar chaves órfãs
sail artisan i18n:check-orphans
sail artisan i18n:check-orphans --fix  # Remove órfãs

# Workflow completo
sail artisan i18n:split && sail artisan types:generate && sail artisan i18n:check-orphans
```

---

## Checklist de Implementação

### Fase 1: Preparação
- [ ] Criar backup dos arquivos de tradução atuais
- [ ] Criar comando `i18n:split`
- [ ] Testar com `--dry-run`
- [ ] Executar split real

### Fase 2: Configuração
- [ ] Atualizar glob em `app.tsx`
- [ ] Atualizar glob em `ssr.tsx`
- [ ] Testar carregamento de traduções

### Fase 3: Geração de Tipos
- [ ] Adicionar método `generateTranslationTypes()` ao `GenerateTypes`
- [ ] Atualizar método `generateTranslations()` para nova estrutura
- [ ] Criar arquivo `translations.d.ts`
- [ ] Exportar em `index.d.ts`

### Fase 4: Detecção de Órfãos
- [ ] Criar comando `i18n:check-orphans`
- [ ] Testar detecção
- [ ] Documentar chaves dinâmicas ignoradas

### Fase 5: Limpeza
- [ ] Remover arquivos `.json` monolíticos antigos (após validação)
- [ ] Atualizar `.gitignore` se necessário
- [ ] Atualizar documentação `docs/I18N.md`

### Fase 6: Validação Final
- [ ] Testar SSR (`sail npm run build:ssr`)
- [ ] Testar dev server (`sail npm run dev`)
- [ ] Verificar autocomplete no IDE
- [ ] Executar testes (`sail artisan test`)

---

## Arquivos Gerados

| Arquivo | Descrição | Gerador |
|---------|-----------|---------|
| `lang/{locale}/*.json` | Traduções por namespace | `i18n:split` |
| `resources/js/types/translations.d.ts` | Tipos TypeScript de chaves | `types:generate` |
| `resources/js/types/enums.d.ts` | Tipos de enums | `types:generate` |
| `resources/js/lib/enum-metadata.ts` | Metadata de enums | `types:generate` |

---

## Compatibilidade com SSR

O `laravel-react-i18n` suporta glob recursivo para SSR:

```typescript
// Funciona com eager: true para SSR
files={import.meta.glob('/lang/**/*.json', { eager: true })}
```

O glob `**/*.json` carrega todos os arquivos JSON em subpastas, mantendo a mesma API de tradução.

---

## Detecção de Órfãos: Chaves Ignoradas

As seguintes chaves são ignoradas automaticamente pois são usadas dinamicamente:

| Padrão | Motivo |
|--------|--------|
| `enums.*` | Geradas pelo `types:generate` |
| `permissions.*` | Geradas pelo `types:generate` |
| `validation.*` | Usadas internamente pelo Laravel |
| `flash.*` | Usadas via backend (controllers) |

Para adicionar exceções customizadas, use:
```bash
sail artisan i18n:check-orphans --ignore="minha_chave.*"
```

---

## Rollback (se necessário)

```bash
# Restaurar arquivos originais do backup
mv lang/en.json.bak lang/en.json
mv lang/pt_BR.json.bak lang/pt_BR.json
mv lang/es.json.bak lang/es.json

# Remover diretórios criados
rm -rf lang/en/ lang/pt_BR/ lang/es/

# Reverter glob nos arquivos
# app.tsx e ssr.tsx: mudar '/lang/**/*.json' para '/lang/*.json'
```
