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
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import AdminLayout from '@/layouts/central/admin-layout';
import admin from '@/routes/central/admin';
import { Head, Link, router } from '@inertiajs/react';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import { useSetBreadcrumbs } from '@/contexts/breadcrumb-context';
import { type ReactElement } from 'react';
import {
    AlertTriangle,
    Building2,
    CheckCircle, CirclePlay, CircleStop,
    Crown,
    Mail,
    Network,
    Pencil,
    Play,
    RefreshCw,
    StopCircle,
    User,
    Users,
    XCircle,
} from 'lucide-react';
import {
    Page,
    PageContent,
    PageDescription,
    PageHeader,
    PageHeaderActions,
    PageHeaderContent,
    PageTitle,
} from '@/components/shared/layout/page';
import { type BreadcrumbItem } from '@/types';

interface Tenant {
    id: string;
    name: string;
    slug: string;
    is_master: boolean;
    sync_enabled: boolean;
    joined_at: string;
    settings: Record<string, unknown>;
}

interface FederatedUser {
    id: string;
    email: string;
    name: string;
    is_active: boolean;
    created_at: string;
    master_tenant: { id: string; name: string } | null;
    linked_tenants_count: number;
}

interface Stats {
    total_users: number;
    active_syncs: number;
    pending_conflicts: number;
    failed_syncs: number;
}

interface FederationGroup {
    id: string;
    name: string;
    description: string | null;
    sync_strategy: 'master_wins' | 'last_write_wins' | 'manual_review';
    settings: Record<string, unknown>;
    is_active: boolean;
    created_at: string;
    updated_at: string;
    master_tenant: { id: string; name: string; slug: string } | null;
    tenants: Tenant[];
    federated_users: FederatedUser[];
    tenants_count: number;
    federated_users_count: number;
    stats?: Stats;
}

interface AvailableTenant {
    id: string;
    name: string;
    slug: string;
}

interface Props {
    group: FederationGroup;
    availableTenants: AvailableTenant[];
}

