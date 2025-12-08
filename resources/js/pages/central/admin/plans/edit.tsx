import { Head, router } from '@inertiajs/react';
import type { FormDataConvertible } from '@inertiajs/core';
import AdminLayout from '@/layouts/central/admin-layout';
import admin from '@/routes/central/admin';
import { PlanForm, type PlanData } from './components/plan-form';
import { Page, PageHeader, PageHeaderContent, PageTitle, PageDescription, PageContent } from '@/components/shared/layout/page';
import { type BreadcrumbItem, type EnumOption } from '@/types';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import type { BadgePreset } from '@/components/central/forms/badge-selector';
import { useSetBreadcrumbs } from '@/contexts/breadcrumb-context';
import { type ReactElement } from 'react';

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

interface PlanEditData extends PlanData {
    id: string; // Required for edit page (overrides optional from PlanData)
    name_display: string;
}

interface Props {
    plan: PlanEditData;
    addons: Addon[];
    featureDefinitions: FeatureDefinition[];
    limitDefinitions: LimitDefinition[];
    categories: EnumOption[];
    badgePresets: BadgePreset[];
}

function EditPlan({ plan, addons, featureDefinitions, limitDefinitions, categories, badgePresets }: Props) {
    const { t } = useLaravelReactI18n();

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('breadcrumbs.dashboard'), href: admin.dashboard.url() },
        { title: t('breadcrumbs.plan_catalog'), href: admin.plans.index.url() },
        { title: plan.name_display, href: admin.plans.edit.url(plan.id) },
    ];

    useSetBreadcrumbs(breadcrumbs);

    const handleSubmit = (data: Parameters<typeof PlanForm>[0]['onSubmit'] extends (d: infer T) => void ? T : never) => {
        router.put(admin.plans.update.url(plan.id), data as unknown as Record<string, FormDataConvertible>);
    };

    return (
        <>
            <Head title={`${t('admin.plans.edit_plan')}: ${plan.name_display}`} />

            <Page>
                <PageHeader>
                    <PageHeaderContent>
                        <PageTitle>{t('admin.plans.edit_plan')}</PageTitle>
                        <PageDescription>{t('admin.plans.update')} {plan.name_display}</PageDescription>
                    </PageHeaderContent>
                </PageHeader>

                <PageContent>
                    <PlanForm
                        plan={plan}
                        addons={addons}
                        featureDefinitions={featureDefinitions}
                        limitDefinitions={limitDefinitions}
                        categories={categories}
                        badgePresets={badgePresets}
                        onSubmit={handleSubmit}
                    />
                </PageContent>
            </Page>
        </>
    );
}

EditPlan.layout = (page: ReactElement) => <AdminLayout>{page}</AdminLayout>;

export default EditPlan;
