import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Page,
    PageHeader,
    PageHeaderContent,
    PageHeaderActions,
    PageTitle,
    PageDescription,
    PageContent,
} from '@/components/shared/layout/page';
import { DynamicIcon } from '@/components/shared/icons/dynamic-icon';
import AdminLayout from '@/layouts/central/admin-layout';
import admin from '@/routes/central/admin';
import { create, destroy, edit, sync, syncAll } from '@/routes/central/admin/bundles';
import { Head, Link, router } from '@inertiajs/react';
import { CheckCircle, Edit as EditIcon, Package, Plus, RefreshCw, Trash2, XCircle } from 'lucide-react';
import { type BreadcrumbItem, type BundleResource } from '@/types';
import { BADGE_PRESET } from '@/lib/enum-metadata';
import { useSetBreadcrumbs } from '@/contexts/breadcrumb-context';
import { type ReactElement } from 'react';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import { cn, formatPrice } from '@/lib/utils';
import type { BadgePreset } from '@/types/enums';

interface Props {
    bundles: BundleResource[];
}

function BundleIndex({ bundles }: Props) {
    const { t } = useLaravelReactI18n();

    const getBadgePreset = (value: BadgePreset | null) => {
        if (!value) return null;
        return BADGE_PRESET[value] ?? null;
    };

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('dashboard.page.title'), href: admin.dashboard.url() },
        { title: t('bundles.page.title'), href: admin.bundles.index.url() },
    ];

    useSetBreadcrumbs(breadcrumbs);

    const handleDelete = (bundle: BundleResource) => {
        if (confirm(t('bundles.delete_confirm', { name: bundle.name_display }))) {
            router.delete(destroy.url(bundle.id));
        }
    };

    const handleSync = (bundle: BundleResource) => {
        router.post(sync.url(bundle.id));
    };

    const handleSyncAll = () => {
        router.post(syncAll.url());
    };

    return (
        <>
            <Head title={t('bundles.page.title')} />

            <Page>
                <PageHeader>
                    <PageHeaderContent>
                        <PageTitle>{t('bundles.page.title')}</PageTitle>
                        <PageDescription>{t('bundles.page.description')}</PageDescription>
                    </PageHeaderContent>
                    <PageHeaderActions>
                        <Button variant="outline" onClick={handleSyncAll}>
                            <RefreshCw className="mr-2 h-4 w-4" />
                            {t('bundles.page.sync_all')}
                        </Button>
                        <Button asChild>
                            <Link href={create.url()}>
                                <Plus className="mr-2 h-4 w-4" />
                                {t('bundles.page.new_bundle')}
                            </Link>
                        </Button>
                    </PageHeaderActions>
                </PageHeader>

                <PageContent>
                    <div className="grid gap-4">
                    {bundles.map((bundle) => (
                        <Card key={bundle.id}>
                            <CardHeader className="pb-2">
                                <div className="flex items-start justify-between">
                                    <div className="flex items-center gap-2">
                                        <DynamicIcon
                                            name={bundle.icon}
                                            color={bundle.icon_color}
                                            className="h-5 w-5"
                                        />
                                        <CardTitle className="text-lg">{bundle.name_display}</CardTitle>
                                        {bundle.badge && (() => {
                                            const preset = getBadgePreset(bundle.badge);
                                            if (preset) {
                                                return (
                                                    <span
                                                        className={cn(
                                                            'inline-flex items-center gap-1 rounded-full border px-2 py-0.5 text-xs font-medium',
                                                            preset.bg,
                                                            preset.text,
                                                            preset.border
                                                        )}
                                                    >
                                                        <DynamicIcon name={preset.icon} className="h-3 w-3" />
                                                        {preset.label}
                                                    </span>
                                                );
                                            }
                                            return <Badge variant="secondary">{bundle.badge}</Badge>;
                                        })()}
                                        {bundle.discount_percent > 0 && (
                                            <Badge variant="default">
                                                {t('bundles.discount', { percent: bundle.discount_percent })}
                                            </Badge>
                                        )}
                                        {!bundle.active && (
                                            <Badge variant="destructive">{t('common.inactive')}</Badge>
                                        )}
                                    </div>
                                    <div className="flex gap-1">
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            onClick={() => handleSync(bundle)}
                                            title={t('bundles.page.sync_with_stripe')}
                                        >
                                            <RefreshCw className="h-4 w-4" />
                                        </Button>
                                        <Button variant="ghost" size="icon" asChild>
                                            <Link href={edit.url(bundle.id)}>
                                                <EditIcon className="h-4 w-4" />
                                            </Link>
                                        </Button>
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            onClick={() => handleDelete(bundle)}
                                        >
                                            <Trash2 className="h-4 w-4" />
                                        </Button>
                                    </div>
                                </div>
                                <CardDescription>
                                    {bundle.slug} &bull; {t('bundles.addons_count', { count: bundle.addon_count })}
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <div className="space-y-3">
                                    {/* Addons list */}
                                    <div className="flex flex-wrap gap-2">
                                        {bundle.addons.map((addon) => (
                                            <Badge key={addon.id} variant="outline">
                                                {addon.name}
                                                {addon.quantity > 1 && ` x${addon.quantity}`}
                                            </Badge>
                                        ))}
                                    </div>

                                    {/* Pricing info */}
                                    <div className="flex items-center justify-between text-sm">
                                        <div className="flex gap-4">
                                            <span className="text-muted-foreground">
                                                {t('bundles.base_price', { price: formatPrice(bundle.base_price_monthly) })}
                                            </span>
                                            <span className="font-medium">
                                                {t('bundles.effective_price', { price: formatPrice(bundle.price_monthly_effective) })}
                                            </span>
                                            {bundle.savings_monthly > 0 && (
                                                <span className="text-green-600">
                                                    {t('bundles.savings', { amount: formatPrice(bundle.savings_monthly) })}
                                                </span>
                                            )}
                                        </div>
                                        <div className="flex items-center gap-4">
                                            <span className="text-muted-foreground">
                                                {t('catalog.page.plans')}: {bundle.plans.map((p) => p.name).join(', ') || t('common.none')}
                                            </span>
                                            <span className="flex items-center gap-1">
                                                {bundle.is_synced ? (
                                                    <>
                                                        <CheckCircle className="h-4 w-4 text-green-500" />
                                                        <span className="text-green-600">{t('catalog.page.synced')}</span>
                                                    </>
                                                ) : (
                                                    <>
                                                        <XCircle className="h-4 w-4 text-yellow-500" />
                                                        <span className="text-yellow-600">{t('catalog.page.not_synced')}</span>
                                                    </>
                                                )}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    ))}

                    {bundles.length === 0 && (
                        <Card>
                            <CardContent className="py-8 text-center">
                                <Package className="mx-auto h-12 w-12 text-muted-foreground" />
                                <p className="mt-4 text-muted-foreground">{t('bundles.page.no_bundles')}</p>
                                <Button asChild className="mt-4">
                                    <Link href={create.url()}>{t('bundles.page.create_first')}</Link>
                                </Button>
                            </CardContent>
                        </Card>
                    )}
                    </div>
                </PageContent>
            </Page>
        </>
    );
}

BundleIndex.layout = (page: ReactElement) => <AdminLayout>{page}</AdminLayout>;

export default BundleIndex;
