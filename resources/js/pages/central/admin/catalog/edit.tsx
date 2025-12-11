import { Head, router } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Page,
    PageHeader,
    PageHeaderContent,
    PageHeaderActions,
    PageTitle,
    PageDescription,
    PageContent,
} from '@/components/shared/layout/page';
import AdminLayout from '@/layouts/central/admin-layout';
import admin from '@/routes/central/admin';
import { CheckCircle, RefreshCw, XCircle } from 'lucide-react';
import { AddonForm } from './components/addon-form';
import { type BreadcrumbItem, type FeatureDefinitionResource, type LimitDefinitionResource, type CategoryOptionResource } from '@/types';
import type { AddonTypeOption } from '@/types/enums';
import { useSetBreadcrumbs } from '@/contexts/breadcrumb-context';
import { type ReactElement } from 'react';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import { Translations } from '@/components/central/forms/translatable-input';
import type { BadgePreset } from '@/types/enums';

interface AddonEditData {
    id: string;
    slug: string;
    name: Translations;
    name_display: string;
    description: Translations | null;
    type: string;
    category: string;
    active: boolean;
    sort_order: number;
    limit_key: string | null;
    unit_value: number | null;
    unit_label: Translations | null;
    min_quantity: number;
    max_quantity: number | null;
    stackable: boolean;
    price_monthly: number | null;
    price_yearly: number | null;
    price_one_time: number | null;
    validity_months: number | null;
    stripe_product_id: string | null;
    stripe_price_monthly_id: string | null;
    stripe_price_yearly_id: string | null;
    stripe_price_one_time_id: string | null;
    icon: string;
    icon_color: string | null;
    badge: BadgePreset | null;
    is_synced: boolean;
    plan_ids: string[];
    features: Record<string, boolean>;
}

interface Props {
    addon: AddonEditData;
    types: AddonTypeOption[];
    plans: { id: string; name: string; slug: string }[];
    featureDefinitions: FeatureDefinitionResource[];
    limitDefinitions: LimitDefinitionResource[];
    categories: CategoryOptionResource[];
}

function CatalogEdit({ addon, types, plans, featureDefinitions, limitDefinitions, categories }: Props) {
    const { t } = useLaravelReactI18n();

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('admin.dashboard.title'), href: admin.dashboard.url() },
        { title: t('admin.catalog.title'), href: admin.catalog.index.url() },
        { title: addon.name_display, href: admin.catalog.edit.url(addon.id) },
    ];

    useSetBreadcrumbs(breadcrumbs);

    const handleSync = () => {
        router.post(`/admin/catalog/${addon.id}/sync`);
    };

    return (
        <>
            <Head title={`${t('admin.catalog.edit_addon')}: ${addon.name_display}`} />

            <Page>
                <PageHeader>
                    <PageHeaderContent>
                        <PageTitle>{t('admin.catalog.edit_addon')}</PageTitle>
                        <PageDescription>{addon.slug}</PageDescription>
                    </PageHeaderContent>
                    <PageHeaderActions>
                        <Button variant="outline" onClick={handleSync}>
                            <RefreshCw className="mr-2 h-4 w-4" />
                            {t('admin.catalog.sync_with_stripe')}
                        </Button>
                    </PageHeaderActions>
                </PageHeader>

                <PageContent>
                    <Card>
                        <CardHeader>
                            <CardTitle>{t('admin.catalog.stripe_integration')}</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="flex items-center gap-4">
                                {addon.is_synced ? (
                                    <Badge variant="default" className="bg-green-600">
                                        <CheckCircle className="mr-1 h-3 w-3" />
                                        {t('admin.catalog.synced')}
                                    </Badge>
                                ) : (
                                    <Badge variant="secondary">
                                        <XCircle className="mr-1 h-3 w-3" />
                                        {t('admin.catalog.not_synced')}
                                    </Badge>
                                )}
                                {addon.stripe_product_id && (
                                    <span className="text-sm text-muted-foreground">
                                        {t('admin.catalog.product')}: {addon.stripe_product_id}
                                    </span>
                                )}
                            </div>
                            {addon.stripe_price_monthly_id && (
                                <p className="mt-2 text-sm text-muted-foreground">
                                    {t('admin.catalog.monthly_price')}: {addon.stripe_price_monthly_id}
                                </p>
                            )}
                            {addon.stripe_price_yearly_id && (
                                <p className="text-sm text-muted-foreground">
                                    {t('admin.catalog.yearly_price')}: {addon.stripe_price_yearly_id}
                                </p>
                            )}
                            {addon.stripe_price_one_time_id && (
                                <p className="text-sm text-muted-foreground">
                                    {t('admin.catalog.one_time_price')}: {addon.stripe_price_one_time_id}
                                </p>
                            )}
                        </CardContent>
                    </Card>

                    <AddonForm
                        addon={addon}
                        types={types}
                        plans={plans}
                        featureDefinitions={featureDefinitions}
                        limitDefinitions={limitDefinitions}
                        categories={categories}
                        isEdit
                    />
                </PageContent>
            </Page>
        </>
    );
}

CatalogEdit.layout = (page: ReactElement) => <AdminLayout>{page}</AdminLayout>;

export default CatalogEdit;
