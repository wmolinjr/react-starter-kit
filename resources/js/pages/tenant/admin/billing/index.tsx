import { Head, router } from '@inertiajs/react';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import { type ReactElement } from 'react';
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
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { ArrowRight, Download, FileText, Package, Puzzle, Settings } from 'lucide-react';
import {
    SubscriptionOverviewWidget,
    CurrentPlanBanner,
    UsageDashboard,
    CostBreakdownWidget,
} from '@/components/shared/billing';
import { BillingPeriodProvider } from '@/hooks/billing';
import type {
    BreadcrumbItem,
    PlanResource,
    InvoiceResource,
} from '@/types';
import type {
    SubscriptionInfo,
    UsageMetric,
    CostBreakdown,
    NextInvoicePreview,
    AddonSubscriptionInfo,
    BundleSubscriptionInfo,
} from '@/types/billing';

interface BillingDashboardProps {
    plan: PlanResource | null;
    subscription: SubscriptionInfo | null;
    usage: Record<string, UsageMetric>;
    costs: CostBreakdown | null;
    nextInvoice: NextInvoicePreview | null;
    activeAddons: AddonSubscriptionInfo[];
    activeBundles: BundleSubscriptionInfo[];
    recentInvoices: InvoiceResource[];
    trialEndsAt: string | null;
    [key: string]: unknown;
}

