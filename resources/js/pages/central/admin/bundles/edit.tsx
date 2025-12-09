import { Head } from '@inertiajs/react';
import AdminLayout from '@/layouts/central/admin-layout';
import admin from '@/routes/central/admin';
import { BundleForm } from './components/bundle-form';
import { Page, PageHeader, PageHeaderContent, PageTitle, PageDescription, PageContent } from '@/components/shared/layout/page';
import { type BreadcrumbItem, type BundleResource } from '@/types';
import { useSetBreadcrumbs } from '@/contexts/breadcrumb-context';
import { type ReactElement } from 'react';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import type { AddonOption, PlanOption } from './components/bundle-form';

interface Props {
    bundle: BundleResource;
    addons: AddonOption[];
    plans: PlanOption[];
}

function BundleEdit({ bundle, addons, plans }: Props) {
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
        addons: bundle.addons?.map(a => ({
            addon_id: a.addon_id,
            quantity: a.quantity,
        })) ?? [],
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
                        isEdit
                    />
                </PageContent>
            </Page>
        </>
    );
}

BundleEdit.layout = (page: ReactElement) => <AdminLayout>{page}</AdminLayout>;

export default BundleEdit;
