import { Head } from '@inertiajs/react';
import AdminLayout from '@/layouts/central/admin-layout';
import admin from '@/routes/central/admin';
import { BundleForm } from './components/bundle-form';
import { Page, PageHeader, PageHeaderContent, PageTitle, PageDescription, PageContent } from '@/components/shared/layout/page';
import { type BreadcrumbItem } from '@/types';
import { useSetBreadcrumbs } from '@/contexts/breadcrumb-context';
import { type ReactElement } from 'react';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import type { BadgePresetOption } from '@/types/enums';

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

interface Props {
    addons: AddonOption[];
    plans: PlanOption[];
    badgePresets: BadgePresetOption[];
}

function BundleCreate({ addons, plans, badgePresets }: Props) {
    const { t } = useLaravelReactI18n();

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('breadcrumbs.dashboard'), href: admin.dashboard.url() },
        { title: t('breadcrumbs.bundle_catalog'), href: admin.bundles.index.url() },
        { title: t('breadcrumbs.create_bundle'), href: admin.bundles.create.url() },
    ];

    useSetBreadcrumbs(breadcrumbs);

    return (
        <>
            <Head title={t('admin.bundles.create_bundle')} />

            <Page>
                <PageHeader>
                    <PageHeaderContent>
                        <PageTitle>{t('admin.bundles.create_bundle')}</PageTitle>
                        <PageDescription>{t('admin.bundles.description')}</PageDescription>
                    </PageHeaderContent>
                </PageHeader>

                <PageContent>
                    <BundleForm addons={addons} plans={plans} badgePresets={badgePresets} />
                </PageContent>
            </Page>
        </>
    );
}

BundleCreate.layout = (page: ReactElement) => <AdminLayout>{page}</AdminLayout>;

export default BundleCreate;
