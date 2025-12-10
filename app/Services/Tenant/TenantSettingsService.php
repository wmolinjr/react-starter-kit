<?php

namespace App\Services\Tenant;

use App\Enums\TenantConfigKey;
use App\Exceptions\Tenant\SettingsException;
use App\Models\Central\Tenant;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * TenantSettingsService
 *
 * Handles all business logic for tenant settings management.
 * Includes branding, domains, features, notifications, and language settings.
 */
class TenantSettingsService
{
    /**
     * Get all settings for the tenant.
     *
     * @return array<string, mixed>
     */
    public function getAllSettings(Tenant $tenant): array
    {
        return $tenant->settings ?? [];
    }

    /**
     * Get branding settings.
     *
     * @return array<string, mixed>
     */
    public function getBrandingSettings(Tenant $tenant): array
    {
        return $tenant->getSetting('branding', []);
    }

    /**
     * Update branding settings.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateBranding(Tenant $tenant, array $data): void
    {
        // Upload logo if provided
        if (isset($data['logo']) && $data['logo'] instanceof UploadedFile) {
            $this->updateLogo($tenant, $data['logo']);
        }

        // Update colors
        if (! empty($data['primary_color'])) {
            $tenant->updateSetting('branding.primary_color', $data['primary_color']);
        }

        if (! empty($data['secondary_color'])) {
            $tenant->updateSetting('branding.secondary_color', $data['secondary_color']);
        }

        // Update custom CSS
        if (! empty($data['custom_css'])) {
            $tenant->updateSetting('branding.custom_css', $data['custom_css']);
        }
    }

    /**
     * Update tenant logo.
     */
    public function updateLogo(Tenant $tenant, UploadedFile $logo): string
    {
        // Delete old logo if exists
        $oldLogoUrl = $tenant->getSetting('branding.logo_url');
        if ($oldLogoUrl && Storage::disk('public')->exists($oldLogoUrl)) {
            Storage::disk('public')->delete($oldLogoUrl);
        }

        // Store new logo
        $path = $logo->store('tenant-logos', 'public');
        $fullUrl = Storage::disk('public')->url($path);

        $tenant->updateSetting('branding.logo_url', $fullUrl);

        return $fullUrl;
    }

    /**
     * Get domains configuration for tenant.
     *
     * @return array{domains: \Illuminate\Database\Eloquent\Collection, hasCustomDomainFeature: bool}
     */
    public function getDomainsConfig(Tenant $tenant): array
    {
        return [
            'domains' => $tenant->domains,
            'hasCustomDomainFeature' => $tenant->hasFeature('custom_domain'),
        ];
    }

