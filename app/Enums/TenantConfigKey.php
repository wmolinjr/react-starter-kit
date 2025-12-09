<?php

namespace App\Enums;

/**
 * Tenant-specific configuration keys.
 *
 * Single source of truth for tenant configuration keys with translations.
 * Maps tenant settings to Laravel config keys via TenantConfigBootstrapper.
 * Settings are stored in tenant.settings JSON column under 'config' key.
 *
 * @see \Stancl\Tenancy\Bootstrappers\TenantConfigBootstrapper
 */
enum TenantConfigKey: string
{
    // Branding
    case APP_NAME = 'app_name';

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
     * Get translatable name.
     *
     * @return array<string, string>
     */
    public function name(): array
    {
        return match ($this) {
            self::APP_NAME => ['en' => 'Application Name', 'pt_BR' => 'Nome da Aplicação'],
            self::LOCALE => ['en' => 'Language', 'pt_BR' => 'Idioma'],
            self::TIMEZONE => ['en' => 'Timezone', 'pt_BR' => 'Fuso Horário'],
            self::MAIL_FROM_ADDRESS => ['en' => 'From Email', 'pt_BR' => 'E-mail de Origem'],
            self::MAIL_FROM_NAME => ['en' => 'From Name', 'pt_BR' => 'Nome de Origem'],
            self::CURRENCY => ['en' => 'Currency', 'pt_BR' => 'Moeda'],
            self::CURRENCY_LOCALE => ['en' => 'Currency Locale', 'pt_BR' => 'Localidade da Moeda'],
        };
    }

    /**
     * Get translatable description.
     *
     * @return array<string, string>
     */
    public function description(): array
    {
        return match ($this) {
            self::APP_NAME => [
                'en' => 'Custom name displayed to your users',
                'pt_BR' => 'Nome personalizado exibido aos seus usuários',
            ],
            self::LOCALE => [
                'en' => 'Default language for your workspace',
                'pt_BR' => 'Idioma padrão para o seu espaço de trabalho',
            ],
            self::TIMEZONE => [
                'en' => 'Timezone for dates and times',
                'pt_BR' => 'Fuso horário para datas e horas',
            ],
            self::MAIL_FROM_ADDRESS => [
                'en' => 'Email address used for sending notifications',
                'pt_BR' => 'Endereço de e-mail usado para enviar notificações',
            ],
            self::MAIL_FROM_NAME => [
                'en' => 'Name displayed in email sender field',
                'pt_BR' => 'Nome exibido no campo de remetente do e-mail',
            ],
            self::CURRENCY => [
                'en' => 'Default currency for billing',
                'pt_BR' => 'Moeda padrão para faturamento',
            ],
            self::CURRENCY_LOCALE => [
                'en' => 'Locale for currency formatting',
                'pt_BR' => 'Localidade para formatação de moeda',
            ],
        };
    }

    /**
     * Get Lucide icon name.
     */
    public function icon(): string
    {
        return match ($this) {
            self::APP_NAME => 'Type',
            self::LOCALE => 'Globe',
            self::TIMEZONE => 'Clock',
            self::MAIL_FROM_ADDRESS => 'Mail',
            self::MAIL_FROM_NAME => 'User',
            self::CURRENCY => 'DollarSign',
            self::CURRENCY_LOCALE => 'Languages',
        };
    }

    /**
     * Get color for UI display.
     */
    public function color(): string
    {
        return match ($this->category()) {
            'branding' => 'purple',
            'localization' => 'blue',
            'email' => 'orange',
            'payments' => 'green',
            default => 'gray',
        };
    }

    /**
     * Get badge variant for UI display.
     */
    public function badgeVariant(): string
    {
        return match ($this->category()) {
            'branding' => 'default',
            'localization' => 'secondary',
            'email' => 'outline',
            'payments' => 'default',
            default => 'outline',
        };
    }

    /**
     * Get the category for this config key.
     */
    public function category(): string
    {
        return match ($this) {
            self::APP_NAME => 'branding',
            self::LOCALE, self::TIMEZONE => 'localization',
            self::MAIL_FROM_ADDRESS, self::MAIL_FROM_NAME => 'email',
            self::CURRENCY, self::CURRENCY_LOCALE => 'payments',
        };
    }

    /**
     * Get translated label for current locale.
     */
    public function label(?string $locale = null): string
    {
        $locale = $locale ?? app()->getLocale();
        $names = $this->name();

        return $names[$locale] ?? $names['en'] ?? $this->value;
    }

    /**
     * Get translated description for current locale.
     */
    public function translatedDescription(?string $locale = null): string
    {
        $locale = $locale ?? app()->getLocale();
        $descriptions = $this->description();

        return $descriptions[$locale] ?? $descriptions['en'];
    }

