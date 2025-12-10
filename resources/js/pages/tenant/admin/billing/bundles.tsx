import { Head, router } from '@inertiajs/react';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import { type ReactElement, useMemo, useState } from 'react';
import AdminLayout from '@/layouts/tenant/admin-layout';
import admin from '@/routes/tenant/admin';
import { useSetBreadcrumbs } from '@/contexts/breadcrumb-context';
import {
    Page,
    PageHeader,
    PageHeaderContent,
    PageTitle,
    PageDescription,
    PageContent,
    PageHeaderActions,
} from '@/components/shared/layout/page';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { ArrowLeft, Package, CheckCircle2, ShoppingCart } from 'lucide-react';
import {
    BundleCard,
    PricingToggle,
} from '@/components/shared/billing';
import { BillingPeriodProvider, useBillingPeriod } from '@/hooks/billing';
import type { BreadcrumbItem, PlanResource, BundleResource } from '@/types';

interface ActiveBundle {
    purchase_id: string;
    bundle_slug: string | null;
    bundle_name: string | null;
    addons: Array<{ name: string; slug: string }>;
    addon_count: number;
    total_monthly: number;
    started_at: string | null;
}

interface BundlesPageProps {
    bundles: BundleResource[];
    activeBundles: ActiveBundle[];
    activeAddonSlugs: string[];
    currentPlan: PlanResource | null;
    [key: string]: unknown;
}

function BundlesPageContent({
    bundles,
    activeBundles,
    activeAddonSlugs,
    currentPlan,
}: BundlesPageProps) {
    const { t } = useLaravelReactI18n();
    const { period, setPeriod } = useBillingPeriod();
    const [purchasingBundle, setPurchasingBundle] = useState<string | null>(null);

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('breadcrumbs.dashboard'), href: admin.dashboard.url() },
        { title: t('tenant.billing.title'), href: admin.billing.index.url() },
        { title: t('tenant.billing.bundles', { default: 'Bundles' }), href: admin.billing.bundles.url() },
    ];

    useSetBreadcrumbs(breadcrumbs);

    const handleBack = () => {
        router.visit(admin.billing.index.url());
    };

    const handlePurchaseBundle = (bundleSlug: string) => {
        setPurchasingBundle(bundleSlug);
        // Navigate to addons page with bundle pre-selected for purchase
        router.post(admin.addons.purchase.url(), {
            type: 'bundle',
            slug: bundleSlug,
            billing_period: period,
        }, {
            onFinish: () => setPurchasingBundle(null),
        });
    };

    // Check which bundles have conflicts with existing addons
    const bundleConflicts = useMemo(() => {
        const conflicts: Record<string, string[]> = {};
        bundles.forEach((bundle) => {
            const conflictingAddons = bundle.addons
                ?.filter((addon) => activeAddonSlugs.includes(addon.slug))
                .map((addon) => addon.name) ?? [];
            if (conflictingAddons.length > 0) {
                conflicts[bundle.slug] = conflictingAddons;
            }
        });
        return conflicts;
    }, [bundles, activeAddonSlugs]);

    // Check which bundles are already active
    const activeBundleSlugs = useMemo(() => {
        return new Set(activeBundles.map((b) => b.bundle_slug).filter(Boolean));
    }, [activeBundles]);

    return (
        <>
            <Head title={t('tenant.billing.browse_bundles', { default: 'Browse Bundles' })} />

            <Page>
                <PageHeader>
                    <PageHeaderContent>
                        <PageTitle>
                            <Package className="mr-2 h-6 w-6" />
                            {t('tenant.billing.browse_bundles', { default: 'Browse Bundles' })}
                        </PageTitle>
                        <PageDescription>
                            {t('tenant.billing.bundles_description', {
                                default: 'Save money by purchasing add-ons together in curated bundles',
                            })}
                        </PageDescription>
                    </PageHeaderContent>
                    <PageHeaderActions>
                        <Button variant="outline" onClick={handleBack}>
                            <ArrowLeft className="mr-2 h-4 w-4" />
                            {t('common.back', { default: 'Back' })}
                        </Button>
                    </PageHeaderActions>
                </PageHeader>

                <PageContent className="space-y-8">
                    {/* Active Bundles */}
                    {activeBundles.length > 0 && (
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2 text-base">
                                    <CheckCircle2 className="h-4 w-4 text-green-500" />
                                    {t('tenant.billing.active_bundles', { default: 'Your Active Bundles' })}
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="space-y-3">
                                    {activeBundles.map((bundle) => (
                                        <div
                                            key={bundle.purchase_id}
                                            className="flex items-center justify-between rounded-lg border p-3"
                                        >
                                            <div className="flex items-center gap-3">
                                                <Package className="text-muted-foreground h-5 w-5" />
                                                <div>
                                                    <p className="font-medium">{bundle.bundle_name}</p>
                                                    <p className="text-muted-foreground text-sm">
                                                        {bundle.addon_count} {t('tenant.billing.addons', { default: 'add-ons' })}
                                                    </p>
                                                </div>
                                            </div>
                                            <Badge variant="secondary">
                                                ${(bundle.total_monthly / 100).toFixed(2)}/mo
                                            </Badge>
                                        </div>
                                    ))}
                                </div>
                            </CardContent>
                        </Card>
                    )}

                    {/* Billing Period Toggle */}
                    <div className="flex justify-center">
                        <PricingToggle
                            value={period}
                            onChange={setPeriod}
                            savings={t('billing.yearly_savings', { default: 'Save 20%' })}
                        />
                    </div>

                    {/* Bundles Grid */}
                    {bundles.length === 0 ? (
                        <Card>
                            <CardContent className="py-12 text-center">
                                <Package className="text-muted-foreground mx-auto mb-4 h-12 w-12" />
                                <h3 className="text-lg font-semibold">
                                    {t('tenant.billing.no_bundles', { default: 'No bundles available' })}
                                </h3>
                                <p className="text-muted-foreground mt-2">
                                    {t('tenant.billing.no_bundles_description', {
                                        default: 'There are no bundles available for your current plan.',
                                    })}
                                </p>
                            </CardContent>
                        </Card>
                    ) : (
                        <div className="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                            {bundles.map((bundle) => {
                                const isPurchased = activeBundleSlugs.has(bundle.slug);
                                const conflicts = bundleConflicts[bundle.slug] ?? [];
                                const hasConflicts = conflicts.length > 0;

                                return (
                                    <BundleCard
                                        key={bundle.slug}
                                        bundle={bundle}
                                        billingPeriod={period}
                                        isPurchased={isPurchased}
                                        hasConflicts={hasConflicts}
                                        conflictMessage={hasConflicts
                                            ? t('tenant.billing.bundle_conflict', {
                                                default: 'You already have: :addons',
                                                addons: conflicts.join(', '),
                                            }).replace(':addons', conflicts.join(', '))
                                            : undefined
                                        }
                                        isLoading={purchasingBundle === bundle.slug}
                                        onPurchase={() => handlePurchaseBundle(bundle.slug)}
                                    />
                                );
                            })}
                        </div>
                    )}
                </PageContent>
            </Page>
        </>
    );
}

function BundlesPage(props: BundlesPageProps) {
    return (
        <BillingPeriodProvider>
            <BundlesPageContent {...props} />
        </BillingPeriodProvider>
    );
}

BundlesPage.layout = (page: ReactElement) => <AdminLayout>{page}</AdminLayout>;

export default BundlesPage;