    /**
     * Add custom domain to tenant.
     *
     * @throws SettingsException
     */
    public function addDomain(Tenant $tenant, string $domain): void
    {
        // Check if tenant has custom domain feature
        if (! $tenant->hasFeature('custom_domain')) {
            throw new SettingsException(__('tenant.settings.custom_domain_not_available'));
        }

        // Parse and validate domain
        $domain = $this->sanitizeDomain($domain);

        // Validate domain format
        if (! filter_var($domain, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) {
            throw new SettingsException(__('tenant.settings.invalid_domain_format'));
        }

        // Create domain
        $tenant->domains()->create([
            'domain' => $domain,
            'is_primary' => false,
        ]);
    }

    /**
     * Remove custom domain from tenant.
     *
     * @throws SettingsException
     */
    public function removeDomain(Tenant $tenant, string $domainId): void
    {
        $domain = $tenant->domains()->findOrFail($domainId);

        // Prevent removing primary domain
        if ($domain->is_primary) {
            throw new SettingsException(__('tenant.settings.cannot_remove_primary_domain'));
        }

        $domain->delete();
    }

    /**
     * Sanitize domain input.
     */
    protected function sanitizeDomain(string $domain): string
    {
        $domain = strtolower(trim($domain));

        // Remove protocol if present
        $domain = preg_replace('#^https?://#', '', $domain);

        // Remove trailing slash
        $domain = rtrim($domain, '/');

        return $domain;
    }

    /**
     * Update feature settings.
     *
     * @param  array<string, bool>  $features
     */
    public function updateFeatures(Tenant $tenant, array $features): void
    {
        if (isset($features['api_enabled'])) {
            $tenant->updateSetting('features.api_enabled', (bool) $features['api_enabled']);
        }

        if (isset($features['two_factor_required'])) {
            $tenant->updateSetting('features.two_factor_required', (bool) $features['two_factor_required']);
        }
    }

    /**
     * Update notification settings.
     *
     * @param  array<string, mixed>  $notifications
     */
    public function updateNotifications(Tenant $tenant, array $notifications): void
    {
        if (! empty($notifications['email_digest'])) {
            $tenant->updateSetting('notifications.email_digest', $notifications['email_digest']);
        }

        if (! empty($notifications['slack_webhook'])) {
            $tenant->updateSetting('notifications.slack_webhook', $notifications['slack_webhook']);
        }
    }

    /**
     * Get language settings.
     *
     * @return array{currentLanguage: string, availableLanguages: array, languageLabels: array}
     */
    public function getLanguageSettings(Tenant $tenant): array
    {
        return [
            'currentLanguage' => $tenant->getSetting('language.default', config('app.locale')),
            'availableLanguages' => config('app.locales'),
            'languageLabels' => collect(config('app.locale_labels'))
                ->only(config('app.locales'))
                ->toArray(),
        ];
    }

    /**
     * Update language setting.
     */
    public function updateLanguage(Tenant $tenant, string $language): void
    {
        $availableLocales = config('app.locales');

        if (! in_array($language, $availableLocales)) {
            throw new SettingsException(__('tenant.settings.invalid_language'));
        }

        $tenant->updateSetting('language.default', $language);
    }

    // ==========================================
    // Config Settings (TenantConfigBootstrapper)
    // ==========================================

    /**
     * Get all configuration settings for the config page.
     *
     * NOTE: Uses 'tenantData' instead of 'tenant' to avoid overriding
     * the shared 'tenant' prop from HandleInertiaRequests middleware,
     * which contains plan.features needed for sidebar navigation.
     *
     * @return array{
     *     tenantData: array{id: string, name: string},
     *     config: array<string, mixed>,
     *     availableLocales: array<string>,
     *     localeLabels: array<string, string>,
     *     availableTimezones: array<string>,
     *     availableCurrencies: array<string, string>
     * }
     */
    public function getConfigSettings(Tenant $tenant): array
    {
        return [
            'tenantData' => [
                'id' => $tenant->id,
                'name' => $tenant->name,
            ],
            'config' => $tenant->getAllConfig(),
            'availableLocales' => config('app.locales', ['en']),
            'localeLabels' => collect(config('app.locale_labels', []))
                ->only(config('app.locales', ['en']))
                ->toArray(),
            'availableTimezones' => $this->getGroupedTimezones(),
            'availableCurrencies' => TenantConfigKey::availableCurrencies(),
            'availableDateFormats' => TenantConfigKey::dateFormatOptions(),
            'availableTimeFormats' => TenantConfigKey::timeFormatOptions(),
            'availableWeekdays' => TenantConfigKey::weekdayOptions(),
        ];
    }

    /**
     * Update configuration settings.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public function updateConfig(Tenant $tenant, array $data): void
    {
        foreach ($data as $key => $value) {
            $configKey = TenantConfigKey::tryFrom($key);

            if (! $configKey) {
                continue;
            }

            // Skip null/empty values for optional fields
            if ($value === null || $value === '') {
                if (in_array($configKey, [TenantConfigKey::APP_NAME, TenantConfigKey::MAIL_FROM_ADDRESS, TenantConfigKey::MAIL_FROM_NAME])) {
                    $tenant->updateConfig($configKey, null);

                    continue;
                }
            }

            $tenant->updateConfig($configKey, $value);
        }

        // Backward compatibility: sync locale with language.default
        if (isset($data['locale'])) {
            $tenant->updateSetting('language.default', $data['locale']);
        }
    }

    /**
     * Get timezones grouped by region for better UX.
     *
     * @return array<string>
     */
    protected function getGroupedTimezones(): array
    {
        // Return flat list - grouping can be done in frontend if needed
        return timezone_identifiers_list();
    }

    /**
     * Delete tenant permanently.
     */
    public function deleteTenant(Tenant $tenant): void
    {
        $tenant->delete();
    }
}
