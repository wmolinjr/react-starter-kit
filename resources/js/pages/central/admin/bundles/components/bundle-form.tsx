import { useForm } from '@inertiajs/react';
import { useMemo } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Switch } from '@/components/ui/switch';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import { TranslatableInput, Translations } from '@/components/central/forms/translatable-input';
import { useLocales } from '@/hooks/shared/use-locales';
import { BadgeSelector } from '@/components/central/forms/badge-selector';
import { IconSelector } from '@/components/central/forms/icon-selector';
import { ColorSelector } from '@/components/central/forms/color-selector';
import { Plus, Trash2 } from 'lucide-react';
import { formatPrice } from '@/lib/utils';
import type { BadgePreset } from '@/types/enums';

interface AddonOption {
    id: string;
    slug: string;
    name: string;
    type: string;
    type_label: string;
    price_monthly: number;
    price_yearly: number;
}

interface PlanOption {
    id: string;
    name: string;
    slug: string;
}

interface BundleAddon {
    addon_id: string;
    quantity: number;
}

interface BundleInput {
    id?: string;
    slug?: string;
    name?: Translations;
    description?: Translations | null;
    active?: boolean;
    discount_percent?: number;
    price_monthly?: number | string | null;
    price_yearly?: number | string | null;
    badge?: BadgePreset | null;
    icon?: string | null;
    icon_color?: string | null;
    features?: Translations[];
    sort_order?: number;
    addons?: BundleAddon[];
    plan_ids?: string[];
}

interface Props {
    bundle?: BundleInput;
    addons: AddonOption[];
    plans: PlanOption[];
    isEdit?: boolean;
}

