import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import AdminLayout from '@/layouts/central/admin-layout';
import { Head, Link, router } from '@inertiajs/react';
import { Pencil, Plus, Trash2, Users, Shield, Eye } from 'lucide-react';
import { Page, PageHeader, PageHeaderContent, PageHeaderActions, PageTitle, PageDescription, PageContent } from '@/components/shared/layout/page';
import { type BreadcrumbItem } from '@/types';
import admin from '@/routes/central/admin';
import { useLaravelReactI18n } from 'laravel-react-i18n';

interface Role {
    id: string;
    name: string;
    display_name: string;
    description: string | null;
    users_count: number;
    permissions_count: number;
    is_protected: boolean;
    created_at: string | null;
}

interface Props {
    centralRoles: Role[];
}

export default function RolesIndex({ centralRoles }: Props) {
    const { t } = useLaravelReactI18n();

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('breadcrumbs.dashboard'), href: admin.dashboard.url() },
        { title: t('breadcrumbs.role_management'), href: admin.roles.index.url() },
    ];

    const handleDelete = (role: Role) => {
        if (role.is_protected) {
            alert(t('admin.roles.delete_protected_error'));
            return;
        }
        if (role.users_count > 0) {
            alert(t('admin.roles.delete_has_users_error'));
            return;
        }
        if (confirm(t('admin.roles.delete_confirm', { name: role.display_name }))) {
            router.delete(admin.roles.destroy.url(role.id));
        }
    };

    const RoleCard = ({ role }: { role: Role }) => (
        <Card className={role.is_protected ? 'border-primary/20' : ''}>
            <CardHeader className="pb-3">
                <div className="flex items-start justify-between">
                    <div>
                        <CardTitle className="text-base">
                            {role.display_name}
                        </CardTitle>
                        <div className="flex items-center gap-2">
                            <code className="text-muted-foreground text-xs">
                                {role.name}
                            </code>
                            {role.is_protected && (
                                <Badge variant="secondary" className="text-xs">
                                    {t('common.protected')}
                                </Badge>
                            )}
                        </div>
                    </div>
                    <div className="flex gap-1">
                        <Button variant="ghost" size="icon" asChild>
                            <Link href={admin.roles.show.url(role.id)}>
                                <Eye className="h-4 w-4" />
                            </Link>
                        </Button>
                        <Button variant="ghost" size="icon" asChild>
                            <Link href={admin.roles.edit.url(role.id)}>
                                <Pencil className="h-4 w-4" />
                            </Link>
                        </Button>
                        <Button
                            variant="ghost"
                            size="icon"
                            onClick={() => handleDelete(role)}
                            disabled={role.is_protected || role.users_count > 0}
                        >
                            <Trash2 className="h-4 w-4" />
                        </Button>
                    </div>
                </div>
            </CardHeader>
            <CardContent className="space-y-3">
                {role.description && (
                    <CardDescription className="text-sm">
                        {role.description}
                    </CardDescription>
                )}
                <div className="flex flex-wrap gap-2">
                    <Badge variant="outline" className="gap-1">
                        <Users className="h-3 w-3" />
                        {t('common.users_count', { count: role.users_count })}
                    </Badge>
                    <Badge variant="outline" className="gap-1">
                        <Shield className="h-3 w-3" />
                        {t('common.permissions_count', { count: role.permissions_count })}
                    </Badge>
                </div>
                {role.created_at && (
                    <p className="text-muted-foreground text-xs">
                        {t('common.created')}: {role.created_at}
                    </p>
                )}
            </CardContent>
        </Card>
    );

    return (
        <AdminLayout breadcrumbs={breadcrumbs}>
            <Head title={t('admin.roles.title')} />

            <Page>
                <PageHeader>
                    <PageHeaderContent>
                        <PageTitle>{t('admin.roles.title')}</PageTitle>
                        <PageDescription>
                            {t('admin.roles.description')}
                        </PageDescription>
                    </PageHeaderContent>
                    <PageHeaderActions>
                        <Button asChild>
                            <Link href={admin.roles.create.url()}>
                                <Plus className="mr-2 h-4 w-4" />
                                {t('admin.roles.new_role')}
                            </Link>
                        </Button>
                    </PageHeaderActions>
                </PageHeader>

                <PageContent>
                    {centralRoles.length > 0 ? (
                        <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                            {centralRoles.map((role) => (
                                <RoleCard key={role.id} role={role} />
                            ))}
                        </div>
                    ) : (
                        <Card className="border-dashed">
                            <CardContent className="flex flex-col items-center justify-center py-12">
                                <Shield className="text-muted-foreground mb-4 h-12 w-12" />
                                <p className="text-muted-foreground mb-4">{t('admin.roles.no_roles')}</p>
                                <Button asChild>
                                    <Link href={admin.roles.create.url()}>
                                        <Plus className="mr-2 h-4 w-4" />
                                        {t('admin.roles.create_first')}
                                    </Link>
                                </Button>
                            </CardContent>
                        </Card>
                    )}
                </PageContent>
            </Page>
        </AdminLayout>
    );
}
