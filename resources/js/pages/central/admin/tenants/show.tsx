import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
    AlertDialogTrigger,
} from '@/components/ui/alert-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import AdminLayout from '@/layouts/central/admin-layout';
import { useSetBreadcrumbs } from '@/contexts/breadcrumb-context';
import admin from '@/routes/central/admin';
import { Head, Link, router } from '@inertiajs/react';
import {
    CheckCircle,
    CreditCard,
    Crown,
    Globe,
    LogIn,
    Network,
    Package,
    Play,
    StopCircle,
    Users,
    XCircle,
} from 'lucide-react';
import { Page, PageHeader, PageHeaderContent, PageHeaderActions, PageTitle, PageDescription, PageContent } from '@/components/shared/layout/page';
import { useImpersonation } from '@/hooks/central/use-impersonation';
import { type BreadcrumbItem } from '@/types';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import { type ReactElement } from 'react';

interface FederationGroup {
    id: string;
    name: string;
    description: string | null;
    sync_strategy: string;
    is_active: boolean;
    federated_users_count: number;
    master_tenant_id: string | null;
    is_master: boolean;
    master_tenant: { id: string; name: string } | null;
    sync_enabled: boolean;
    joined_at: string | null;
    left_at: string | null;
}

interface AvailableFederationGroup {
    id: string;
    name: string;
}

interface Props {
    tenant: {
        id: string;
        name: string;
        slug: string;
        created_at: string;
        plan: { id: string; name: string } | null;
        domains: { id: string; domain: string; is_primary: boolean }[];
        users: { id: string; name: string; email: string }[];
        addons: { id: string; name: string; status: string }[];
        federation_groups?: FederationGroup[];
    };
    availableFederationGroups: AvailableFederationGroup[];
}

