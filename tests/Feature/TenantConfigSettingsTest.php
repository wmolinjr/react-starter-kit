<?php

namespace Tests\Feature;

use App\Enums\TenantConfigKey;
use App\Models\Central\Tenant;
use App\Services\Tenant\TenantSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests for tenant configuration settings service.
 *
 * Note: HTTP tests require tenant_translation_overrides table which is
 * part of tenant migrations. These tests focus on the service layer.
 */
class TenantConfigSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected TenantSettingsService $service;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed plans
        \Artisan::call('db:seed', ['--class' => 'PlanSeeder']);

        // Create tenant
        $this->tenant = Tenant::factory()->create([
            'slug' => 'config-test-'.uniqid(),
        ]);

        $this->tenant->domains()->create([
            'domain' => $this->tenant->slug.'.myapp.test',
            'is_primary' => true,
        ]);

        $this->service = new TenantSettingsService;
    }

    public function test_get_config_settings_returns_correct_structure(): void
    {
        $settings = $this->service->getConfigSettings($this->tenant);

        $this->assertArrayHasKey('tenantData', $settings);
        $this->assertArrayHasKey('config', $settings);
        $this->assertArrayHasKey('availableLocales', $settings);
        $this->assertArrayHasKey('localeLabels', $settings);
        $this->assertArrayHasKey('availableTimezones', $settings);
        $this->assertArrayHasKey('availableCurrencies', $settings);

        // Verify tenant structure
        $this->assertArrayHasKey('id', $settings['tenantData']);
        $this->assertArrayHasKey('name', $settings['tenantData']);
        $this->assertEquals($this->tenant->id, $settings['tenantData']['id']);
        $this->assertEquals($this->tenant->name, $settings['tenantData']['name']);
    }

    public function test_get_config_settings_includes_all_config_keys(): void
    {
        $settings = $this->service->getConfigSettings($this->tenant);
        $config = $settings['config'];

        $this->assertArrayHasKey('locale', $config);
        $this->assertArrayHasKey('timezone', $config);
        $this->assertArrayHasKey('mail_from_address', $config);
        $this->assertArrayHasKey('mail_from_name', $config);
        $this->assertArrayHasKey('currency', $config);
        $this->assertArrayHasKey('currency_locale', $config);
    }

    public function test_get_config_settings_returns_available_locales(): void
    {
        $settings = $this->service->getConfigSettings($this->tenant);

        $this->assertIsArray($settings['availableLocales']);
        $this->assertNotEmpty($settings['availableLocales']);
    }

    public function test_get_config_settings_returns_timezones(): void
    {
        $settings = $this->service->getConfigSettings($this->tenant);

        $this->assertIsArray($settings['availableTimezones']);
        $this->assertNotEmpty($settings['availableTimezones']);
        $this->assertContains('UTC', $settings['availableTimezones']);
        $this->assertContains('America/Sao_Paulo', $settings['availableTimezones']);
    }

    public function test_get_config_settings_returns_currencies(): void
    {
        $settings = $this->service->getConfigSettings($this->tenant);

        $this->assertIsArray($settings['availableCurrencies']);
        $this->assertArrayHasKey('usd', $settings['availableCurrencies']);
        $this->assertArrayHasKey('brl', $settings['availableCurrencies']);
        $this->assertArrayHasKey('eur', $settings['availableCurrencies']);
    }

    public function test_update_config_updates_locale(): void
    {
        $this->service->updateConfig($this->tenant, [
            'locale' => 'pt_BR',
        ]);

        $this->tenant->refresh();

        $this->assertEquals('pt_BR', $this->tenant->getConfig(TenantConfigKey::LOCALE));
    }

    public function test_update_config_updates_timezone(): void
    {
        $this->service->updateConfig($this->tenant, [
            'timezone' => 'America/New_York',
        ]);

        $this->tenant->refresh();

        $this->assertEquals('America/New_York', $this->tenant->getConfig(TenantConfigKey::TIMEZONE));
    }

    public function test_update_config_updates_mail_from_address(): void
    {
        $this->service->updateConfig($this->tenant, [
            'mail_from_address' => 'test@company.com',
        ]);

        $this->tenant->refresh();

        $this->assertEquals('test@company.com', $this->tenant->getConfig(TenantConfigKey::MAIL_FROM_ADDRESS));
    }

    public function test_update_config_updates_mail_from_name(): void
    {
        $this->service->updateConfig($this->tenant, [
            'mail_from_name' => 'My Company',
        ]);

        $this->tenant->refresh();

        $this->assertEquals('My Company', $this->tenant->getConfig(TenantConfigKey::MAIL_FROM_NAME));
    }

    public function test_update_config_updates_currency(): void
    {
        $this->service->updateConfig($this->tenant, [
            'currency' => 'brl',
        ]);

        $this->tenant->refresh();

        $this->assertEquals('brl', $this->tenant->getConfig(TenantConfigKey::CURRENCY));
    }

    public function test_update_config_updates_multiple_values(): void
    {
        $this->service->updateConfig($this->tenant, [
            'locale' => 'es',
            'timezone' => 'Europe/Madrid',
            'currency' => 'eur',
            'mail_from_address' => 'hola@empresa.es',
            'mail_from_name' => 'Mi Empresa',
        ]);

        $this->tenant->refresh();

        $this->assertEquals('es', $this->tenant->getConfig(TenantConfigKey::LOCALE));
        $this->assertEquals('Europe/Madrid', $this->tenant->getConfig(TenantConfigKey::TIMEZONE));
        $this->assertEquals('eur', $this->tenant->getConfig(TenantConfigKey::CURRENCY));
        $this->assertEquals('hola@empresa.es', $this->tenant->getConfig(TenantConfigKey::MAIL_FROM_ADDRESS));
        $this->assertEquals('Mi Empresa', $this->tenant->getConfig(TenantConfigKey::MAIL_FROM_NAME));
    }

    public function test_update_config_allows_empty_mail_values(): void
    {
        // First set a value
        $this->tenant->updateConfig(TenantConfigKey::MAIL_FROM_ADDRESS, 'old@email.com');

        // Then clear it
        $this->service->updateConfig($this->tenant, [
            'mail_from_address' => '',
        ]);

        $this->tenant->refresh();

        // The stored value should be null (cleared)
        $storedValue = $this->tenant->getSetting('config.mail_from_address');
        $this->assertNull($storedValue);

        // getConfig() returns Laravel default when null (expected fallback behavior)
        $configValue = $this->tenant->getConfig(TenantConfigKey::MAIL_FROM_ADDRESS);
        $this->assertEquals(config('mail.from.address'), $configValue);
    }

    public function test_update_config_ignores_unknown_keys(): void
    {
        // Should not throw exception
        $this->service->updateConfig($this->tenant, [
            'locale' => 'pt_BR',
            'unknown_key' => 'some_value',
        ]);

        $this->tenant->refresh();

        $this->assertEquals('pt_BR', $this->tenant->getConfig(TenantConfigKey::LOCALE));
    }

    public function test_get_config_settings_returns_tenant_values(): void
    {
        // Set some config values
        $this->tenant->updateConfig(TenantConfigKey::LOCALE, 'pt_BR');
        $this->tenant->updateConfig(TenantConfigKey::TIMEZONE, 'America/Sao_Paulo');

        $settings = $this->service->getConfigSettings($this->tenant);

        $this->assertEquals('pt_BR', $settings['config']['locale']);
        $this->assertEquals('America/Sao_Paulo', $settings['config']['timezone']);
    }
}
