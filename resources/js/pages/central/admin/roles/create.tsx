import { Head, router } from '@inertiajs/react';
import type { FormDataConvertible } from '@inertiajs/core';
import AdminLayout from '@/layouts/central/admin-layout';
import { RoleForm } from './components/role-form';
import { Page, PageHeader, PageHeaderContent, PageTitle, PageDescription, PageContent } from '@/components/shared/layout/page';
import { type BreadcrumbItem, type CategoryPermissions } from '@/types';
import { useSetBreadcrumbs } from '@/contexts/breadcrumb-context';
import { type ReactElement } from 'react';
import admin from '@/routes/central/admin';
import { useLaravelReactI18n } from 'laravel-react-i18n';

interface Props {
    permissions: Record<string, CategoryPermissions>;
}

function CreateRole({ permissions }: Props) {
    const { t } = useLaravelReactI18n();

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('admin.dashboard.title'), href: admin.dashboard.url() },
        { title: t('admin.roles.title'), href: admin.roles.index.url() },
        { title: t('roles.create_title'), href: admin.roles.create.url() },
    ];

    useSetBreadcrumbs(breadcrumbs);

    const handleSubmit = (data: Parameters<typeof RoleForm>[0]['onSubmit'] extends (d: infer T) => void ? T : never) => {
        router.post(admin.roles.store.url(), data as unknown as Record<string, FormDataConvertible>);
    };

    return (
        <>
            <Head title={t('admin.roles.create_role')} />

            <Page>
                <PageHeader>
                    <PageHeaderContent>
                        <PageTitle>{t('admin.roles.create_role')}</PageTitle>
                        <PageDescription>{t('admin.roles.create_description')}</PageDescription>
                    </PageHeaderContent>
                </PageHeader>

                <PageContent>
                    <RoleForm permissions={permissions} onSubmit={handleSubmit} />
                </PageContent>
            </Page>
        </>
    );
}

CreateRole.layout = (page: ReactElement) => <AdminLayout>{page}</AdminLayout>;

export default CreateRole;
