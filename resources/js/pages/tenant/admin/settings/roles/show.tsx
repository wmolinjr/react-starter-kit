import admin from '@/routes/tenant/admin';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import AdminLayout from '@/layouts/tenant/admin-layout';
import { Head, Link, router } from '@inertiajs/react';
import { Pencil, Trash2, Users, Shield } from 'lucide-react';
import { Page, PageHeader, PageHeaderContent, PageHeaderActions, PageTitle, PageDescription, PageContent } from '@/components/shared/layout/page';
import { type BreadcrumbItem } from '@/types';
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

interface Permission {
    id: string;
    name: string;
    description: string | null;
    category: string | null;
}

interface User {
    id: string;
    name: string;
    email: string;
}

interface Role {
    id: string;
    name: string;
    display_name: string;
    description: string | null;
    is_protected: boolean;
    permissions: Permission[];
    users: User[];
    created_at: string | null;
}

interface Props {
    role: Role;
}

function ShowRole({ role }: Props) {
    const { t } = useLaravelReactI18n();

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('breadcrumbs.dashboard'), href: admin.dashboard.url() },
        { title: t('breadcrumbs.settings'), href: admin.settings.index.url() },
        { title: t('breadcrumbs.custom_roles'), href: admin.settings.roles.index.url() },
        { title: role.display_name, href: admin.settings.roles.show.url(role.id) },
    ];

    const handleDelete = () => {
        if (role.is_protected) {
            alert(t('roles.delete_protected_error'));
            return;
        }
        if (role.users.length > 0) {
            alert(t('roles.delete_has_users_error'));
            return;
        }
        if (confirm(t('roles.delete_confirm', { name: role.display_name }))) {
            router.delete(admin.settings.roles.destroy.url(role.id));
        }
    };

    // Group permissions by category
    const permissionsByCategory = role.permissions.reduce(
        (acc, permission) => {
            const category = permission.category || 'other';
            if (!acc[category]) acc[category] = [];
            acc[category].push(permission);
            return acc;
        },
        {} as Record<string, Permission[]>
    );

    return (
        <>
            <Head title={role.display_name} />

            <Page>
                <PageHeader>
                    <PageHeaderContent>
                        <div className="flex items-center gap-3">
                            <PageTitle>{role.display_name}</PageTitle>
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
                            disabled={role.is_protected || role.users.length > 0}
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
                                    <Badge variant="outline">{role.permissions.length}</Badge>
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
                                    <Badge variant="outline">{role.users.length}</Badge>
                                </CardTitle>
                                <CardDescription>
                                    {t('roles.users_description')}
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                {role.users.length > 0 ? (
                                    <Table>
                                        <TableHeader>
                                            <TableRow>
                                                <TableHead>{t('common.name')}</TableHead>
                                                <TableHead>{t('common.email')}</TableHead>
                                            </TableRow>
                                        </TableHeader>
                                        <TableBody>
                                            {role.users.map((user) => (
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