function FederationShow({ group, availableTenants }: Props) {
    const { t } = useLaravelReactI18n();

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('breadcrumbs.dashboard'), href: admin.dashboard.url() },
        { title: t('admin.federation.title'), href: admin.federation.index.url() },
        { title: group.name, href: admin.federation.show.url(group.id) },
    ];

    useSetBreadcrumbs(breadcrumbs);

    const getSyncStrategyLabel = (strategy: string) => {
        const labels: Record<string, string> = {
            master_wins: t('admin.federation.sync_strategy.master_wins'),
            last_write_wins: t('admin.federation.sync_strategy.last_write_wins'),
            manual_review: t('admin.federation.sync_strategy.manual_review'),
        };
        return labels[strategy] || strategy;
    };

    const handleAddTenant = (tenantId: string) => {
        router.post(admin.federation.tenants.add.url(group.id), { tenant_id: tenantId });
    };

    const handleSyncUser = (userId: string) => {
        router.post(admin.federation.users.sync.url({ group: group.id, user: userId }));
    };

    const handleRetryAllSync = () => {
        if (confirm(t('admin.federation.retry_sync_confirm'))) {
            router.post(admin.federation.retrySync.url(group.id));
        }
    };

    const handleToggleTenantSync = (tenantId: string) => {
        router.post(admin.federation.tenants.toggleSync.url({ group: group.id, tenant: tenantId }));
    };

    return (
        <>
            <Head title={`${t('admin.federation.title')}: ${group.name}`} />

            <Page>
                <PageHeader>
                    <PageHeaderContent>
                        <PageTitle icon={Network}>
                            {group.name}
                            {!group.is_active && (
                                <Badge variant="secondary" className="ml-2">
                                    {t('common.inactive')}
                                </Badge>
                            )}
                        </PageTitle>
                        <PageDescription>
                            {group.description || t('admin.federation.no_description')}
                        </PageDescription>
                    </PageHeaderContent>
                    <PageHeaderActions>
                        <Button variant="outline" asChild>
                            <Link href={admin.federation.edit.url(group.id)}>
                                <Pencil className="mr-2 h-4 w-4" />
                                {t('common.edit')}
                            </Link>
                        </Button>
                    </PageHeaderActions>
                </PageHeader>

                <PageContent>
                    {/* Stats Cards */}
                    <div className="grid gap-4 md:grid-cols-4">
                        <Card>
                            <CardContent>
                                <div className="flex items-center gap-4">
                                    <div className="bg-primary/10 rounded-full p-3">
                                        <Building2 className="text-primary h-5 w-5" />
                                    </div>
                                    <div>
                                        <p className="text-muted-foreground text-sm">{t('admin.federation.tenants')}</p>
                                        <p className="text-2xl font-bold">{group.tenants_count}</p>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                        <Card>
                            <CardContent>
                                <div className="flex items-center gap-4">
                                    <div className="bg-primary/10 rounded-full p-3">
                                        <Users className="text-primary h-5 w-5" />
                                    </div>
                                    <div>
                                        <p className="text-muted-foreground text-sm">
                                            {t('admin.federation.federated_users')}
                                        </p>
                                        <p className="text-2xl font-bold">{group.federated_users_count}</p>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                        <Card>
                            <CardContent>
                                <div className="flex items-center gap-4">
                                    <div className="bg-primary/10 rounded-full p-3">
                                        <RefreshCw className="text-primary h-5 w-5" />
                                    </div>
                                    <div>
                                        <p className="text-muted-foreground text-sm">{t('admin.federation.strategy')}</p>
                                        <p className="text-sm font-medium">{getSyncStrategyLabel(group.sync_strategy)}</p>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                        {group.stats && group.stats.pending_conflicts > 0 && (
                            <Card className="border-yellow-500">
                                <CardContent>
                                    <div className="flex items-center gap-4">
                                        <div className="rounded-full bg-yellow-100 p-3 dark:bg-yellow-900">
                                            <AlertTriangle className="h-5 w-5 text-yellow-600 dark:text-yellow-400" />
                                        </div>
                                        <div>
                                            <p className="text-muted-foreground text-sm">
                                                {t('admin.federation.conflicts')}
                                            </p>
                                            <p className="text-2xl font-bold">{group.stats.pending_conflicts}</p>
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>
                        )}
                    </div>

                    {/* Info Card */}
                    <Card>
                        <CardHeader>
                            <CardTitle>{t('admin.federation.group_info')}</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="grid gap-4 md:grid-cols-2">
                                <div>
                                    <p className="text-muted-foreground text-sm">{t('admin.federation.master')}</p>
                                    <p className="font-medium">
                                        {group.master_tenant ? (
                                            <span className="flex items-center gap-1">
                                                <Crown className="h-4 w-4 text-yellow-500" />
                                                {group.master_tenant.name}
                                            </span>
                                        ) : (
                                            t('admin.federation.no_master')
                                        )}
                                    </p>
                                </div>
                                <div>
                                    <p className="text-muted-foreground text-sm">{t('common.created')}</p>
                                    <p className="font-medium">{new Date(group.created_at).toLocaleDateString()}</p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Tabs for Tenants and Users */}
                    <Tabs defaultValue="tenants">
                        <TabsList>
                            <TabsTrigger value="tenants">
                                <Building2 className="mr-2 h-4 w-4" />
                                {t('admin.federation.tenants')} ({group.tenants?.length || 0})
                            </TabsTrigger>
                            <TabsTrigger value="users">
                                <Users className="mr-2 h-4 w-4" />
                                {t('admin.federation.federated_users')} ({group.federated_users?.length || 0})
                            </TabsTrigger>
                            {group.stats && group.stats.pending_conflicts > 0 && (
                                <TabsTrigger value="conflicts">
                                    <AlertTriangle className="mr-2 h-4 w-4" />
                                    {t('admin.federation.conflicts')} ({group.stats.pending_conflicts})
                                </TabsTrigger>
                            )}
                        </TabsList>

                        <TabsContent value="tenants" className="mt-4">
                            <Card>
                                <CardHeader>
                                    <div className="flex items-center justify-between">
                                        <div>
                                            <CardTitle>{t('admin.federation.member_tenants')}</CardTitle>
                                            <CardDescription>
                                                {t('admin.federation.member_tenants_description')}
                                            </CardDescription>
                                        </div>
                                        {availableTenants.length > 0 && (
                                            <div className="flex gap-2">
                                                <select
                                                    className="border-input bg-background rounded-md border px-3 py-2 text-sm"
                                                    onChange={(e) => e.target.value && handleAddTenant(e.target.value)}
                                                    defaultValue=""
                                                >
                                                    <option value="">{t('admin.federation.add_tenant')}</option>
                                                    {availableTenants.map((tenant) => (
                                                        <option key={tenant.id} value={tenant.id}>
                                                            {tenant.name}
                                                        </option>
                                                    ))}
                                                </select>
                                            </div>
                                        )}
                                    </div>
                                </CardHeader>
                                <CardContent>
                                    {group.tenants && group.tenants.length > 0 ? (
                                        <Table>
                                            <TableHeader>
                                                <TableRow>
                                                    <TableHead>{t('common.name')}</TableHead>
                                                    <TableHead>Slug</TableHead>
                                                    <TableHead>{t('admin.federation.sync_status')}</TableHead>
                                                    <TableHead>{t('admin.federation.joined_at')}</TableHead>
                                                    <TableHead>{t('common.actions')}</TableHead>
                                                </TableRow>
                                            </TableHeader>
                                            <TableBody>
                                                {group.tenants.map((tenant) => (
                                                    <TableRow key={tenant.id}>
                                                        <TableCell>
                                                            <div className="flex items-center gap-2">
                                                                {tenant.is_master && (
                                                                    <Crown className="h-4 w-4 text-yellow-500" />
                                                                )}
                                                                <span className="font-medium">{tenant.name}</span>
                                                            </div>
                                                        </TableCell>
                                                        <TableCell>
                                                            <code className="text-muted-foreground text-xs">
                                                                {tenant.slug}
                                                            </code>
                                                        </TableCell>
                                                        <TableCell>
                                                            {tenant.sync_enabled ? (
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
                                                        </TableCell>
                                                        <TableCell>
                                                            {tenant.joined_at
                                                                ? new Date(tenant.joined_at).toLocaleDateString()
                                                                : '-'}
                                                        </TableCell>
                                                        <TableCell>
                                                            <div className="flex gap-1">
                                                                {!tenant.is_master && (
                                                                    <AlertDialog>
                                                                        <AlertDialogTrigger asChild>
                                                                            <Button
                                                                                variant="ghost"
                                                                                size="icon"
                                                                                title={tenant.sync_enabled
                                                                                    ? t('admin.federation.disable_sync')
                                                                                    : t('admin.federation.enable_sync')
                                                                                }
                                                                            >
                                                                                {tenant.sync_enabled ? (
                                                                                    <CircleStop className="h-4 w-4 text-destructive" />
                                                                                ) : (
                                                                                    <CirclePlay className="h-4 w-4 text-green-600" />
                                                                                )}
                                                                            </Button>
                                                                        </AlertDialogTrigger>
                                                                        <AlertDialogContent>
                                                                            <AlertDialogHeader>
                                                                                <AlertDialogTitle>
                                                                                    {tenant.sync_enabled
                                                                                        ? t('admin.federation.disable_sync_title')
                                                                                        : t('admin.federation.enable_sync_title')
                                                                                    }
                                                                                </AlertDialogTitle>
                                                                                <AlertDialogDescription>
                                                                                    {tenant.sync_enabled
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
                                                                                    onClick={() => handleToggleTenantSync(tenant.id)}
                                                                                >
                                                                                    {t('common.confirm')}
                                                                                </AlertDialogAction>
                                                                            </AlertDialogFooter>
                                                                        </AlertDialogContent>
                                                                    </AlertDialog>
                                                                )}
                                                                <Button variant="ghost" size="icon" asChild>
                                                                    <Link href={admin.tenants.show.url(tenant.id)}>
                                                                        <Building2 className="h-4 w-4" />
                                                                    </Link>
                                                                </Button>
                                                            </div>
                                                        </TableCell>
                                                    </TableRow>
                                                ))}
                                            </TableBody>
                                        </Table>
                                    ) : (
                                        <div className="py-8 text-center">
                                            <Building2 className="text-muted-foreground mx-auto mb-4 h-12 w-12" />
                                            <p className="text-muted-foreground">{t('admin.federation.no_tenants')}</p>
                                        </div>
                                    )}
                                </CardContent>
                            </Card>
                        </TabsContent>

                        <TabsContent value="users" className="mt-4">
                            <Card>
                                <CardHeader>
                                    <div className="flex items-center justify-between">
                                        <div>
                                            <CardTitle>{t('admin.federation.federated_users')}</CardTitle>
                                            <CardDescription>
                                                {t('admin.federation.federated_users_description')}
                                            </CardDescription>
                                        </div>
                                        {group.stats && group.stats.failed_syncs > 0 && (
                                            <Button variant="outline" onClick={handleRetryAllSync}>
                                                <RefreshCw className="mr-2 h-4 w-4" />
                                                {t('admin.federation.retry_failed_syncs')}
                                            </Button>
                                        )}
                                    </div>
                                </CardHeader>
                                <CardContent>
                                    {group.federated_users && group.federated_users.length > 0 ? (
                                        <Table>
                                            <TableHeader>
                                                <TableRow>
                                                    <TableHead>{t('common.user')}</TableHead>
                                                    <TableHead>{t('admin.federation.master_tenant')}</TableHead>
                                                    <TableHead>{t('admin.federation.linked_tenants')}</TableHead>
                                                    <TableHead>{t('common.status')}</TableHead>
                                                    <TableHead>{t('common.actions')}</TableHead>
                                                </TableRow>
                                            </TableHeader>
                                            <TableBody>
                                                {group.federated_users.map((user) => (
                                                    <TableRow key={user.id}>
                                                        <TableCell>
                                                            <div className="flex items-center gap-3">
                                                                <div className="bg-muted flex h-8 w-8 items-center justify-center rounded-full">
                                                                    <User className="h-4 w-4" />
                                                                </div>
                                                                <div>
                                                                    <p className="font-medium">{user.name}</p>
                                                                    <p className="text-muted-foreground flex items-center gap-1 text-xs">
                                                                        <Mail className="h-3 w-3" />
                                                                        {user.email}
                                                                    </p>
                                                                </div>
                                                            </div>
                                                        </TableCell>
                                                        <TableCell>
                                                            {user.master_tenant ? (
                                                                <span className="flex items-center gap-1">
                                                                    <Crown className="h-3 w-3 text-yellow-500" />
                                                                    {user.master_tenant.name}
                                                                </span>
                                                            ) : (
                                                                '-'
                                                            )}
                                                        </TableCell>
                                                        <TableCell>
                                                            <Badge variant="outline">{user.linked_tenants_count}</Badge>
                                                        </TableCell>
                                                        <TableCell>
                                                            {user.is_active ? (
                                                                <Badge variant="default">{t('common.active')}</Badge>
                                                            ) : (
                                                                <Badge variant="secondary">{t('common.inactive')}</Badge>
                                                            )}
                                                        </TableCell>
                                                        <TableCell>
                                                            <div className="flex gap-1">
                                                                <Button
                                                                    variant="ghost"
                                                                    size="icon"
                                                                    onClick={() => handleSyncUser(user.id)}
                                                                    title={t('admin.federation.sync_user')}
                                                                >
                                                                    <RefreshCw className="h-4 w-4" />
                                                                </Button>
                                                                <Button variant="ghost" size="icon" asChild>
                                                                    <Link
                                                                        href={admin.federation.users.show.url({
                                                                            group: group.id,
                                                                            user: user.id,
                                                                        })}
                                                                    >
                                                                        <User className="h-4 w-4" />
                                                                    </Link>
                                                                </Button>
                                                            </div>
                                                        </TableCell>
                                                    </TableRow>
                                                ))}
                                            </TableBody>
                                        </Table>
                                    ) : (
                                        <div className="py-8 text-center">
                                            <Users className="text-muted-foreground mx-auto mb-4 h-12 w-12" />
                                            <p className="text-muted-foreground">
                                                {t('admin.federation.no_federated_users')}
                                            </p>
                                        </div>
                                    )}
                                </CardContent>
                            </Card>
                        </TabsContent>

                        {group.stats && group.stats.pending_conflicts > 0 && (
                            <TabsContent value="conflicts" className="mt-4">
                                <Card>
                                    <CardHeader>
                                        <div className="flex items-center justify-between">
                                            <div>
                                                <CardTitle>{t('admin.federation.conflicts')}</CardTitle>
                                                <CardDescription>
                                                    {t('admin.federation.conflicts_description')}
                                                </CardDescription>
                                            </div>
                                            <Button asChild>
                                                <Link href={admin.federation.conflicts.index.url(group.id)}>
                                                    <AlertTriangle className="mr-2 h-4 w-4" />
                                                    {t('admin.federation.view_all_conflicts')}
                                                </Link>
                                            </Button>
                                        </div>
                                    </CardHeader>
                                    <CardContent>
                                        <div className="py-8 text-center">
                                            <AlertTriangle className="mx-auto mb-4 h-12 w-12 text-yellow-500" />
                                            <p className="text-muted-foreground">
                                                {t('admin.federation.conflicts_pending', {
                                                    count: group.stats.pending_conflicts,
                                                })}
                                            </p>
                                            <Button asChild className="mt-4">
                                                <Link href={admin.federation.conflicts.index.url(group.id)}>
                                                    {t('admin.federation.resolve_conflicts')}
                                                </Link>
                                            </Button>
                                        </div>
                                    </CardContent>
                                </Card>
                            </TabsContent>
                        )}
                    </Tabs>
                </PageContent>
            </Page>
        </>
    );
}

FederationShow.layout = (page: ReactElement) => <AdminLayout>{page}</AdminLayout>;

export default FederationShow;
