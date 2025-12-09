import { FederatedUserStatusBadge } from '@/components/shared/status-badge';
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
import { useSetBreadcrumbs } from '@/contexts/breadcrumb-context';
import { useFederation, isCentralFederation } from '@/hooks/shared/use-federation';
import AdminLayout from '@/layouts/central/admin-layout';
import { FEDERATION_SYNC_STRATEGY } from '@/lib/enum-metadata';
import admin from '@/routes/central/admin';
import {
    type BreadcrumbItem,
    type FederationGroupDetailResource,
    type TenantSummaryResource,
} from '@/types';
import { type FederationGroupShowStats } from '@/types/common';
import { Head, Link } from '@inertiajs/react';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import {
    AlertTriangle,
    ArrowRightLeft,
    Building2,
    CheckCircle,
    CirclePlay,
    CircleStop,
    Crown,
    LogIn,
    Mail,
    Network,
    Pencil,
    RefreshCw,
    Trash2,
    User,
    Users,
    XCircle,
} from 'lucide-react';
import { type ReactElement, useState } from 'react';
import { ChangeMasterDialog } from './components/change-master-dialog';
import {
    Page,
    PageContent,
    PageDescription,
    PageHeader,
    PageHeaderActions,
    PageHeaderContent,
    PageTitle,
} from '@/components/shared/layout/page';

/**
 * Extended group interface combining FederationGroupDetailResource
 * with show-page specific stats format
 */
interface FederationGroupShowData extends Omit<FederationGroupDetailResource, 'stats'> {
    stats?: FederationGroupShowStats;
}

interface Props {
    group: FederationGroupShowData;
    availableTenants: TenantSummaryResource[];
}

function FederationShow({ group, availableTenants }: Props) {
    const { t } = useLaravelReactI18n();
    const federation = useFederation();
    const [changeMasterOpen, setChangeMasterOpen] = useState(false);

    // Type guard ensures we have central operations
    if (!isCentralFederation(federation)) {
        throw new Error('FederationShow must be used in central context');
    }

    const {
        processingId,
        addTenant,
        removeTenant,
        rejoinTenant,
        toggleTenantSync,
        syncUser,
        retryAllSync,
    } = federation;

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('breadcrumbs.dashboard'), href: admin.dashboard.url() },
        { title: t('admin.federation.title'), href: admin.federation.index.url() },
        { title: group.name, href: admin.federation.show.url(group.id) },
    ];

    useSetBreadcrumbs(breadcrumbs);

    const handleAddTenant = (tenantId: string) => {
        addTenant(group.id, tenantId);
    };

    const handleSyncUser = (userId: string) => {
        syncUser(group.id, userId);
    };

    const handleRetryAllSync = () => {
        if (confirm(t('admin.federation.retry_sync_confirm'))) {
            retryAllSync(group.id);
        }
    };

    const handleToggleTenantSync = (tenantId: string) => {
        toggleTenantSync(group.id, tenantId);
    };

    const handleRemoveTenant = (tenantId: string) => {
        removeTenant(group.id, tenantId);
    };

    const handleRejoinTenant = (tenantId: string) => {
        rejoinTenant(group.id, tenantId);
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
                                        <p className="text-sm font-medium">{FEDERATION_SYNC_STRATEGY[group.sync_strategy].label}</p>
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
                                    <div className="flex items-center gap-2">
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
                                        {group.tenants && group.tenants.filter(t => !t.left_at).length > 1 && (
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                onClick={() => setChangeMasterOpen(true)}
                                                title={t('admin.federation.change_master.title')}
                                            >
                                                <ArrowRightLeft className="h-4 w-4" />
                                            </Button>
                                        )}
                                    </div>
                                </div>
                                <div>
                                    <p className="text-muted-foreground text-sm">{t('common.created')}</p>
                                    <p className="font-medium">{new Date(group.created_at).toLocaleDateString()}</p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Change Master Dialog */}
                    {group.master_tenant && (
                        <ChangeMasterDialog
                            open={changeMasterOpen}
                            onOpenChange={setChangeMasterOpen}
                            groupId={group.id}
                            groupName={group.name}
                            currentMasterId={group.master_tenant.id}
                            tenants={group.tenants?.filter(t => !t.left_at) || []}
                        />
                    )}

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
                                                    <TableRow key={tenant.id} className={tenant.left_at ? 'opacity-60' : ''}>
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
                                                            {tenant.left_at ? (
                                                                <Badge variant="outline">
                                                                    <XCircle className="mr-1 h-3 w-3" />
                                                                    {t('admin.federation.left')}
                                                                </Badge>
                                                            ) : tenant.sync_enabled ? (
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
                                                            {tenant.left_at
                                                                ? new Date(tenant.left_at).toLocaleDateString()
                                                                : tenant.joined_at
                                                                    ? new Date(tenant.joined_at).toLocaleDateString()
                                                                    : '-'}
                                                        </TableCell>
                                                        <TableCell>
                                                            <div className="flex gap-1">
                                                                {/* Tenant que saiu: mostrar botão de Rejoin */}
                                                                {tenant.left_at && !tenant.is_master && (
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
                                                                                    onClick={() => handleRejoinTenant(tenant.id)}
                                                                                >
                                                                                    {t('admin.federation.rejoin')}
                                                                                </AlertDialogAction>
                                                                            </AlertDialogFooter>
                                                                        </AlertDialogContent>
                                                                    </AlertDialog>
                                                                )}
                                                                {/* Tenant ativo: toggle sync */}
                                                                {!tenant.left_at && !tenant.is_master && (
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
                                                                {/* Tenant ativo: remover */}
                                                                {!tenant.left_at && !tenant.is_master && (
                                                                    <AlertDialog>
                                                                        <AlertDialogTrigger asChild>
                                                                            <Button
                                                                                variant="ghost"
                                                                                size="icon"
                                                                                title={t('admin.federation.remove_tenant')}
                                                                            >
                                                                                <Trash2 className="text-destructive h-4 w-4" />
                                                                            </Button>
                                                                        </AlertDialogTrigger>
                                                                        <AlertDialogContent>
                                                                            <AlertDialogHeader>
                                                                                <AlertDialogTitle>
                                                                                    {t('admin.federation.remove_tenant_title')}
                                                                                </AlertDialogTitle>
                                                                                <AlertDialogDescription>
                                                                                    {t('admin.federation.remove_tenant_confirm', { name: tenant.name })}
                                                                                </AlertDialogDescription>
                                                                            </AlertDialogHeader>
                                                                            <AlertDialogFooter>
                                                                                <AlertDialogCancel>
                                                                                    {t('common.cancel')}
                                                                                </AlertDialogCancel>
                                                                                <AlertDialogAction
                                                                                    className="bg-destructive text-destructive-foreground hover:bg-destructive/90"
                                                                                    onClick={() => handleRemoveTenant(tenant.id)}
                                                                                >
                                                                                    {t('common.remove')}
                                                                                </AlertDialogAction>
                                                                            </AlertDialogFooter>
                                                                        </AlertDialogContent>
                                                                    </AlertDialog>
                                                                )}
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
                                                                    <p className="font-medium">{user.name || user.global_email}</p>
                                                                    <p className="text-muted-foreground flex items-center gap-1 text-xs">
                                                                        <Mail className="h-3 w-3" />
                                                                        {user.global_email}
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
                                                            <Badge variant="outline">{user.links_count}</Badge>
                                                        </TableCell>
                                                        <TableCell>
                                                            <FederatedUserStatusBadge status={user.status} />
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
