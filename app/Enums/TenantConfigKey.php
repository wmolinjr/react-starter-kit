<?php

namespace App\Enums;

/**
 * Tenant-specific configuration keys.
 *
 * Single source of truth for tenant configuration keys with translations.
 * Maps tenant settings to Laravel config keys via TenantConfigBootstrapper.
 * Settings are stored in tenant.settings JSON column under 'config' key.
 *
 * @see \Stancl\Tenancy\Bootstrappers\TenantConfigBootstrapper
 */
enum TenantConfigKey: string
{
    // Branding
    case APP_NAME = 'app_name';

    // Localization
    case LOCALE = 'locale';
    case TIMEZONE = 'timezone';
    case DATE_FORMAT = 'date_format';
    case TIME_FORMAT = 'time_format';
    case WEEK_STARTS_ON = 'week_starts_on';

    // Email
    case MAIL_FROM_ADDRESS = 'mail_from_address';
    case MAIL_FROM_NAME = 'mail_from_name';

    // Payments
    case CURRENCY = 'currency';
    case CURRENCY_LOCALE = 'currency_locale';

    /**
     * Get translatable name.
     *
     * @return array<string, string>
     */
    public function name(): array
    {
        return match ($this) {
            self::APP_NAME => ['en' => 'Application Name', 'pt_BR' => 'Nome da Aplicação', 'es' => 'Nombre de la Aplicación'],
            self::LOCALE => ['en' => 'Language', 'pt_BR' => 'Idioma', 'es' => 'Idioma'],
            self::TIMEZONE => ['en' => 'Timezone', 'pt_BR' => 'Fuso Horário', 'es' => 'Zona Horaria'],
            self::DATE_FORMAT => ['en' => 'Date Format', 'pt_BR' => 'Formato de Data', 'es' => 'Formato de Fecha'],
            self::TIME_FORMAT => ['en' => 'Time Format', 'pt_BR' => 'Formato de Hora', 'es' => 'Formato de Hora'],
            self::WEEK_STARTS_ON => ['en' => 'Week Starts On', 'pt_BR' => 'Semana Começa em', 'es' => 'Semana Comienza en'],
            self::MAIL_FROM_ADDRESS => ['en' => 'From Email', 'pt_BR' => 'E-mail de Origem', 'es' => 'Correo de Origen'],
            self::MAIL_FROM_NAME => ['en' => 'From Name', 'pt_BR' => 'Nome de Origem', 'es' => 'Nombre de Origen'],
            self::CURRENCY => ['en' => 'Currency', 'pt_BR' => 'Moeda', 'es' => 'Moneda'],
            self::CURRENCY_LOCALE => ['en' => 'Currency Locale', 'pt_BR' => 'Localidade da Moeda', 'es' => 'Configuración Regional de Moneda'],
        };
    }

    /**
     * Get translatable description.
     *
     * @return array<string, string>
     */
    public function description(): array
    {
        return match ($this) {
            self::APP_NAME => [
                'en' => 'Custom name displayed to your users',
                'pt_BR' => 'Nome personalizado exibido aos seus usuários',
                'es' => 'Nombre personalizado mostrado a sus usuarios',
            ],
            self::LOCALE => [
                'en' => 'Default language for your workspace',
                'pt_BR' => 'Idioma padrão para o seu espaço de trabalho',
                'es' => 'Idioma predeterminado para su espacio de trabajo',
            ],
            self::TIMEZONE => [
                'en' => 'Timezone for dates and times',
                'pt_BR' => 'Fuso horário para datas e horas',
                'es' => 'Zona horaria para fechas y horas',
            ],
            self::DATE_FORMAT => [
                'en' => 'How dates are displayed throughout the application',
                'pt_BR' => 'Como as datas são exibidas em toda a aplicação',
                'es' => 'Cómo se muestran las fechas en toda la aplicación',
            ],
            self::TIME_FORMAT => [
                'en' => 'How times are displayed (12-hour or 24-hour)',
                'pt_BR' => 'Como os horários são exibidos (12 ou 24 horas)',
                'es' => 'Cómo se muestran las horas (12 o 24 horas)',
            ],
            self::WEEK_STARTS_ON => [
                'en' => 'First day of the week in calendars',
                'pt_BR' => 'Primeiro dia da semana nos calendários',
                'es' => 'Primer día de la semana en los calendarios',
            ],
            self::MAIL_FROM_ADDRESS => [
                'en' => 'Email address used for sending notifications',
                'pt_BR' => 'Endereço de e-mail usado para enviar notificações',
                'es' => 'Dirección de correo electrónico usada para enviar notificaciones',
            ],
            self::MAIL_FROM_NAME => [
                'en' => 'Name displayed in email sender field',
                'pt_BR' => 'Nome exibido no campo de remetente do e-mail',
                'es' => 'Nombre mostrado en el campo de remitente del correo',
            ],
            self::CURRENCY => [
                'en' => 'Default currency for billing',
                'pt_BR' => 'Moeda padrão para faturamento',
                'es' => 'Moneda predeterminada para facturación',
            ],
            self::CURRENCY_LOCALE => [
                'en' => 'Locale for currency formatting',
                'pt_BR' => 'Localidade para formatação de moeda',
                'es' => 'Configuración regional para formato de moneda',
            ],
        };
    }

