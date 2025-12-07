import { Head, router } from '@inertiajs/react';
import type { FormDataConvertible } from '@inertiajs/core';
import AdminLayout from '@/layouts/central/admin-layout';
import { RoleForm } from './components/role-form';
import { Page, PageHeader, PageHeaderContent, PageTitle, PageDescription, PageContent } from '@/components/shared/layout/page';
import { type BreadcrumbItem } from '@/types';
import admin from '@/routes/central/admin';
import { useLaravelReactI18n } from 'laravel-react-i18n';

interface Permission {
    id: string;
    name: string;
    description: string | null;
}

interface CategoryPermissions {
    label: string;
    permissions: Permission[];
}

interface Props {
    permissions: Record<string, CategoryPermissions>;
}

export default function CreateRole({ permissions }: Props) {
    const { t } = useLaravelReactI18n();

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('breadcrumbs.dashboard'), href: admin.dashboard.url() },
        { title: t('breadcrumbs.role_management'), href: admin.roles.index.url() },
        { title: t('breadcrumbs.create_role'), href: admin.roles.create.url() },
    ];

    const handleSubmit = (data: Parameters<typeof RoleForm>[0]['onSubmit'] extends (d: infer T) => void ? T : never) => {
        router.post(admin.roles.store.url(), data as unknown as Record<string, FormDataConvertible>);
    };

    return (
        <AdminLayout breadcrumbs={breadcrumbs}>
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
        </AdminLayout>
    );
}
