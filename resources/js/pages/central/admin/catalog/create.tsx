import { Head } from '@inertiajs/react';
import AdminLayout from '@/layouts/central/admin-layout';
import admin from '@/routes/central/admin';
import { AddonForm } from './components/addon-form';
import { Page, PageHeader, PageHeaderContent, PageTitle, PageDescription, PageContent } from '@/components/shared/layout/page';
import { type BreadcrumbItem } from '@/types';
import { type FeatureDefinition, type LimitDefinition, type CategoryOption, type AddonTypeInfo } from '@/types/common';
import { useSetBreadcrumbs } from '@/contexts/breadcrumb-context';
import { type ReactElement } from 'react';
import { useLaravelReactI18n } from 'laravel-react-i18n';

interface Props {
    types: AddonTypeInfo[];
    plans: { id: string; name: string; slug: string }[];
    featureDefinitions: FeatureDefinition[];
    limitDefinitions: LimitDefinition[];
    categories: CategoryOption[];
}

function CatalogCreate({ types, plans, featureDefinitions, limitDefinitions, categories }: Props) {
    const { t } = useLaravelReactI18n();

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('breadcrumbs.dashboard'), href: admin.dashboard.url() },
        { title: t('breadcrumbs.addon_catalog'), href: admin.catalog.index.url() },
        { title: t('breadcrumbs.create_addon'), href: admin.catalog.create.url() },
    ];

    useSetBreadcrumbs(breadcrumbs);

    return (
        <>
            <Head title={t('admin.catalog.create_addon')} />

            <Page>
                <PageHeader>
                    <PageHeaderContent>
                        <PageTitle>{t('admin.catalog.create_addon')}</PageTitle>
                        <PageDescription>{t('admin.catalog.add_new_item')}</PageDescription>
                    </PageHeaderContent>
                </PageHeader>

                <PageContent>
                    <AddonForm
                        types={types}
                        plans={plans}
                        featureDefinitions={featureDefinitions}
                        limitDefinitions={limitDefinitions}
                        categories={categories}
                    />
                </PageContent>
            </Page>
        </>
    );
}

CatalogCreate.layout = (page: ReactElement) => <AdminLayout>{page}</AdminLayout>;

export default CatalogCreate;
