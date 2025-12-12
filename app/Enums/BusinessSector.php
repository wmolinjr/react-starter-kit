<?php

namespace App\Enums;

/**
 * Business Sector Enum
 *
 * Defines the business sectors/industries available for tenant selection.
 * Used during signup for workspace categorization and analytics.
 */
enum BusinessSector: string
{
    case ECOMMERCE = 'ecommerce';
    case SAAS = 'saas';
    case HEALTHCARE = 'healthcare';
    case EDUCATION = 'education';
    case FINANCE = 'finance';
    case REAL_ESTATE = 'real_estate';
    case CONSULTING = 'consulting';
    case MARKETING = 'marketing';
    case LEGAL = 'legal';
    case TECHNOLOGY = 'technology';
    case MANUFACTURING = 'manufacturing';
    case RETAIL = 'retail';
    case HOSPITALITY = 'hospitality';
    case NONPROFIT = 'nonprofit';
    case OTHER = 'other';

    /**
     * Get translated label for the sector.
     *
     * @return array<string, string>
     */
    public function label(): array
    {
        return match ($this) {
            self::ECOMMERCE => [
                'en' => 'E-commerce',
                'pt_BR' => 'E-commerce',
                'es' => 'Comercio Electrónico',
            ],
            self::SAAS => [
                'en' => 'Software as a Service (SaaS)',
                'pt_BR' => 'Software como Serviço (SaaS)',
                'es' => 'Software como Servicio (SaaS)',
            ],
            self::HEALTHCARE => [
                'en' => 'Healthcare',
                'pt_BR' => 'Saúde',
                'es' => 'Salud',
            ],
            self::EDUCATION => [
                'en' => 'Education',
                'pt_BR' => 'Educação',
                'es' => 'Educación',
            ],
            self::FINANCE => [
                'en' => 'Finance & Banking',
                'pt_BR' => 'Finanças e Bancos',
                'es' => 'Finanzas y Banca',
            ],
            self::REAL_ESTATE => [
                'en' => 'Real Estate',
                'pt_BR' => 'Imobiliário',
                'es' => 'Bienes Raíces',
            ],
            self::CONSULTING => [
                'en' => 'Consulting',
                'pt_BR' => 'Consultoria',
                'es' => 'Consultoría',
            ],
            self::MARKETING => [
                'en' => 'Marketing & Advertising',
                'pt_BR' => 'Marketing e Publicidade',
                'es' => 'Marketing y Publicidad',
            ],
            self::LEGAL => [
                'en' => 'Legal Services',
                'pt_BR' => 'Serviços Jurídicos',
                'es' => 'Servicios Legales',
            ],
            self::TECHNOLOGY => [
                'en' => 'Technology',
                'pt_BR' => 'Tecnologia',
                'es' => 'Tecnología',
            ],
            self::MANUFACTURING => [
                'en' => 'Manufacturing',
                'pt_BR' => 'Manufatura',
                'es' => 'Manufactura',
            ],
            self::RETAIL => [
                'en' => 'Retail',
                'pt_BR' => 'Varejo',
                'es' => 'Retail',
            ],
            self::HOSPITALITY => [
                'en' => 'Hospitality & Tourism',
                'pt_BR' => 'Hotelaria e Turismo',
                'es' => 'Hostelería y Turismo',
            ],
            self::NONPROFIT => [
                'en' => 'Non-Profit',
                'pt_BR' => 'Organização Sem Fins Lucrativos',
                'es' => 'Organización Sin Fines de Lucro',
            ],
            self::OTHER => [
                'en' => 'Other',
                'pt_BR' => 'Outro',
                'es' => 'Otro',
            ],
        };
    }

    /**
     * Get translated label for the current locale.
     */
    public function localizedLabel(): string
    {
        $locale = app()->getLocale();
        $labels = $this->label();

        return $labels[$locale] ?? $labels['en'];
    }

    /**
     * Get all sectors as array for select options.
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];
        foreach (self::cases() as $case) {
            $options[$case->value] = $case->localizedLabel();
        }

        return $options;
    }

    /**
     * Get all sectors with translations for frontend.
     *
     * @return array<int, array{value: string, label: array<string, string>}>
     */
    public static function toArray(): array
    {
        return array_map(
            fn (self $case) => [
                'value' => $case->value,
                'label' => $case->label(),
            ],
            self::cases()
        );
    }
}