    /**
     * Get Lucide icon name.
     */
    public function icon(): string
    {
        return match ($this) {
            self::APP_NAME => 'Type',
            self::LOCALE => 'Globe',
            self::TIMEZONE => 'Clock',
            self::DATE_FORMAT => 'Calendar',
            self::TIME_FORMAT => 'Clock3',
            self::WEEK_STARTS_ON => 'CalendarDays',
            self::MAIL_FROM_ADDRESS => 'Mail',
            self::MAIL_FROM_NAME => 'User',
            self::CURRENCY => 'DollarSign',
            self::CURRENCY_LOCALE => 'Languages',
        };
    }

    /**
     * Get color for UI display.
     */
    public function color(): string
    {
        return match ($this->category()) {
            'branding' => 'purple',
            'localization' => 'blue',
            'email' => 'orange',
            'payments' => 'green',
            default => 'gray',
        };
    }

    /**
     * Get badge variant for UI display.
     */
    public function badgeVariant(): string
    {
        return match ($this->category()) {
            'branding' => 'default',
            'localization' => 'secondary',
            'email' => 'outline',
            'payments' => 'default',
            default => 'outline',
        };
    }

    /**
     * Get the category for this config key.
     */
    public function category(): string
    {
        return match ($this) {
            self::APP_NAME => 'branding',
            self::LOCALE, self::TIMEZONE, self::DATE_FORMAT, self::TIME_FORMAT, self::WEEK_STARTS_ON => 'localization',
            self::MAIL_FROM_ADDRESS, self::MAIL_FROM_NAME => 'email',
            self::CURRENCY, self::CURRENCY_LOCALE => 'payments',
        };
    }

    /**
     * Get translated label for current locale.
     */
    public function label(?string $locale = null): string
    {
        $locale = $locale ?? app()->getLocale();
        $names = $this->name();

        return $names[$locale] ?? $names['en'] ?? $this->value;
    }

    /**
     * Get translated description for current locale.
     */
    public function translatedDescription(?string $locale = null): string
    {
        $locale = $locale ?? app()->getLocale();
        $descriptions = $this->description();

        return $descriptions[$locale] ?? $descriptions['en'];
    }

    /**
     * Get the Laravel config key(s) this maps to.
     *
     * @return array<string>
     */
    public function configKeys(): array
    {
        return match ($this) {
            self::APP_NAME => ['app.name'],
            self::LOCALE => ['app.locale'],
            self::TIMEZONE => ['app.timezone'],
            self::DATE_FORMAT => ['app.date_format'],
            self::TIME_FORMAT => ['app.time_format'],
            self::WEEK_STARTS_ON => ['app.week_starts_on'],
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
        return 'config.'.$this->value;
    }

    /**
     * Get default value for this config key.
     */
    public function defaultValue(): mixed
    {
        return match ($this) {
            self::APP_NAME => null, // Falls back to config('app.name')
            self::LOCALE => 'en',
            self::TIMEZONE => 'UTC',
            self::DATE_FORMAT => 'dd/MM/yyyy',
            self::TIME_FORMAT => '24h',
            self::WEEK_STARTS_ON => 0, // 0 = Sunday, 1 = Monday, etc.
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
            self::APP_NAME => ['nullable', 'string', 'max:100'],
            self::LOCALE => ['string', 'in:'.implode(',', config('app.locales', ['en']))],
            self::TIMEZONE => ['string', 'timezone'],
            self::DATE_FORMAT => ['string', 'in:'.implode(',', array_keys(self::availableDateFormats()))],
            self::TIME_FORMAT => ['string', 'in:12h,24h'],
            self::WEEK_STARTS_ON => ['integer', 'min:0', 'max:6'],
            self::MAIL_FROM_ADDRESS => ['nullable', 'email', 'max:255'],
            self::MAIL_FROM_NAME => ['nullable', 'string', 'max:100'],
            self::CURRENCY => ['string', 'size:3', 'lowercase'],
            self::CURRENCY_LOCALE => ['string', 'max:10'],
        };
    }

    /**
     * Get all config key values as strings.
     *
     * @return string[]
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Get all cases as options for select inputs.
     *
     * @return array<string, string>
     */
    public static function options(?string $locale = null): array
    {
        $options = [];
        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label($locale);
        }

        return $options;
    }

