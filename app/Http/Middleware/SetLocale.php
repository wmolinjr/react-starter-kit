<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    /**
     * Handle an incoming request.
     *
     * Sets the application locale based on priority:
     * 1. Authenticated user's locale preference
     * 2. Tenant's default language (if in tenant context)
     * 3. Cookie 'locale' (for guests)
     * 4. Accept-Language header (browser preference)
     * 5. App's default locale
     */
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $this->determineLocale($request);

        if ($locale) {
            app()->setLocale($locale);
        }

        return $next($request);
    }

    /**
     * Determine the best locale for the request.
     */
    protected function determineLocale(Request $request): ?string
    {
        $availableLocales = config('app.locales', config('app.locale', ['pt_BR']));

        // 1. Authenticated user's preference (highest priority)
        if ($user = $request->user()) {
            $userLocale = $user->getPreferredLocale();
            if ($userLocale && in_array($userLocale, $availableLocales)) {
                return $userLocale;
            }
        }

        // 2. Tenant's default language (for tenant context)
        if (tenancy()->initialized) {
            $tenantLocale = tenant()?->getSetting('language.default');
            if ($tenantLocale && in_array($tenantLocale, $availableLocales)) {
                return $tenantLocale;
            }
        }

        // 3. Cookie locale (for guests who selected a language)
        $cookieLocale = $request->cookie('locale');
        if ($cookieLocale && in_array($cookieLocale, $availableLocales)) {
            return $cookieLocale;
        }

        // 4. Accept-Language header (browser preference)
        $browserLocale = $this->getLocaleFromAcceptHeader($request, $availableLocales);
        if ($browserLocale) {
            return $browserLocale;
        }

        // 5. Default to app locale
        return config('app.locale', 'pt_BR');
    }

    /**
     * Parse Accept-Language header to find a matching locale.
     */
    protected function getLocaleFromAcceptHeader(Request $request, array $availableLocales): ?string
    {
        $acceptLanguage = $request->header('Accept-Language');

        if (!$acceptLanguage) {
            return null;
        }

        // Parse Accept-Language header (e.g., "pt-BR,pt;q=0.9,en;q=0.8")
        $languages = [];
        foreach (explode(',', $acceptLanguage) as $part) {
            $part = trim($part);
            $segments = explode(';', $part);
            $lang = trim($segments[0]);
            $quality = 1.0;

            if (isset($segments[1])) {
                $qSegment = trim($segments[1]);
                if (str_starts_with($qSegment, 'q=')) {
                    $quality = (float) substr($qSegment, 2);
                }
            }

            $languages[$lang] = $quality;
        }

        // Sort by quality descending
        arsort($languages);

        // Find first matching locale
        foreach (array_keys($languages) as $lang) {
            // Try exact match (pt-BR -> pt_BR)
            $normalizedLang = str_replace('-', '_', $lang);
            if (in_array($normalizedLang, $availableLocales)) {
                return $normalizedLang;
            }

            // Try base language (pt-BR -> pt)
            $baseLang = explode('_', $normalizedLang)[0];
            foreach ($availableLocales as $available) {
                if (str_starts_with($available, $baseLang)) {
                    return $available;
                }
            }
        }

        return null;
    }
}
