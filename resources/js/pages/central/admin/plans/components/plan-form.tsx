import { useForm } from '@inertiajs/react';
import { useMemo } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import { TranslatableInput, Translations } from '@/components/central/forms/translatable-input';
import { useLocales } from '@/hooks/shared/use-locales';
import { BadgeSelector } from '@/components/central/forms/badge-selector';
import type { BadgePreset } from '@/types/enums';
import { IconSelector } from '@/components/central/forms/icon-selector';
import { ColorSelector } from '@/components/central/forms/color-selector';
import { DynamicIcon } from '@/components/shared/icons/dynamic-icon';
import { formatPrice } from '@/lib/utils';
import type { EnumOption } from '@/types';
import type { FeatureDefinition, LimitDefinition, AddonOptionForPlan } from '@/types/common';

export interface PlanData {
    id?: string;
    name: Translations;
    slug: string;
    description: Translations;
    price: number;
    currency: string;
    billing_period: string;
    features: Record<string, boolean>;
    limits: Record<string, number | null>;
    is_active: boolean;
    is_featured: boolean;
    badge?: BadgePreset | null;
    icon?: string;
    icon_color?: string | null;
    sort_order: number;
    addon_ids: string[];
}

interface Props {
    plan?: PlanData;
    addons: AddonOptionForPlan[];
    featureDefinitions: FeatureDefinition[];
    limitDefinitions: LimitDefinition[];
    categories: EnumOption[];
    onSubmit: (data: PlanData) => void;
    isSubmitting?: boolean;
}

// Build default values from definitions
function buildDefaultFeatures(definitions: FeatureDefinition[]): Record<string, boolean> {
    return definitions.reduce(
        (acc, def) => ({ ...acc, [def.key]: false }),
        {}
    );
}

function buildDefaultLimits(definitions: LimitDefinition[]): Record<string, number> {
    return definitions.reduce(
        (acc, def) => ({ ...acc, [def.key]: def.default_value }),
        {}
    );
}

