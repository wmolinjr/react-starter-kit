import { Head, router } from '@inertiajs/react';
import type { FormDataConvertible } from '@inertiajs/core';
import CentralAdminLayout from '@/layouts/central-admin-layout';
import { RoleForm } from './components/role-form';
import { Page, PageHeader, PageHeaderContent, PageTitle, PageDescription, PageContent } from '@/components/page';
import { type BreadcrumbItem } from '@/types';
import admin from '@/routes/central/admin';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import { Translations } from '@/components/translatable-input';

interface Permission {
    id: string;
    name: string;
    description: string | null;
}

interface CategoryPermissions {
    label: string;
    permissions: Permission[];
}

interface Role {
    id: string;
    name: string;
    display_name: Translations;
    display_name_display: string;
    description: Translations | null;
    is_protected: boolean;
    permission_ids: string[];
}

interface Props {
    role: Role;
    permissions: Record<string, CategoryPermissions>;
}

export default function EditRole({ role, permissions }: Props) {
    const { t } = useLaravelReactI18n();

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('breadcrumbs.dashboard'), href: admin.dashboard.url() },
        { title: t('breadcrumbs.role_management'), href: admin.roles.index.url() },
        { title: role.display_name_display, href: admin.roles.edit.url(role.id) },
    ];

    const handleSubmit = (data: Parameters<typeof RoleForm>[0]['onSubmit'] extends (d: infer T) => void ? T : never) => {
        router.put(admin.roles.update.url(role.id), data as unknown as Record<string, FormDataConvertible>);
    };

    return (
        <CentralAdminLayout breadcrumbs={breadcrumbs}>
            <Head title={`${t('admin.roles.edit_role')}: ${role.display_name_display}`} />

            <Page>
                <PageHeader>
                    <PageHeaderContent>
                        <PageTitle>{t('admin.roles.edit_role')}</PageTitle>
                        <PageDescription>{t('admin.roles.update_description', { name: role.display_name_display })}</PageDescription>
                    </PageHeaderContent>
                </PageHeader>

                <PageContent>
                    <RoleForm
                        role={{
                            id: role.id,
                            name: role.name,
                            display_name: role.display_name,
                            description: role.description ?? { en: '', pt_BR: '' },
                            permissions: role.permission_ids,
                            is_protected: role.is_protected,
                        }}
                        permissions={permissions}
                        onSubmit={handleSubmit}
                    />
                </PageContent>
            </Page>
        </CentralAdminLayout>
    );
}
