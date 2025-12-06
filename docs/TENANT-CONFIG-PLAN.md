# TenantConfig Implementation Plan

## Resumo Executivo

Implementar o TenantConfigBootstrapper do Stancl/Tenancy v4 para sobrescrever automaticamente configurações do Laravel com valores específicos por tenant.

### Estado Atual

O projeto já possui:
- Modelo `Tenant` com campo `settings` (JSON)
- Métodos `getSetting()` e `updateSetting()`
- `TenantSettingsService` para branding, domains, features, notifications, language
- Middleware `SetLocale` que lê `language.default` do tenant
- UI completa para configurações de idioma (`/tenant-settings/language`)

### Gap Identificado

- Settings são armazenados mas **NÃO aplicados automaticamente** ao config do Laravel
- Requer chamadas manuais `$tenant->getSetting()` em vez de `config()`
- Sem integração com TenantConfigBootstrapper

### Solução

- Habilitar TenantConfigBootstrapper para override automático de config keys
- Criar enum tipado para chaves de config do tenant
- Adicionar MailTenancyBootstrapper para SMTP customizado (enterprise)

---

## Arquitetura

### Estado Atual

```
Tenant Model                     Application Code
+------------------+             +------------------+
| settings (JSON)  | ----X-----> | config('app.locale')
| - language.default             | config('mail.from.name')
| - branding.*                   | Não atualiza automaticamente!
+------------------+             +------------------+
                    Requer getSetting() manual
```

### Estado Alvo

```
Tenant Model                     TenantConfigBootstrapper       Application Code
+------------------+             +----------------------+       +------------------+
| settings (JSON)  | ---------> | $storageToConfigMap  | ----> | config('app.locale')
| - config.locale  |             | mapeia tenant attrs  |       | Atualiza automaticamente!
| - config.timezone|             | para config keys     |       |
| - config.mail_*  |             +----------------------+       +------------------+
+------------------+
```

---

## Estrutura de Dados

### Usar Campo `settings` Existente (Recomendado)

```php
// Estrutura atual em settings:
[
    'language' => ['default' => 'pt_BR'],
    'branding' => ['logo_url' => '...', 'primary_color' => '...'],
    'features' => ['api_enabled' => true],
    'notifications' => ['email_digest' => 'weekly'],
]

// Estrutura estendida:
[
    // Existentes
    'language' => ['default' => 'pt_BR'],
    'branding' => [...],
    'features' => [...],
    'notifications' => [...],

    // NOVO: Config overrides (chaves flat para TenantConfigBootstrapper)
    'config' => [
        'locale' => 'pt_BR',                      // -> app.locale
        'timezone' => 'America/Sao_Paulo',        // -> app.timezone
        'mail_from_address' => 'contato@empresa.com', // -> mail.from.address
        'mail_from_name' => 'Minha Empresa',      // -> mail.from.name
        'currency' => 'brl',                      // -> cashier.currency
        'currency_locale' => 'pt_BR',             // -> cashier.currency_locale
    ],
]
```

---

## Configurações por Prioridade

### Alta Prioridade (Fase 1)

| Config Key | Laravel Config | Descrição |
|------------|----------------|-----------|
| `locale` | `app.locale` | Idioma padrão do tenant |
| `timezone` | `app.timezone` | Fuso horário |
| `mail_from_address` | `mail.from.address` | Email remetente |
| `mail_from_name` | `mail.from.name` | Nome remetente |

### Média Prioridade (Fase 2)

| Config Key | Laravel Config | Descrição |
|------------|----------------|-----------|
| `currency` | `cashier.currency` | Moeda (BRL, USD, EUR) |
| `currency_locale` | `cashier.currency_locale` | Locale para formatação |

### Baixa Prioridade (Enterprise)

| Config Key | Laravel Config | Descrição |
|------------|----------------|-----------|
| `smtp_host` | `mail.mailers.smtp.host` | SMTP customizado |
| `smtp_port` | `mail.mailers.smtp.port` | Porta SMTP |
| `smtp_username` | `mail.mailers.smtp.username` | Usuário SMTP |
| `smtp_password` | `mail.mailers.smtp.password` | Senha SMTP (encriptada) |

