import { Head, router } from '@inertiajs/react';
import type { FormDataConvertible } from '@inertiajs/core';
import AdminLayout from '@/layouts/central/admin-layout';
import { RoleForm } from './components/role-form';
import { Page, PageHeader, PageHeaderContent, PageTitle, PageDescription, PageContent } from '@/components/shared/layout/page';
import { type BreadcrumbItem, type CategoryPermissions, type RoleEditResource } from '@/types';
import type { Translations } from '@/components/central/forms/translatable-input';
import { useSetBreadcrumbs } from '@/contexts/breadcrumb-context';
import { type ReactElement } from 'react';
import admin from '@/routes/central/admin';
import { useLaravelReactI18n } from 'laravel-react-i18n';

interface Props {
    role: RoleEditResource;
    permissions: Record<string, CategoryPermissions>;
}

function EditRole({ role, permissions }: Props) {
    const { t } = useLaravelReactI18n();

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('admin.dashboard.title'), href: admin.dashboard.url() },
        { title: t('admin.roles.title'), href: admin.roles.index.url() },
        { title: role.display_name_display, href: admin.roles.edit.url(role.id) },
    ];

    useSetBreadcrumbs(breadcrumbs);

    const handleSubmit = (data: Parameters<typeof RoleForm>[0]['onSubmit'] extends (d: infer T) => void ? T : never) => {
        router.put(admin.roles.update.url(role.id), data as unknown as Record<string, FormDataConvertible>);
    };

    return (
        <>
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
                            display_name: role.display_name as Translations,
                            description: (role.description ?? { en: '', pt_BR: '' }) as Translations,
                            permissions: role.permission_ids ?? [],
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
