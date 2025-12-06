<?php

namespace Tests\Feature;

use App\Enums\TenantConfigKey;
use App\Models\Central\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests for TenantConfigBootstrapper integration.
 *
 * Verifies that tenant-specific configuration values are properly
 * applied to Laravel's config when tenancy is initialized.
 */
class TenantConfigBootstrapperTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed plans for tenant creation
        \Artisan::call('db:seed', ['--class' => 'PlanSeeder']);

        // Create test tenant
        $this->tenant = Tenant::factory()->create([
            'slug' => 'config-test-' . uniqid(),
        ]);

        $this->tenant->domains()->create([
            'domain' => $this->tenant->slug . '.myapp.test',
            'is_primary' => true,
        ]);
    }

    protected function tearDown(): void
    {
        if (tenancy()->initialized) {
            tenancy()->end();
        }

        parent::tearDown();
    }

    public function test_tenant_locale_overrides_app_config(): void
    {
        // Store original config
        $originalLocale = config('app.locale');

        // Set tenant config
        $this->tenant->updateConfig(TenantConfigKey::LOCALE, 'pt_BR');

        // Initialize tenancy
        tenancy()->initialize($this->tenant);

        // Assert config was overridden
        $this->assertEquals('pt_BR', config('app.locale'));

        // End tenancy
        tenancy()->end();

        // Assert config reverted (bootstrapper should restore original)
        $this->assertEquals($originalLocale, config('app.locale'));
    }

    public function test_tenant_timezone_stored_correctly(): void
    {
        $this->tenant->updateConfig(TenantConfigKey::TIMEZONE, 'America/Sao_Paulo');

        $this->assertEquals('America/Sao_Paulo', $this->tenant->getConfig(TenantConfigKey::TIMEZONE));
    }

    public function test_tenant_mail_from_address_stored_correctly(): void
    {
        $this->tenant->updateConfig(TenantConfigKey::MAIL_FROM_ADDRESS, 'contact@tenant.com');

        $this->assertEquals('contact@tenant.com', $this->tenant->getConfig(TenantConfigKey::MAIL_FROM_ADDRESS));
    }

    public function test_tenant_mail_from_name_stored_correctly(): void
    {
        $this->tenant->updateConfig(TenantConfigKey::MAIL_FROM_NAME, 'My Tenant Company');

        $this->assertEquals('My Tenant Company', $this->tenant->getConfig(TenantConfigKey::MAIL_FROM_NAME));
    }

    public function test_tenant_currency_stored_correctly(): void
    {
        $this->tenant->updateConfig(TenantConfigKey::CURRENCY, 'brl');

        $this->assertEquals('brl', $this->tenant->getConfig(TenantConfigKey::CURRENCY));
    }

    public function test_tenant_currency_locale_stored_correctly(): void
    {
        $this->tenant->updateConfig(TenantConfigKey::CURRENCY_LOCALE, 'pt_BR');

        $this->assertEquals('pt_BR', $this->tenant->getConfig(TenantConfigKey::CURRENCY_LOCALE));
    }

    public function test_multiple_config_values_stored_correctly(): void
    {
        // Set multiple configs
        $this->tenant->updateConfig(TenantConfigKey::LOCALE, 'es');
        $this->tenant->updateConfig(TenantConfigKey::TIMEZONE, 'Europe/Madrid');
        $this->tenant->updateConfig(TenantConfigKey::CURRENCY, 'eur');

        $this->assertEquals('es', $this->tenant->getConfig(TenantConfigKey::LOCALE));
        $this->assertEquals('Europe/Madrid', $this->tenant->getConfig(TenantConfigKey::TIMEZONE));
        $this->assertEquals('eur', $this->tenant->getConfig(TenantConfigKey::CURRENCY));
    }

    public function test_unset_config_returns_default(): void
    {
        // Don't set any config - should return default
        $locale = $this->tenant->getConfig(TenantConfigKey::LOCALE);
        $timezone = $this->tenant->getConfig(TenantConfigKey::TIMEZONE);

        // Should have default values
        $this->assertNotNull($locale);
        $this->assertNotNull($timezone);
    }

    public function test_get_config_returns_tenant_value(): void
    {
        $this->tenant->updateConfig(TenantConfigKey::LOCALE, 'fr');

        $value = $this->tenant->getConfig(TenantConfigKey::LOCALE);

        $this->assertEquals('fr', $value);
    }

    public function test_get_config_returns_default_when_not_set(): void
    {
        // Don't set any config - should return default
        $value = $this->tenant->getConfig(TenantConfigKey::LOCALE);

        // Should fall back to Laravel config or enum default
        $this->assertNotNull($value);
    }

    public function test_get_all_config_returns_all_values(): void
    {
        $this->tenant->updateConfig(TenantConfigKey::LOCALE, 'de');
        $this->tenant->updateConfig(TenantConfigKey::TIMEZONE, 'Europe/Berlin');

        $allConfig = $this->tenant->getAllConfig();

        $this->assertIsArray($allConfig);
        $this->assertArrayHasKey('locale', $allConfig);
        $this->assertArrayHasKey('timezone', $allConfig);
        $this->assertEquals('de', $allConfig['locale']);
        $this->assertEquals('Europe/Berlin', $allConfig['timezone']);
    }

    public function test_update_config_persists_value(): void
    {
        $result = $this->tenant->updateConfig(TenantConfigKey::LOCALE, 'ja');

        $this->assertTrue($result);

        // Refresh model and check
        $this->tenant->refresh();

        $this->assertEquals('ja', $this->tenant->getConfig(TenantConfigKey::LOCALE));
    }

    public function test_config_stored_in_settings_json(): void
    {
        $this->tenant->updateConfig(TenantConfigKey::LOCALE, 'ko');
        $this->tenant->updateConfig(TenantConfigKey::CURRENCY, 'krw');

        $this->tenant->refresh();

        $settings = $this->tenant->settings;

        $this->assertArrayHasKey('config', $settings);
        $this->assertEquals('ko', $settings['config']['locale']);
        $this->assertEquals('krw', $settings['config']['currency']);
    }

    public function test_different_tenants_have_independent_configs(): void
    {
        // Set config for first tenant
        $this->tenant->updateConfig(TenantConfigKey::LOCALE, 'pt_BR');

        // Create second tenant with different config
        $tenant2 = Tenant::factory()->create([
            'slug' => 'config-test-2-' . uniqid(),
        ]);
        $tenant2->domains()->create([
            'domain' => $tenant2->slug . '.myapp.test',
            'is_primary' => true,
        ]);
        $tenant2->updateConfig(TenantConfigKey::LOCALE, 'en');

        // Verify each tenant has independent config stored
        $this->assertEquals('pt_BR', $this->tenant->getConfig(TenantConfigKey::LOCALE));
        $this->assertEquals('en', $tenant2->getConfig(TenantConfigKey::LOCALE));
    }
}
