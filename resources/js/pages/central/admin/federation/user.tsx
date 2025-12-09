import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AdminLayout from '@/layouts/central/admin-layout';
import admin from '@/routes/central/admin';
import { Head, Link, router } from '@inertiajs/react';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import { useSetBreadcrumbs } from '@/contexts/breadcrumb-context';
import { type ReactElement } from 'react';
import {
    AlertTriangle,
    Building2,
    CheckCircle,
    Clock,
    Crown,
    Mail,
    Network,
    RefreshCw,
    Shield,
    User,
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

interface TenantLink {
    id: string;
    tenant_id: string;
    tenant_name: string | null;
    tenant_user_id: string | null;
    sync_status: 'synced' | 'pending' | 'failed' | 'disabled';
    sync_attempts: number;
    last_synced_at: string | null;
    last_sync_error: string | null;
    is_master: boolean;
    created_via: string;
}

interface FederatedUser {
    id: string;
    federation_group_id: string;
    global_email: string;
    status: 'active' | 'pending' | 'suspended' | 'pending_master_sync' | 'pending_review';
    sync_version: number;
    last_synced_at: string | null;
    last_sync_source: string | null;
    created_at: string;
    updated_at: string;
    synced_data: {
        name: string | null;
        locale: string | null;
        two_factor_enabled: boolean;
        password_changed_at: string | null;
    };
    master_tenant: { id: string; name: string; slug: string } | null;
    links: TenantLink[];
    links_count: number;
}

interface FederationGroup {
    id: string;
    name: string;
    description: string | null;
}

interface Props {
    group: FederationGroup;
    user: FederatedUser;
}

function FederationUserShow({ group, user }: Props) {
    const { t } = useLaravelReactI18n();

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('breadcrumbs.dashboard'), href: admin.dashboard.url() },
        { title: t('admin.federation.title'), href: admin.federation.index.url() },
        { title: group.name, href: admin.federation.show.url(group.id) },
        { title: user.synced_data.name || user.global_email, href: admin.federation.users.show.url({ group: group.id, user: user.id }) },
    ];

    useSetBreadcrumbs(breadcrumbs);

    const handleSyncUser = () => {
        router.post(admin.federation.users.sync.url({ group: group.id, user: user.id }));
    };

    const getStatusBadge = (status: string) => {
        switch (status) {
            case 'active':
                return <Badge variant="default"><CheckCircle className="mr-1 h-3 w-3" />{t('common.active')}</Badge>;
            case 'pending':
                return <Badge variant="secondary"><Clock className="mr-1 h-3 w-3" />{t('common.pending')}</Badge>;
            case 'pending_master_sync':
                return <Badge variant="outline"><Clock className="mr-1 h-3 w-3" />{t('common.pending_master_sync')}</Badge>;
            case 'pending_review':
                return <Badge variant="secondary"><Clock className="mr-1 h-3 w-3" />{t('common.pending_review')}</Badge>;
            case 'suspended':
                return <Badge variant="destructive"><XCircle className="mr-1 h-3 w-3" />{t('common.suspended')}</Badge>;
            default:
                return <Badge variant="outline">{status}</Badge>;
        }
    };

    const getSyncStatusBadge = (status: string) => {
        switch (status) {
            case 'synced':
                return <Badge variant="default"><CheckCircle className="mr-1 h-3 w-3" />{t('admin.federation.synced')}</Badge>;
            case 'pending':
                return <Badge variant="secondary"><Clock className="mr-1 h-3 w-3" />{t('common.pending')}</Badge>;
            case 'failed':
                return <Badge variant="destructive"><AlertTriangle className="mr-1 h-3 w-3" />{t('admin.federation.failed')}</Badge>;
            case 'disabled':
                return <Badge variant="outline"><XCircle className="mr-1 h-3 w-3" />{t('admin.federation.sync_disabled')}</Badge>;
            default:
                return <Badge variant="outline">{status}</Badge>;
        }
    };

    const syncedLinks = user.links.filter((l) => l.sync_status === 'synced').length;
    const failedLinks = user.links.filter((l) => l.sync_status === 'failed').length;

    return (
        <>
            <Head title={`${t('admin.federation.federated_user')}: ${user.synced_data.name || user.global_email}`} />

            <Page>
                <PageHeader>
                    <PageHeaderContent>
                        <PageTitle icon={User}>
                            {user.synced_data.name || user.global_email}
                            {getStatusBadge(user.status)}
                        </PageTitle>
                        <PageDescription className="flex items-center gap-2">
                            <Mail className="h-4 w-4" />
                            {user.global_email}
                        </PageDescription>
                    </PageHeaderContent>
                    <PageHeaderActions>
                        <Button variant="outline" onClick={handleSyncUser}>
                            <RefreshCw className="mr-2 h-4 w-4" />
                            {t('admin.federation.sync_now')}
                        </Button>
                    </PageHeaderActions>
                </PageHeader>

                <PageContent>
                    {/* Stats Cards */}
                    <div className="grid gap-4 md:grid-cols-4">
                        <Card>
                            <CardContent className="pt-6">
                                <div className="flex items-center gap-4">
                                    <div className="bg-primary/10 rounded-full p-3">
                                        <Building2 className="text-primary h-5 w-5" />
                                    </div>
                                    <div>
                                        <p className="text-muted-foreground text-sm">{t('admin.federation.linked_tenants')}</p>
                                        <p className="text-2xl font-bold">{user.links_count}</p>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                        <Card>
                            <CardContent className="pt-6">
                                <div className="flex items-center gap-4">
                                    <div className="rounded-full bg-green-100 p-3 dark:bg-green-900">
                                        <CheckCircle className="h-5 w-5 text-green-600 dark:text-green-400" />
                                    </div>
                                    <div>
                                        <p className="text-muted-foreground text-sm">{t('admin.federation.synced')}</p>
                                        <p className="text-2xl font-bold">{syncedLinks}</p>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                        {failedLinks > 0 && (
                            <Card className="border-destructive">
                                <CardContent className="pt-6">
                                    <div className="flex items-center gap-4">
                                        <div className="bg-destructive/10 rounded-full p-3">
                                            <AlertTriangle className="text-destructive h-5 w-5" />
                                        </div>
                                        <div>
                                            <p className="text-muted-foreground text-sm">{t('admin.federation.failed')}</p>
                                            <p className="text-2xl font-bold">{failedLinks}</p>
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>
                        )}
                        <Card>
                            <CardContent className="pt-6">
                                <div className="flex items-center gap-4">
                                    <div className="bg-primary/10 rounded-full p-3">
                                        <RefreshCw className="text-primary h-5 w-5" />
                                    </div>
                                    <div>
                                        <p className="text-muted-foreground text-sm">{t('admin.federation.sync_version')}</p>
                                        <p className="text-2xl font-bold">v{user.sync_version}</p>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    </div>

                    {/* User Info */}
                    <div className="grid gap-6 md:grid-cols-2">
                        <Card>
                            <CardHeader>
                                <CardTitle>{t('admin.federation.user_info')}</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="grid grid-cols-2 gap-4">
                                    <div>
                                        <p className="text-muted-foreground text-sm">{t('common.name')}</p>
                                        <p className="font-medium">{user.synced_data.name || '-'}</p>
                                    </div>
                                    <div>
                                        <p className="text-muted-foreground text-sm">{t('common.email')}</p>
                                        <p className="font-medium">{user.global_email}</p>
                                    </div>
                                    <div>
                                        <p className="text-muted-foreground text-sm">{t('common.locale')}</p>
                                        <p className="font-medium">{user.synced_data.locale || '-'}</p>
                                    </div>
                                    <div>
                                        <p className="text-muted-foreground text-sm">{t('admin.federation.two_factor')}</p>
                                        <p className="font-medium">
                                            {user.synced_data.two_factor_enabled ? (
                                                <span className="flex items-center gap-1 text-green-600">
                                                    <Shield className="h-4 w-4" />
                                                    {t('common.enabled')}
                                                </span>
                                            ) : (
                                                <span className="text-muted-foreground">{t('common.disabled')}</span>
                                            )}
                                        </p>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>{t('admin.federation.sync_info')}</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="grid grid-cols-2 gap-4">
                                    <div>
                                        <p className="text-muted-foreground text-sm">{t('admin.federation.master_tenant')}</p>
                                        <p className="font-medium">
                                            {user.master_tenant ? (
                                                <span className="flex items-center gap-1">
                                                    <Crown className="h-4 w-4 text-yellow-500" />
                                                    {user.master_tenant.name}
                                                </span>
                                            ) : (
                                                '-'
                                            )}
                                        </p>
                                    </div>
                                    <div>
                                        <p className="text-muted-foreground text-sm">{t('admin.federation.last_sync_source')}</p>
                                        <p className="font-medium">{user.last_sync_source || '-'}</p>
                                    </div>
                                    <div>
                                        <p className="text-muted-foreground text-sm">{t('admin.federation.last_synced')}</p>
                                        <p className="font-medium">
                                            {user.last_synced_at
                                                ? new Date(user.last_synced_at).toLocaleString()
                                                : t('common.never')}
                                        </p>
                                    </div>
                                    <div>
                                        <p className="text-muted-foreground text-sm">{t('common.created')}</p>
                                        <p className="font-medium">{new Date(user.created_at).toLocaleDateString()}</p>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    </div>

                    {/* Tenant Links */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Network className="h-5 w-5" />
                                {t('admin.federation.tenant_links')}
                            </CardTitle>
                            <CardDescription>
                                {t('admin.federation.tenant_links_description')}
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            {user.links.length > 0 ? (
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>{t('admin.federation.tenant')}</TableHead>
                                            <TableHead>{t('admin.federation.sync_status')}</TableHead>
                                            <TableHead>{t('admin.federation.last_synced')}</TableHead>
                                            <TableHead>{t('admin.federation.created_via')}</TableHead>
                                            <TableHead>{t('common.actions')}</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {user.links.map((link) => (
                                            <TableRow key={link.id}>
                                                <TableCell>
                                                    <div className="flex items-center gap-2">
                                                        {link.is_master && (
                                                            <Crown className="h-4 w-4 text-yellow-500" />
                                                        )}
                                                        <span className="font-medium">{link.tenant_name || link.tenant_id}</span>
                                                    </div>
                                                </TableCell>
                                                <TableCell>
                                                    <div className="space-y-1">
                                                        {getSyncStatusBadge(link.sync_status)}
                                                        {link.sync_status === 'failed' && link.last_sync_error && (
                                                            <p className="text-destructive text-xs">{link.last_sync_error}</p>
                                                        )}
                                                    </div>
                                                </TableCell>
                                                <TableCell>
                                                    {link.last_synced_at
                                                        ? new Date(link.last_synced_at).toLocaleString()
                                                        : '-'}
                                                </TableCell>
                                                <TableCell>
                                                    <Badge variant="outline">{link.created_via}</Badge>
                                                </TableCell>
                                                <TableCell>
                                                    <Button variant="ghost" size="sm" asChild>
                                                        <Link href={admin.tenants.show.url(link.tenant_id)}>
                                                            <Building2 className="mr-1 h-4 w-4" />
                                                            {t('common.view')}
                                                        </Link>
                                                    </Button>
                                                </TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                            ) : (
                                <div className="py-8 text-center">
                                    <Network className="text-muted-foreground mx-auto mb-4 h-12 w-12" />
                                    <p className="text-muted-foreground">{t('admin.federation.no_tenant_links')}</p>
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </PageContent>
            </Page>
        </>
    );
}

FederationUserShow.layout = (page: ReactElement) => <AdminLayout>{page}</AdminLayout>;

export default FederationUserShow;
