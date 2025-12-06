import { Head } from '@inertiajs/react';
import CentralAdminLayout from '@/layouts/central-admin-layout';
import admin from '@/routes/central/admin';
import { AddonForm } from './components/addon-form';
import { Page, PageHeader, PageHeaderContent, PageTitle, PageDescription, PageContent } from '@/components/page';
import { type BreadcrumbItem } from '@/types';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import { type BadgePreset } from '@/components/badge-selector';

interface FeatureDefinition {
    id: string;
    key: string;
    name: string;
    description: string | null;
    category: string | null;
    icon: string | null;
}

interface LimitDefinition {
    id: string;
    key: string;
    name: string;
    description: string | null;
    unit: string | null;
    unit_label: string | null;
    default_value: number;
    allows_unlimited: boolean;
    icon: string | null;
}

interface CategoryOption {
    value: string;
    label: string;
}

interface Props {
    types: { value: string; label: string }[];
    plans: { id: string; name: string; slug: string }[];
    featureDefinitions: FeatureDefinition[];
    limitDefinitions: LimitDefinition[];
    categories: CategoryOption[];
    badgePresets: BadgePreset[];
}

export default function CatalogCreate({ types, plans, featureDefinitions, limitDefinitions, categories, badgePresets }: Props) {
    const { t } = useLaravelReactI18n();

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('breadcrumbs.dashboard'), href: admin.dashboard.url() },
        { title: t('breadcrumbs.addon_catalog'), href: admin.catalog.index.url() },
        { title: t('breadcrumbs.create_addon'), href: admin.catalog.create.url() },
    ];

    return (
        <CentralAdminLayout breadcrumbs={breadcrumbs}>
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
                        badgePresets={badgePresets}
                    />
                </PageContent>
            </Page>
        </CentralAdminLayout>
    );
}
