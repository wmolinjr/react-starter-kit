<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class StripeLocaleTest extends TestCase
{
    #[Test]
    public function converts_english_locale(): void
    {
        $this->assertEquals('en', stripe_locale('en'));
    }

    #[Test]
    public function converts_portuguese_brazil_locale(): void
    {
        $this->assertEquals('pt-BR', stripe_locale('pt_BR'));
    }

    #[Test]
    public function converts_spanish_locale(): void
    {
        $this->assertEquals('es', stripe_locale('es'));
    }

    #[Test]
    public function converts_french_locale(): void
    {
        $this->assertEquals('fr', stripe_locale('fr'));
    }

    #[Test]
    public function falls_back_to_auto_for_unsupported_locale(): void
    {
        $this->assertEquals('auto', stripe_locale('xx_XX'));
    }

    #[Test]
    public function falls_back_to_base_locale_when_variant_not_supported(): void
    {
        // If fr_CA were not mapped, it should fall back to 'fr'
        // But fr_CA is actually supported by Stripe (fr-CA)
        // Let's test with a locale that has no variant support
        $this->assertEquals('de', stripe_locale('de_AT')); // German Austria -> falls back to 'de'
    }

    #[Test]
    public function uses_current_app_locale_when_null(): void
    {
        app()->setLocale('pt_BR');
        $this->assertEquals('pt-BR', stripe_locale());

        app()->setLocale('en');
        $this->assertEquals('en', stripe_locale());
    }

    #[Test]
    public function stripe_supported_locales_returns_array(): void
    {
        $locales = stripe_supported_locales();

        $this->assertIsArray($locales);
        $this->assertContains('en', $locales);
        $this->assertContains('pt-BR', $locales);
        $this->assertContains('auto', $locales);
    }

    #[Test]
    public function converts_underscore_to_hyphen_for_direct_match(): void
    {
        // zh_TW -> zh-TW (direct conversion)
        $this->assertEquals('zh-TW', stripe_locale('zh_TW'));
    }

    #[Test]
    public function handles_chinese_simplified(): void
    {
        $this->assertEquals('zh', stripe_locale('zh_CN'));
    }
}
