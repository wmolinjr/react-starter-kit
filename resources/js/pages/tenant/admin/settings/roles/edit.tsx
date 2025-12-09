import admin from '@/routes/tenant/admin';
import { Head, router } from '@inertiajs/react';
import AdminLayout from '@/layouts/tenant/admin-layout';
import { RoleForm } from './components/role-form';
import { Page, PageHeader, PageHeaderContent, PageTitle, PageDescription, PageContent } from '@/components/shared/layout/page';
import { type BreadcrumbItem, type CategoryPermissions, type RoleEditResource, type Translations } from '@/types';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import { Shield } from 'lucide-react';

import { useSetBreadcrumbs } from '@/contexts/breadcrumb-context';
import { type ReactElement } from 'react';

interface Props {
    role: RoleEditResource;
    permissions: Record<string, CategoryPermissions>;
}

/**
 * Get translation value for current locale from Translations object
 */
function getTranslation(translations: Translations | null | undefined, fallback: string = ''): string {
    if (!translations) return fallback;
    // Try current locale first, then English, then first available
    return translations.pt_BR || translations.en || Object.values(translations)[0] || fallback;
}

function EditRole({ role, permissions }: Props) {
    const { t } = useLaravelReactI18n();

    // Use display_name_display for breadcrumbs (translated string)
    const displayName = role.display_name_display;

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('breadcrumbs.dashboard'), href: admin.dashboard.url() },
        { title: t('breadcrumbs.settings'), href: admin.settings.index.url() },
        { title: t('breadcrumbs.custom_roles'), href: admin.settings.roles.index.url() },
        { title: displayName, href: admin.settings.roles.edit.url(role.id) },
    ];

    useSetBreadcrumbs(breadcrumbs);

    const handleSubmit = (data: Parameters<typeof RoleForm>[0]['onSubmit'] extends (d: infer T) => void ? T : never) => {
        router.put(admin.settings.roles.update.url(role.id), data);
    };

    return (
        <>
            <Head title={`${t('roles.edit_title')} ${displayName}`} />

            <Page>
                <PageHeader>
                    <PageHeaderContent>
                        <PageTitle icon={Shield}>{t('roles.edit_title')}</PageTitle>
                        <PageDescription>{t('roles.edit_description', { name: displayName })}</PageDescription>
                    </PageHeaderContent>
                </PageHeader>

                <PageContent>
                    <RoleForm
                        role={{
                            id: role.id,
                            name: role.name,
                            // Convert Translations to string for form display
                            display_name: getTranslation(role.display_name),
                            description: getTranslation(role.description),
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