    /**
     * Get the Laravel config key(s) this maps to.
     *
     * @return array<string>
     */
    public function configKeys(): array
    {
        return match ($this) {
            self::APP_NAME => ['app.name'],
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
     * Settings are stored as tenant.settings['config']['key'].
     */
    public function settingsPath(): string
    {
        return 'config.'.$this->value;
    }

    /**
     * Get default value for this config key.
     */
    public function defaultValue(): mixed
    {
        return match ($this) {
            self::APP_NAME => null, // Falls back to config('app.name')
            self::LOCALE => 'en',
            self::TIMEZONE => 'UTC',
            self::MAIL_FROM_ADDRESS => null,
            self::MAIL_FROM_NAME => null,
            self::CURRENCY => 'usd',
            self::CURRENCY_LOCALE => 'en',
        };
    }

    /**
     * Get validation rules for this config key.
     *
     * @return array<string>
     */
    public function validationRules(): array
    {
        return match ($this) {
            self::APP_NAME => ['nullable', 'string', 'max:100'],
            self::LOCALE => ['string', 'in:'.implode(',', config('app.locales', ['en']))],
            self::TIMEZONE => ['string', 'timezone'],
            self::MAIL_FROM_ADDRESS => ['nullable', 'email', 'max:255'],
            self::MAIL_FROM_NAME => ['nullable', 'string', 'max:100'],
            self::CURRENCY => ['string', 'size:3', 'lowercase'],
            self::CURRENCY_LOCALE => ['string', 'max:10'],
        };
    }

    /**
     * Get all config key values as strings.
     *
     * @return string[]
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Get all cases as options for select inputs.
     *
     * @return array<string, string>
     */
    public static function options(?string $locale = null): array
    {
        $options = [];
        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label($locale);
        }

        return $options;
    }

    /**
     * Convert single config key to frontend format.
     *
     * @return array<string, mixed>
     */
    public function toFrontend(?string $locale = null): array
    {
        return [
            'value' => $this->value,
            'label' => $this->label($locale),
            'description' => $this->translatedDescription($locale),
            'icon' => $this->icon(),
            'color' => $this->color(),
            'badge_variant' => $this->badgeVariant(),
            'category' => $this->category(),
            'default_value' => $this->defaultValue(),
        ];
    }

    /**
     * Convert all config keys to frontend array format.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function toFrontendArray(?string $locale = null): array
    {
        return array_map(
            fn (self $key) => $key->toFrontend($locale),
            self::cases()
        );
    }

    /**
     * Convert all cases to frontend map format (keyed by value).
     *
     * @return array<string, array<string, mixed>>
     */
    public static function toFrontendMap(?string $locale = null): array
    {
        $map = [];
        foreach (self::cases() as $case) {
            $map[$case->value] = $case->toFrontend($locale);
        }

        return $map;
    }

    /**
     * Generate storageToConfigMap for TenantConfigBootstrapper.
     *
     * Returns a map where:
     * - Key: tenant settings path (e.g., 'config.locale')
     * - Value: Laravel config key(s) (e.g., 'app.locale' or ['app.locale', 'other.key'])
     *
     * @return array<string, string|array<string>>
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

    /**
     * Get all unique categories with their descriptions.
     *
     * @return array<string, array{en: string, pt_BR: string}>
     */
    public static function categories(): array
    {
        return [
            'branding' => ['en' => 'Branding', 'pt_BR' => 'Marca'],
            'localization' => ['en' => 'Localization', 'pt_BR' => 'Localização'],
            'email' => ['en' => 'Email', 'pt_BR' => 'E-mail'],
            'payments' => ['en' => 'Payments', 'pt_BR' => 'Pagamentos'],
        ];
    }

    /**
     * Get translated category name.
     */
    public static function categoryTrans(string $category, ?string $locale = null): string
    {
        $locale = $locale ?? app()->getLocale();
        $categories = self::categories();

        return $categories[$category][$locale] ?? $categories[$category]['en'] ?? ucfirst($category);
    }

    /**
     * Get all available currencies with labels.
     *
     * @return array<string, string>
     */
    public static function availableCurrencies(): array
    {
        return [
            'usd' => 'US Dollar (USD)',
            'brl' => 'Brazilian Real (BRL)',
            'eur' => 'Euro (EUR)',
            'gbp' => 'British Pound (GBP)',
            'cad' => 'Canadian Dollar (CAD)',
            'aud' => 'Australian Dollar (AUD)',
            'jpy' => 'Japanese Yen (JPY)',
            'cny' => 'Chinese Yuan (CNY)',
            'inr' => 'Indian Rupee (INR)',
            'mxn' => 'Mexican Peso (MXN)',
        ];
    }
}
