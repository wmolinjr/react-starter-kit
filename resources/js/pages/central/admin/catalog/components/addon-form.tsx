import { useForm } from '@inertiajs/react';
import { useMemo, useEffect } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Switch } from '@/components/ui/switch';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import { TranslatableInput, Translations } from '@/components/central/forms/translatable-input';
import { useLocales } from '@/hooks/shared/use-locales';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { DynamicIcon } from '@/components/shared/icons/dynamic-icon';
import { BadgeSelector } from '@/components/central/forms/badge-selector';
import { IconSelector } from '@/components/central/forms/icon-selector';
import { ColorSelector } from '@/components/central/forms/color-selector';
import { TrendingUp, Sparkles, CreditCard, Info } from 'lucide-react';
import type { AddonType, BadgePreset } from '@/types/enums';
import { ADDON_TYPE } from '@/lib/enum-metadata';

// Feature definition from backend
interface FeatureDefinition {
    id: string;
    key: string;
    name: string;
    description: string | null;
    category: string | null;
    icon: string | null;
}

// Limit definition from backend
interface LimitDefinition {
    id: string;
    key: string;
    name: string;
    description: string | null;
    unit: string | null;
    unit_label: string | null;
    default_value: number;
    allows_unlimited: boolean;
    icon: string | null;
}

// Category option
interface CategoryOption {
    value: string;
    label: string;
}

// Extended type info from backend
interface AddonTypeInfo {
    value: string;
    label: string;
    description?: string;
    icon?: string;
    color?: string;
    is_stackable?: boolean;
    is_recurring?: boolean;
    is_one_time?: boolean;
    has_validity?: boolean;
}

// Input type for addon prop
interface AddonInput {
    id?: string;
    slug?: string;
    name?: Translations;
    description?: Translations | null;
    type?: string;
    active?: boolean;
    sort_order?: number;
    limit_key?: string | null;
    unit_value?: number | string | null;
    unit_label?: Translations | null;
    min_quantity?: number;
    max_quantity?: number | string | null;
    stackable?: boolean;
    price_monthly?: number | string | null;
    price_yearly?: number | string | null;
    price_one_time?: number | string | null;
    validity_months?: number | string | null;
    icon?: string | null;
    icon_color?: string | null;
    badge?: BadgePreset | null;
    plan_ids?: string[];
    features?: Record<string, boolean>;
}

interface Props {
    addon?: AddonInput;
    types: AddonTypeInfo[];
    plans: { id: string; name: string; slug: string }[];
    featureDefinitions?: FeatureDefinition[];
    limitDefinitions?: LimitDefinition[];
    categories?: CategoryOption[];
    isEdit?: boolean;
}

/**
 * Get Tailwind border/bg classes from enum color name.
 * Uses ADDON_TYPE metadata color as the source.
 */
const getTypeColorClasses = (addonType: string): string => {
    const metadata = ADDON_TYPE[addonType as AddonType];
    const color = metadata?.color ?? 'gray';
    const colorMap: Record<string, string> = {
        blue: 'border-blue-500 bg-blue-50 dark:bg-blue-950',
        purple: 'border-purple-500 bg-purple-50 dark:bg-purple-950',
        orange: 'border-orange-500 bg-orange-50 dark:bg-orange-950',
        green: 'border-green-500 bg-green-50 dark:bg-green-950',
        gray: 'border-gray-500 bg-gray-50 dark:bg-gray-950',
    };
    return colorMap[color] ?? colorMap.gray;
};

