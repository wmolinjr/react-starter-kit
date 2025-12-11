import admin from '@/routes/tenant/admin';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import AdminLayout from '@/layouts/tenant/admin-layout';
import { Head, Link, router } from '@inertiajs/react';
import { Pencil, Trash2, Users, Shield } from 'lucide-react';
import { Page, PageHeader, PageHeaderContent, PageHeaderActions, PageTitle, PageDescription, PageContent } from '@/components/shared/layout/page';
import { type BreadcrumbItem, type RoleDetailResource, type PermissionResource } from '@/types';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useLaravelReactI18n } from 'laravel-react-i18n';

import { useSetBreadcrumbs } from '@/contexts/breadcrumb-context';
import { type ReactElement } from 'react';

interface Props {
    role: RoleDetailResource;
}

function ShowRole({ role }: Props) {
    const { t } = useLaravelReactI18n();

    // Provide defaults for optional arrays
    const users = role.users ?? [];
    const permissions = role.permissions ?? [];

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('admin.dashboard.title'), href: admin.dashboard.url() },
        { title: t('tenant.settings.title'), href: admin.settings.index.url() },
        { title: t('roles.title'), href: admin.settings.roles.index.url() },
        { title: role.display_name, href: admin.settings.roles.show.url(role.id) },
    ];

    useSetBreadcrumbs(breadcrumbs);

    const handleDelete = () => {
        if (role.is_protected) {
            alert(t('roles.delete_protected_error'));
            return;
        }
        if (users.length > 0) {
            alert(t('roles.delete_has_users_error'));
            return;
        }
        if (confirm(t('roles.delete_confirm', { name: role.display_name }))) {
            router.delete(admin.settings.roles.destroy.url(role.id));
        }
    };

    // Group permissions by category
    const permissionsByCategory = permissions.reduce(
        (acc, permission) => {
            const category = permission.category || 'other';
            if (!acc[category]) acc[category] = [];
            acc[category].push(permission);
            return acc;
        },
        {} as Record<string, PermissionResource[]>
    );

    return (
        <>
            <Head title={role.display_name} />

            <Page>
                <PageHeader>
                    <PageHeaderContent>
                        <div className="flex items-center gap-3">
                            <PageTitle icon={Shield}>{role.display_name}</PageTitle>
                            {role.is_protected && (
                                <Badge variant="secondary">{t('common.protected')}</Badge>
                            )}
                        </div>
                        <PageDescription>
                            <code className="text-muted-foreground">{role.name}</code>
                            {role.description && ` - ${role.description}`}
                        </PageDescription>
                    </PageHeaderContent>
                    <PageHeaderActions>
                        <Button variant="outline" asChild>
                            <Link href={admin.settings.roles.edit.url(role.id)}>
                                <Pencil className="mr-2 h-4 w-4" />
                                {t('common.edit')}
                            </Link>
                        </Button>
                        <Button
                            variant="destructive"
                            onClick={handleDelete}
                            disabled={role.is_protected || users.length > 0}
                        >
                            <Trash2 className="mr-2 h-4 w-4" />
                            {t('common.delete')}
                        </Button>
                    </PageHeaderActions>
                </PageHeader>

                <PageContent>
                    <div className="grid gap-6 lg:grid-cols-2">
                        {/* Permissions Section */}
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <Shield className="h-5 w-5" />
                                    {t('roles.permissions_title')}
                                    <Badge variant="outline">{permissions.length}</Badge>
                                </CardTitle>
                                <CardDescription>
                                    {t('roles.permissions_description')}
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                {Object.entries(permissionsByCategory).length > 0 ? (
                                    <div className="space-y-4">
                                        {Object.entries(permissionsByCategory).map(([category, permissions]) => (
                                            <div key={category}>
                                                <h4 className="text-muted-foreground mb-2 text-sm font-medium capitalize">
                                                    {category.replace(/-/g, ' ')}
                                                </h4>
                                                <div className="flex flex-wrap gap-1">
                                                    {permissions.map((permission) => (
                                                        <Badge
                                                            key={permission.id}
                                                            variant="secondary"
                                                            className="text-xs"
                                                            title={permission.description || undefined}
                                                        >
                                                            {permission.name}
                                                        </Badge>
                                                    ))}
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                ) : (
                                    <p className="text-muted-foreground text-sm">
                                        {t('roles.no_permissions')}
                                    </p>
                                )}
                            </CardContent>
                        </Card>

                        {/* Users Section */}
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <Users className="h-5 w-5" />
                                    {t('roles.users_title')}
                                    <Badge variant="outline">{users.length}</Badge>
                                </CardTitle>
                                <CardDescription>
                                    {t('roles.users_description')}
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                {users.length > 0 ? (
                                    <Table>
                                        <TableHeader>
                                            <TableRow>
                                                <TableHead>{t('common.name')}</TableHead>
                                                <TableHead>{t('common.email')}</TableHead>
                                            </TableRow>
                                        </TableHeader>
                                        <TableBody>
                                            {users.map((user) => (
                                                <TableRow key={user.id}>
                                                    <TableCell className="font-medium">
                                                        {user.name}
                                                    </TableCell>
                                                    <TableCell className="text-muted-foreground">
                                                        {user.email}
                                                    </TableCell>
                                                </TableRow>
                                            ))}
                                        </TableBody>
                                    </Table>
                                ) : (
                                    <p className="text-muted-foreground text-sm">
                                        {t('roles.no_users')}
                                    </p>
                                )}
                            </CardContent>
                        </Card>
                    </div>

                    {/* Metadata */}
                    {role.created_at && (
                        <Card className="mt-6">
                            <CardContent className="pt-6">
                                <p className="text-muted-foreground text-sm">
                                    {t('common.created')}: {role.created_at}
                                </p>
                            </CardContent>
                        </Card>
                    )}
                </PageContent>
            </Page>
        </>
    );
}

ShowRole.layout = (page: ReactElement) => <AdminLayout>{page}</AdminLayout>;

export default ShowRole;
