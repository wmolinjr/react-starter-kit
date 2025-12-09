import admin from '@/routes/tenant/admin';
import { Head, router } from '@inertiajs/react';
import AdminLayout from '@/layouts/tenant/admin-layout';
import { RoleForm } from './components/role-form';
import { Page, PageHeader, PageHeaderContent, PageTitle, PageDescription, PageContent } from '@/components/shared/layout/page';
import { type BreadcrumbItem, type CategoryPermissions } from '@/types';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import { Shield } from 'lucide-react';

import { useSetBreadcrumbs } from '@/contexts/breadcrumb-context';
import { type ReactElement } from 'react';

interface Role {
    id: string;
    name: string;
    display_name: string;
    description: string | null;
    is_protected: boolean;
    permission_ids: string[];
}

interface Props {
    role: Role;
    permissions: Record<string, CategoryPermissions>;
}

function EditRole({ role, permissions }: Props) {
    const { t } = useLaravelReactI18n();

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('breadcrumbs.dashboard'), href: admin.dashboard.url() },
        { title: t('breadcrumbs.settings'), href: admin.settings.index.url() },
        { title: t('breadcrumbs.custom_roles'), href: admin.settings.roles.index.url() },
        { title: role.display_name, href: admin.settings.roles.edit.url(role.id) },
    ];

    useSetBreadcrumbs(breadcrumbs);

    const handleSubmit = (data: Parameters<typeof RoleForm>[0]['onSubmit'] extends (d: infer T) => void ? T : never) => {
        router.put(admin.settings.roles.update.url(role.id), data);
    };

    return (
        <>
            <Head title={`${t('roles.edit_title')} ${role.display_name}`} />

            <Page>
                <PageHeader>
                    <PageHeaderContent>
                        <PageTitle icon={Shield}>{t('roles.edit_title')}</PageTitle>
                        <PageDescription>{t('roles.edit_description', { name: role.display_name })}</PageDescription>
                    </PageHeaderContent>
                </PageHeader>

                <PageContent>
                    <RoleForm
                        role={{
                            id: role.id,
                            name: role.name,
                            display_name: role.display_name,
                            description: role.description ?? '',
                            permissions: role.permission_ids,
                            is_protected: role.is_protected,
                        }}
                        permissions={permissions}
                        onSubmit={handleSubmit}
                    />
                </PageContent>
            </Page>
        </>
    );
}

EditRole.layout = (page: ReactElement) => <AdminLayout>{page}</AdminLayout>;

export default EditRole;
