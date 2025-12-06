import { Head, router } from '@inertiajs/react';
import type { FormDataConvertible } from '@inertiajs/core';
import CentralAdminLayout from '@/layouts/central-admin-layout';
import admin from '@/routes/central/admin';
import { PlanForm } from './components/plan-form';
import { Page, PageHeader, PageHeaderContent, PageTitle, PageDescription, PageContent } from '@/components/page';
import { type BreadcrumbItem, type EnumOption } from '@/types';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import type { BadgePreset } from '@/components/badge-selector';

interface Addon {
    id: string;
    name: string;
    slug: string;
}

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

interface Props {
    addons: Addon[];
    featureDefinitions: FeatureDefinition[];
    limitDefinitions: LimitDefinition[];
    categories: EnumOption[];
    badgePresets: BadgePreset[];
}

export default function CreatePlan({ addons, featureDefinitions, limitDefinitions, categories, badgePresets }: Props) {
    const { t } = useLaravelReactI18n();

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('breadcrumbs.dashboard'), href: admin.dashboard.url() },
        { title: t('breadcrumbs.plan_catalog'), href: admin.plans.index.url() },
        { title: t('breadcrumbs.create_plan'), href: admin.plans.create.url() },
    ];

    const handleSubmit = (data: Parameters<typeof PlanForm>[0]['onSubmit'] extends (d: infer T) => void ? T : never) => {
        router.post(admin.plans.store.url(), data as unknown as Record<string, FormDataConvertible>);
    };

    return (
        <CentralAdminLayout breadcrumbs={breadcrumbs}>
            <Head title={t('admin.plans.create_plan')} />

            <Page>
                <PageHeader>
                    <PageHeaderContent>
                        <PageTitle>{t('admin.plans.create_plan')}</PageTitle>
                        <PageDescription>{t('admin.plans.add_new_plan')}</PageDescription>
                    </PageHeaderContent>
                </PageHeader>

                <PageContent>
                    <PlanForm
                        addons={addons}
                        featureDefinitions={featureDefinitions}
                        limitDefinitions={limitDefinitions}
                        categories={categories}
                        badgePresets={badgePresets}
                        onSubmit={handleSubmit}
                    />
                </PageContent>
            </Page>
        </CentralAdminLayout>
    );
}
