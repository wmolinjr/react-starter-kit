import admin from '@/routes/tenant/admin';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import TenantAdminLayout from '@/layouts/tenant-admin-layout';
import { Head, Link, router } from '@inertiajs/react';
import { Pencil, Plus, Trash2, Users, Shield, Eye, Info } from 'lucide-react';
import { Page, PageHeader, PageHeaderContent, PageHeaderActions, PageTitle, PageDescription, PageContent } from '@/components/page';
import { type BreadcrumbItem } from '@/types';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { useLaravelReactI18n } from 'laravel-react-i18n';

function useBreadcrumbs() {
    const { t } = useLaravelReactI18n();
    return [
        { title: t('breadcrumbs.dashboard'), href: admin.dashboard.url() },
        { title: t('breadcrumbs.settings'), href: admin.settings.index.url() },
        { title: t('breadcrumbs.custom_roles'), href: admin.settings.roles.index.url() },
    ] as BreadcrumbItem[];
}

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

interface PlanInfo {
    canCreateCustomRoles: boolean;
    customRolesLimit: number;
    customRolesCount: number;
    hasReachedLimit: boolean;
    planName: string | null;
}

interface Props {
    roles: Role[];
    planInfo: PlanInfo;
}

export default function RolesIndex({ roles, planInfo }: Props) {
    const { t } = useLaravelReactI18n();
    const breadcrumbs = useBreadcrumbs();

    // Format custom roles limit display
    const formatLimit = (limit: number) => {
        if (limit === -1) return t('common.unlimited');
        return String(limit);
    };

    const handleDelete = (role: Role) => {
        if (role.is_protected) {
            alert(t('roles.delete_protected_error'));
            return;
        }
        if (role.users_count > 0) {
            alert(t('roles.delete_has_users_error'));
            return;
        }
        if (confirm(t('roles.delete_confirm', { name: role.display_name }))) {
            router.delete(admin.settings.roles.destroy.url(role.id));
        }
    };

    return (
        <TenantAdminLayout breadcrumbs={breadcrumbs}>
            <Head title={t('roles.title')} />

            <Page>
                <PageHeader>
                    <PageHeaderContent>
                        <PageTitle>{t('roles.title')}</PageTitle>
                        <PageDescription>
                            {t('roles.description')}
                        </PageDescription>
                    </PageHeaderContent>
                    <PageHeaderActions>
                        {planInfo.canCreateCustomRoles && !planInfo.hasReachedLimit ? (
                            <Button asChild>
                                <Link href={admin.settings.roles.create.url()}>
                                    <Plus className="mr-2 h-4 w-4" />
                                    {t('roles.new_role')}
                                </Link>
                            </Button>
                        ) : planInfo.hasReachedLimit ? (
                            <Button disabled>
                                <Plus className="mr-2 h-4 w-4" />
                                {t('roles.new_role')}
                            </Button>
                        ) : null}
                    </PageHeaderActions>
                </PageHeader>

                <PageContent>
                    {/* Plan Limit Info */}
                    {planInfo.canCreateCustomRoles && (
                        <Alert variant={planInfo.hasReachedLimit ? 'destructive' : 'default'}>
                            <Info className="h-4 w-4" />
                            <AlertDescription className="flex items-center justify-between">
                                <span>
                                    {planInfo.hasReachedLimit
                                        ? t('roles.limit_reached_info', {
                                              limit: formatLimit(planInfo.customRolesLimit),
                                              plan: planInfo.planName ?? '',
                                          })
                                        : t('roles.limit_info', {
                                              count: planInfo.customRolesCount,
                                              limit: formatLimit(planInfo.customRolesLimit),
                                          })}
                                </span>
                                {planInfo.hasReachedLimit && (
                                    <Button variant="outline" size="sm" asChild>
                                        <Link href={admin.billing.index.url()}>
                                            {t('common.upgrade')}
                                        </Link>
                                    </Button>
                                )}
                            </AlertDescription>
                        </Alert>
                    )}

                    {/* Roles Info */}
                    <Alert>
                        <Info className="h-4 w-4" />
                        <AlertDescription>
                            {t('roles.roles_info')}
                        </AlertDescription>
                    </Alert>

                    {/* All Roles */}
                    <h2 className="text-lg font-semibold">{t('roles.all_roles')}</h2>
                    {roles.length > 0 ? (
                        <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                            {roles.map((role) => (
                                <Card key={role.id}>
                                    <CardHeader className="pb-3">
                                        <div className="flex items-start justify-between">
                                            <div className="flex items-center gap-3">
                                                <div className="bg-primary/10 flex h-10 w-10 items-center justify-center rounded-lg">
                                                    <Shield className="text-primary h-5 w-5" />
                                                </div>
                                                <div>
                                                    <CardTitle className="flex items-center gap-2 text-base">
                                                        {role.display_name}
                                                        {role.is_protected && (
                                                            <Badge variant="secondary" className="text-xs">
                                                                {t('common.protected')}
                                                            </Badge>
                                                        )}
                                                    </CardTitle>
                                                    <code className="text-muted-foreground text-xs">
                                                        {role.name}
                                                    </code>
                                                </div>
                                            </div>
                                            <div className="flex gap-1">
                                                <Button variant="ghost" size="icon" asChild>
                                                    <Link href={admin.settings.roles.show.url(role.id)}>
                                                        <Eye className="h-4 w-4" />
                                                    </Link>
                                                </Button>
                                                {!role.is_protected && (
                                                    <>
                                                        <Button variant="ghost" size="icon" asChild>
                                                            <Link href={admin.settings.roles.edit.url(role.id)}>
                                                                <Pencil className="h-4 w-4" />
                                                            </Link>
                                                        </Button>
                                                        <Button
                                                            variant="ghost"
                                                            size="icon"
                                                            onClick={() => handleDelete(role)}
                                                            disabled={role.users_count > 0}
                                                        >
                                                            <Trash2 className="h-4 w-4" />
                                                        </Button>
                                                    </>
                                                )}
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
                            ))}
                        </div>
                    ) : (
                        <Card>
                            <CardContent className="flex flex-col items-center justify-center py-12">
                                <Shield className="text-muted-foreground mb-4 h-12 w-12" />
                                <p className="text-muted-foreground mb-4">
                                    {t('roles.no_roles')}
                                </p>
                                <p className="text-muted-foreground text-center text-sm mb-4 max-w-md">
                                    {t('roles.no_roles_description')}
                                </p>
                                <Button asChild>
                                    <Link href={admin.settings.roles.create.url()}>
                                        <Plus className="mr-2 h-4 w-4" />
                                        {t('roles.create_first')}
                                    </Link>
                                </Button>
                            </CardContent>
                        </Card>
                    )}
                </PageContent>
            </Page>
        </TenantAdminLayout>
    );
}
