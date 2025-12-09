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
import { create, destroy, sync, syncAll } from '@/routes/central/admin/plans';
import { Head, Link, router } from '@inertiajs/react';
import { Check, Pencil, Plus, RefreshCw, Trash2, Users, X } from 'lucide-react';
import { type BreadcrumbItem, type PlanResource } from '@/types';
import { BADGE_PRESET } from '@/lib/enum-metadata';
import type { BadgePreset } from '@/types/enums';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import { cn } from '@/lib/utils';
import { useSetBreadcrumbs } from '@/contexts/breadcrumb-context';
import { type ReactElement } from 'react';

interface Props {
    plans: PlanResource[];
}

function PlansIndex({ plans }: Props) {
    const { t } = useLaravelReactI18n();

    const getBadgePreset = (value: BadgePreset | null) => {
        if (!value) return null;
        return BADGE_PRESET[value] ?? null;
    };

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('breadcrumbs.dashboard'), href: admin.dashboard.url() },
        { title: t('breadcrumbs.plan_catalog'), href: admin.plans.index.url() },
    ];

    useSetBreadcrumbs(breadcrumbs);

    const handleSync = (planId: string) => {
        router.post(sync.url(planId));
    };

    const handleSyncAll = () => {
        router.post(syncAll.url());
    };

    const handleDelete = (planId: string) => {
        if (confirm(t('admin.plans.delete_confirm'))) {
            router.delete(destroy.url(planId));
        }
    };

    return (
        <>
            <Head title={t('admin.plans.title')} />

            <Page>
                <PageHeader>
                    <PageHeaderContent>
                        <PageTitle>{t('admin.plans.title')}</PageTitle>
                        <PageDescription>{t('admin.plans.description')}</PageDescription>
                    </PageHeaderContent>
                    <PageHeaderActions>
                        <Button variant="outline" onClick={handleSyncAll}>
                            <RefreshCw className="mr-2 h-4 w-4" />
                            {t('admin.plans.sync_all')}
                        </Button>
                        <Button asChild>
                            <Link href={create.url()}>
                                <Plus className="mr-2 h-4 w-4" />
                                {t('admin.plans.new_plan')}
                            </Link>
                        </Button>
                    </PageHeaderActions>
                </PageHeader>

                <PageContent>
                    <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                    {plans.map((plan) => (
                        <Card key={plan.id} className={!plan.is_active ? 'opacity-60' : ''}>
                            <CardHeader>
                                <div className="flex items-start justify-between">
                                    <div>
                                        <CardTitle className="flex items-center gap-2">
                                            <DynamicIcon
                                                name={plan.icon}
                                                color={plan.icon_color}
                                                className="h-5 w-5"
                                            />
                                            {plan.name}
                                        </CardTitle>
                                        <CardDescription>{plan.slug}</CardDescription>
                                    </div>
                                    <div className="flex gap-1">
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            onClick={() => handleSync(plan.id)}
                                            title={t('admin.plans.sync_with_stripe')}
                                        >
                                            <RefreshCw className="h-4 w-4" />
                                        </Button>
                                        <Button variant="ghost" size="icon" asChild>
                                            <Link href={`/admin/plans/${plan.id}/edit`}>
                                                <Pencil className="h-4 w-4" />
                                            </Link>
                                        </Button>
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            onClick={() => handleDelete(plan.id)}
                                            disabled={plan.tenants_count > 0}
                                        >
                                            <Trash2 className="h-4 w-4" />
                                        </Button>
                                    </div>
                                </div>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                {/* Badge */}
                                {plan.badge && (() => {
                                    const preset = getBadgePreset(plan.badge);
                                    if (preset) {
                                        return (
                                            <span
                                                className={cn(
                                                    'inline-flex items-center gap-1.5 rounded-full border px-3 py-1 text-sm font-medium',
                                                    preset.bg,
                                                    preset.text,
                                                    preset.border
                                                )}
                                            >
                                                <DynamicIcon name={preset.icon} className="h-3.5 w-3.5" />
                                                {preset.label}
                                            </span>
                                        );
                                    }
                                    return <Badge variant="secondary">{plan.badge}</Badge>;
                                })()}
                                {plan.is_featured && !plan.badge && (
                                    <Badge variant="secondary">{t('common.featured')}</Badge>
                                )}

                                <div>
                                    <div className="text-3xl font-bold">{plan.formatted_price}</div>
                                    <div className="text-muted-foreground text-sm">
                                        {t('admin.plans.per')} {plan.billing_period === 'yearly' ? t('admin.plans.year') : t('admin.plans.month')}
                                    </div>
                                </div>

                                {plan.description && (
                                    <p className="text-muted-foreground text-sm">{plan.description}</p>
                                )}

                                <div className="flex flex-wrap gap-2 text-sm">
                                    <div className="flex items-center gap-1">
                                        <Users className="h-4 w-4" />
                                        <span>{plan.tenants_count} tenants</span>
                                    </div>
                                    <div className="flex items-center gap-1">
                                        <span>{plan.addons_count} addons</span>
                                    </div>
                                </div>

                                {plan.limits && (
                                    <div className="text-muted-foreground space-y-1 text-sm">
                                        {plan.limits.users !== undefined && (
                                            <div>Users: {plan.limits.users === -1 ? t('common.unlimited') : plan.limits.users}</div>
                                        )}
                                        {plan.limits.projects !== undefined && (
                                            <div>Projects: {plan.limits.projects === -1 ? t('common.unlimited') : plan.limits.projects}</div>
                                        )}
                                        {plan.limits.storage !== undefined && (
                                            <div>Storage: {plan.limits.storage === -1 ? t('common.unlimited') : `${plan.limits.storage}MB`}</div>
                                        )}
                                    </div>
                                )}

                                <div className="flex items-center gap-2 text-sm">
                                    {plan.stripe_price_id ? (
                                        <Badge variant="outline" className="text-green-600">
                                            <Check className="mr-1 h-3 w-3" />
                                            {t('admin.plans.synced')}
                                        </Badge>
                                    ) : (
                                        <Badge variant="outline" className="text-yellow-600">
                                            <X className="mr-1 h-3 w-3" />
                                            {t('admin.plans.not_synced')}
                                        </Badge>
                                    )}
                                    {!plan.is_active && (
                                        <Badge variant="destructive">{t('common.inactive')}</Badge>
                                    )}
                                </div>
                            </CardContent>
                        </Card>
                    ))}
                </div>

                {plans.length === 0 && (
                    <Card>
                        <CardContent className="flex flex-col items-center justify-center py-12">
                            <p className="text-muted-foreground mb-4">{t('admin.plans.no_plans')}</p>
                            <Button asChild>
                                <Link href={create.url()}>
                                    <Plus className="mr-2 h-4 w-4" />
                                    {t('admin.plans.create_first')}
                                </Link>
                            </Button>
                        </CardContent>
                    </Card>
                )}
                </PageContent>
            </Page>
        </>
    );
}

PlansIndex.layout = (page: ReactElement) => <AdminLayout>{page}</AdminLayout>;

export default PlansIndex;
