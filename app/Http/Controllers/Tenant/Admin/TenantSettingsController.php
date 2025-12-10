<?php

namespace App\Http\Controllers\Tenant\Admin;

use App\Enums\TenantPermission;
use App\Exceptions\Tenant\SettingsException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\AddDomainRequest;
use App\Http\Requests\Tenant\UpdateBrandingRequest;
use App\Http\Resources\Tenant\ApiTokenResource;
use App\Services\Tenant\TenantSettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class TenantSettingsController extends Controller implements HasMiddleware
{
    public function __construct(
        protected TenantSettingsService $settingsService
    ) {}

    /**
     * Get the middleware that should be assigned to the controller.
     */
    public static function middleware(): array
    {
        return [
            // View permissions
            new Middleware('permission:'.TenantPermission::SETTINGS_VIEW->value, only: ['index', 'branding', 'domains', 'language', 'config']),
            new Middleware('permission:'.TenantPermission::API_TOKENS_VIEW->value, only: ['apiTokens']),
            new Middleware('permission:'.TenantPermission::SETTINGS_DANGER->value, only: ['danger']),

            // Edit permissions
            new Middleware('permission:'.TenantPermission::SETTINGS_EDIT->value, only: ['updateBranding', 'addDomain', 'removeDomain', 'updateFeatures', 'updateNotifications', 'updateLanguage', 'updateConfig']),
            new Middleware('permission:'.TenantPermission::SETTINGS_DANGER->value, only: ['destroy']),
        ];
    }

    /**
     * Display tenant settings page.
     */
    public function index(): Response
    {
        $tenant = tenant();

        return Inertia::render('tenant/admin/settings/index', [
            'settings' => $this->settingsService->getAllSettings($tenant),
            'domains' => $tenant->domains,
        ]);
    }

    /**
     * Display branding settings page.
     */
    public function branding(): Response
    {
        return Inertia::render('tenant/admin/settings/branding', [
            'branding' => $this->settingsService->getBrandingSettings(tenant()),
        ]);
    }

    /**
     * Update branding settings.
     */
    public function updateBranding(UpdateBrandingRequest $request): RedirectResponse
    {
        $this->settingsService->updateBranding(tenant(), $request->validated());

        return back()->with('success', __('flash.settings.branding_updated'));
    }

    /**
     * Display domains management page.
     */
    public function domains(): Response
    {
        $config = $this->settingsService->getDomainsConfig(tenant());

        return Inertia::render('tenant/admin/settings/domains', [
            'domains' => $config['domains'],
            'hasCustomDomainFeature' => $config['hasCustomDomainFeature'],
        ]);
    }

    /**
     * Add custom domain to tenant.
     */
    public function addDomain(AddDomainRequest $request): RedirectResponse
    {
        try {
            $this->settingsService->addDomain(tenant(), $request->validated()['domain']);

            return back()->with('success', __('flash.settings.domain_added'));
        } catch (SettingsException $e) {
            throw ValidationException::withMessages([
                'domain' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Remove custom domain from tenant.
     */
    public function removeDomain(Request $request, $domainId): RedirectResponse
    {
        try {
            $this->settingsService->removeDomain(tenant(), $domainId);

            return back()->with('success', __('flash.settings.domain_removed'));
        } catch (SettingsException $e) {
            throw ValidationException::withMessages([
                'domain' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Update tenant features.
     */
    public function updateFeatures(Request $request): RedirectResponse
    {
        $request->validate([
            'api_enabled' => 'boolean',
            'two_factor_required' => 'boolean',
        ]);

        $this->settingsService->updateFeatures(tenant(), $request->only([
            'api_enabled',
            'two_factor_required',
        ]));

        return back()->with('success', __('flash.settings.features_updated'));
    }

    /**
     * Update notification settings.
     */
    public function updateNotifications(Request $request): RedirectResponse
    {
        $request->validate([
            'email_digest' => 'in:never,daily,weekly,monthly',
            'slack_webhook' => 'nullable|url',
        ]);

        $this->settingsService->updateNotifications(tenant(), $request->only([
            'email_digest',
            'slack_webhook',
        ]));

        return back()->with('success', __('flash.settings.notifications_updated'));
    }

    /**
     * Display API tokens management page.
     */
    public function apiTokens(): Response
    {
        $user = auth()->user();

        return Inertia::render('tenant/admin/settings/api-tokens', [
            'tokens' => ApiTokenResource::collection($user->tokens),
        ]);
    }

    /**
     * Display configuration settings page.
     *
     * Shows locale, timezone, email, and currency settings that override
     * Laravel config via TenantConfigBootstrapper.
     */
    public function config(): Response
    {
        return Inertia::render(
            'tenant/admin/settings/config',
            $this->settingsService->getConfigSettings(tenant())
        );
    }

    /**
     * Update configuration settings.
     */
    public function updateConfig(Request $request): RedirectResponse
    {
        $availableLocales = config('app.locales');

        $request->validate([
            'app_name' => ['nullable', 'string', 'max:100'],
            'locale' => ['sometimes', 'string', 'in:'.implode(',', $availableLocales)],
            'timezone' => ['sometimes', 'string', 'timezone'],
            'mail_from_address' => ['nullable', 'email', 'max:255'],
            'mail_from_name' => ['nullable', 'string', 'max:100'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'currency_locale' => ['sometimes', 'string', 'max:10'],
        ]);

        $this->settingsService->updateConfig(tenant(), $request->only([
            'app_name',
            'locale',
            'timezone',
            'mail_from_address',
            'mail_from_name',
            'currency',
            'currency_locale',
        ]));

        return back()->with('success', __('flash.settings.config_updated'));
    }

    /**
     * Display danger zone page.
     */
    public function danger(): Response
    {
        return Inertia::render('tenant/admin/settings/danger');
    }

    /**
     * Delete the tenant permanently.
     */
    public function destroy(): RedirectResponse
    {
        $this->settingsService->deleteTenant(tenant());

        return redirect()->to(config('app.url'))
            ->with('success', __('flash.tenant.organization_deleted'));
    }
}
