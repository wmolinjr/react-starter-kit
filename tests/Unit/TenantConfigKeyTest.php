<?php

namespace Tests\Unit;

use App\Enums\TenantConfigKey;
use Tests\TestCase;

/**
 * Unit tests for TenantConfigKey enum.
 *
 * Uses Laravel TestCase because validation rules use config().
 */
class TenantConfigKeyTest extends TestCase
{
    public function test_all_cases_have_config_keys(): void
    {
        foreach (TenantConfigKey::cases() as $case) {
            $configKeys = $case->configKeys();

            $this->assertIsArray($configKeys);
            $this->assertNotEmpty($configKeys, "Config key {$case->value} should have at least one config key");
        }
    }

    public function test_locale_maps_to_app_locale(): void
    {
        $configKeys = TenantConfigKey::LOCALE->configKeys();

        $this->assertContains('app.locale', $configKeys);
    }

    public function test_timezone_maps_to_app_timezone(): void
    {
        $configKeys = TenantConfigKey::TIMEZONE->configKeys();

        $this->assertContains('app.timezone', $configKeys);
    }

    public function test_mail_from_address_maps_to_mail_config(): void
    {
        $configKeys = TenantConfigKey::MAIL_FROM_ADDRESS->configKeys();

        $this->assertContains('mail.from.address', $configKeys);
    }

    public function test_mail_from_name_maps_to_mail_config(): void
    {
        $configKeys = TenantConfigKey::MAIL_FROM_NAME->configKeys();

        $this->assertContains('mail.from.name', $configKeys);
    }

    public function test_currency_maps_to_cashier_currency(): void
    {
        $configKeys = TenantConfigKey::CURRENCY->configKeys();

        $this->assertContains('cashier.currency', $configKeys);
    }

    public function test_currency_locale_maps_to_cashier_currency_locale(): void
    {
        $configKeys = TenantConfigKey::CURRENCY_LOCALE->configKeys();

        $this->assertContains('cashier.currency_locale', $configKeys);
    }

    public function test_settings_path_returns_config_prefixed_value(): void
    {
        foreach (TenantConfigKey::cases() as $case) {
            $settingsPath = $case->settingsPath();

            $this->assertStringStartsWith('config.', $settingsPath);
            $this->assertEquals('config.'.$case->value, $settingsPath);
        }
    }

    public function test_all_cases_have_default_values(): void
    {
        foreach (TenantConfigKey::cases() as $case) {
            // Should not throw exception
            $defaultValue = $case->defaultValue();

            // Default values can be null, string, or other types
            $this->assertTrue(
                is_null($defaultValue) || is_string($defaultValue) || is_numeric($defaultValue),
                "Default value for {$case->value} should be null, string, or numeric"
            );
        }
    }

    public function test_default_locale_is_en(): void
    {
        $this->assertEquals('en', TenantConfigKey::LOCALE->defaultValue());
    }

    public function test_default_timezone_is_utc(): void
    {
        $this->assertEquals('UTC', TenantConfigKey::TIMEZONE->defaultValue());
    }

    public function test_default_currency_is_usd(): void
    {
        $this->assertEquals('usd', TenantConfigKey::CURRENCY->defaultValue());
    }

    public function test_default_mail_from_address_is_null(): void
    {
        $this->assertNull(TenantConfigKey::MAIL_FROM_ADDRESS->defaultValue());
    }

    public function test_default_mail_from_name_is_null(): void
    {
        $this->assertNull(TenantConfigKey::MAIL_FROM_NAME->defaultValue());
    }

    public function test_all_cases_have_validation_rules(): void
    {
        foreach (TenantConfigKey::cases() as $case) {
            $rules = $case->validationRules();

            $this->assertIsArray($rules);
            $this->assertNotEmpty($rules, "Validation rules for {$case->value} should not be empty");
        }
    }

    public function test_locale_validation_requires_string(): void
    {
        $rules = TenantConfigKey::LOCALE->validationRules();

        $this->assertContains('string', $rules);
    }

    public function test_timezone_validation_requires_timezone(): void
    {
        $rules = TenantConfigKey::TIMEZONE->validationRules();

        $this->assertContains('timezone', $rules);
    }

    public function test_mail_from_address_validation_allows_nullable_email(): void
    {
        $rules = TenantConfigKey::MAIL_FROM_ADDRESS->validationRules();

        $this->assertContains('nullable', $rules);
        $this->assertContains('email', $rules);
    }

    public function test_currency_validation_requires_3_char_lowercase(): void
    {
        $rules = TenantConfigKey::CURRENCY->validationRules();

        $this->assertContains('size:3', $rules);
        $this->assertContains('lowercase', $rules);
    }

    public function test_to_storage_config_map_returns_correct_structure(): void
    {
        $map = TenantConfigKey::toStorageConfigMap();

        $this->assertIsArray($map);
        $this->assertNotEmpty($map);

        // Should have same count as enum cases
        $this->assertCount(count(TenantConfigKey::cases()), $map);
    }

    public function test_to_storage_config_map_has_correct_keys(): void
    {
        $map = TenantConfigKey::toStorageConfigMap();

        // Check all expected keys exist
        $this->assertArrayHasKey('config.locale', $map);
        $this->assertArrayHasKey('config.timezone', $map);
        $this->assertArrayHasKey('config.mail_from_address', $map);
        $this->assertArrayHasKey('config.mail_from_name', $map);
        $this->assertArrayHasKey('config.currency', $map);
        $this->assertArrayHasKey('config.currency_locale', $map);
    }

    public function test_to_storage_config_map_values_are_strings_for_single_config_keys(): void
    {
        $map = TenantConfigKey::toStorageConfigMap();

        // When config maps to single key, value should be string
        $this->assertEquals('app.locale', $map['config.locale']);
        $this->assertEquals('app.timezone', $map['config.timezone']);
        $this->assertEquals('mail.from.address', $map['config.mail_from_address']);
        $this->assertEquals('mail.from.name', $map['config.mail_from_name']);
        $this->assertEquals('cashier.currency', $map['config.currency']);
        $this->assertEquals('cashier.currency_locale', $map['config.currency_locale']);
    }

    public function test_available_currencies_returns_array(): void
    {
        $currencies = TenantConfigKey::availableCurrencies();

        $this->assertIsArray($currencies);
        $this->assertNotEmpty($currencies);
    }

    public function test_available_currencies_includes_common_currencies(): void
    {
        $currencies = TenantConfigKey::availableCurrencies();

        $this->assertArrayHasKey('usd', $currencies);
        $this->assertArrayHasKey('brl', $currencies);
        $this->assertArrayHasKey('eur', $currencies);
        $this->assertArrayHasKey('gbp', $currencies);
    }

    public function test_available_currencies_has_labels(): void
    {
        $currencies = TenantConfigKey::availableCurrencies();

        foreach ($currencies as $code => $label) {
            $this->assertIsString($code);
            $this->assertIsString($label);
            $this->assertEquals(3, strlen($code), "Currency code {$code} should be 3 characters");
            $this->assertNotEmpty($label, "Currency {$code} should have a label");
        }
    }
}
