import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import AdminLayout from '@/layouts/central/admin-layout';
import admin from '@/routes/central/admin';
import { Head, Link, router } from '@inertiajs/react';
import { KeyRound, Shield, User, Users } from 'lucide-react';
import { Page, PageHeader, PageHeaderContent, PageHeaderActions, PageTitle, PageDescription, PageContent } from '@/components/shared/layout/page';
import { type BreadcrumbItem } from '@/types';
import { useSetBreadcrumbs } from '@/contexts/breadcrumb-context';
import { type ReactElement } from 'react';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import { useState } from 'react';

interface TenantUser {
    id: string;
    name: string;
    email: string;
    created_at: string | null;
    roles: string[];
}

interface TenantInfo {
    id: string;
    name: string;
    slug: string;
    domain: string | null;
}

interface Props {
    tenant: TenantInfo;
    users: TenantUser[];
}

function ImpersonateTenant({ tenant, users }: Props) {
    const { t } = useLaravelReactI18n();
    const [loading, setLoading] = useState<string | null>(null);

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: admin.dashboard.url() },
        { title: 'Tenants', href: admin.tenants.index.url() },
        { title: tenant.name, href: admin.tenants.show.url(tenant.id) },
        { title: t('impersonation.select_user'), href: admin.tenants.impersonate.index.url(tenant.id) },
    ];

    useSetBreadcrumbs(breadcrumbs);

    const handleAdminMode = () => {
        setLoading('admin-mode');
        router.post(
            admin.tenants.impersonate.adminMode.url(tenant.id),
            {},
            {
                onFinish: () => setLoading(null),
            }
        );
    };

    const handleImpersonateUser = (userId: string) => {
        setLoading(userId);
        router.post(
            admin.tenants.impersonate.asUser.url({ tenant: tenant.id, userId }),
            {},
            {
                onFinish: () => setLoading(null),
            }
        );
    };

    const getInitials = (name: string) => {
        return name
            .split(' ')
            .map((n) => n[0])
            .join('')
            .toUpperCase()
            .slice(0, 2);
    };

    const getRoleBadgeVariant = (role: string): 'default' | 'secondary' | 'outline' => {
        switch (role.toLowerCase()) {
            case 'owner':
                return 'default';
            case 'admin':
                return 'secondary';
            default:
                return 'outline';
        }
    };

    return (
        <>
            <Head title={`${t('impersonation.impersonate_tenant')}: ${tenant.name}`} />

            <Page>
                <PageHeader>
                    <PageHeaderContent>
                        <PageTitle>{t('impersonation.impersonate_tenant')}</PageTitle>
                        <PageDescription>
                            {tenant.name} ({tenant.domain || tenant.slug})
                        </PageDescription>
                    </PageHeaderContent>
                </PageHeader>

                <PageContent>
                    <div className="space-y-6">
                        {/* Admin Mode Card */}
                        <Card className="border-amber-200 bg-amber-50 dark:border-amber-800 dark:bg-amber-950">
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2 text-amber-800 dark:text-amber-200">
                                    <Shield className="h-5 w-5" />
                                    {t('impersonation.admin_mode')}
                                </CardTitle>
                                <CardDescription className="text-amber-700 dark:text-amber-300">
                                    {t('impersonation.admin_mode_description')}
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <Button
                                    onClick={handleAdminMode}
                                    disabled={loading !== null}
                                    className="bg-amber-600 hover:bg-amber-700 dark:bg-amber-700 dark:hover:bg-amber-600"
                                    data-admin-mode
                                >
                                    <KeyRound className="mr-2 h-4 w-4" />
                                    {loading === 'admin-mode'
                                        ? t('impersonation.entering')
                                        : t('impersonation.enter_admin_mode')}
                                </Button>
                            </CardContent>
                        </Card>

                        {/* Users List Card */}
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <Users className="h-5 w-5" />
                                    {t('impersonation.tenant_users')} ({users.length})
                                </CardTitle>
                                <CardDescription>
                                    {t('impersonation.select_user_description')}
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                {users.length > 0 ? (
                                    <div className="space-y-3">
                                        {users.map((user) => (
                                            <div
                                                key={user.id}
                                                className="flex items-center justify-between rounded-lg border p-4 transition-colors hover:bg-muted/50"
                                            >
                                                <div className="flex items-center gap-3">
                                                    <Avatar className="h-10 w-10">
                                                        <AvatarFallback className="bg-primary/10 text-primary">
                                                            {getInitials(user.name)}
                                                        </AvatarFallback>
                                                    </Avatar>
                                                    <div>
                                                        <p className="font-medium">{user.name}</p>
                                                        <p className="text-muted-foreground text-sm">{user.email}</p>
                                                    </div>
                                                </div>
                                                <div className="flex items-center gap-3">
                                                    <div className="flex gap-1">
                                                        {user.roles.map((role) => (
                                                            <Badge
                                                                key={role}
                                                                variant={getRoleBadgeVariant(role)}
                                                            >
                                                                {role}
                                                            </Badge>
                                                        ))}
                                                    </div>
                                                    <Button
                                                        variant="outline"
                                                        size="sm"
                                                        onClick={() => handleImpersonateUser(user.id)}
                                                        disabled={loading !== null}
                                                        data-impersonate-user={user.email}
                                                    >
                                                        <User className="mr-2 h-4 w-4" />
                                                        {loading === user.id
                                                            ? t('impersonation.entering')
                                                            : t('impersonation.impersonate')}
                                                    </Button>
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                ) : (
                                    <div className="py-8 text-center">
                                        <Users className="mx-auto h-12 w-12 text-muted-foreground/50" />
                                        <p className="text-muted-foreground mt-4">
                                            {t('impersonation.no_users_in_tenant')}
                                        </p>
                                        <p className="text-muted-foreground mt-1 text-sm">
                                            {t('impersonation.use_admin_mode_instead')}
                                        </p>
                                    </div>
                                )}
                            </CardContent>
                        </Card>
                    </div>
                </PageContent>
            </Page>
        </>
    );
}

ImpersonateTenant.layout = (page: ReactElement) => <AdminLayout>{page}</AdminLayout>;

export default ImpersonateTenant;
