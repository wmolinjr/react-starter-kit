<?php

/**
 * Stripe helper functions.
 *
 * @see https://stripe.com/docs/checkout/customization#supported-locales
 */

if (! function_exists('stripe_locale')) {
    /**
     * Stripe supported locales (34 languages).
     */
    define('STRIPE_SUPPORTED_LOCALES', [
        'auto', 'bg', 'cs', 'da', 'de', 'el', 'en', 'en-GB', 'es', 'es-419',
        'et', 'fi', 'fil', 'fr', 'fr-CA', 'hr', 'hu', 'id', 'it', 'ja',
        'ko', 'lt', 'lv', 'ms', 'mt', 'nb', 'nl', 'pl', 'pt', 'pt-BR',
        'ro', 'ru', 'sk', 'sl', 'sv', 'th', 'tr', 'uk', 'vi',
        'zh', 'zh-HK', 'zh-TW',
    ]);

    /**
     * Laravel to Stripe locale mapping.
     */
    define('STRIPE_LOCALE_MAP', [
        'en' => 'en',
        'pt_BR' => 'pt-BR',
        'es' => 'es',
        'fr' => 'fr',
        'de' => 'de',
        'it' => 'it',
        'ja' => 'ja',
        'ko' => 'ko',
        'zh_CN' => 'zh',
        'zh_TW' => 'zh-TW',
    ]);

    /**
     * Get Stripe-compatible locale from Laravel locale.
     *
     * Converts Laravel locale format (e.g., 'pt_BR') to Stripe format (e.g., 'pt-BR').
     * Falls back to 'auto' if locale is not supported by Stripe.
     *
     * @param  string|null  $laravelLocale  Laravel locale (defaults to app locale)
     * @return string Stripe-compatible locale
     */
    function stripe_locale(?string $laravelLocale = null): string
    {
        $locale = $laravelLocale ?? app()->getLocale();

        // Check direct mapping first
        if (isset(STRIPE_LOCALE_MAP[$locale])) {
            return STRIPE_LOCALE_MAP[$locale];
        }

        // Try converting underscore to hyphen (e.g., pt_BR -> pt-BR)
        $stripeLocale = str_replace('_', '-', $locale);
        if (in_array($stripeLocale, STRIPE_SUPPORTED_LOCALES)) {
            return $stripeLocale;
        }

        // Fallback to base language (e.g., pt_BR -> pt)
        $baseLocale = explode('_', $locale)[0];
        if (in_array($baseLocale, STRIPE_SUPPORTED_LOCALES)) {
            return $baseLocale;
        }

        // Default to auto-detection by Stripe
        return 'auto';
    }
}

if (! function_exists('stripe_supported_locales')) {
    /**
     * Get list of Stripe supported locales.
     *
     * @return array<string>
     */
    function stripe_supported_locales(): array
    {
        return STRIPE_SUPPORTED_LOCALES;
    }
}

if (! function_exists('stripe_currency')) {
    /**
     * Get the configured Stripe currency.
     *
     * @return string Currency code (e.g., 'brl', 'usd')
     */
    function stripe_currency(): string
    {
        return strtolower(config('cashier.currency', 'usd'));
    }
}

if (! function_exists('stripe_currency_symbol')) {
    /**
     * Currency symbols mapping.
     */
    define('CURRENCY_SYMBOLS', [
        'usd' => '$',
        'brl' => 'R$',
        'eur' => '€',
        'gbp' => '£',
        'jpy' => '¥',
        'cny' => '¥',
        'krw' => '₩',
        'inr' => '₹',
        'mxn' => '$',
        'cad' => 'C$',
        'aud' => 'A$',
        'chf' => 'CHF',
        'ars' => '$',
        'clp' => '$',
        'cop' => '$',
        'pen' => 'S/',
    ]);

    /**
     * Get currency symbol for the configured currency.
     *
     * @param  string|null  $currency  Currency code (defaults to configured currency)
     * @return string Currency symbol
     */
    function stripe_currency_symbol(?string $currency = null): string
    {
        $currency = strtolower($currency ?? stripe_currency());

        return CURRENCY_SYMBOLS[$currency] ?? strtoupper($currency);
    }
}

if (! function_exists('format_stripe_price')) {
    /**
     * Format a price in cents to a human-readable string.
     *
     * @param  int  $cents  Price in cents
     * @param  string|null  $currency  Currency code (defaults to configured currency)
     * @param  string|null  $locale  Locale for formatting (defaults to app locale)
     * @return string Formatted price (e.g., "R$ 29,90" or "$29.90")
     */
    function format_stripe_price(int $cents, ?string $currency = null, ?string $locale = null): string
    {
        $currency = strtoupper($currency ?? stripe_currency());
        $locale = $locale ?? config('cashier.currency_locale', 'en');

        // Convert underscore to hyphen for NumberFormatter (e.g., pt_BR -> pt-BR)
        $formatterLocale = str_replace('_', '-', $locale);

        if (class_exists(\NumberFormatter::class)) {
            $formatter = new \NumberFormatter($formatterLocale, \NumberFormatter::CURRENCY);
            return $formatter->formatCurrency($cents / 100, $currency);
        }

        // Fallback if intl extension is not available
        $symbol = stripe_currency_symbol($currency);
        $amount = number_format($cents / 100, 2, '.', ',');

        return "{$symbol}{$amount}";
    }
}

if (! function_exists('stripe_currency_config')) {
    /**
     * Get currency configuration for frontend.
     *
     * @return array{code: string, symbol: string, locale: string}
     */
    function stripe_currency_config(): array
    {
        return [
            'code' => stripe_currency(),
            'symbol' => stripe_currency_symbol(),
            'locale' => config('cashier.currency_locale', 'en'),
        ];
    }
}
