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
import { ArrowLeft } from 'lucide-react';
import {
    PlanCard,
    PlanComparisonTable,
    CurrentPlanBanner,
} from '@/components/shared/billing';
import { BillingPeriodProvider, useBillingPeriod } from '@/hooks/billing';
import { PricingToggle } from '@/components/shared/billing/primitives';
import type { BreadcrumbItem, PlanResource } from '@/types';
import type { SubscriptionInfo } from '@/types/billing';

interface PlansPageProps {
    plans: PlanResource[];
    currentPlan: PlanResource | null;
    subscription: SubscriptionInfo | null;
    [key: string]: unknown;
}

function PlansPageContent({
    plans,
    currentPlan,
    subscription,
}: PlansPageProps) {
    const { t } = useLaravelReactI18n();
    const { period, setPeriod } = useBillingPeriod();

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('admin.dashboard.title'), href: admin.dashboard.url() },
        { title: t('tenant.billing.title'), href: admin.billing.index.url() },
        { title: t('tenant.billing.plans', { default: 'Plans' }), href: admin.billing.plans.url() },
    ];

    useSetBreadcrumbs(breadcrumbs);

    const handleSelectPlan = (planSlug: string) => {
        router.post(admin.billing.checkout.url(), { plan: planSlug });
    };

    const handleBack = () => {
        router.visit(admin.billing.index.url());
    };

    return (
        <>
            <Head title={t('tenant.billing.compare_plans', { default: 'Compare Plans' })} />

            <Page>
                <PageHeader>
                    <PageHeaderContent>
                        <PageTitle>{t('tenant.billing.compare_plans', { default: 'Compare Plans' })}</PageTitle>
                        <PageDescription>
                            {t('tenant.billing.compare_plans_description', {
                                default: 'Choose the plan that best fits your needs',
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
                    {/* Current Plan Banner */}
                    {currentPlan && (
                        <CurrentPlanBanner
                            plan={currentPlan}
                            subscription={subscription}
                            onManage={() => router.get(admin.billing.portal.url())}
                        />
                    )}

                    {/* Billing Period Toggle */}
                    <div className="flex justify-center">
                        <PricingToggle
                            value={period}
                            onChange={setPeriod}
                            savings={t('billing.price.yearly_savings', { default: 'Save 20%' })}
                        />
                    </div>

                    {/* Plan Cards Grid */}
                    <div className="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                        {plans.map((plan) => (
                            <PlanCard
                                key={plan.slug}
                                plan={plan}
                                currentPlanSlug={currentPlan?.slug}
                                billingPeriod={period}
                                onSelect={handleSelectPlan}
                                showFeatures
                            />
                        ))}
                    </div>

                    {/* Plan Comparison Table */}
                    <div className="mt-12">
                        <h2 className="mb-6 text-center text-2xl font-bold">
                            {t('tenant.billing.detailed_comparison', { default: 'Detailed Comparison' })}
                        </h2>
                        <PlanComparisonTable
                            plans={plans}
                            currentPlanSlug={currentPlan?.slug}
                            billingPeriod={period}
                            onSelect={handleSelectPlan}
                        />
                    </div>
                </PageContent>
            </Page>
        </>
    );
}

function PlansPage(props: PlansPageProps) {
    return (
        <BillingPeriodProvider>
            <PlansPageContent {...props} />
        </BillingPeriodProvider>
    );
}

PlansPage.layout = (page: ReactElement) => <AdminLayout>{page}</AdminLayout>;

export default PlansPage;