export function BundleForm({ bundle, addons, plans, isEdit = false }: Props) {
    const { t } = useLaravelReactI18n();
    const { ensureTranslations } = useLocales();

    // Ensure features have proper translation structure
    const ensureFeaturesTranslations = (features?: Translations[]): Translations[] => {
        if (!features || features.length === 0) return [];
        return features.map(f => ensureTranslations(f));
    };

    const { data, setData, post, put, processing, errors } = useForm({
        slug: bundle?.slug || '',
        name: ensureTranslations(bundle?.name),
        description: ensureTranslations(bundle?.description ?? undefined),
        active: bundle?.active ?? true,
        discount_percent: bundle?.discount_percent || 0,
        price_monthly: bundle?.price_monthly?.toString() || '',
        price_yearly: bundle?.price_yearly?.toString() || '',
        badge: bundle?.badge ?? null,
        icon: bundle?.icon || 'Package',
        icon_color: bundle?.icon_color ?? null,
        features: ensureFeaturesTranslations(bundle?.features),
        sort_order: bundle?.sort_order || 0,
        addons: bundle?.addons || [] as BundleAddon[],
        plan_ids: bundle?.plan_ids || [] as string[],
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (isEdit && bundle?.id) {
            put(`/admin/bundles/${bundle.id}`);
        } else {
            post('/admin/bundles');
        }
    };

    const togglePlan = (planId: string) => {
        setData('plan_ids',
            data.plan_ids.includes(planId)
                ? data.plan_ids.filter(id => id !== planId)
                : [...data.plan_ids, planId]
        );
    };

    // Addon management
    const addAddon = () => {
        setData('addons', [...data.addons, { addon_id: '', quantity: 1 }]);
    };

    const removeAddon = (index: number) => {
        setData('addons', data.addons.filter((_, i) => i !== index));
    };

    const updateAddon = (index: number, field: keyof BundleAddon, value: string | number) => {
        const updated = [...data.addons];
        updated[index] = { ...updated[index], [field]: value };
        setData('addons', updated);
    };

    // Features management
    const addFeature = () => {
        setData('features', [...data.features, ensureTranslations({})]);
    };

    const removeFeature = (index: number) => {
        setData('features', data.features.filter((_, i) => i !== index));
    };

    const updateFeature = (index: number, value: Translations) => {
        const updated = [...data.features];
        updated[index] = value;
        setData('features', updated);
    };

    // Calculate prices based on selected addons
    const calculatedPrices = useMemo(() => {
        let monthlyBase = 0;
        let yearlyBase = 0;

        data.addons.forEach(bundleAddon => {
            const addon = addons.find(a => a.id === bundleAddon.addon_id);
            if (addon) {
                monthlyBase += (addon.price_monthly || 0) * bundleAddon.quantity;
                yearlyBase += (addon.price_yearly || 0) * bundleAddon.quantity;
            }
        });

        const discountMultiplier = 1 - (data.discount_percent / 100);
        const monthlyEffective = Math.round(monthlyBase * discountMultiplier);
        const yearlyEffective = Math.round(yearlyBase * discountMultiplier);

        return {
            monthlyBase,
            yearlyBase,
            monthlyEffective,
            yearlyEffective,
            monthlySavings: monthlyBase - monthlyEffective,
            yearlySavings: yearlyBase - yearlyEffective,
        };
    }, [data.addons, data.discount_percent, addons]);

    // Get available addons (not already selected)
    const availableAddons = useMemo(() => {
        const selectedIds = data.addons.map(a => a.addon_id);
        return addons.filter(a => !selectedIds.includes(a.id));
    }, [data.addons, addons]);

    return (
        <form onSubmit={handleSubmit} className="space-y-6">
            <Card>
                <CardHeader>
                    <CardTitle>{t('common.basic_info')}</CardTitle>
                </CardHeader>
                <CardContent className="space-y-4">
                    <div className="space-y-2">
                        <Label htmlFor="slug">{t('common.slug')}</Label>
                        <Input
                            id="slug"
                            value={data.slug}
                            onChange={e => setData('slug', e.target.value)}
                            disabled={isEdit}
                            placeholder={t('admin.bundles.form.slug_placeholder')}
                            className="max-w-xs"
                        />
                        {errors.slug && <p className="text-sm text-red-500">{errors.slug}</p>}
                    </div>

                    <TranslatableInput
                        label={t('common.name')}
                        value={data.name}
                        onChange={v => setData('name', v)}
                        placeholder={{ en: 'Power Pack', pt_BR: 'Pacote Completo' }}
                        required
                    />
                    {errors.name && <p className="text-sm text-red-500">{errors.name}</p>}

                    <TranslatableInput
                        label={t('common.description')}
                        value={data.description}
                        onChange={v => setData('description', v)}
                        placeholder={{
                            en: t('admin.bundles.form.description_placeholder'),
                            pt_BR: 'Obtenha mais armazenamento, usuários e relatórios com desconto...',
                        }}
                        multiline
                    />
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>{t('admin.bundles.form.addons')}</CardTitle>
                    <CardDescription>{t('admin.bundles.form.addons_description')}</CardDescription>
                </CardHeader>
                <CardContent className="space-y-4">
                    {data.addons.map((bundleAddon, index) => {
                        const selectedAddon = addons.find(a => a.id === bundleAddon.addon_id);
                        return (
                            <div key={index} className="flex items-center gap-4 rounded-lg border p-4">
                                <div className="flex-1">
                                    <Label>{t('admin.bundles.form.addon')}</Label>
                                    <Select
                                        value={bundleAddon.addon_id}
                                        onValueChange={v => updateAddon(index, 'addon_id', v)}
                                    >
                                        <SelectTrigger>
                                            <SelectValue placeholder={t('admin.bundles.form.select_addon')} />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {selectedAddon && (
                                                <SelectItem key={selectedAddon.id} value={selectedAddon.id}>
                                                    {selectedAddon.name} ({formatPrice(selectedAddon.price_monthly)}/mo)
                                                </SelectItem>
                                            )}
                                            {availableAddons.map(addon => (
                                                <SelectItem key={addon.id} value={addon.id}>
                                                    {addon.name} ({formatPrice(addon.price_monthly)}/mo)
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div className="w-24">
                                    <Label>{t('common.quantity')}</Label>
                                    <Input
                                        type="number"
                                        min={1}
                                        value={bundleAddon.quantity}
                                        onChange={e => updateAddon(index, 'quantity', parseInt(e.target.value) || 1)}
                                    />
                                </div>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    onClick={() => removeAddon(index)}
                                    className="mt-6"
                                >
                                    <Trash2 className="h-4 w-4" />
                                </Button>
                            </div>
                        );
                    })}

                    {data.addons.length < 2 && (
                        <p className="text-sm text-muted-foreground">
                            {t('admin.bundles.form.no_addons')}
                        </p>
                    )}

                    <Button
                        type="button"
                        variant="outline"
                        onClick={addAddon}
                        disabled={availableAddons.length === 0}
                    >
                        <Plus className="mr-2 h-4 w-4" />
                        {t('admin.bundles.form.add_addon')}
                    </Button>

                    {errors.addons && <p className="text-sm text-red-500">{errors.addons}</p>}
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>{t('common.pricing')}</CardTitle>
                </CardHeader>
                <CardContent className="space-y-4">
                    <div className="grid grid-cols-2 gap-4">
                        <div className="space-y-2">
                            <Label htmlFor="discount_percent">{t('admin.bundles.form.discount_percent')}</Label>
                            <div className="flex items-center gap-2">
                                <Input
                                    id="discount_percent"
                                    type="number"
                                    min={0}
                                    max={100}
                                    value={data.discount_percent}
                                    onChange={e => setData('discount_percent', parseInt(e.target.value) || 0)}
                                    className="w-24"
                                />
                                <span>%</span>
                            </div>
                        </div>
                    </div>

                    <div className="grid grid-cols-2 gap-4">
                        <div className="space-y-2">
                            <Label htmlFor="price_monthly">
                                {t('admin.bundles.form.price_override')} ({t('common.monthly')})
                            </Label>
                            <Input
                                id="price_monthly"
                                type="number"
                                value={data.price_monthly}
                                onChange={e => setData('price_monthly', e.target.value)}
                                placeholder={t('placeholders.leave_empty_auto_calculate')}
                            />
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="price_yearly">
                                {t('admin.bundles.form.price_override')} ({t('common.yearly')})
                            </Label>
                            <Input
                                id="price_yearly"
                                type="number"
                                value={data.price_yearly}
                                onChange={e => setData('price_yearly', e.target.value)}
                                placeholder={t('placeholders.leave_empty_auto_calculate')}
                            />
                        </div>
                    </div>

                    {/* Calculated price preview */}
                    <div className="rounded-lg border bg-muted/50 p-4">
                        <h4 className="mb-2 font-medium">{t('admin.bundles.form.calculated_price')}</h4>
                        <div className="grid grid-cols-2 gap-4 text-sm">
                            <div>
                                <span className="text-muted-foreground">{t('common.monthly')}: </span>
                                <span className="line-through text-muted-foreground mr-2">
                                    {formatPrice(calculatedPrices.monthlyBase)}
                                </span>
                                <span className="font-medium">
                                    {formatPrice(calculatedPrices.monthlyEffective)}
                                </span>
                                {calculatedPrices.monthlySavings > 0 && (
                                    <span className="ml-2 text-green-600">
                                        (save {formatPrice(calculatedPrices.monthlySavings)})
                                    </span>
                                )}
                            </div>
                            <div>
                                <span className="text-muted-foreground">{t('common.yearly')}: </span>
                                <span className="line-through text-muted-foreground mr-2">
                                    {formatPrice(calculatedPrices.yearlyBase)}
                                </span>
                                <span className="font-medium">
                                    {formatPrice(calculatedPrices.yearlyEffective)}
                                </span>
                                {calculatedPrices.yearlySavings > 0 && (
                                    <span className="ml-2 text-green-600">
                                        (save {formatPrice(calculatedPrices.yearlySavings)})
                                    </span>
                                )}
                            </div>
                        </div>
                    </div>
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

            <Card>
                <CardHeader>
                    <CardTitle>{t('admin.bundles.form.features')}</CardTitle>
                    <CardDescription>{t('admin.bundles.form.features_description')}</CardDescription>
                </CardHeader>
                <CardContent className="space-y-4">
                    {data.features.map((feature, index) => (
                        <div key={index} className="flex items-start gap-2">
                            <div className="flex-1">
                                <TranslatableInput
                                    label={`${t('admin.bundles.form.feature')} ${index + 1}`}
                                    value={feature}
                                    onChange={v => updateFeature(index, v)}
                                    placeholder={{
                                        en: 'Access to advanced reports',
                                        pt_BR: 'Acesso a relatórios avançados',
                                    }}
                                />
                            </div>
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                onClick={() => removeFeature(index)}
                                className="mt-8"
                            >
                                <Trash2 className="h-4 w-4" />
                            </Button>
                        </div>
                    ))}
                    <Button type="button" variant="outline" onClick={addFeature}>
                        <Plus className="mr-2 h-4 w-4" />
                        {t('admin.bundles.form.add_feature')}
                    </Button>
                </CardContent>
            </Card>

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
                <Button type="submit" disabled={processing || data.addons.length < 2}>
                    {isEdit ? t('admin.bundles.update_bundle') : t('admin.bundles.create_bundle')}
                </Button>
            </div>
        </form>
    );
}