---

## Fases de Implementação

### Fase 1: Core TenantConfigBootstrapper

#### 1.1 Criar Enum TenantConfigKey

```php
// app/Enums/TenantConfigKey.php
<?php

namespace App\Enums;

/**
 * Tenant-specific configuration keys.
 * Maps tenant settings to Laravel config keys.
 */
enum TenantConfigKey: string
{
    // Localization
    case LOCALE = 'locale';
    case TIMEZONE = 'timezone';

    // Email
    case MAIL_FROM_ADDRESS = 'mail_from_address';
    case MAIL_FROM_NAME = 'mail_from_name';

    // Payments
    case CURRENCY = 'currency';
    case CURRENCY_LOCALE = 'currency_locale';

    /**
     * Get the Laravel config key(s) this maps to.
     */
    public function configKeys(): array
    {
        return match ($this) {
            self::LOCALE => ['app.locale'],
            self::TIMEZONE => ['app.timezone'],
            self::MAIL_FROM_ADDRESS => ['mail.from.address'],
            self::MAIL_FROM_NAME => ['mail.from.name'],
            self::CURRENCY => ['cashier.currency'],
            self::CURRENCY_LOCALE => ['cashier.currency_locale'],
        };
    }

    /**
     * Get the tenant settings key path.
     */
    public function settingsPath(): string
    {
        return 'config.' . $this->value;
    }

    /**
     * Get default value.
     */
    public function defaultValue(): mixed
    {
        return match ($this) {
            self::LOCALE => 'en',
            self::TIMEZONE => 'UTC',
            self::MAIL_FROM_ADDRESS => null,
            self::MAIL_FROM_NAME => null,
            self::CURRENCY => 'usd',
            self::CURRENCY_LOCALE => 'en',
        };
    }

    /**
     * Get validation rules.
     */
    public function validationRules(): array
    {
        return match ($this) {
            self::LOCALE => ['string', 'in:' . implode(',', config('app.locales'))],
            self::TIMEZONE => ['string', 'timezone'],
            self::MAIL_FROM_ADDRESS => ['nullable', 'email'],
            self::MAIL_FROM_NAME => ['nullable', 'string', 'max:100'],
            self::CURRENCY => ['string', 'size:3', 'lowercase'],
            self::CURRENCY_LOCALE => ['string', 'max:10'],
        };
    }

    /**
     * Generate storageToConfigMap for TenantConfigBootstrapper.
     */
    public static function toStorageConfigMap(): array
    {
        $map = [];
        foreach (self::cases() as $case) {
            $configKeys = $case->configKeys();
            $map[$case->settingsPath()] = count($configKeys) === 1
                ? $configKeys[0]
                : $configKeys;
        }
        return $map;
    }
}
```

#### 1.2 Habilitar TenantConfigBootstrapper

```php
// config/tenancy.php - Adicionar ao array bootstrappers
'bootstrappers' => array_filter([
    // ... bootstrappers existentes ...

    // TenantConfigBootstrapper: Override Laravel config com valores do tenant
    Bootstrappers\TenantConfigBootstrapper::class,
]),
```

#### 1.3 Configurar Storage-to-Config Map

```php
// app/Providers/TenancyServiceProvider.php - Adicionar ao boot()
use App\Enums\TenantConfigKey;
use Stancl\Tenancy\Bootstrappers\TenantConfigBootstrapper;

public function boot()
{
    // ... código existente ...

    // Configurar TenantConfigBootstrapper para mapear settings do tenant para config keys
    TenantConfigBootstrapper::$storageToConfigMap = TenantConfigKey::toStorageConfigMap();

    // Resultado:
    // [
    //     'config.locale' => 'app.locale',
    //     'config.timezone' => 'app.timezone',
    //     'config.mail_from_address' => 'mail.from.address',
    //     'config.mail_from_name' => 'mail.from.name',
    //     'config.currency' => 'cashier.currency',
    //     'config.currency_locale' => 'cashier.currency_locale',
    // ]
}
```

#### 1.4 Adicionar Métodos ao Modelo Tenant

