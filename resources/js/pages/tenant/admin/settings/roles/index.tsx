import admin from '@/routes/tenant/admin';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import AdminLayout from '@/layouts/tenant/admin-layout';
import { Head, Link, router } from '@inertiajs/react';
import { Pencil, Plus, Trash2, Users, Shield, Eye, Info } from 'lucide-react';
import { Page, PageHeader, PageHeaderContent, PageHeaderActions, PageTitle, PageDescription, PageContent } from '@/components/shared/layout/page';
import { type BreadcrumbItem, type RoleResource } from '@/types';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { useLaravelReactI18n } from 'laravel-react-i18n';

import { useSetBreadcrumbs } from '@/contexts/breadcrumb-context';
import { type ReactElement } from 'react';

function useBreadcrumbs() {
    const { t } = useLaravelReactI18n();
    return [
        { title: t('dashboard.page.title'), href: admin.dashboard.url() },
        { title: t('settings.title'), href: admin.settings.index.url() },
        { title: t('roles.page.title'), href: admin.settings.roles.index.url() },
    ] as BreadcrumbItem[];
}

interface PlanInfo {
    canCreateCustomRoles: boolean;
    customRolesLimit: number;
    customRolesCount: number;
    hasReachedLimit: boolean;
    planName: string | null;
}

interface Props {
    roles: RoleResource[];
    planInfo: PlanInfo;
}

function RolesIndex({ roles, planInfo }: Props) {
    const { t } = useLaravelReactI18n();
    const breadcrumbs = useBreadcrumbs();

    useSetBreadcrumbs(breadcrumbs);

    // Format custom roles limit display
    const formatLimit = (limit: number) => {
        if (limit === -1) return t('common.unlimited');
        return String(limit);
    };

    const handleDelete = (role: RoleResource) => {
        if (role.is_protected) {
            alert(t('roles.error.delete_protected'));
            return;
        }
        if (role.users_count > 0) {
            alert(t('roles.error.delete_has_users'));
            return;
        }
        if (confirm(t('roles.form.delete_confirm', { name: role.display_name }))) {
            router.delete(admin.settings.roles.destroy.url(role.id));
        }
    };

    return (
        <>
            <Head title={t('roles.page.title')} />

            <Page>
                <PageHeader>
                    <PageHeaderContent>
                        <PageTitle icon={Shield}>{t('settings.custom_roles')}</PageTitle>
                        <PageDescription>
                            {t('roles.page.description')}
                        </PageDescription>
                    </PageHeaderContent>
                    <PageHeaderActions>
                        {planInfo.canCreateCustomRoles && !planInfo.hasReachedLimit ? (
                            <Button asChild>
                                <Link href={admin.settings.roles.create.url()}>
                                    <Plus className="mr-2 h-4 w-4" />
                                    {t('roles.form.new')}
                                </Link>
                            </Button>
                        ) : planInfo.hasReachedLimit ? (
                            <Button disabled>
                                <Plus className="mr-2 h-4 w-4" />
                                {t('roles.form.new')}
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
                                        ? t('roles.limit.reached', {
                                              limit: formatLimit(planInfo.customRolesLimit),
                                              plan: planInfo.planName ?? '',
                                          })
                                        : t('roles.limit.info', {
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
                            {t('roles.page.info')}
                        </AlertDescription>
                    </Alert>

                    {/* All Roles */}
                    <h2 className="text-lg font-semibold">{t('roles.list.all')}</h2>
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
                                    {t('roles.list.no_roles')}
                                </p>
                                <p className="text-muted-foreground text-center text-sm mb-4 max-w-md">
                                    {t('roles.list.no_roles_description')}
                                </p>
                                <Button asChild>
                                    <Link href={admin.settings.roles.create.url()}>
                                        <Plus className="mr-2 h-4 w-4" />
                                        {t('roles.page.create_first')}
                                    </Link>
                                </Button>
                            </CardContent>
                        </Card>
                    )}
                </PageContent>
            </Page>
        </>
    );
}

RolesIndex.layout = (page: ReactElement) => <AdminLayout>{page}</AdminLayout>;

export default RolesIndex;