function TenantShow({ tenant, availableFederationGroups }: Props) {
    const { t } = useLaravelReactI18n();
    const { impersonatingId, impersonateTenant, impersonateAsUser } = useImpersonation();

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('breadcrumbs.dashboard'), href: admin.dashboard.url() },
        { title: t('admin.tenants.title'), href: admin.tenants.index.url() },
        { title: tenant.name, href: admin.tenants.show.url(tenant.id) },
    ];
    useSetBreadcrumbs(breadcrumbs);

    const handleToggleFederationSync = (groupId: string) => {
        router.post(admin.federation.tenants.toggleSync.url({ group: groupId, tenant: tenant.id }));
    };

    const handleAddToFederationGroup = (groupId: string) => {
        router.post(admin.federation.tenants.add.url(groupId), { tenant_id: tenant.id });
    };

    const handleRejoinGroup = (groupId: string) => {
        router.post(admin.federation.tenants.add.url(groupId), { tenant_id: tenant.id });
    };

    const federationGroups = tenant.federation_groups || [];
    const activeGroups = federationGroups.filter((g) => !g.left_at);
    const leftGroups = federationGroups.filter((g) => g.left_at);
    const activeSyncs = activeGroups.filter((g) => g.sync_enabled).length;
    const totalFederatedUsers = activeGroups.reduce((acc, g) => acc + g.federated_users_count, 0);

    return (
        <>
            <Head title={`Tenant: ${tenant.name}`} />

            <Page>
                <PageHeader>
                    <PageHeaderContent>
                        <PageTitle>{tenant.name}</PageTitle>
                        <PageDescription>ID: {tenant.id}</PageDescription>
                    </PageHeaderContent>
                    <PageHeaderActions>
                        <Button
                            variant="outline"
                            onClick={() => impersonateTenant(tenant.id)}
                        >
                            <LogIn className="mr-2 h-4 w-4" />
                            {t('admin.tenants.impersonate')}
                        </Button>
                    </PageHeaderActions>
                </PageHeader>

                <PageContent>
                    <div className="grid gap-6 md:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Globe className="h-5 w-5" />
                                Domains
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            {tenant.domains.length > 0 ? (
                                <div className="space-y-2">
                                    {tenant.domains.map((domain) => (
                                        <div
                                            key={domain.id}
                                            className="flex items-center justify-between rounded-lg border p-3"
                                        >
                                            <span>{domain.domain}</span>
                                            {domain.is_primary && (
                                                <Badge variant="default">{t('admin.tenants.primary')}</Badge>
                                            )}
                                        </div>
                                    ))}
                                </div>
                            ) : (
                                <p className="text-muted-foreground text-sm">{t('admin.tenants.no_domains')}</p>
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <CreditCard className="h-5 w-5" />
                                Plan
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            {tenant.plan ? (
                                <div className="flex items-center justify-between">
                                    <span className="font-medium">{tenant.plan.name}</span>
                                    <Button variant="outline" size="sm" asChild>
                                        <Link href={`/admin/tenants/${tenant.id}/edit`}>{t('common.change')}</Link>
                                    </Button>
                                </div>
                            ) : (
                                <p className="text-muted-foreground text-sm">{t('admin.tenants.no_plan_assigned')}</p>
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Users className="h-5 w-5" />
                                Users ({tenant.users.length})
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            {tenant.users.length > 0 ? (
                                <div className="space-y-2">
                                    {tenant.users.slice(0, 5).map((user) => (
                                        <div
                                            key={user.id}
                                            className="flex items-center justify-between rounded-lg border p-3"
                                        >
                                            <div>
                                                <p className="font-medium">{user.name}</p>
                                                <p className="text-muted-foreground text-xs">{user.email}</p>
                                            </div>
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                onClick={() => impersonateAsUser(tenant.id, user.id)}
                                                disabled={impersonatingId === user.id}
                                                title={t('admin.tenants.impersonate_as', { name: user.name })}
                                            >
                                                <LogIn className="mr-1 h-3 w-3" />
                                                {impersonatingId === user.id ? '...' : t('admin.tenants.impersonate')}
                                            </Button>
                                        </div>
                                    ))}
                                    {tenant.users.length > 5 && (
                                        <p className="text-muted-foreground text-center text-sm">
                                            +{tenant.users.length - 5} {t('admin.tenants.more_users')}
                                        </p>
                                    )}
                                </div>
                            ) : (
                                <p className="text-muted-foreground text-sm">{t('admin.tenants.no_users')}</p>
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Package className="h-5 w-5" />
                                Add-ons ({tenant.addons.length})
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            {tenant.addons.length > 0 ? (
                                <div className="space-y-2">
                                    {tenant.addons.map((addon: { id: string; name: string; status: string }) => (
                                        <div
                                            key={addon.id}
                                            className="flex items-center justify-between rounded-lg border p-3"
                                        >
                                            <span>{addon.name}</span>
                                            <Badge
                                                variant={addon.status === 'active' ? 'default' : 'secondary'}
                                            >
                                                {addon.status}
                                            </Badge>
                                        </div>
                                    ))}
                                </div>
                            ) : (
                                <p className="text-muted-foreground text-sm">{t('admin.tenants.no_addons')}</p>
                            )}
                        </CardContent>
                    </Card>
                </div>

                    {/* Federation Groups - Full Width */}
                    <Card>
                        <CardHeader>
                            <div className="flex items-center justify-between">
                                <div>
                                    <CardTitle className="flex items-center gap-2">
                                        <Network className="h-5 w-5" />
                                        {t('admin.tenants.federation_groups')} ({federationGroups.length})
                                    </CardTitle>
                                    <CardDescription>
                                        {activeGroups.length > 0 ? (
                                            <>
                                                {activeSyncs} {t('admin.tenants.active_syncs')} · {totalFederatedUsers} {t('admin.tenants.federated_users')}
                                                {leftGroups.length > 0 && (
                                                    <> · {leftGroups.length} {t('admin.federation.left').toLowerCase()}</>
                                                )}
                                            </>
                                        ) : leftGroups.length > 0 ? (
                                            <>
                                                {leftGroups.length} {t('admin.federation.left').toLowerCase()}
                                            </>
                                        ) : (
                                            t('admin.tenants.federation_groups_description')
                                        )}
                                    </CardDescription>
                                </div>
                                {availableFederationGroups.length > 0 && (
                                    <select
                                        className="border-input bg-background rounded-md border px-3 py-2 text-sm"
                                        onChange={(e) => e.target.value && handleAddToFederationGroup(e.target.value)}
                                        defaultValue=""
                                    >
                                        <option value="">{t('admin.tenants.add_to_federation')}</option>
                                        {availableFederationGroups.map((group) => (
                                            <option key={group.id} value={group.id}>
                                                {group.name}
                                            </option>
                                        ))}
                                    </select>
                                )}
                            </div>
                        </CardHeader>
                        <CardContent>
                            {federationGroups.length > 0 ? (
                                <div className="space-y-3">
                                    {federationGroups.map((group) => (
                                        <div
                                            key={group.id}
                                            className={`flex items-center justify-between rounded-lg border p-4 ${group.left_at ? 'opacity-60' : ''}`}
                                        >
                                            <div className="flex items-center gap-3">
                                                <div className="bg-primary/10 rounded-full p-2">
                                                    <Network className="text-primary h-4 w-4" />
                                                </div>
                                                <div>
                                                    <div className="flex items-center gap-2">
                                                        {group.is_master && (
                                                            <Crown className="h-4 w-4 text-yellow-500" />
                                                        )}
                                                        <span className="font-medium">{group.name}</span>
                                                        {!group.is_active && (
                                                            <Badge variant="secondary">{t('common.inactive')}</Badge>
                                                        )}
                                                    </div>
                                                    <div className="text-muted-foreground flex items-center gap-2 text-xs">
                                                        <span>
                                                            {group.is_master
                                                                ? t('admin.tenants.master_tenant')
                                                                : t('admin.tenants.member_tenant')}
                                                        </span>
                                                        {group.left_at ? (
                                                            <>
                                                                <span>·</span>
                                                                <span>
                                                                    {t('admin.federation.left')}: {new Date(group.left_at).toLocaleDateString()}
                                                                </span>
                                                            </>
                                                        ) : group.joined_at ? (
                                                            <>
                                                                <span>·</span>
                                                                <span>
                                                                    {t('admin.tenants.joined')}: {new Date(group.joined_at).toLocaleDateString()}
                                                                </span>
                                                            </>
                                                        ) : null}
                                                        {!group.left_at && (
                                                            <>
                                                                <span>·</span>
                                                                <span>{group.federated_users_count} {t('admin.tenants.users')}</span>
                                                            </>
                                                        )}
                                                    </div>
                                                </div>
                                            </div>
                                            <div className="flex items-center gap-2">
                                                {group.left_at ? (
                                                    <>
                                                        <Badge variant="outline">
                                                            <XCircle className="mr-1 h-3 w-3" />
                                                            {t('admin.federation.left')}
                                                        </Badge>
                                                        <AlertDialog>
                                                            <AlertDialogTrigger asChild>
                                                                <Button
                                                                    variant="ghost"
                                                                    size="icon"
                                                                    title={t('admin.federation.rejoin')}
                                                                >
                                                                    <LogIn className="h-4 w-4 text-green-600" />
                                                                </Button>
                                                            </AlertDialogTrigger>
                                                            <AlertDialogContent>
                                                                <AlertDialogHeader>
                                                                    <AlertDialogTitle>
                                                                        {t('admin.federation.rejoin_title')}
                                                                    </AlertDialogTitle>
                                                                    <AlertDialogDescription>
                                                                        {t('admin.federation.rejoin_confirm', { name: tenant.name })}
                                                                    </AlertDialogDescription>
                                                                </AlertDialogHeader>
                                                                <AlertDialogFooter>
                                                                    <AlertDialogCancel>
                                                                        {t('common.cancel')}
                                                                    </AlertDialogCancel>
                                                                    <AlertDialogAction
                                                                        onClick={() => handleRejoinGroup(group.id)}
                                                                    >
                                                                        {t('admin.federation.rejoin')}
                                                                    </AlertDialogAction>
                                                                </AlertDialogFooter>
                                                            </AlertDialogContent>
                                                        </AlertDialog>
                                                    </>
                                                ) : (
                                                    <>
                                                        {group.sync_enabled ? (
                                                            <Badge variant="default">
                                                                <CheckCircle className="mr-1 h-3 w-3" />
                                                                {t('admin.federation.sync_enabled')}
                                                            </Badge>
                                                        ) : (
                                                            <Badge variant="secondary">
                                                                <XCircle className="mr-1 h-3 w-3" />
                                                                {t('admin.federation.sync_disabled')}
                                                            </Badge>
                                                        )}
                                                        {!group.is_master && (
                                                            <AlertDialog>
                                                                <AlertDialogTrigger asChild>
                                                                    <Button
                                                                        variant="ghost"
                                                                        size="icon"
                                                                        title={group.sync_enabled
                                                                            ? t('admin.federation.disable_sync')
                                                                            : t('admin.federation.enable_sync')
                                                                        }
                                                                    >
                                                                        {group.sync_enabled ? (
                                                                            <StopCircle className="h-4 w-4 text-destructive" />
                                                                        ) : (
                                                                            <Play className="h-4 w-4 text-green-600" />
                                                                        )}
                                                                    </Button>
                                                                </AlertDialogTrigger>
                                                                <AlertDialogContent>
                                                                    <AlertDialogHeader>
                                                                        <AlertDialogTitle>
                                                                            {group.sync_enabled
                                                                                ? t('admin.federation.disable_sync_title')
                                                                                : t('admin.federation.enable_sync_title')
                                                                            }
                                                                        </AlertDialogTitle>
                                                                        <AlertDialogDescription>
                                                                            {group.sync_enabled
                                                                                ? t('admin.federation.disable_sync_confirm', { name: tenant.name })
                                                                                : t('admin.federation.enable_sync_confirm', { name: tenant.name })
                                                                            }
                                                                        </AlertDialogDescription>
                                                                    </AlertDialogHeader>
                                                                    <AlertDialogFooter>
                                                                        <AlertDialogCancel>
                                                                            {t('common.cancel')}
                                                                        </AlertDialogCancel>
                                                                        <AlertDialogAction
                                                                            onClick={() => handleToggleFederationSync(group.id)}
                                                                        >
                                                                            {t('common.confirm')}
                                                                        </AlertDialogAction>
                                                                    </AlertDialogFooter>
                                                                </AlertDialogContent>
                                                            </AlertDialog>
                                                        )}
                                                    </>
                                                )}
                                                <Button variant="ghost" size="sm" asChild>
                                                    <Link href={admin.federation.show.url(group.id)}>
                                                        {t('common.view')}
                                                    </Link>
                                                </Button>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            ) : (
                                <div className="py-6 text-center">
                                    <Network className="text-muted-foreground mx-auto mb-3 h-10 w-10" />
                                    <p className="text-muted-foreground text-sm">
                                        {t('admin.tenants.no_federation_groups')}
                                    </p>
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </PageContent>
            </Page>
        </>
    );
}

TenantShow.layout = (page: ReactElement) => <AdminLayout>{page}</AdminLayout>;

export default TenantShow;
