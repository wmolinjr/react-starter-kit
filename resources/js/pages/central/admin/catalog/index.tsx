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
import { create, destroy, sync, syncAll } from '@/routes/central/admin/catalog';
import { Head, Link, router } from '@inertiajs/react';
import { CheckCircle, Edit, Plus, RefreshCw, Trash2, XCircle } from 'lucide-react';
import { type BreadcrumbItem } from '@/types';
import type { BadgePreset } from '@/types/enums';
import { BADGE_PRESET } from '@/lib/enum-metadata';
import { useSetBreadcrumbs } from '@/contexts/breadcrumb-context';
import { type ReactElement } from 'react';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import { cn, formatPrice } from '@/lib/utils';

interface Addon {
    id: string;
    slug: string;
    name_display: string;
    description: string | null;
    type: string;
    type_label: string;
    active: boolean;
    price_monthly: number | null;
    price_yearly: number | null;
    price_one_time: number | null;
    icon: string;
    icon_color: string | null;
    badge: BadgePreset | null;
    is_synced: boolean;
    stripe_product_id: string | null;
    plans: { id: string; name: string; slug: string }[];
}

interface Props {
    addons: Addon[];
    types: { value: string; label: string }[];
}

function CatalogIndex({ addons }: Props) {
    const { t } = useLaravelReactI18n();

    const getBadgePreset = (value: BadgePreset | null) => {
        if (!value) return null;
        return BADGE_PRESET[value] ?? null;
    };

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('admin.dashboard.title'), href: admin.dashboard.url() },
        { title: t('admin.catalog.title'), href: admin.catalog.index.url() },
    ];

    useSetBreadcrumbs(breadcrumbs);

    const handleSync = (addon: Addon) => {
        router.post(sync.url(addon.id));
    };

    const handleSyncAll = () => {
        router.post(syncAll.url());
    };

    const handleDelete = (addon: Addon) => {
        if (confirm(t('admin.catalog.delete_confirm', { name: addon.name_display }))) {
            router.delete(destroy.url(addon.id));
        }
    };

    return (
        <>
            <Head title={t('admin.catalog.title')} />

            <Page>
                <PageHeader>
                    <PageHeaderContent>
                        <PageTitle>{t('admin.catalog.title')}</PageTitle>
                        <PageDescription>{t('admin.catalog.description')}</PageDescription>
                    </PageHeaderContent>
                    <PageHeaderActions>
                        <Button variant="outline" onClick={handleSyncAll}>
                            <RefreshCw className="mr-2 h-4 w-4" />
                            {t('admin.catalog.sync_all')}
                        </Button>
                        <Button asChild>
                            <Link href={create.url()}>
                                <Plus className="mr-2 h-4 w-4" />
                                {t('admin.catalog.new_addon')}
                            </Link>
                        </Button>
                    </PageHeaderActions>
                </PageHeader>

                <PageContent>
                    <div className="grid gap-4">
                    {addons.map((addon) => (
                        <Card key={addon.id}>
                            <CardHeader className="pb-2">
                                <div className="flex items-start justify-between">
                                    <div className="flex items-center gap-2">
                                        <DynamicIcon
                                            name={addon.icon}
                                            color={addon.icon_color}
                                            className="h-5 w-5"
                                        />
                                        <CardTitle className="text-lg">{addon.name_display}</CardTitle>
                                        {addon.badge && (() => {
                                            const preset = getBadgePreset(addon.badge);
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
                                            return <Badge variant="secondary">{addon.badge}</Badge>;
                                        })()}
                                        {!addon.active && (
                                            <Badge variant="destructive">{t('common.inactive')}</Badge>
                                        )}
                                    </div>
                                    <div className="flex gap-1">
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            onClick={() => handleSync(addon)}
                                            title={t('admin.catalog.sync_with_stripe')}
                                        >
                                            <RefreshCw className="h-4 w-4" />
                                        </Button>
                                        <Button variant="ghost" size="icon" asChild>
                                            <Link href={`/admin/catalog/${addon.id}/edit`}>
                                                <Edit className="h-4 w-4" />
                                            </Link>
                                        </Button>
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            onClick={() => handleDelete(addon)}
                                        >
                                            <Trash2 className="h-4 w-4" />
                                        </Button>
                                    </div>
                                </div>
                                <CardDescription>
                                    {addon.slug} &bull; {addon.type_label}
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <div className="flex items-center justify-between text-sm">
                                    <div className="flex gap-4">
                                        {addon.price_monthly && (
                                            <span>{t('common.monthly')}: {formatPrice(addon.price_monthly)}</span>
                                        )}
                                        {addon.price_yearly && (
                                            <span>{t('common.yearly')}: {formatPrice(addon.price_yearly)}</span>
                                        )}
                                        {addon.price_one_time && (
                                            <span>{t('common.one_time')}: {formatPrice(addon.price_one_time)}</span>
                                        )}
                                    </div>
                                    <div className="flex items-center gap-4">
                                        <span className="text-muted-foreground">
                                            {t('admin.catalog.plans')}: {addon.plans.map((p) => p.name).join(', ') || t('common.none')}
                                        </span>
                                        <span className="flex items-center gap-1">
                                            {addon.is_synced ? (
                                                <>
                                                    <CheckCircle className="h-4 w-4 text-green-500" />
                                                    <span className="text-green-600">{t('admin.catalog.synced')}</span>
                                                </>
                                            ) : (
                                                <>
                                                    <XCircle className="h-4 w-4 text-yellow-500" />
                                                    <span className="text-yellow-600">{t('admin.catalog.not_synced')}</span>
                                                </>
                                            )}
                                        </span>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    ))}

                    {addons.length === 0 && (
                        <Card>
                            <CardContent className="py-8 text-center">
                                <p className="text-muted-foreground">{t('admin.catalog.no_addons')}</p>
                                <Button asChild className="mt-4">
                                    <Link href={create.url()}>{t('admin.catalog.create_first')}</Link>
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

CatalogIndex.layout = (page: ReactElement) => <AdminLayout>{page}</AdminLayout>;

export default CatalogIndex;