```php
// app/Models/Central/Tenant.php - Adicionar métodos

use App\Enums\TenantConfigKey;

/**
 * Get a config setting with fallback to Laravel default.
 */
public function getConfig(TenantConfigKey $key): mixed
{
    $value = $this->getSetting($key->settingsPath());

    if ($value === null) {
        $configKeys = $key->configKeys();
        return config($configKeys[0], $key->defaultValue());
    }

    return $value;
}

/**
 * Update a config setting.
 */
public function updateConfig(TenantConfigKey $key, mixed $value): bool
{
    return $this->updateSetting($key->settingsPath(), $value);
}

/**
 * Get all config settings as array.
 */
public function getAllConfig(): array
{
    $config = [];
    foreach (TenantConfigKey::cases() as $key) {
        $config[$key->value] = $this->getConfig($key);
    }
    return $config;
}
```

---

### Fase 2: Atualizar TenantSettingsService

```php
// app/Services/Tenant/TenantSettingsService.php - Adicionar métodos

use App\Enums\TenantConfigKey;

/**
 * Get all configuration settings.
 */
public function getConfigSettings(Tenant $tenant): array
{
    return [
        'config' => $tenant->getAllConfig(),
        'availableLocales' => config('app.locales'),
        'localeLabels' => config('app.locale_labels'),
        'availableTimezones' => timezone_identifiers_list(),
        'availableCurrencies' => $this->getAvailableCurrencies(),
    ];
}

/**
 * Update configuration settings.
 */
public function updateConfig(Tenant $tenant, array $data): void
{
    foreach ($data as $key => $value) {
        $configKey = TenantConfigKey::tryFrom($key);

        if (!$configKey) {
            continue;
        }

        $this->validateConfigValue($configKey, $value);
        $tenant->updateConfig($configKey, $value);
    }

    // Backward compatibility: update language.default
    if (isset($data['locale'])) {
        $tenant->updateSetting('language.default', $data['locale']);
    }
}

/**
 * Get available currencies.
 */
protected function getAvailableCurrencies(): array
{
    return [
        'usd' => 'US Dollar (USD)',
        'brl' => 'Brazilian Real (BRL)',
        'eur' => 'Euro (EUR)',
        'gbp' => 'British Pound (GBP)',
    ];
}
```

---

### Fase 3: Controller e Rotas

```php
// app/Http/Controllers/Tenant/Admin/TenantSettingsController.php

/**
 * Display configuration settings page.
 */
public function config(): Response
{
    return Inertia::render('tenant/admin/settings/config',
        $this->settingsService->getConfigSettings(tenant())
    );
}

/**
 * Update configuration settings.
 */
public function updateConfig(Request $request): RedirectResponse
{
    $request->validate([
        'locale' => ['sometimes', 'string', 'in:' . implode(',', config('app.locales'))],
        'timezone' => ['sometimes', 'string', 'timezone'],
        'mail_from_address' => ['nullable', 'email'],
        'mail_from_name' => ['nullable', 'string', 'max:100'],
        'currency' => ['sometimes', 'string', 'size:3'],
        'currency_locale' => ['sometimes', 'string', 'max:10'],
    ]);

    $this->settingsService->updateConfig(tenant(), $request->only([
        'locale', 'timezone', 'mail_from_address', 'mail_from_name',
        'currency', 'currency_locale',
    ]));

    return back()->with('success', __('flash.settings.config_updated'));
}
```

```php
// routes/tenant.php - Adicionar ao grupo settings
Route::get('/config', [TenantSettingsController::class, 'config'])->name('config');
Route::post('/config', [TenantSettingsController::class, 'updateConfig'])->name('config.update');
```

---

### Fase 4: Componente React

