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
import { FederationGroupForm } from './components/federation-group-form';
import { useSetBreadcrumbs } from '@/contexts/breadcrumb-context';
import { type ReactElement } from 'react';

interface Props {
    tenants: TenantSummaryResource[];
}

function FederationCreate({ tenants }: Props) {
    const { t } = useLaravelReactI18n();

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('breadcrumbs.dashboard'), href: admin.dashboard.url() },
        { title: t('admin.federation.title'), href: admin.federation.index.url() },
        { title: t('admin.federation.create_group'), href: admin.federation.create.url() },
    ];

    useSetBreadcrumbs(breadcrumbs);

    const handleSubmit = (data: Parameters<typeof FederationGroupForm>[0]['onSubmit'] extends (d: infer T) => void ? T : never) => {
        router.post(admin.federation.store.url(), data as unknown as Record<string, FormDataConvertible>);
    };

    return (
        <>
            <Head title={t('admin.federation.create_group')} />

            <Page>
                <PageHeader>
                    <PageHeaderContent>
                        <PageTitle icon={Network}>{t('admin.federation.create_group')}</PageTitle>
                        <PageDescription>{t('admin.federation.create_description')}</PageDescription>
                    </PageHeaderContent>
                </PageHeader>

                <PageContent>
                    <FederationGroupForm tenants={tenants} onSubmit={handleSubmit} />
                </PageContent>
            </Page>
        </>
    );
}

FederationCreate.layout = (page: ReactElement) => <AdminLayout>{page}</AdminLayout>;

export default FederationCreate;
