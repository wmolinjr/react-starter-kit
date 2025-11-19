<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class TenantSettingsController extends Controller
{
    /**
     * Display tenant settings page.
     */
    public function index(): Response
    {
        $tenant = current_tenant();

        return Inertia::render('tenant/settings/index', [
            'tenant' => $tenant,
            'settings' => $tenant->settings ?? [],
            'domains' => $tenant->domains,
        ]);
    }

    /**
     * Display branding settings page.
     */
    public function branding(): Response
    {
        $tenant = current_tenant();

        return Inertia::render('tenant/settings/branding', [
            'tenant' => $tenant,
            'branding' => $tenant->getSetting('branding', []),
        ]);
    }

    /**
     * Update branding settings.
     */
    public function updateBranding(Request $request)
    {
        $request->validate([
            'logo' => 'nullable|image|max:2048',
            'primary_color' => 'nullable|regex:/^#[0-9A-F]{6}$/i',
            'secondary_color' => 'nullable|regex:/^#[0-9A-F]{6}$/i',
            'custom_css' => 'nullable|string|max:10000',
        ]);

        $tenant = current_tenant();

        // Upload logo if provided
        if ($request->hasFile('logo')) {
            // Delete old logo if exists
            $oldLogoUrl = $tenant->getSetting('branding.logo_url');
            if ($oldLogoUrl && Storage::disk('public')->exists($oldLogoUrl)) {
                Storage::disk('public')->delete($oldLogoUrl);
            }

            // Store new logo
            $path = $request->file('logo')->store('tenant-logos', 'public');
            $tenant->updateSetting('branding.logo_url', Storage::disk('public')->url($path));
        }

        // Update colors
        if ($request->filled('primary_color')) {
            $tenant->updateSetting('branding.primary_color', $request->primary_color);
        }

        if ($request->filled('secondary_color')) {
            $tenant->updateSetting('branding.secondary_color', $request->secondary_color);
        }

        // Update custom CSS
        if ($request->filled('custom_css')) {
            $tenant->updateSetting('branding.custom_css', $request->custom_css);
        }

        return back()->with('success', 'Branding atualizado com sucesso!');
    }

    /**
     * Display domains management page.
     */
    public function domains(): Response
    {
        $tenant = current_tenant();

        return Inertia::render('tenant/settings/domains', [
            'tenant' => $tenant,
            'domains' => $tenant->domains,
            'hasCustomDomainFeature' => $tenant->hasFeature('custom_domain'),
        ]);
    }

    /**
     * Add custom domain to tenant.
     */
    public function addDomain(Request $request)
    {
        $request->validate([
            'domain' => 'required|string|max:255|unique:domains,domain',
        ]);

        $tenant = current_tenant();

        // Check if tenant has custom domain feature
        if (!$tenant->hasFeature('custom_domain')) {
            throw ValidationException::withMessages([
                'domain' => 'Feature de domínio customizado não disponível no seu plano.',
            ]);
        }

        // Parse and validate domain
        $domain = strtolower(trim($request->domain));

        // Remove protocol if present
        $domain = preg_replace('#^https?://#', '', $domain);

        // Remove trailing slash
        $domain = rtrim($domain, '/');

        // Validate domain format
        if (!filter_var($domain, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) {
            throw ValidationException::withMessages([
                'domain' => 'Formato de domínio inválido.',
            ]);
        }

        // Create domain
        $tenant->domains()->create([
            'domain' => $domain,
            'is_primary' => false,
        ]);

        return back()->with('success', 'Domínio adicionado! Configure o DNS para apontar para o servidor.');
    }

    /**
     * Remove custom domain from tenant.
     */
    public function removeDomain(Request $request, $domainId)
    {
        $tenant = current_tenant();

        $domain = $tenant->domains()->findOrFail($domainId);

        // Prevent removing primary domain
        if ($domain->is_primary) {
            throw ValidationException::withMessages([
                'domain' => 'Não é possível remover o domínio primário.',
            ]);
        }

        $domain->delete();

        return back()->with('success', 'Domínio removido com sucesso!');
    }

    /**
     * Update tenant features.
     */
    public function updateFeatures(Request $request)
    {
        $request->validate([
            'api_enabled' => 'boolean',
            'two_factor_required' => 'boolean',
        ]);

        $tenant = current_tenant();

        // Only allow enabling features that are available in the plan
        if ($request->filled('api_enabled')) {
            $tenant->updateSetting('features.api_enabled', $request->boolean('api_enabled'));
        }

        if ($request->filled('two_factor_required')) {
            $tenant->updateSetting('features.two_factor_required', $request->boolean('two_factor_required'));
        }

        return back()->with('success', 'Configurações de features atualizadas!');
    }

    /**
     * Update notification settings.
     */
    public function updateNotifications(Request $request)
    {
        $request->validate([
            'email_digest' => 'in:never,daily,weekly,monthly',
            'slack_webhook' => 'nullable|url',
        ]);

        $tenant = current_tenant();

        if ($request->filled('email_digest')) {
            $tenant->updateSetting('notifications.email_digest', $request->email_digest);
        }

        if ($request->filled('slack_webhook')) {
            $tenant->updateSetting('notifications.slack_webhook', $request->slack_webhook);
        }

        return back()->with('success', 'Configurações de notificações atualizadas!');
    }
}