```tsx
// resources/js/pages/tenant/admin/settings/config.tsx

import { Head, useForm } from '@inertiajs/react';
import TenantAdminLayout from '@/layouts/tenant-admin-layout';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Globe, Mail, DollarSign } from 'lucide-react';

interface Props {
    config: {
        locale: string;
        timezone: string;
        mail_from_address: string | null;
        mail_from_name: string | null;
        currency: string;
        currency_locale: string;
    };
    availableLocales: string[];
    localeLabels: Record<string, string>;
    availableTimezones: string[];
    availableCurrencies: Record<string, string>;
}

export default function ConfigSettings({ config, availableLocales, localeLabels, availableTimezones, availableCurrencies }: Props) {
    const { data, setData, post, processing } = useForm({
        locale: config.locale,
        timezone: config.timezone,
        mail_from_address: config.mail_from_address ?? '',
        mail_from_name: config.mail_from_name ?? '',
        currency: config.currency,
        currency_locale: config.currency_locale,
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post(route('tenant.settings.config.update'));
    };

    return (
        <TenantAdminLayout>
            <Head title="Configuration" />

            <form onSubmit={handleSubmit} className="space-y-6">
                {/* Localization */}
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Globe className="h-5 w-5" />
                            Localization
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="grid gap-4 md:grid-cols-2">
                        <div className="space-y-2">
                            <Label>Language</Label>
                            <Select value={data.locale} onValueChange={v => setData('locale', v)}>
                                <SelectTrigger><SelectValue /></SelectTrigger>
                                <SelectContent>
                                    {availableLocales.map(locale => (
                                        <SelectItem key={locale} value={locale}>
                                            {localeLabels[locale] ?? locale}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                        <div className="space-y-2">
                            <Label>Timezone</Label>
                            <Select value={data.timezone} onValueChange={v => setData('timezone', v)}>
                                <SelectTrigger><SelectValue /></SelectTrigger>
                                <SelectContent>
                                    {availableTimezones.map(tz => (
                                        <SelectItem key={tz} value={tz}>{tz}</SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                    </CardContent>
                </Card>

                {/* Email */}
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Mail className="h-5 w-5" />
                            Email Settings
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="grid gap-4 md:grid-cols-2">
                        <div className="space-y-2">
                            <Label>From Address</Label>
                            <Input
                                type="email"
                                value={data.mail_from_address}
                                onChange={e => setData('mail_from_address', e.target.value)}
                            />
                        </div>
                        <div className="space-y-2">
                            <Label>From Name</Label>
                            <Input
                                value={data.mail_from_name}
                                onChange={e => setData('mail_from_name', e.target.value)}
                            />
                        </div>
                    </CardContent>
                </Card>

                {/* Currency */}
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <DollarSign className="h-5 w-5" />
                            Currency
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="space-y-2 max-w-xs">
                            <Label>Default Currency</Label>
                            <Select value={data.currency} onValueChange={v => setData('currency', v)}>
                                <SelectTrigger><SelectValue /></SelectTrigger>
                                <SelectContent>
                                    {Object.entries(availableCurrencies).map(([code, name]) => (
                                        <SelectItem key={code} value={code}>{name}</SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                    </CardContent>
                </Card>

                <div className="flex justify-end">
                    <Button type="submit" disabled={processing}>
                        Save Settings
                    </Button>
                </div>
            </form>
        </TenantAdminLayout>
    );
}
```

---

### Fase 5: Migration para Tenants Existentes

```php
// database/migrations/xxxx_migrate_tenant_config_settings.php

<?php

use Illuminate\Database\Migrations\Migration;
use App\Models\Central\Tenant;

return new class extends Migration
{
    public function up(): void
    {
        Tenant::all()->each(function ($tenant) {
            $settings = $tenant->settings ?? [];

            if (!isset($settings['config'])) {
                $settings['config'] = [];
            }

            // Migrate language.default to config.locale
            if (isset($settings['language']['default'])) {
                $settings['config']['locale'] = $settings['language']['default'];
            }

            // Set defaults
            $settings['config'] = array_merge([
                'locale' => 'en',
                'timezone' => 'UTC',
                'mail_from_address' => null,
                'mail_from_name' => $tenant->name,
                'currency' => 'usd',
                'currency_locale' => 'en',
            ], $settings['config']);

            $tenant->update(['settings' => $settings]);
        });
    }

    public function down(): void
    {
        Tenant::all()->each(function ($tenant) {
            $settings = $tenant->settings ?? [];
            unset($settings['config']);
            $tenant->update(['settings' => $settings]);
        });
    }
};
```

---

## Testes

### Unit Tests

