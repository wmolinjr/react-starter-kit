import admin from '@/routes/tenant/admin';
import { Head } from '@inertiajs/react';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import AdminLayout from '@/layouts/tenant/admin-layout';
import { useAddons } from '@/hooks/tenant/use-addons';
import { usePurchase } from '@/hooks/tenant/use-purchase';
import { AddonCard } from '@/components/tenant/addons/addon-card';
import { ActiveAddonCard } from '@/components/tenant/addons/active-addon-card';
import { PurchaseModal } from '@/components/tenant/addons/purchase-modal';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { AlertCircle } from 'lucide-react';
import { useState } from 'react';
import type { AddonCatalogItem } from '@/types/addons';
import type { BillingPeriod } from '@/types/enums';
import { Page, PageHeader, PageHeaderContent, PageTitle, PageDescription, PageContent } from '@/components/shared/layout/page';
import { type BreadcrumbItem } from '@/types';

import { useSetBreadcrumbs } from '@/contexts/breadcrumb-context';
import { type ReactElement } from 'react';

function AddonsIndex() {
    const { t } = useLaravelReactI18n();

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('breadcrumbs.dashboard'), href: admin.dashboard.url() },
        { title: 'Add-ons', href: admin.addons.index.url() },
    ];

    useSetBreadcrumbs(breadcrumbs);

    const { active, catalog, formattedMonthlyCost } = useAddons();
    const { purchase, cancel, isPurchasing, isCanceling, error } = usePurchase();
    const [selectedAddon, setSelectedAddon] = useState<AddonCatalogItem | null>(null);

    const handlePurchase = (slug: string, billingPeriod: BillingPeriod) => {
        const addon = catalog.find((a) => a.slug === slug);
        if (addon && addon.billing.monthly && addon.billing.yearly) {
            setSelectedAddon(addon);
        } else {
            purchase(slug, 1, billingPeriod);
        }
    };

    const handleModalConfirm = (slug: string, quantity: number, billingPeriod: BillingPeriod) => {
        purchase(slug, quantity, billingPeriod);
        setSelectedAddon(null);
    };

    const handleCancel = (addonId: string) => {
        cancel(addonId);
    };

    return (
        <>
            <Head title="Add-ons" />

            <Page>
                <PageHeader>
                    <PageHeaderContent>
                        <PageTitle>Add-ons</PageTitle>
                        <PageDescription>
                            {t('tenant.addons.description')}
                        </PageDescription>
                    </PageHeaderContent>
                </PageHeader>

                <PageContent>
                    {error && (
                        <Alert variant="destructive">
                            <AlertCircle className="h-4 w-4" />
                            <AlertDescription>{error}</AlertDescription>
                        </Alert>
                    )}

                    {active.length > 0 && (
                        <div className="space-y-4">
                            <div className="flex items-center justify-between">
                                <h2 className="text-lg font-semibold">{t('tenant.addons.active_addons')}</h2>
                                <span className="text-muted-foreground text-sm">
                                    {t('tenant.addons.monthly_cost')}: {formattedMonthlyCost}
                                </span>
                            </div>
                            <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                                {active.map((addon) => (
                                    <ActiveAddonCard
                                        key={addon.id}
                                        addon={addon}
                                        onCancel={handleCancel}
                                        disabled={isCanceling}
                                    />
                                ))}
                            </div>
                        </div>
                    )}

                    <div className="space-y-4">
                        <h2 className="text-lg font-semibold">{t('tenant.addons.available_addons')}</h2>
                        <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                            {catalog.map((addon) => (
                                <AddonCard
                                    key={addon.slug}
                                    addon={addon}
                                    onPurchase={handlePurchase}
                                    disabled={isPurchasing}
                                />
                            ))}
                        </div>
                    </div>
                </PageContent>
            </Page>

            <PurchaseModal
                addon={selectedAddon}
                open={!!selectedAddon}
                onClose={() => setSelectedAddon(null)}
                onConfirm={handleModalConfirm}
                isPurchasing={isPurchasing}
            />
        </>
    );
}

AddonsIndex.layout = (page: ReactElement) => <AdminLayout>{page}</AdminLayout>;

export default AddonsIndex;
