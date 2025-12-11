import { Head } from '@inertiajs/react';
import AdminLayout from '@/layouts/central/admin-layout';
import admin from '@/routes/central/admin';
import { AddonForm } from './components/addon-form';
import { Page, PageHeader, PageHeaderContent, PageTitle, PageDescription, PageContent } from '@/components/shared/layout/page';
import { type BreadcrumbItem, type FeatureDefinitionResource, type LimitDefinitionResource, type CategoryOptionResource } from '@/types';
import type { AddonTypeOption } from '@/types/enums';
import { useSetBreadcrumbs } from '@/contexts/breadcrumb-context';
import { type ReactElement } from 'react';
import { useLaravelReactI18n } from 'laravel-react-i18n';

interface Props {
    types: AddonTypeOption[];
    plans: { id: string; name: string; slug: string }[];
    featureDefinitions: FeatureDefinitionResource[];
    limitDefinitions: LimitDefinitionResource[];
    categories: CategoryOptionResource[];
}

function CatalogCreate({ types, plans, featureDefinitions, limitDefinitions, categories }: Props) {
    const { t } = useLaravelReactI18n();

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('dashboard.page.title'), href: admin.dashboard.url() },
        { title: t('catalog.page.title'), href: admin.catalog.index.url() },
        { title: t('catalog.page.create_addon'), href: admin.catalog.create.url() },
    ];

    useSetBreadcrumbs(breadcrumbs);

    return (
        <>
            <Head title={t('catalog.page.create_addon')} />

            <Page>
                <PageHeader>
                    <PageHeaderContent>
                        <PageTitle>{t('catalog.page.create_addon')}</PageTitle>
                        <PageDescription>{t('catalog.page.add_new_item')}</PageDescription>
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
