import { Head } from '@inertiajs/react';
import AdminLayout from '@/layouts/central/admin-layout';
import admin from '@/routes/central/admin';
import { BundleForm } from './components/bundle-form';
import { Page, PageHeader, PageHeaderContent, PageTitle, PageDescription, PageContent } from '@/components/shared/layout/page';
import { type BreadcrumbItem } from '@/types';
import { useSetBreadcrumbs } from '@/contexts/breadcrumb-context';
import { type ReactElement } from 'react';
import { useLaravelReactI18n } from 'laravel-react-i18n';

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
    id: string;
    addon_id: string;
    slug: string;
    name: string;
    type: string;
    type_label: string;
    price_monthly: number;
    quantity: number;
}

interface Bundle {
    id: string;
    slug: string;
    name: Record<string, string>;
    name_display: string;
    description: Record<string, string>;
    active: boolean;
    discount_percent: number;
    price_monthly: number | null;
    price_yearly: number | null;
    badge: string | null;
    icon: string;
    icon_color: string;
    features: Record<string, string>[];
    sort_order: number;
    addons: BundleAddon[];
    plan_ids: string[];
}

interface BadgePreset {
    value: string;
    label: string;
    icon: string;
    bg: string;
    text: string;
    border: string;
}

interface Props {
    bundle: Bundle;
    addons: AddonOption[];
    plans: PlanOption[];
    badgePresets: BadgePreset[];
}

function BundleEdit({ bundle, addons, plans, badgePresets }: Props) {
    const { t } = useLaravelReactI18n();

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('breadcrumbs.dashboard'), href: admin.dashboard.url() },
        { title: t('breadcrumbs.bundle_catalog'), href: admin.bundles.index.url() },
        { title: bundle.name_display, href: admin.bundles.edit.url(bundle.id) },
    ];

    useSetBreadcrumbs(breadcrumbs);

    // Transform bundle addons to the format expected by the form
    const bundleForForm = {
        ...bundle,
        addons: bundle.addons.map(a => ({
            addon_id: a.addon_id,
            quantity: a.quantity,
        })),
    };

    return (
        <>
            <Head title={`${t('admin.bundles.edit_bundle')} - ${bundle.name_display}`} />

            <Page>
                <PageHeader>
                    <PageHeaderContent>
                        <PageTitle>{t('admin.bundles.edit_bundle')}</PageTitle>
                        <PageDescription>{bundle.name_display}</PageDescription>
                    </PageHeaderContent>
                </PageHeader>

                <PageContent>
                    <BundleForm
                        bundle={bundleForForm}
                        addons={addons}
                        plans={plans}
                        badgePresets={badgePresets}
                        isEdit
                    />
                </PageContent>
            </Page>
        </>
    );
}

BundleEdit.layout = (page: ReactElement) => <AdminLayout>{page}</AdminLayout>;

export default BundleEdit;
