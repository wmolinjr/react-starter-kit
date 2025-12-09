import { Head, router } from '@inertiajs/react';
import type { FormDataConvertible } from '@inertiajs/core';
import AdminLayout from '@/layouts/central/admin-layout';
import admin from '@/routes/central/admin';
import { PlanForm } from './components/plan-form';
import { Page, PageHeader, PageHeaderContent, PageTitle, PageDescription, PageContent } from '@/components/shared/layout/page';
import { type BreadcrumbItem, type EnumOption } from '@/types';
import { type FeatureDefinition, type LimitDefinition, type AddonOptionForPlan } from '@/types/common';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import { useSetBreadcrumbs } from '@/contexts/breadcrumb-context';
import { type ReactElement } from 'react';

interface Props {
    addons: AddonOptionForPlan[];
    featureDefinitions: FeatureDefinition[];
    limitDefinitions: LimitDefinition[];
    categories: EnumOption[];
}

function CreatePlan({ addons, featureDefinitions, limitDefinitions, categories }: Props) {
    const { t } = useLaravelReactI18n();

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('breadcrumbs.dashboard'), href: admin.dashboard.url() },
        { title: t('breadcrumbs.plan_catalog'), href: admin.plans.index.url() },
        { title: t('breadcrumbs.create_plan'), href: admin.plans.create.url() },
    ];

    useSetBreadcrumbs(breadcrumbs);

    const handleSubmit = (data: Parameters<typeof PlanForm>[0]['onSubmit'] extends (d: infer T) => void ? T : never) => {
        router.post(admin.plans.store.url(), data as unknown as Record<string, FormDataConvertible>);
    };

    return (
        <>
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
                        onSubmit={handleSubmit}
                    />
                </PageContent>
            </Page>
        </>
    );
}

CreatePlan.layout = (page: ReactElement) => <AdminLayout>{page}</AdminLayout>;

export default CreatePlan;
