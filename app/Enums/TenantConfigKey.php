<?php

namespace App\Enums;

/**
 * Tenant-specific configuration keys.
 *
 * Maps tenant settings to Laravel config keys via TenantConfigBootstrapper.
 * Settings are stored in tenant.settings JSON column under 'config' key.
 *
 * @see \Stancl\Tenancy\Bootstrappers\TenantConfigBootstrapper
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
     *
     * @return array<string>
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
     * Settings are stored as tenant.settings['config']['key'].
     */
    public function settingsPath(): string
    {
        return 'config.' . $this->value;
    }

    /**
     * Get default value for this config key.
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
     * Get validation rules for this config key.
     *
     * @return array<string>
     */
    public function validationRules(): array
    {
        return match ($this) {
            self::LOCALE => ['string', 'in:' . implode(',', config('app.locales', ['en']))],
            self::TIMEZONE => ['string', 'timezone'],
            self::MAIL_FROM_ADDRESS => ['nullable', 'email', 'max:255'],
            self::MAIL_FROM_NAME => ['nullable', 'string', 'max:100'],
            self::CURRENCY => ['string', 'size:3', 'lowercase'],
            self::CURRENCY_LOCALE => ['string', 'max:10'],
        };
    }

    /**
     * Get human-readable label for this config key.
     */
    public function label(): string
    {
        return match ($this) {
            self::LOCALE => __('tenant.config.locale'),
            self::TIMEZONE => __('tenant.config.timezone'),
            self::MAIL_FROM_ADDRESS => __('tenant.config.mail_from_address'),
            self::MAIL_FROM_NAME => __('tenant.config.mail_from_name'),
            self::CURRENCY => __('tenant.config.currency'),
            self::CURRENCY_LOCALE => __('tenant.config.currency_locale'),
        };
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