function BillingDashboard({
    plan,
    subscription,
    usage,
    costs,
    nextInvoice,
    activeAddons,
    activeBundles,
    recentInvoices,
    trialEndsAt,
}: BillingDashboardProps) {
    const { t } = useLaravelReactI18n();

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('breadcrumbs.dashboard'), href: admin.dashboard.url() },
        { title: t('tenant.billing.title'), href: admin.billing.index.url() },
    ];

    useSetBreadcrumbs(breadcrumbs);

    const handleUpgrade = () => {
        router.visit('/admin/billing/plans');
    };

    const handleManageAddons = () => {
        router.visit('/admin/addons');
    };

    const handleManageSubscription = () => {
        router.get(admin.billing.portal.url());
    };

    const handleViewInvoices = () => {
        router.visit('/admin/billing/invoices');
    };

    const handleViewBundles = () => {
        router.visit('/admin/billing/bundles');
    };

    // Build overview object for SubscriptionOverviewWidget
    const overview = plan
        ? {
              plan: {
                  ...plan,
                  type: 'plan' as const,
                  pricing: {
                      monthly: {
                          price: plan.price,
                          formattedPrice: plan.formatted_price,
                      },
                  },
                  limits: plan.limits,
                  features: Object.entries(plan.features)
                      .filter(([_, value]) => value === true)
                      .map(([key]) => key),
              },
              subscription,
              addons: activeAddons,
              bundles: activeBundles,
              usage,
              costs: costs || {
                  planCost: plan.price,
                  addonsCost: 0,
                  bundlesCost: 0,
                  totalMonthlyCost: plan.price,
                  formattedPlanCost: plan.formatted_price,
                  formattedAddonsCost: '$0',
                  formattedBundlesCost: '$0',
                  formattedTotal: plan.formatted_price,
                  currency: plan.currency,
              },
              nextInvoice,
          }
        : null;

    return (
        <BillingPeriodProvider>
            <Head title={t('tenant.billing.title')} />

            <Page>
                <PageHeader>
                    <PageHeaderContent>
                        <PageTitle>{t('tenant.billing.title')}</PageTitle>
                        <PageDescription>
                            {t('tenant.billing.dashboard_description', {
                                default: 'Manage your subscription, add-ons, and billing',
                            })}
                        </PageDescription>
                    </PageHeaderContent>
                    <PageHeaderActions>
                        <Button variant="outline" onClick={handleManageSubscription}>
                            <Settings className="mr-2 h-4 w-4" />
                            {t('tenant.billing.billing_portal', { default: 'Billing Portal' })}
                        </Button>
                    </PageHeaderActions>
                </PageHeader>

                <PageContent className="space-y-6">
                    {/* Current Plan Banner */}
                    {plan && (
                        <CurrentPlanBanner
                            plan={plan}
                            subscription={subscription}
                            trialEndsAt={trialEndsAt}
                            onManage={handleManageSubscription}
                            onUpgrade={handleUpgrade}
                        />
                    )}

                    {/* Main Grid */}
                    <div className="grid gap-6 lg:grid-cols-2">
                        {/* Cost Breakdown */}
                        {costs && (
                            <CostBreakdownWidget
                                costs={costs}
                                showDetails
                                showPercentages
                            />
                        )}

                        {/* Usage Dashboard */}
                        {usage && Object.keys(usage).length > 0 && (
                            <UsageDashboard
                                usage={usage}
                                variant="list"
                                maxItems={5}
                            />
                        )}
                    </div>

                    {/* Quick Actions */}
                    <div className="grid gap-4 sm:grid-cols-3">
                        <Card
                            className="cursor-pointer transition-colors hover:bg-muted/50"
                            onClick={handleUpgrade}
                        >
                            <CardContent className="flex items-center gap-4 p-4">
                                <div className="bg-primary/10 text-primary rounded-lg p-2">
                                    <ArrowRight className="h-5 w-5" />
                                </div>
                                <div>
                                    <p className="font-medium">
                                        {t('tenant.billing.upgrade_plan', { default: 'Upgrade Plan' })}
                                    </p>
                                    <p className="text-muted-foreground text-sm">
                                        {t('tenant.billing.compare_plans', { default: 'Compare plans and features' })}
                                    </p>
                                </div>
                            </CardContent>
                        </Card>

                        <Card
                            className="cursor-pointer transition-colors hover:bg-muted/50"
                            onClick={handleManageAddons}
                        >
                            <CardContent className="flex items-center gap-4 p-4">
                                <div className="bg-blue-500/10 rounded-lg p-2 text-blue-500">
                                    <Puzzle className="h-5 w-5" />
                                </div>
                                <div>
                                    <p className="font-medium">
                                        {t('tenant.billing.browse_addons', { default: 'Browse Add-ons' })}
                                    </p>
                                    <p className="text-muted-foreground text-sm">
                                        {t('tenant.billing.extend_capabilities', { default: 'Extend your capabilities' })}
                                    </p>
                                </div>
                            </CardContent>
                        </Card>

                        <Card
                            className="cursor-pointer transition-colors hover:bg-muted/50"
                            onClick={handleViewBundles}
                        >
                            <CardContent className="flex items-center gap-4 p-4">
                                <div className="bg-amber-500/10 rounded-lg p-2 text-amber-500">
                                    <Package className="h-5 w-5" />
                                </div>
                                <div>
                                    <p className="font-medium">
                                        {t('tenant.billing.view_bundles', { default: 'View Bundles' })}
                                    </p>
                                    <p className="text-muted-foreground text-sm">
                                        {t('tenant.billing.save_with_bundles', { default: 'Save with bundle deals' })}
                                    </p>
                                </div>
                            </CardContent>
                        </Card>
                    </div>

                    {/* Active Add-ons & Bundles */}
                    {(activeAddons.length > 0 || activeBundles.length > 0) && (
                        <Card>
                            <CardHeader className="flex flex-row items-center justify-between pb-2">
                                <CardTitle className="text-base">
                                    {t('tenant.billing.active_subscriptions', { default: 'Active Subscriptions' })}
                                </CardTitle>
                                <Button variant="ghost" size="sm" onClick={handleManageAddons}>
                                    {t('common.manage', { default: 'Manage' })}
                                </Button>
                            </CardHeader>
                            <CardContent>
                                <div className="space-y-2">
                                    {activeBundles.map((bundle) => (
                                        <div
                                            key={bundle.id}
                                            className="flex items-center justify-between rounded-lg border p-3"
                                        >
                                            <div className="flex items-center gap-2">
                                                <Package className="text-muted-foreground h-4 w-4" />
                                                <div>
                                                    <p className="font-medium">{bundle.name}</p>
                                                    <p className="text-muted-foreground text-xs">
                                                        {bundle.addonCount}{' '}
                                                        {t('tenant.billing.addons_included', { default: 'add-ons' })}
                                                    </p>
                                                </div>
                                            </div>
                                            <span className="font-medium">{bundle.formattedPrice}</span>
                                        </div>
                                    ))}
                                    {activeAddons.map((addon) => (
                                        <div
                                            key={addon.id}
                                            className="flex items-center justify-between rounded-lg border p-3"
                                        >
                                            <div className="flex items-center gap-2">
                                                <Puzzle className="text-muted-foreground h-4 w-4" />
                                                <div>
                                                    <p className="font-medium">{addon.name}</p>
                                                    {addon.quantity > 1 && (
                                                        <p className="text-muted-foreground text-xs">
                                                            x{addon.quantity}
                                                        </p>
                                                    )}
                                                </div>
                                            </div>
                                            <span className="font-medium">{addon.formattedPrice}</span>
                                        </div>
                                    ))}
                                </div>
                            </CardContent>
                        </Card>
                    )}

                    {/* Recent Invoices */}
                    {recentInvoices.length > 0 && (
                        <Card>
                            <CardHeader className="flex flex-row items-center justify-between pb-2">
                                <CardTitle className="flex items-center gap-2 text-base">
                                    <FileText className="h-4 w-4" />
                                    {t('tenant.billing.recent_invoices', { default: 'Recent Invoices' })}
                                </CardTitle>
                                <Button variant="ghost" size="sm" onClick={handleViewInvoices}>
                                    {t('common.view_all', { default: 'View All' })}
                                </Button>
                            </CardHeader>
                            <CardContent>
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>{t('tenant.billing.date', { default: 'Date' })}</TableHead>
                                            <TableHead>{t('tenant.billing.amount', { default: 'Amount' })}</TableHead>
                                            <TableHead className="text-right">
                                                {t('common.actions', { default: 'Actions' })}
                                            </TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {recentInvoices.slice(0, 3).map((invoice) => (
                                            <TableRow key={invoice.id}>
                                                <TableCell>{invoice.date}</TableCell>
                                                <TableCell>{invoice.total}</TableCell>
                                                <TableCell className="text-right">
                                                    <Button variant="ghost" size="sm" asChild>
                                                        <a href={invoice.download_url}>
                                                            <Download className="mr-2 h-4 w-4" />
                                                            {t('tenant.billing.download', { default: 'Download' })}
                                                        </a>
                                                    </Button>
                                                </TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                            </CardContent>
                        </Card>
                    )}
                </PageContent>
            </Page>
        </BillingPeriodProvider>
    );
}

BillingDashboard.layout = (page: ReactElement) => <AdminLayout>{page}</AdminLayout>;

export default BillingDashboard;