export function AddonForm({ addon, types, plans, featureDefinitions = [], limitDefinitions = [], categories = [], isEdit = false }: Props) {
    const { t } = useLaravelReactI18n();
    const { ensureTranslations } = useLocales();

    // Build categoryLabels from props
    const categoryLabels = useMemo(() => {
        return categories.reduce((acc, cat) => ({ ...acc, [cat.value]: cat.label }), {} as Record<string, string>);
    }, [categories]);

    // Group features by category
    const featuresByCategory = useMemo(() => {
        return featureDefinitions.reduce(
            (acc, feature) => {
                const category = feature.category || 'other';
                if (!acc[category]) acc[category] = [];
                acc[category].push(feature);
                return acc;
            },
            {} as Record<string, FeatureDefinition[]>
        );
    }, [featureDefinitions]);

    const { data, setData, post, put, processing, errors } = useForm({
        slug: addon?.slug || '',
        name: ensureTranslations(addon?.name),
        description: ensureTranslations(addon?.description ?? undefined),
        type: addon?.type || 'quota',
        active: addon?.active ?? true,
        sort_order: addon?.sort_order || 0,
        limit_key: addon?.limit_key || '',
        unit_value: addon?.unit_value?.toString() || '',
        unit_label: ensureTranslations(addon?.unit_label ?? undefined),
        min_quantity: addon?.min_quantity || 1,
        max_quantity: addon?.max_quantity?.toString() || '',
        stackable: addon?.stackable ?? true,
        price_monthly: addon?.price_monthly?.toString() || '',
        price_yearly: addon?.price_yearly?.toString() || '',
        price_one_time: addon?.price_one_time?.toString() || '',
        validity_months: addon?.validity_months?.toString() || '',
        icon: addon?.icon || 'Package',
        icon_color: addon?.icon_color ?? null,
        badge: addon?.badge ?? null,
        plan_ids: addon?.plan_ids || [] as string[],
        features: addon?.features ?? {} as Record<string, boolean>,
    });

    // Determine which sections to show based on type
    const isQuotaType = data.type === 'quota';
    const isFeatureType = data.type === 'feature';
    const isMeteredType = data.type === 'metered';
    const isCreditType = data.type === 'credit';

    const showLimitSection = isQuotaType || isMeteredType || isCreditType;
    const showFeaturesSection = isFeatureType;
    const showRecurringPricing = isQuotaType || isFeatureType || isMeteredType;
    const showOneTimePricing = isCreditType;
    const showQuantitySection = isQuotaType || isMeteredType || isCreditType;

    // Reset irrelevant fields when type changes
    useEffect(() => {
        if (isFeatureType) {
            // Feature type: clear limit fields, set stackable to false
            setData(prev => ({
                ...prev,
                limit_key: '',
                unit_value: '',
                stackable: false,
                min_quantity: 1,
                max_quantity: '1',
            }));
        } else if (isCreditType) {
            // Credit type: clear recurring pricing
            setData(prev => ({
                ...prev,
                price_monthly: '',
                price_yearly: '',
                features: {},
            }));
        } else if (isQuotaType || isMeteredType) {
            // Quota/Metered: clear features, set stackable to true
            setData(prev => ({
                ...prev,
                features: {},
                stackable: true,
                price_one_time: '',
                validity_months: '',
            }));
        }
    // Only run when type changes, not on every render
    // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [data.type]);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (isEdit && addon?.id) {
            put(`/admin/catalog/${addon.id}`);
        } else {
            post('/admin/catalog');
        }
    };

    const togglePlan = (planId: string) => {
        setData('plan_ids',
            data.plan_ids.includes(planId)
                ? data.plan_ids.filter(id => id !== planId)
                : [...data.plan_ids, planId]
        );
    };

    const toggleFeature = (featureKey: string) => {
        setData('features', {
            ...data.features,
            [featureKey]: !data.features[featureKey],
        });
    };

    // Find the selected limit definition for display
    const selectedLimit = limitDefinitions.find(l => l.key === data.limit_key);

    return (
        <form onSubmit={handleSubmit} className="space-y-6">
            {/* Type Selection - Most Important */}
            <Card className="border-2">
                <CardHeader>
                    <CardTitle>{t('admin.catalog.form.type')}</CardTitle>
                    <CardDescription>
                        {t('admin.catalog.form.type_description')}
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <div className="grid grid-cols-2 gap-4 md:grid-cols-4">
                        {types.map(type => {
                            const metadata = ADDON_TYPE[type.value as AddonType];
                            const isSelected = data.type === type.value;
                            return (
                                <button
                                    key={type.value}
                                    type="button"
                                    onClick={() => setData('type', type.value)}
                                    className={`flex flex-col items-center gap-2 rounded-lg border-2 p-4 text-center transition-all hover:shadow-md ${
                                        isSelected
                                            ? `${getTypeColorClasses(type.value)} border-current`
                                            : 'border-muted hover:border-muted-foreground/50'
                                    }`}
                                >
                                    <DynamicIcon
                                        name={metadata?.icon ?? 'Info'}
                                        className={`h-8 w-8 ${isSelected ? '' : 'text-muted-foreground'}`}
                                    />
                                    <span className="font-medium">{type.label}</span>
                                    {type.description && (
                                        <span className="text-xs text-muted-foreground line-clamp-2">
                                            {type.description}
                                        </span>
                                    )}
                                </button>
                            );
                        })}
                    </div>
                    {errors.type && <p className="mt-2 text-sm text-red-500">{errors.type}</p>}
                </CardContent>
            </Card>

            {/* Type-specific guidance */}
            <Alert>
                <Info className="h-4 w-4" />
                <AlertDescription>
                    {isQuotaType && t('admin.catalog.form.type_hint_quota')}
                    {isFeatureType && t('admin.catalog.form.type_hint_feature')}
                    {isMeteredType && t('admin.catalog.form.type_hint_metered')}
                    {isCreditType && t('admin.catalog.form.type_hint_credit')}
                </AlertDescription>
            </Alert>

            {/* Basic Info */}
            <Card>
                <CardHeader>
                    <CardTitle>{t('common.basic_info')}</CardTitle>
                </CardHeader>
                <CardContent className="space-y-4">
                    <div className="space-y-2">
                        <Label htmlFor="slug">Slug</Label>
                        <Input
                            id="slug"
                            value={data.slug}
                            onChange={e => setData('slug', e.target.value)}
                            disabled={isEdit}
                            placeholder={isQuotaType ? 'storage_50gb' : isFeatureType ? 'advanced_reports' : 'addon_slug'}
                            className="max-w-xs"
                        />
                        {errors.slug && <p className="text-sm text-red-500">{errors.slug}</p>}
                    </div>

                    <TranslatableInput
                        label={t('common.name')}
                        value={data.name}
                        onChange={v => setData('name', v)}
                        placeholder={{ en: 'Storage 50GB', pt_BR: 'Armazenamento 50GB' }}
                        required
                    />
                    {errors.name && <p className="text-sm text-red-500">{errors.name}</p>}

                    <TranslatableInput
                        label={t('common.description')}
                        value={data.description}
                        onChange={v => setData('description', v)}
                        placeholder={{
                            en: 'Add 50GB of additional storage to your plan',
                            pt_BR: 'Adicione 50GB de armazenamento extra ao seu plano',
                        }}
                        multiline
                    />
                </CardContent>
            </Card>

            {/* Pricing - Conditional based on type */}
            <Card>
                <CardHeader>
                    <CardTitle>{t('common.pricing')}</CardTitle>
                    <CardDescription>
                        {showRecurringPricing && t('admin.catalog.form.pricing_recurring_hint')}
                        {showOneTimePricing && t('admin.catalog.form.pricing_onetime_hint')}
                    </CardDescription>
                </CardHeader>
                <CardContent className="space-y-4">
                    {showRecurringPricing && (
                        <div className="grid grid-cols-2 gap-4">
                            <div className="space-y-2">
                                <Label htmlFor="price_monthly">{t('common.monthly')}</Label>
                                <Input
                                    id="price_monthly"
                                    type="number"
                                    value={data.price_monthly}
                                    onChange={e => setData('price_monthly', e.target.value)}
                                    placeholder="4900"
                                />
                                <p className="text-xs text-muted-foreground">{t('admin.catalog.form.price_in_cents')}</p>
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="price_yearly">{t('common.yearly')}</Label>
                                <Input
                                    id="price_yearly"
                                    type="number"
                                    value={data.price_yearly}
                                    onChange={e => setData('price_yearly', e.target.value)}
                                    placeholder="49000"
                                />
                                <p className="text-xs text-muted-foreground">{t('admin.catalog.form.price_in_cents')}</p>
                            </div>
                        </div>
                    )}
                    {showOneTimePricing && (
                        <div className="grid grid-cols-2 gap-4">
                            <div className="space-y-2">
                                <Label htmlFor="price_one_time">{t('common.one_time')}</Label>
                                <Input
                                    id="price_one_time"
                                    type="number"
                                    value={data.price_one_time}
                                    onChange={e => setData('price_one_time', e.target.value)}
                                    placeholder="7900"
                                />
                                <p className="text-xs text-muted-foreground">{t('admin.catalog.form.price_in_cents')}</p>
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="validity_months">{t('admin.catalog.form.validity_months')}</Label>
                                <Input
                                    id="validity_months"
                                    type="number"
                                    value={data.validity_months}
                                    onChange={e => setData('validity_months', e.target.value)}
                                    placeholder="12"
                                />
                            </div>
                        </div>
                    )}
                </CardContent>
            </Card>

            {/* Limit Increment - For QUOTA, METERED, and CREDIT types */}
            {showLimitSection && (
                <Card className={isCreditType ? 'border-green-200 dark:border-green-800' : 'border-blue-200 dark:border-blue-800'}>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            {isCreditType ? (
                                <CreditCard className="h-5 w-5 text-green-500" />
                            ) : (
                                <TrendingUp className="h-5 w-5 text-blue-500" />
                            )}
                            {isCreditType ? t('admin.catalog.form.credit_target') : t('admin.catalog.form.limit_increment')}
                        </CardTitle>
                        <CardDescription>
                            {isCreditType
                                ? t('admin.catalog.form.credit_target_description')
                                : t('admin.catalog.form.limit_increment_description')}
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <div className="grid grid-cols-2 gap-4">
                            <div className="space-y-2">
                                <Label htmlFor="limit_key">{t('admin.catalog.form.target_limit')}</Label>
                                <Select
                                    value={data.limit_key || '__none__'}
                                    onValueChange={v => setData('limit_key', v === '__none__' ? '' : v)}
                                >
                                    <SelectTrigger>
                                        <SelectValue placeholder={t('admin.catalog.form.select_limit')} />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="__none__">{t('admin.catalog.form.select_limit')}</SelectItem>
                                        {limitDefinitions.map(limit => (
                                            <SelectItem key={limit.key} value={limit.key}>
                                                <span className="flex items-center gap-2">
                                                    <DynamicIcon name={limit.icon} className="h-4 w-4" />
                                                    {limit.name}
                                                    {limit.unit_label && (
                                                        <span className="text-muted-foreground">({limit.unit_label})</span>
                                                    )}
                                                </span>
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                {selectedLimit?.description && (
                                    <p className="text-muted-foreground text-xs">{selectedLimit.description}</p>
                                )}
                                {!data.limit_key && (
                                    <p className="text-amber-600 text-xs">
                                        {isCreditType
                                            ? t('admin.catalog.form.credit_limit_required_warning')
                                            : t('admin.catalog.form.limit_required_warning')}
                                    </p>
                                )}
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="unit_value">{t('admin.catalog.form.increment_value')}</Label>
                                <Input
                                    id="unit_value"
                                    type="number"
                                    value={data.unit_value}
                                    onChange={e => setData('unit_value', e.target.value)}
                                    placeholder="50"
                                    disabled={!data.limit_key}
                                />
                                {selectedLimit && (
                                    <p className="text-muted-foreground text-xs">
                                        {t('admin.catalog.form.adds_to_limit', { limit: selectedLimit.name })}
                                    </p>
                                )}
                            </div>
                        </div>
                        <TranslatableInput
                            label={t('admin.catalog.form.unit_label')}
                            value={data.unit_label}
                            onChange={v => setData('unit_label', v)}
                            placeholder={{ en: 'GB', pt_BR: 'GB' }}
                        />
                    </CardContent>
                </Card>
            )}

            {/* Features - Only for FEATURE type */}
            {showFeaturesSection && featureDefinitions.length > 0 && (
                <Card className="border-purple-200 dark:border-purple-800">
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Sparkles className="h-5 w-5 text-purple-500" />
                            {t('admin.catalog.form.features_enabled')}
                        </CardTitle>
                        <CardDescription>
                            {t('admin.catalog.form.features_enabled_description')}
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-6">
                        {Object.keys(data.features).length === 0 && (
                            <p className="text-amber-600 text-sm">{t('admin.catalog.form.feature_required_warning')}</p>
                        )}
                        {Object.entries(featuresByCategory).map(([category, features]) => (
                            <div key={category} className="space-y-3">
                                <h4 className="text-muted-foreground text-sm font-medium">
                                    {categoryLabels[category] || category}
                                </h4>
                                <div className="grid grid-cols-2 gap-3">
                                    {features.map((feature) => (
                                        <div
                                            key={feature.key}
                                            className={`flex items-start gap-3 rounded-lg border p-3 transition-colors ${
                                                data.features[feature.key] ? 'border-purple-300 bg-purple-50 dark:border-purple-700 dark:bg-purple-950' : ''
                                            }`}
                                        >
                                            <Switch
                                                id={`feature-${feature.key}`}
                                                checked={data.features[feature.key] ?? false}
                                                onCheckedChange={() => toggleFeature(feature.key)}
                                            />
                                            <div className="flex-1">
                                                <Label
                                                    htmlFor={`feature-${feature.key}`}
                                                    className="flex cursor-pointer items-center gap-2"
                                                >
                                                    <DynamicIcon name={feature.icon} className="h-4 w-4" />
                                                    {feature.name}
                                                </Label>
                                                {feature.description && (
                                                    <p className="text-muted-foreground mt-1 text-xs">
                                                        {feature.description}
                                                    </p>
                                                )}
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            </div>
                        ))}
                    </CardContent>
                </Card>
            )}

            {/* Quantity & Limits - Only for stackable types */}
            {showQuantitySection && (
                <Card>
                    <CardHeader>
                        <CardTitle>{t('admin.catalog.form.quantity_limits')}</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <div className="grid grid-cols-3 gap-4">
                            <div className="space-y-2">
                                <Label htmlFor="min_quantity">{t('admin.catalog.form.min_qty')}</Label>
                                <Input
                                    id="min_quantity"
                                    type="number"
                                    value={data.min_quantity}
                                    onChange={e => setData('min_quantity', parseInt(e.target.value) || 1)}
                                />
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="max_quantity">{t('admin.catalog.form.max_qty')}</Label>
                                <Input
                                    id="max_quantity"
                                    type="number"
                                    value={data.max_quantity}
                                    onChange={e => setData('max_quantity', e.target.value)}
                                />
                            </div>
                        </div>
                        <div className="flex items-center space-x-2">
                            <Switch
                                id="stackable"
                                checked={data.stackable}
                                onCheckedChange={v => setData('stackable', !!v)}
                            />
                            <Label htmlFor="stackable">{t('admin.catalog.form.stackable')}</Label>
                        </div>
                    </CardContent>
                </Card>
            )}

            {/* Display */}
            <Card>
                <CardHeader>
                    <CardTitle>{t('common.display')}</CardTitle>
                </CardHeader>
                <CardContent className="space-y-6">
                    {/* Badge Selector */}
                    <BadgeSelector
                        label={t('common.badge')}
                        value={data.badge}
                        onChange={(value) => setData('badge', value)}
                    />

                    {/* Icon Selector */}
                    <IconSelector
                        label={t('common.icon')}
                        value={data.icon}
                        onChange={(value) => setData('icon', value)}
                        iconColor={data.icon_color}
                    />

                    {/* Icon Color Selector */}
                    <ColorSelector
                        label={t('common.icon_color')}
                        value={data.icon_color}
                        onChange={(value) => setData('icon_color', value)}
                    />

                    {/* Sort Order */}
                    <div className="max-w-[200px] space-y-2">
                        <Label htmlFor="sort_order">{t('common.sort_order')}</Label>
                        <Input
                            id="sort_order"
                            type="number"
                            value={data.sort_order}
                            onChange={e => setData('sort_order', parseInt(e.target.value) || 0)}
                        />
                    </div>
                </CardContent>
            </Card>

            {/* Availability */}
            <Card>
                <CardHeader>
                    <CardTitle>{t('common.availability')}</CardTitle>
                </CardHeader>
                <CardContent className="space-y-4">
                    <div className="flex items-center space-x-2">
                        <Switch
                            id="active"
                            checked={data.active}
                            onCheckedChange={v => setData('active', !!v)}
                        />
                        <Label htmlFor="active">{t('common.active_in_catalog')}</Label>
                    </div>
                    <div className="space-y-2">
                        <Label>{t('common.available_plans')}</Label>
                        <div className="flex flex-wrap gap-4">
                            {plans.map(plan => (
                                <div key={plan.id} className="flex items-center space-x-2">
                                    <Switch
                                        id={`plan-${plan.id}`}
                                        checked={data.plan_ids.includes(plan.id)}
                                        onCheckedChange={() => togglePlan(plan.id)}
                                    />
                                    <Label htmlFor={`plan-${plan.id}`}>{plan.name}</Label>
                                </div>
                            ))}
                        </div>
                    </div>
                </CardContent>
            </Card>

            <div className="flex justify-end gap-2">
                <Button type="button" variant="outline" onClick={() => window.history.back()}>
                    {t('common.cancel')}
                </Button>
                <Button type="submit" disabled={processing}>
                    {isEdit ? t('admin.catalog.update_addon') : t('admin.catalog.create_addon')}
                </Button>
            </div>
        </form>
    );
}
