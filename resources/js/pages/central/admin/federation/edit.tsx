import { Head, router } from '@inertiajs/react';
import type { FormDataConvertible } from '@inertiajs/core';
import AdminLayout from '@/layouts/central/admin-layout';
import admin from '@/routes/central/admin';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import { Network } from 'lucide-react';
import {
    Page,
    PageContent,
    PageDescription,
    PageHeader,
    PageHeaderContent,
    PageTitle,
} from '@/components/shared/layout/page';
import { type BreadcrumbItem, type TenantSummaryResource } from '@/types';
import { FederationGroupForm, type FederationGroupFormData } from './components/federation-group-form';
import { useSetBreadcrumbs } from '@/contexts/breadcrumb-context';
import { type ReactElement } from 'react';

interface Props {
    group: FederationGroupFormData & { id: string };
    tenants: TenantSummaryResource[];
}

function FederationEdit({ group, tenants }: Props) {
    const { t } = useLaravelReactI18n();

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('breadcrumbs.dashboard'), href: admin.dashboard.url() },
        { title: t('admin.federation.title'), href: admin.federation.index.url() },
        { title: group.name, href: admin.federation.show.url(group.id) },
        { title: t('common.edit'), href: admin.federation.edit.url(group.id) },
    ];

    useSetBreadcrumbs(breadcrumbs);

    const handleSubmit = (data: Parameters<typeof FederationGroupForm>[0]['onSubmit'] extends (d: infer T) => void ? T : never) => {
        router.put(admin.federation.update.url(group.id), data as unknown as Record<string, FormDataConvertible>);
    };

    return (
        <>
            <Head title={`${t('common.edit')}: ${group.name}`} />

            <Page>
                <PageHeader>
                    <PageHeaderContent>
                        <PageTitle icon={Network}>{t('admin.federation.edit_group')}</PageTitle>
                        <PageDescription>
                            {t('admin.federation.edit_description', { name: group.name })}
                        </PageDescription>
                    </PageHeaderContent>
                </PageHeader>

                <PageContent>
                    <FederationGroupForm group={group} tenants={tenants} onSubmit={handleSubmit} />
                </PageContent>
            </Page>
        </>
    );
}

FederationEdit.layout = (page: ReactElement) => <AdminLayout>{page}</AdminLayout>;

export default FederationEdit;