export function PlanForm({ plan, addons, featureDefinitions, limitDefinitions, categories, onSubmit, isSubmitting }: Props) {
    const { t } = useLaravelReactI18n();
    const { ensureTranslations } = useLocales();
    const defaultFeatures = buildDefaultFeatures(featureDefinitions);
    const defaultLimits = buildDefaultLimits(limitDefinitions);

    // Build categoryLabels from props
    const categoryLabels = useMemo(() => {
        return categories.reduce((acc, cat) => ({ ...acc, [cat.value]: cat.label }), {} as Record<string, string>);
    }, [categories]);

    const { data, setData, processing } = useForm({
        name: ensureTranslations(plan?.name),
        slug: plan?.slug ?? '',
        description: ensureTranslations(plan?.description),
        price: plan?.price ?? 0,
        currency: plan?.currency ?? 'usd',
        billing_period: plan?.billing_period ?? 'monthly',
        features: plan?.features ?? defaultFeatures,
        limits: plan?.limits ?? defaultLimits as Record<string, number | null>,
        is_active: plan?.is_active ?? true,
        is_featured: plan?.is_featured ?? false,
        badge: plan?.badge ?? null,
        icon: plan?.icon ?? 'Layers',
        icon_color: plan?.icon_color ?? null,
        sort_order: plan?.sort_order ?? 0,
        addon_ids: plan?.addon_ids ?? [] as string[],
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        onSubmit(data);
    };

    const toggleAddon = (addonId: string) => {
        const newIds = data.addon_ids.includes(addonId)
            ? data.addon_ids.filter((id) => id !== addonId)
            : [...data.addon_ids, addonId];
        setData('addon_ids', newIds);
    };

    const toggleFeature = (featureKey: string) => {
        setData('features', {
            ...data.features,
            [featureKey]: !data.features[featureKey],
        });
    };

    const setLimit = (key: string, value: number) => {
        setData('limits', {
            ...data.limits,
            [key]: value,
        });
    };

    // Group features by category
    const featuresByCategory = featureDefinitions.reduce(
        (acc, feature) => {
            const category = feature.category || 'other';
            if (!acc[category]) acc[category] = [];
            acc[category].push(feature);
            return acc;
        },
        {} as Record<string, FeatureDefinition[]>
    );

    return (
        <form onSubmit={handleSubmit} className="space-y-6">
            <Card>
                <CardHeader>
                    <CardTitle>{t('common.basic_info')}</CardTitle>
                </CardHeader>
                <CardContent className="space-y-4">
                    <div className="space-y-2">
                        <Label>Slug</Label>
                        <Input
                            value={data.slug}
                            onChange={(e) => setData('slug', e.target.value)}
                            placeholder="professional"
                            disabled={!!plan?.id}
                            className="max-w-xs"
                        />
                    </div>

                    <TranslatableInput
                        label={t('common.name')}
                        value={data.name}
                        onChange={(v) => setData('name', v)}
                        placeholder={{ en: 'Professional', pt_BR: 'Profissional' }}
                        required
                    />

                    <TranslatableInput
                        label={t('common.description')}
                        value={data.description}
                        onChange={(v) => setData('description', v)}
                        placeholder={{
                            en: 'Plan description in English...',
                            pt_BR: 'Descrição do plano em português...',
                        }}
                        multiline
                    />
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>{t('common.pricing')}</CardTitle>
                </CardHeader>
                <CardContent className="space-y-4">
                    <div className="grid grid-cols-3 gap-4">
                        <div className="space-y-2">
                            <Label>{t('admin.plans.form.price_cents')}</Label>
                            <Input
                                type="number"
                                value={data.price}
                                onChange={(e) => setData('price', parseInt(e.target.value) || 0)}
                                min={0}
                            />
                            <p className="text-muted-foreground text-xs">
                                {formatPrice(data.price)}
                            </p>
                        </div>
                        <div className="space-y-2">
                            <Label>{t('admin.plans.form.currency')}</Label>
                            <Select value={data.currency} onValueChange={(v) => setData('currency', v)}>
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="usd">USD</SelectItem>
                                    <SelectItem value="eur">EUR</SelectItem>
                                    <SelectItem value="brl">BRL</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <div className="space-y-2">
                            <Label>{t('admin.plans.form.billing_period')}</Label>
                            <Select value={data.billing_period} onValueChange={(v) => setData('billing_period', v)}>
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="monthly">{t('common.monthly')}</SelectItem>
                                    <SelectItem value="yearly">{t('common.yearly')}</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Limits</CardTitle>
                </CardHeader>
                <CardContent className="space-y-4">
                    <div className="grid grid-cols-3 gap-4">
                        {limitDefinitions.map((limit) => (
                            <div key={limit.key} className="space-y-2">
                                <Label className="flex items-center gap-2">
                                    <DynamicIcon name={limit.icon} className="h-4 w-4" />
                                    {limit.name}
                                    {limit.allows_unlimited && (
                                        <span className="text-muted-foreground text-xs">(-1 = {t('common.unlimited').toLowerCase()})</span>
                                    )}
                                </Label>
                                <Input
                                    type="number"
                                    value={data.limits[limit.key] ?? limit.default_value}
                                    onChange={(e) => setLimit(limit.key, parseInt(e.target.value) || 0)}
                                    min={limit.allows_unlimited ? -1 : 0}
                                />
                                {limit.unit_label && (
                                    <p className="text-muted-foreground text-xs">{limit.unit_label}</p>
                                )}
                            </div>
                        ))}
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Features</CardTitle>
                </CardHeader>
                <CardContent className="space-y-6">
                    {Object.entries(featuresByCategory).map(([category, features]) => (
                        <div key={category} className="space-y-3">
                            <h4 className="text-muted-foreground text-sm font-medium">
                                {categoryLabels[category] || category}
                            </h4>
                            <div className="grid grid-cols-2 gap-3">
                                {features.map((feature) => (
                                    <div
                                        key={feature.key}
                                        className="flex items-start gap-3 rounded-lg border p-3"
                                    >
                                        <Switch
                                            id={feature.key}
                                            checked={data.features[feature.key] ?? false}
                                            onCheckedChange={() => toggleFeature(feature.key)}
                                        />
                                        <div className="flex-1">
                                            <Label
                                                htmlFor={feature.key}
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

            <Card>
                <CardHeader>
                    <CardTitle>{t('admin.plans.form.available_addons')}</CardTitle>
                </CardHeader>
                <CardContent className="space-y-3">
                    {addons.length === 0 ? (
                        <p className="text-muted-foreground text-sm">{t('admin.plans.form.no_addons')}</p>
                    ) : (
                        addons.map((addon) => (
                            <div key={addon.id} className="flex items-center gap-2">
                                <Switch
                                    id={`addon-${addon.id}`}
                                    checked={data.addon_ids.includes(addon.id)}
                                    onCheckedChange={() => toggleAddon(addon.id)}
                                />
                                <Label htmlFor={`addon-${addon.id}`}>
                                    {addon.name}{' '}
                                    <span className="text-muted-foreground">({addon.slug})</span>
                                </Label>
                            </div>
                        ))
                    )}
                </CardContent>
            </Card>

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

                    {/* Featured & Sort Order */}
                    <div className="flex items-center gap-6">
                        <div className="flex items-center gap-2">
                            <Switch
                                id="is_featured"
                                checked={data.is_featured}
                                onCheckedChange={(checked) => setData('is_featured', !!checked)}
                            />
                            <Label htmlFor="is_featured">{t('common.featured')}</Label>
                        </div>
                        <div className="flex items-center gap-2">
                            <Label>{t('common.sort_order')}</Label>
                            <Input
                                type="number"
                                value={data.sort_order}
                                onChange={(e) => setData('sort_order', parseInt(e.target.value) || 0)}
                                className="w-20"
                            />
                        </div>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>{t('common.settings')}</CardTitle>
                </CardHeader>
                <CardContent className="space-y-4">
                    <div className="flex items-center gap-2">
                        <Switch
                            id="is_active"
                            checked={data.is_active}
                            onCheckedChange={(checked) => setData('is_active', !!checked)}
                        />
                        <Label htmlFor="is_active">{t('common.active')}</Label>
                    </div>
                </CardContent>
            </Card>

            <div className="flex justify-end gap-2">
                <Button type="button" variant="outline" onClick={() => window.history.back()}>
                    {t('common.cancel')}
                </Button>
                <Button type="submit" disabled={processing || isSubmitting}>
                    {plan?.id ? t('admin.plans.update_plan') : t('admin.plans.create_plan')}
                </Button>
            </div>
        </form>
    );
}
