import { Head } from '@inertiajs/react';
import AdminLayout from '@/layouts/central/admin-layout';
import admin from '@/routes/central/admin';
import { BundleForm } from './components/bundle-form';
import { Page, PageHeader, PageHeaderContent, PageTitle, PageDescription, PageContent } from '@/components/shared/layout/page';
import { type BreadcrumbItem } from '@/types';
import { useSetBreadcrumbs } from '@/contexts/breadcrumb-context';
import { type ReactElement } from 'react';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import type { AddonOption, PlanOption } from './components/bundle-form';

interface Props {
    addons: AddonOption[];
    plans: PlanOption[];
}

function BundleCreate({ addons, plans }: Props) {
    const { t } = useLaravelReactI18n();

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('admin.dashboard.title'), href: admin.dashboard.url() },
        { title: t('admin.bundles.title'), href: admin.bundles.index.url() },
        { title: t('admin.bundles.create_bundle'), href: admin.bundles.create.url() },
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
                    <BundleForm addons={addons} plans={plans} />
                </PageContent>
            </Page>
        </>
    );
}

BundleCreate.layout = (page: ReactElement) => <AdminLayout>{page}</AdminLayout>;

export default BundleCreate;