    /**
     * Convert single config key to frontend format.
     *
     * @return array<string, mixed>
     */
    public function toFrontend(?string $locale = null): array
    {
        return [
            'value' => $this->value,
            'label' => $this->label($locale),
            'description' => $this->translatedDescription($locale),
            'icon' => $this->icon(),
            'color' => $this->color(),
            'badge_variant' => $this->badgeVariant(),
            'category' => $this->category(),
            'default_value' => $this->defaultValue(),
        ];
    }

    /**
     * Convert all config keys to frontend array format.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function toFrontendArray(?string $locale = null): array
    {
        return array_map(
            fn (self $key) => $key->toFrontend($locale),
            self::cases()
        );
    }

    /**
     * Convert all cases to frontend map format (keyed by value).
     *
     * @return array<string, array<string, mixed>>
     */
    public static function toFrontendMap(?string $locale = null): array
    {
        $map = [];
        foreach (self::cases() as $case) {
            $map[$case->value] = $case->toFrontend($locale);
        }

        return $map;
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
     * Get all unique categories with their descriptions.
     *
     * @return array<string, array{en: string, pt_BR: string}>
     */
    public static function categories(): array
    {
        return [
            'branding' => ['en' => 'Branding', 'pt_BR' => 'Marca', 'es' => 'Marca'],
            'localization' => ['en' => 'Localization', 'pt_BR' => 'Localização', 'es' => 'Localización'],
            'email' => ['en' => 'Email', 'pt_BR' => 'E-mail', 'es' => 'Correo Electrónico'],
            'payments' => ['en' => 'Payments', 'pt_BR' => 'Pagamentos', 'es' => 'Pagos'],
        ];
    }

    /**
     * Get translated category name.
     */
    public static function categoryTrans(string $category, ?string $locale = null): string
    {
        $locale = $locale ?? app()->getLocale();
        $categories = self::categories();

        return $categories[$category][$locale] ?? $categories[$category]['en'] ?? ucfirst($category);
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

    /**
     * Get all available date formats with examples.
     *
     * Format keys use date-fns format tokens.
     *
     * @return array<string, array{label: array<string, string>, example: string}>
     */
    public static function availableDateFormats(): array
    {
        return [
            'dd/MM/yyyy' => [
                'label' => ['en' => 'Day/Month/Year', 'pt_BR' => 'Dia/Mês/Ano', 'es' => 'Día/Mes/Año'],
                'example' => ['en' => '31/12/2024', 'pt_BR' => '31/12/2024', 'es' => '31/12/2024'],
            ],
            'MM/dd/yyyy' => [
                'label' => ['en' => 'Month/Day/Year', 'pt_BR' => 'Mês/Dia/Ano', 'es' => 'Mes/Día/Año'],
                'example' => ['en' => '12/31/2024', 'pt_BR' => '12/31/2024', 'es' => '12/31/2024'],
            ],
            'yyyy-MM-dd' => [
                'label' => ['en' => 'Year-Month-Day (ISO)', 'pt_BR' => 'Ano-Mês-Dia (ISO)', 'es' => 'Año-Mes-Día (ISO)'],
                'example' => ['en' => '2024-12-31', 'pt_BR' => '2024-12-31', 'es' => '2024-12-31'],
            ],
            'dd-MM-yyyy' => [
                'label' => ['en' => 'Day-Month-Year', 'pt_BR' => 'Dia-Mês-Ano', 'es' => 'Día-Mes-Año'],
                'example' => ['en' => '31-12-2024', 'pt_BR' => '31-12-2024', 'es' => '31-12-2024'],
            ],
            'dd.MM.yyyy' => [
                'label' => ['en' => 'Day.Month.Year', 'pt_BR' => 'Dia.Mês.Ano', 'es' => 'Día.Mes.Año'],
                'example' => ['en' => '31.12.2024', 'pt_BR' => '31.12.2024', 'es' => '31.12.2024'],
            ],
            'MMM dd, yyyy' => [
                'label' => ['en' => 'Abbrev. Month Day, Year', 'pt_BR' => 'Mês Abrev. Dia, Ano', 'es' => 'Mes Abrev. Día, Año'],
                'example' => ['en' => 'Dec 31, 2024', 'pt_BR' => 'dez 31, 2024', 'es' => 'dic 31, 2024'],
            ],
            'dd MMM yyyy' => [
                'label' => ['en' => 'Day Abbrev. Month Year', 'pt_BR' => 'Dia Mês Abrev. Ano', 'es' => 'Día Mes Abrev. Año'],
                'example' => ['en' => '31 Dec 2024', 'pt_BR' => '31 dez 2024', 'es' => '31 dic 2024'],
            ],
            "d 'de' MMMM 'de' yyyy" => [
                'label' => ['en' => 'Day of Month of Year', 'pt_BR' => 'Dia de Mês de Ano', 'es' => 'Día de Mes de Año'],
                'example' => ['en' => '31 of December of 2024', 'pt_BR' => '31 de dezembro de 2024', 'es' => '31 de diciembre de 2024'],
            ],
            'MMMM d, yyyy' => [
                'label' => ['en' => 'Full Month Day, Year', 'pt_BR' => 'Mês Completo Dia, Ano', 'es' => 'Mes Completo Día, Año'],
                'example' => ['en' => 'December 31, 2024', 'pt_BR' => 'dezembro 31, 2024', 'es' => 'diciembre 31, 2024'],
            ],
            'd MMMM yyyy' => [
                'label' => ['en' => 'Day Full Month Year', 'pt_BR' => 'Dia Mês Completo Ano', 'es' => 'Día Mes Completo Año'],
                'example' => ['en' => '31 December 2024', 'pt_BR' => '31 dezembro 2024', 'es' => '31 diciembre 2024'],
            ],
        ];
    }

    /**
     * Get date formats for frontend select.
     *
     * @return array<string, string>
     */
    public static function dateFormatOptions(?string $locale = null): array
    {
        $locale = $locale ?? app()->getLocale();
        $options = [];

        foreach (self::availableDateFormats() as $format => $data) {
            $label = $data['label'][$locale] ?? $data['label']['en'];
            $example = is_array($data['example'])
                ? ($data['example'][$locale] ?? $data['example']['en'])
                : $data['example'];
            $options[$format] = $label.' ('.$example.')';
        }

        return $options;
    }

    /**
     * Get all available time formats.
     *
     * @return array<string, array<string, string>>
     */
    public static function availableTimeFormats(): array
    {
        return [
            '24h' => ['en' => '24-hour (14:30)', 'pt_BR' => '24 horas (14:30)', 'es' => '24 horas (14:30)'],
            '12h' => ['en' => '12-hour (2:30 PM)', 'pt_BR' => '12 horas (2:30 PM)', 'es' => '12 horas (2:30 PM)'],
        ];
    }

    /**
     * Get time formats for frontend select.
     *
     * @return array<string, string>
     */
    public static function timeFormatOptions(?string $locale = null): array
    {
        $locale = $locale ?? app()->getLocale();
        $options = [];

        foreach (self::availableTimeFormats() as $format => $labels) {
            $options[$format] = $labels[$locale] ?? $labels['en'];
        }

        return $options;
    }

    /**
     * Get weekday names for week starts on selector.
     *
     * @return array<int, array<string, string>>
     */
    public static function availableWeekdays(): array
    {
        return [
            0 => ['en' => 'Sunday', 'pt_BR' => 'Domingo', 'es' => 'Domingo'],
            1 => ['en' => 'Monday', 'pt_BR' => 'Segunda-feira', 'es' => 'Lunes'],
            2 => ['en' => 'Tuesday', 'pt_BR' => 'Terça-feira', 'es' => 'Martes'],
            3 => ['en' => 'Wednesday', 'pt_BR' => 'Quarta-feira', 'es' => 'Miércoles'],
            4 => ['en' => 'Thursday', 'pt_BR' => 'Quinta-feira', 'es' => 'Jueves'],
            5 => ['en' => 'Friday', 'pt_BR' => 'Sexta-feira', 'es' => 'Viernes'],
            6 => ['en' => 'Saturday', 'pt_BR' => 'Sábado', 'es' => 'Sábado'],
        ];
    }

    /**
     * Get weekdays for frontend select.
     *
     * @return array<int, string>
     */
    public static function weekdayOptions(?string $locale = null): array
    {
        $locale = $locale ?? app()->getLocale();
        $options = [];

        foreach (self::availableWeekdays() as $day => $labels) {
            $options[$day] = $labels[$locale] ?? $labels['en'];
        }

        return $options;
    }
}
