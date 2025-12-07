import admin from '@/routes/tenant/admin';
import { Head, router } from '@inertiajs/react';
import AdminLayout from '@/layouts/tenant/admin-layout';
import { RoleForm } from './components/role-form';
import { Page, PageHeader, PageHeaderContent, PageTitle, PageDescription, PageContent } from '@/components/shared/layout/page';
import { type BreadcrumbItem } from '@/types';
import { useLaravelReactI18n } from 'laravel-react-i18n';

function useBreadcrumbs() {
    const { t } = useLaravelReactI18n();
    return [
        { title: t('breadcrumbs.dashboard'), href: admin.dashboard.url() },
        { title: t('breadcrumbs.settings'), href: admin.settings.index.url() },
        { title: t('breadcrumbs.custom_roles'), href: admin.settings.roles.index.url() },
        { title: t('breadcrumbs.create_role'), href: admin.settings.roles.create.url() },
    ] as BreadcrumbItem[];
}

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
    const breadcrumbs = useBreadcrumbs();

    const handleSubmit = (data: Parameters<typeof RoleForm>[0]['onSubmit'] extends (d: infer T) => void ? T : never) => {
        router.post(admin.settings.roles.store.url(), data);
    };

    return (
        <AdminLayout breadcrumbs={breadcrumbs}>
            <Head title={t('roles.create_title')} />

            <Page>
                <PageHeader>
                    <PageHeaderContent>
                        <PageTitle>{t('roles.create_title')}</PageTitle>
                        <PageDescription>
                            {t('roles.create_description')}
                        </PageDescription>
                    </PageHeaderContent>
                </PageHeader>

                <PageContent>
                    <RoleForm permissions={permissions} onSubmit={handleSubmit} />
                </PageContent>
            </Page>
        </AdminLayout>
    );
}