```php
// tests/Unit/TenantConfigKeyTest.php

public function test_storage_config_map_generation(): void
{
    $map = TenantConfigKey::toStorageConfigMap();

    $this->assertArrayHasKey('config.locale', $map);
    $this->assertEquals('app.locale', $map['config.locale']);
}

public function test_validation_rules_for_locale(): void
{
    $rules = TenantConfigKey::LOCALE->validationRules();
    $this->assertContains('string', $rules);
}
```

### Feature Tests

```php
// tests/Feature/TenantConfigBootstrapperTest.php

public function test_tenant_locale_overrides_app_config(): void
{
    $tenant = Tenant::factory()->create();
    $tenant->updateConfig(TenantConfigKey::LOCALE, 'pt_BR');

    tenancy()->initialize($tenant);

    $this->assertEquals('pt_BR', config('app.locale'));

    tenancy()->end();
}

public function test_mail_from_uses_tenant_settings(): void
{
    $tenant = Tenant::factory()->create();
    $tenant->updateConfig(TenantConfigKey::MAIL_FROM_ADDRESS, 'test@tenant.com');
    $tenant->updateConfig(TenantConfigKey::MAIL_FROM_NAME, 'Test Tenant');

    tenancy()->initialize($tenant);

    $this->assertEquals('test@tenant.com', config('mail.from.address'));
    $this->assertEquals('Test Tenant', config('mail.from.name'));
}
```

---

## Considerações de Segurança

1. **Encriptação de Credenciais SMTP** (Enterprise)
   ```php
   $tenant->updateConfig(TenantConfigKey::SMTP_PASSWORD, encrypt($password));
   ```

2. **Validação**: Sempre validar valores contra regras do enum

3. **Permissões**: Edição de config requer `TenantPermission::SETTINGS_EDIT`

4. **Audit Logging**:
   ```php
   activity()
       ->causedBy(auth()->user())
       ->performedOn($tenant)
       ->withProperties(['old' => $oldConfig, 'new' => $newConfig])
       ->log('tenant.config.updated');
   ```

---

## Checklist de Implementação

### Fase 1: Core
- [ ] Criar `app/Enums/TenantConfigKey.php`
- [ ] Habilitar `TenantConfigBootstrapper` em `config/tenancy.php`
- [ ] Configurar `$storageToConfigMap` em `TenancyServiceProvider`
- [ ] Adicionar métodos accessor ao modelo `Tenant`

### Fase 2: Service Layer
- [ ] Adicionar `getConfigSettings()` ao `TenantSettingsService`
- [ ] Adicionar `updateConfig()` ao `TenantSettingsService`
- [ ] Adicionar validação

### Fase 3: Controller/Routes
- [ ] Adicionar métodos `config()` e `updateConfig()` ao controller
- [ ] Adicionar rotas em `routes/tenant.php`
- [ ] Adicionar chaves de tradução

### Fase 4: UI
- [ ] Criar componente React `config.tsx`
- [ ] Adicionar à navegação de settings
- [ ] Testar interações do formulário

### Fase 5: Migration
- [ ] Criar migration para tenants existentes
- [ ] Executar migration

### Fase 6: Testes
- [ ] Unit tests para enum
- [ ] Feature tests para bootstrapper
- [ ] Integration tests para UI

### Fase 7: Enterprise (Futuro)
- [ ] MailTenancyBootstrapper para SMTP customizado
- [ ] Encriptação para credenciais sensíveis
- [ ] Feature flag `custom_smtp`

---

## Arquivos Críticos

| Arquivo | Descrição |
|---------|-----------|
| `app/Enums/TenantConfigKey.php` | Enum com chaves de config e mapeamentos |
| `config/tenancy.php` | Adicionar TenantConfigBootstrapper |
| `app/Providers/TenancyServiceProvider.php` | Configurar `$storageToConfigMap` |
| `app/Models/Central/Tenant.php` | Métodos `getConfig()` e `updateConfig()` |
| `app/Services/Tenant/TenantSettingsService.php` | Gerenciamento de config |
| `app/Http/Controllers/Tenant/Admin/TenantSettingsController.php` | Endpoints de config |
